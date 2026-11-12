<?php

namespace Tests\Feature;

use App\Filament\Resources\Contacts\Pages\ManageContacts;
use App\Filament\Resources\DndEntries\Pages\ManageDndEntries;
use App\Filament\Resources\KnowledgePages\Pages\ManageKnowledgePages;
use App\Filament\Resources\VoiceCalls\Pages\ManageVoiceCalls;
use App\Models\Contact;
use App\Models\DndEntry;
use App\Models\KnowledgePage;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResourceActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_contact_and_record_consent_but_cannot_edit_brain(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->members()->attach($user, ['role' => 'campaign_operator']);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($tenant);
        app(TenantContext::class)->run($tenant, function (): void {
            Livewire::test(ManageContacts::class)->callAction('create', data: ['phone_e164' => '8801712345678', 'name' => 'Parent', 'experiment_group' => 'call'])->assertHasNoActionErrors();
            $contact = Contact::sole();
            $this->assertSame('Parent', $contact->name);
            Livewire::test(ManageContacts::class)->callAction(TestAction::make('consent')->table($contact), data: ['purpose' => 'service', 'granted' => true, 'source' => 'verified_form', 'text_version' => 'consent-v1'])->assertHasNoActionErrors();
            $this->assertTrue($contact->consentEvents()->sole()->granted);
            Livewire::test(ManageKnowledgePages::class)->assertForbidden();
        });
    }

    public function test_content_reviewer_can_create_fact_but_viewer_cannot_create_contacts(): void
    {
        $tenant = Tenant::factory()->create();
        $reviewer = User::factory()->create();
        $tenant->members()->attach($reviewer, ['role' => 'content_reviewer']);
        $this->actingAs($reviewer);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($tenant);
        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            Livewire::test(ManageKnowledgePages::class)->callAction('create', data: ['code' => 'F_EXAMPLE', 'title' => 'Reviewed fact', 'body' => 'A factual statement.', 'source' => 'Client document', 'status' => 'draft', 'expires_at' => '2026-12-31'])->assertHasNoActionErrors();
            $this->assertSame(1, KnowledgePage::count());
            $viewer = User::factory()->create();
            $tenant->members()->attach($viewer, ['role' => 'viewer']);
            $this->actingAs($viewer);
            Livewire::test(ManageContacts::class)->assertActionHidden('create');
        });
    }

    public function test_read_only_call_and_opt_out_actions_cannot_be_edited(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($tenant);
        app(TenantContext::class)->run($tenant, function (): void {
            Livewire::test(ManageVoiceCalls::class)->assertActionHidden('create');
            $entry = DndEntry::create(['phone_e164' => '8801712345678', 'reason' => 'Opt-out']);
            Livewire::test(ManageDndEntries::class)->assertActionHidden(TestAction::make('edit')->table($entry));
        });
    }
}
