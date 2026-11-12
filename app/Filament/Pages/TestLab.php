<?php

namespace App\Filament\Pages;

use App\Models\VoiceCall;
use App\Services\ConversationService;
use App\Support\TenantAccess;
use App\Support\TenantContext;
use Filament\Pages\Page;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;

class TestLab extends Page
{
    protected string $view = 'filament.pages.test-lab';

    public string $message = '';

    public bool $draft = true;

    public bool $useAi = false;

    #[Locked]
    public ?int $callId = null;

    #[Locked]
    public array $lastReply = [];

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Brain';
    }

    public static function canAccess(): bool
    {
        return TenantAccess::allows('brain');
    }

    public function newConversation(): void
    {
        $this->resetErrorBag();
        abort_unless(TenantAccess::allows('brain'), 403);
        $this->validate(['draft' => 'boolean', 'useAi' => 'boolean']);
        $this->callId = app(ConversationService::class)->start($this->draft)->id;
        $this->lastReply = [];
        $this->message = '';
    }

    public function sendMessage(): void
    {
        abort_unless(TenantAccess::allows('brain'), 403);
        $this->validate(['message' => 'required|string|max:2000', 'useAi' => 'boolean']);
        $key = 'voice-test:'.app(TenantContext::class)->id().':'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('message', 'Please wait a minute before sending more test messages.');

            return;
        }
        RateLimiter::hit($key, 60);
        if ($this->callId === null) {
            $this->callId = app(ConversationService::class)->start($this->draft)->id;
        }
        if (VoiceCall::findOrFail($this->callId)->turns()->count() >= 40) {
            $this->addError('message', 'Start a new conversation after 20 messages.');

            return;
        }
        $this->lastReply = app(ConversationService::class)->turn($this->callId, $this->message, $this->useAi);
        $this->message = '';
    }

    protected function getViewData(): array
    {
        $call = $this->callId ? VoiceCall::findOrFail($this->callId) : null;

        return ['call' => $call, 'turns' => $call?->turns()->orderBy('turn_no')->get() ?? collect(),
            'aiReady' => (bool) (config('voice.anthropic_key') && config('voice.anthropic_model'))];
    }
}
