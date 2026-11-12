<?php

namespace App\Services;

use App\Models\CallJob;
use App\Models\Campaign;
use App\Models\ConsentEvent;
use App\Models\Contact;
use App\Models\ListMember;
use App\Models\VoiceCall;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CampaignPlanner
{
    public function blockedReason(Campaign $campaign, Contact $contact): ?string
    {
        $tenant = app(TenantContext::class)->requireTenant();
        if ((int) $campaign->tenant_id !== $tenant->id || (int) $contact->tenant_id !== $tenant->id) {
            return 'wrong_client';
        }
        if ($tenant->status === 'paused' || $campaign->status === 'paused') {
            return 'paused';
        }
        if ($contact->experiment_group === 'control') {
            return 'control_group';
        }
        if (DB::table('dnd_entries')->where('phone_e164', $contact->phone_e164)->where(fn ($q) => $q->where('tenant_id', $tenant->id)->orWhereNull('tenant_id'))->exists()) {
            return 'dnd';
        }
        $consent = ConsentEvent::where('contact_id', $contact->id)->where('channel', 'call')->where('purpose', $campaign->purpose)->orderByDesc('occurred_at')->orderByDesc('id')->first();
        if (! $consent?->granted) {
            return 'no_consent';
        }
        if (! $campaign->brain_release_id || ! $campaign->brainRelease()->exists()) {
            return 'no_published_brain';
        }
        if ($campaign->scheduled_at?->isFuture()) {
            return 'not_started';
        }
        $now = CarbonImmutable::now($tenant->timezone);
        $start = $tenant->settings['calling_start'] ?? '10:00';
        $end = $tenant->settings['calling_end'] ?? '20:00';
        if (! preg_match('/\A\d{2}:\d{2}\z/', $start) || ! preg_match('/\A\d{2}:\d{2}\z/', $end) || $start >= $end) {
            return 'invalid_calling_window';
        }
        if ($now->format('H:i') < $start || $now->format('H:i') >= $end) {
            return 'outside_calling_hours';
        }
        $attempts = VoiceCall::where('contact_id', $contact->id)->where('is_simulation', false)->where('direction', 'outbound');
        if ((clone $attempts)->count() >= min(3, max(1, $campaign->max_attempts))) {
            return 'attempt_limit';
        }
        $last = (clone $attempts)->latest('started_at')->first();
        if ($last && $last->started_at->gt(now()->subMinutes(max(240, $campaign->retry_gap_minutes)))) {
            return 'retry_gap';
        }
        $todayStart = $now->startOfDay()->utc();
        $todayEnd = $now->endOfDay()->utc();
        $today = VoiceCall::where('is_simulation', false)->where('direction', 'outbound')->whereBetween('started_at', [$todayStart, $todayEnd]);
        if ((clone $today)->count() >= max(1, (int) ($tenant->settings['daily_cap'] ?? 300))) {
            return 'client_daily_limit';
        }
        if ((clone $today)->where('campaign_id', $campaign->id)->count() >= $campaign->daily_cap) {
            return 'campaign_daily_limit';
        }
        if ($campaign->budget_tk > 0 && VoiceCall::where('campaign_id', $campaign->id)->where('is_simulation', false)->sum('cost_tk') >= $campaign->budget_tk) {
            return 'campaign_budget';
        }
        if (VoiceCall::where('is_simulation', false)->where('result', 'in_progress')->count() >= min($campaign->concurrency, (int) ($tenant->settings['max_concurrent_calls'] ?? 5))) {
            return 'concurrency_limit';
        }

        return null;
    }

    public function dryRun(Campaign $campaign): array
    {
        $query = Contact::query();
        if ($campaign->contact_list_id) {
            $query->whereIn('id', ListMember::where('contact_list_id', $campaign->contact_list_id)->select('contact_id'));
        }
        $summary = ['eligible' => 0, 'blocked' => 0, 'reasons' => [], 'batch_id' => (string) Str::uuid(), 'simulation' => true];

        return DB::transaction(function () use ($campaign, $query, $summary): array {
            foreach ($query->limit(1000)->get() as $contact) {
                $reason = $this->blockedReason($campaign, $contact);
                $summary[$reason ? 'blocked' : 'eligible']++;
                if ($reason) {
                    $summary['reasons'][$reason] = ($summary['reasons'][$reason] ?? 0) + 1;
                }
                CallJob::create(['campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'status' => $reason ? 'blocked' : 'simulated', 'reason' => $reason, 'batch_id' => $summary['batch_id'], 'next_attempt_at' => now()]);
            }
            $campaign->update(['status' => $campaign->status === 'paused' ? 'paused' : 'dry_run']);

            return $summary;
        });
    }
}
