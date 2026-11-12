<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VoiceCall;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_context_reads_nothing_and_client_b_cannot_read_or_write_client_a_records(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();
        $context = app(TenantContext::class);
        $contact = $context->run($a, fn () => Contact::factory()->create());
        $this->assertSame(0, Contact::count());
        $context->run($b, function () use ($contact): void {
            $this->assertNull(Contact::find($contact->id));
            $this->expectException(\LogicException::class);
            $contact->update(['name' => 'Changed from wrong client']);
        });
    }

    public function test_related_records_cannot_cross_tenant_boundaries(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();
        $context = app(TenantContext::class);
        $contact = $context->run($a, fn () => Contact::factory()->create());
        $context->run($b, function () use ($contact): void {
            $this->expectException(\LogicException::class);
            VoiceCall::create(['call_uuid' => (string) Str::uuid(), 'contact_id' => $contact->id, 'started_at' => now()]);
        });
    }

    public function test_memberships_do_not_grant_access_to_other_clients(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();
        $user = User::factory()->create();
        $a->members()->attach($user, ['role' => 'viewer']);
        $this->assertTrue($user->canAccessTenant($a));
        $this->assertFalse($user->canAccessTenant($b));
        $this->actingAs($user)->get('/admin/'.$b->slug.'/contacts')->assertNotFound();
        $this->actingAs($user)->get('/admin/'.$a->slug.'/contacts')->assertOk();
        $this->actingAs($user)->get('/admin/'.$a->slug.'/brain-studio')->assertForbidden();
        $this->actingAs($user)->get('/admin/'.$a->slug.'/team')->assertForbidden();
        $this->actingAs($user)->get('/admin/'.$a->slug.'/audit-events')->assertForbidden();
    }
}
