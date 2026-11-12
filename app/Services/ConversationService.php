<?php

namespace App\Services;

use App\Models\CallEvent;
use App\Models\CallSummary;
use App\Models\CallTurn;
use App\Models\FollowUpTask;
use App\Models\Message;
use App\Models\ReviewItem;
use App\Models\UsageRecord;
use App\Models\VoiceCall;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    public function start(bool $draft = true): VoiceCall
    {
        $publisher = app(BrainPublisher::class);
        $release = $draft ? null : $publisher->currentRelease();
        if (! $draft && ! $release) {
            throw ValidationException::withMessages(['message' => 'Publish a reviewed brain first or use draft preview.']);
        }

        $snapshot = $release?->snapshot ?? $publisher->snapshot(true);
        unset($snapshot['tests']);

        return VoiceCall::create(['call_uuid' => (string) Str::uuid(), 'brain_release_id' => $release?->id, 'direction' => 'test', 'started_at' => now(), 'is_simulation' => true, 'context' => ['snapshot' => $snapshot, 'draft' => $draft], 'state' => 'greet']);
    }

    public function turn(int $callId, string $text, bool $useAi = false): array
    {
        return DB::transaction(function () use ($callId, $text, $useAi): array {
            $call = VoiceCall::whereKey($callId)->lockForUpdate()->firstOrFail();
            if (! $call->is_simulation || $call->state === 'closed') {
                throw ValidationException::withMessages(['message' => 'Start a new test conversation.']);
            }
            $engine = app(BrainEngine::class);
            $text = $engine->redact($text);
            $reply = $engine->reply($call->context['snapshot'], $text, $call->state, $useAi);
            $turn = ($call->turns()->max('turn_no') ?? 0) + 1;
            CallTurn::create(['voice_call_id' => $call->id, 'turn_no' => $turn, 'speaker' => 'human', 'text' => $text]);
            CallTurn::create(['voice_call_id' => $call->id, 'turn_no' => $turn + 1, 'speaker' => 'bot', 'text' => $reply['text'], 'intent' => $reply['intent'], 'reply_code' => $reply['reply_code'], 'action' => $reply['action'], 'confidence' => $reply['confidence'], 'latency_ms' => $reply['latency_ms'], 'evidence' => $reply['evidence']]);
            if (in_array($reply['intent'], ['unknown', 'guard_fallback', 'provider_error'], true)) {
                ReviewItem::create(['voice_call_id' => $call->id, 'question' => $text, 'issue' => $reply['intent']]);
            }
            if ($reply['action'] !== 'none') {
                CallEvent::create(['voice_call_id' => $call->id, 'type' => 'simulated_action', 'data' => ['action' => $reply['action'], 'executed' => false]]);
                if ($reply['action'] === 'create_callback') {
                    FollowUpTask::create(['voice_call_id' => $call->id, 'type' => 'callback', 'notes' => 'Test Lab preview: no real callback requested.', 'is_simulation' => true]);
                }
                if ($reply['action'] === 'send_sms_link') {
                    Message::create(['voice_call_id' => $call->id, 'body' => $reply['text'], 'status' => 'dry_run']);
                }
            }
            if ($reply['provider'] !== 'offline') {
                UsageRecord::create(['voice_call_id' => $call->id, 'provider' => $reply['provider'], 'llm_input_tokens' => $reply['usage']['input_tokens'] ?? 0, 'llm_output_tokens' => $reply['usage']['output_tokens'] ?? 0]);
            }
            $call->update(['state' => $reply['state'], 'result' => $reply['end'] ? 'simulation_completed' : 'in_progress', 'outcome' => $reply['action'], 'ended_at' => $reply['end'] ? now() : null, 'duration_sec' => (int) $call->started_at->diffInSeconds(now())]);
            if ($reply['end']) {
                CallSummary::create(['voice_call_id' => $call->id, 'summary_bn' => 'টেক্সট সিমুলেশন শেষ হয়েছে; '.($turn + 1).'টি কথোপকথনের পালা রেকর্ড করা হয়েছে।', 'lead_score' => 0, 'sentiment' => 'unknown', 'next_action' => $reply['action']]);
            }

            return $reply;
        });
    }
}
