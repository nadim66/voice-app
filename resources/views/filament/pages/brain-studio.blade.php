<x-filament-panels::page>
    <div class="voice-stack">
        <div class="voice-notice"><strong>{{ $tenant->name }} · Brain Studio</strong><br>তথ্য ও উত্তর review করে approve করুন। Publish করলে একটি আলাদা, অপরিবর্তনীয় version তৈরি হবে।</div>
        <div class="voice-grid">@foreach($counts as $label=>$value)<div class="voice-card"><div class="voice-muted">{{ $label }}</div><div class="voice-number">{{ $value }}</div></div>@endforeach</div>
        <div class="voice-card voice-stack">
            <div class="voice-row"><span class="voice-tag">{{ $release ? 'Active version: v'.$release->version : 'No published brain' }}</span><span class="voice-tag">Calls remain disconnected</span></div>
            <p class="voice-muted">প্রকাশের জন্য কমপক্ষে ১০০ intent, ২০ conversation ও ৩০ safety test প্রয়োজন। Intent/conversation-এ ৯০% এবং safety-তে ১০০% pass করতে হবে। Draft preview-এর ফল দিয়ে approval দেওয়া হয় না।</p>
            <div class="voice-row"><button class="voice-button secondary" wire:click="runTests" wire:loading.attr="disabled">Run draft tests</button>@if($canPublish)<button class="voice-button" wire:click="publish" wire:confirm="Publish a new version using only approved facts and replies?" wire:loading.attr="disabled">Publish approved version</button>@endif</div>
            @error('brain')<p class="voice-error">{{ $message }}</p>@enderror
        </div>
        @if(trim($tenant->settings['open_questions'] ?? '')!=='')<div class="voice-card"><h2 class="voice-title">Open questions</h2><p class="voice-pre">{{ $tenant->settings['open_questions'] }}</p><p class="voice-muted">Client settings-এ উত্তর নিশ্চিত করে এই তালিকা সম্পন্ন করুন।</p></div>@endif
        @if($results)<div class="voice-card voice-table-wrap"><table class="voice-table"><thead><tr><th>Suite</th><th>Passed</th><th>Rate</th><th>Failed cases</th></tr></thead><tbody>@foreach($results as $suite=>$result)<tr><td>{{ ucfirst($suite) }}</td><td>{{ $result['passed'] }} / {{ $result['total'] }}</td><td>{{ round($result['rate']*100,1) }}%</td><td>@foreach($result['failed_cases'] as $failure)<div>{{ $failure['name'] }}: {{ implode('; ',$failure['errors']) }}</div>@endforeach</td></tr>@endforeach</tbody></table></div>@endif
    </div>
</x-filament-panels::page>
