<x-filament-panels::page>
    <div class="voice-stack">
        <div class="voice-notice"><strong>বাংলা Test Lab</strong><br>অভিভাবক সেজে প্রশ্ন করুন। এখানে call, SMS, DND বা callback বাস্তবে চালানো হয় না। ব্যক্তিগত তথ্য, OTP বা password লিখবেন না।</div>
        <div class="voice-card voice-row">
            <label class="voice-row"><input type="checkbox" wire:model="draft"> Draft preview</label>
            @if($aiReady)<label class="voice-row"><input type="checkbox" wire:model="useAi"> Use configured AI for unknown questions (provider usage applies)</label>@else<span class="voice-muted">Offline matching · API key ছাড়াই test করুন</span>@endif
            <button class="voice-button secondary" wire:click="newConversation" wire:loading.attr="disabled">New conversation</button>
            @if($call)<span class="voice-tag">{{ $call->context['draft'] ? 'Draft' : 'Published' }} · {{ $call->state }}</span>@endif
        </div>
        <div class="voice-card voice-chat">
            @forelse($turns as $turn)
                <div class="voice-message {{ $turn->speaker==='human'?'human':'' }}"><strong>{{ $turn->speaker==='human'?'আপনি':'এআই সহকারী' }}</strong><div>{{ $turn->text }}</div>@if($turn->speaker==='bot')<div class="voice-muted">{{ $turn->reply_code }} · {{ $turn->action }} (preview) · {{ $turn->latency_ms }} ms</div><button type="button" class="voice-button secondary" x-on:click="mrcPreviewVoice(@js($turn->text))">Listen in browser</button>@endif</div>
            @empty<p class="voice-muted">উদাহরণ: “টাকা লাগবে?”, “registration korbo kivabe”, “২০২৭ সালে কত?”, “আর কল দিবেন না”।</p>@endforelse
        </div>
        <form class="voice-card voice-stack" wire:submit="sendMessage">
            <label class="voice-field">আপনার প্রশ্ন<textarea class="voice-input" rows="3" wire:model="message" maxlength="2000" placeholder="বাংলা বা Banglish-এ লিখুন…"></textarea></label>
            @error('message')<p class="voice-error">{{ $message }}</p>@enderror
            <div><button type="submit" class="voice-button" wire:loading.attr="disabled" @disabled($call?->state==='closed')>Send test message</button><span wire:loading wire:target="sendMessage" class="voice-muted"> উত্তর তৈরি হচ্ছে…</span></div>
        </form>
        @if($lastReply)<div class="voice-card"><h2 class="voice-title">Why this answer?</h2><div class="voice-grid"><div>Intent<br><strong>{{ $lastReply['intent'] }}</strong></div><div>Confidence<br><strong>{{ round($lastReply['confidence']*100) }}%</strong></div><div>Evidence<br><strong>{{ implode(', ',$lastReply['evidence']) ?: 'Platform safety rule / fallback' }}</strong></div><div>Provider<br><strong>{{ $lastReply['provider'] }}</strong></div></div></div>@endif
    </div>
</x-filament-panels::page>
