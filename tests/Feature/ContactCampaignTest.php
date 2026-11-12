<?php

namespace Tests\Feature;

use App\Models\BrainRelease;
use App\Models\Campaign;
use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\DndEntry;
use App\Models\Tenant;
use App\Models\VoiceCall;
use App\Services\CampaignPlanner;
use App\Services\ContactImporter;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_normalizes_numbers_does_not_assume_consent_and_preserves_newer_withdrawal(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->run($tenant, function (): void {
            $importer = app(ContactImporter::class);
            $this->assertSame('8801712345678', $importer->phone('+880 1712-345678'));
            $this->assertSame('8801712345678', $importer->phone('০১৭১২৩৪৫৬৭৮'));
            $result = $importer->import("phone,parent_name,source,lead_at,consent_calls\n01712345678,Parent,form,2026-01-01 11:00,yes\ninvalid,Invalid,form,,\n");
            $this->assertSame(1, $result['created']);
            $this->assertSame(1, $result['invalid']);
            $contact = Contact::firstOrFail();
            $importer->consent($contact, false, 'service', 'operator', 'withdrawal-v1');
            $importer->import("phone,parent_name,source,lead_at,consent_calls\n01712345678,Parent,old-form,2026-01-01 11:00,yes\n");
            $this->assertFalse(ConsentEvent::where('contact_id', $contact->id)->latest('occurred_at')->latest('id')->first()->granted);
            $importer->import("phone,parent_name\n01812345678,No consent\n");
            $this->assertSame(0, ConsentEvent::where('contact_id', Contact::where('phone_e164', '8801812345678')->first()->id)->count());
        });
    }

    public function test_campaign_planning_enforces_consent_dnd_control_hours_and_retry_without_dialing(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-12 11:00', 'Asia/Dhaka')->utc());
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->run($tenant, function (): void {
            $release = BrainRelease::create(['version' => 1, 'snapshot' => [], 'test_results' => [], 'checksum' => str_repeat('a', 64), 'published_at' => now()]);
            $campaign = Campaign::create(['name' => 'Dry run', 'brain_release_id' => $release->id]);
            $contact = Contact::factory()->create();
            $planner = app(CampaignPlanner::class);
            $this->assertSame('no_consent', $planner->blockedReason($campaign, $contact));
            app(ContactImporter::class)->consent($contact, true, 'service', 'form', 'v1');
            $this->assertNull($planner->blockedReason($campaign, $contact));
            $result = $planner->dryRun($campaign);
            $this->assertSame(1, $result['eligible']);
            $this->assertSame(0, VoiceCall::count());
            $contact->update(['experiment_group' => 'control']);
            $this->assertSame('control_group', $planner->blockedReason($campaign, $contact));
            $contact->update(['experiment_group' => 'call']);
            DndEntry::create(['phone_e164' => $contact->phone_e164, 'reason' => 'Requested opt-out']);
            $this->assertSame('dnd', $planner->blockedReason($campaign, $contact));
            DndEntry::where('phone_e164', $contact->phone_e164)->delete();
            $this->travelTo(CarbonImmutable::parse('2026-11-12 21:00', 'Asia/Dhaka')->utc());
            $this->assertSame('outside_calling_hours', $planner->blockedReason($campaign, $contact));
        });
    }
}
