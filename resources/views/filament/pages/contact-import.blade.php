<x-filament-panels::page>
    <div class="voice-stack">
        <div class="voice-notice"><strong>Contact import</strong><br>প্রতি import-এ সর্বোচ্চ ১,০০০ row। বাংলাদেশি নম্বর 8801… আকারে রাখা হবে। Consent না থাকলে campaign-এ নম্বরটি বাদ যাবে; পুরোনো consent withdrawal CSV দিয়ে মুছে যাবে না।</div>
        <form wire:submit="import" class="voice-card voice-stack">
            <div><button type="button" class="voice-button secondary" wire:click="template">Download CSV template</button></div>
            <label class="voice-field">Contact list (optional)<select class="voice-input" wire:model="listId"><option value="">No list</option>@foreach($lists as $list)<option value="{{ $list->id }}">{{ $list->name }}</option>@endforeach</select></label>
            <label class="voice-field">CSV file<input class="voice-input" type="file" wire:model="upload" accept=".csv,.txt"></label>
            @error('upload')<p class="voice-error">{{ $message }}</p>@enderror
            <label class="voice-field">Or paste CSV<textarea class="voice-input" wire:model="csv" rows="7" placeholder="phone,parent_name,student_class,version,source,lead_at,consent_calls,consent_marketing"></textarea></label>
            @error('csv')<p class="voice-error">{{ $message }}</p>@enderror
            @error('listId')<p class="voice-error">{{ $message }}</p>@enderror
            <p class="voice-muted">Consent “yes” হলে consent_calls_at অথবা lead_at দিতে হবে। নতুন consent text-এর version সংরক্ষণ করুন। experiment_group-এ control দিলে ওই contact-কে dial করা হবে না।</p>
            <div><button class="voice-button" wire:loading.attr="disabled">Import contacts</button></div>
        </form>
        @if($result)<div class="voice-card"><h2 class="voice-title">{{ $result['created'] }} created · {{ $result['updated'] }} updated · {{ $result['invalid'] }} invalid</h2>@foreach($result['errors'] as $error)<p class="voice-error">Row {{ $error['line'] }}: {{ $error['error'] }}</p>@endforeach</div>@endif
    </div>
</x-filament-panels::page>
