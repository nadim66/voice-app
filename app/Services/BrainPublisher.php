<?php

namespace App\Services;

use App\Models\BrainRelease;
use App\Models\BrainTestCase;
use App\Models\IntentRule;
use App\Models\KnowledgePage;
use App\Models\ReplyCard;
use App\Models\Tenant;
use App\Models\TestRun;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BrainPublisher
{
    public function snapshot(bool $preview = false): array
    {
        $tenant = app(TenantContext::class)->requireTenant();
        $today = now($tenant->timezone)->toDateString();
        $live = fn ($q) => $q->where('status', 'approved')
            ->where(fn ($dates) => $dates->whereNull('effective_from')->orWhereDate('effective_from', '<=', $today))
            ->where(fn ($dates) => $dates->whereNull('expires_at')->orWhereDate('expires_at', '>=', $today));

        return [
            'tenant_id' => $tenant->id, 'timezone' => $tenant->timezone, 'preview' => $preview,
            'facts' => KnowledgePage::when(! $preview, $live)->orderBy('code')->get()->map(fn ($m) => $m->only(['code', 'title', 'body', 'source', 'effective_from', 'expires_at']))->all(),
            'replies' => ReplyCard::when(! $preview, $live)->orderBy('code')->get()->map(fn ($m) => $m->only(['code', 'text', 'fact_codes', 'action', 'effective_from', 'expires_at']))->all(),
            'intents' => IntentRule::orderBy('name')->get()->map(fn ($m) => $m->only(['name', 'reply_code', 'examples', 'keywords', 'negatives', 'priority']))->all(),
            'tests' => BrainTestCase::orderBy('name')->get()->map(fn ($m) => $m->only(['name', 'suite', 'input', 'turns', 'expected_reply', 'expected_action', 'expected_end']))->all(),
            'lexicon' => $tenant->settings['lexicon'] ?? [],
        ];
    }

    public function currentRelease(): ?BrainRelease
    {
        $active = app(TenantContext::class)->requireTenant()->settings['active_brain_release_id'] ?? null;

        return $active ? BrainRelease::find($active) : BrainRelease::orderByDesc('version')->first();
    }

    public function test(array $snapshot, bool $record = true): array
    {
        $results = [];
        $engine = app(BrainEngine::class);
        foreach (['intent', 'conversation', 'safety'] as $suite) {
            $cases = collect($snapshot['tests'] ?? [])->where('suite', $suite);
            $failed = [];
            foreach ($cases as $case) {
                $state = 'answer';
                $errors = [];
                $turns = $suite === 'conversation' ? ($case['turns'] ?? []) : [$case];
                if (empty($turns)) {
                    $errors[] = 'Empty test';
                }
                foreach ($turns as $turn) {
                    if ($state === 'closed') {
                        $errors[] = 'Conversation already ended before this turn';
                        break;
                    }
                    $reply = $engine->reply($snapshot, (string) ($turn['input'] ?? ''), $state);
                    foreach (['expected_reply' => 'reply_code', 'expected_action' => 'action', 'expected_end' => 'end'] as $expected => $actual) {
                        if (isset($turn[$expected]) && $turn[$expected] !== $reply[$actual]) {
                            $errors[] = $actual.': '.$reply[$actual];
                        }
                    }
                    if (! isset($turn['expected_reply']) && ! isset($turn['expected_action']) && ! isset($turn['expected_end'])) {
                        $errors[] = 'Missing expectations';
                    }
                    $state = $reply['state'];
                }
                if ($errors) {
                    $failed[] = ['name' => $case['name'], 'errors' => $errors];
                }
            }
            $total = $cases->count();
            $passed = $total - count($failed);
            $results[$suite] = ['total' => $total, 'passed' => $passed, 'rate' => $total > 0 ? $passed / $total : 0, 'failed_cases' => $failed];
            if ($record) {
                TestRun::create(['suite' => $suite, 'total' => $total, 'passed' => $passed, 'failed_cases' => $failed, 'snapshot_checksum' => $this->checksum($snapshot)]);
            }
        }

        return $results;
    }

    public function publish(): BrainRelease
    {
        return DB::transaction(function (): BrainRelease {
            $tenant = Tenant::whereKey(app(TenantContext::class)->requireTenant()->id)->lockForUpdate()->firstOrFail();
            if (trim($tenant->settings['open_questions'] ?? '') !== '') {
                throw ValidationException::withMessages(['brain' => 'Resolve the open questions in Client settings before publishing.']);
            }
            $snapshot = $this->snapshot();
            $errors = [];
            if (strlen(json_encode([$snapshot['facts'], $snapshot['replies']], JSON_UNESCAPED_UNICODE)) > 30000) {
                $errors[] = 'Keep the compiled facts and replies below 30,000 UTF-8 bytes for this pilot.';
            }
            if (empty($snapshot['replies'])) {
                $errors[] = 'Approve reply cards before publishing.';
            }
            foreach ($snapshot['facts'] as $fact) {
                if (trim($fact['source']) === '') {
                    $errors[] = $fact['code'].' needs a source.';
                }
            }
            foreach ($snapshot['replies'] as $card) {
                if (! app(BrainEngine::class)->safeText($card)) {
                    $errors[] = $card['code'].' violates wording rules.';
                }
                foreach ($card['fact_codes'] ?? [] as $code) {
                    if (! collect($snapshot['facts'])->contains('code', $code)) {
                        $errors[] = $card['code'].' needs approved fact '.$code;
                    }
                }
            }
            $results = $this->test($snapshot);
            foreach ($results as $suite => $result) {
                if ($result['total'] < config('voice.minimum_tests.'.$suite) || $result['rate'] < config('voice.minimum_pass_rate.'.$suite)) {
                    $errors[] = $suite.' tests need at least '.config('voice.minimum_tests.'.$suite).' cases and '.(config('voice.minimum_pass_rate.'.$suite) * 100).'% pass.';
                }
            }
            if ($errors) {
                throw ValidationException::withMessages(['brain' => implode(' ', array_unique($errors))]);
            }
            $version = (BrainRelease::max('version') ?? 0) + 1;
            $release = BrainRelease::create(['version' => $version, 'snapshot' => $snapshot, 'test_results' => $results, 'checksum' => $this->checksum($snapshot), 'published_by' => auth()->id(), 'published_at' => now()]);
            Storage::disk('local')->put('tenants/'.$tenant->id.'/brain/v'.$version.'.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            Storage::disk('local')->put('tenants/'.$tenant->id.'/brain/v'.$version.'.md', $this->wiki($snapshot));
            $tenant->update(['settings' => array_merge($tenant->settings ?? [], ['active_brain_release_id' => $release->id])]);
            app(TenantContext::class)->requireTenant()->refresh();

            return $release;
        });
    }

    public function wiki(array $snapshot): string
    {
        $text = "# Approved client knowledge\n\n";
        foreach ($snapshot['facts'] ?? [] as $fact) {
            $text .= '## '.$fact['code'].' — '.$fact['title']."\n".$fact['body']."\nSource: ".$fact['source']."\nExpires: ".($fact['expires_at'] ?? 'not specified')."\n\n";
        }
        foreach ($snapshot['replies'] ?? [] as $reply) {
            $text .= '## '.$reply['code']."\n".$reply['text']."\n\n";
        }

        return $text;
    }

    public function checksum(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}
