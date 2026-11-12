<x-filament-panels::page>
    <div class="voice-stack">
        <div class="voice-grid">@foreach($stats as $label=>$value)<div class="voice-card"><div class="voice-muted">{{ $label }}</div><div class="voice-number">{{ $value }}</div></div>@endforeach</div>
        <div class="voice-card voice-table-wrap"><h2 class="voice-title">Registration outcomes by group</h2><p class="voice-muted">এটি এখন recorded outcome-এর সাধারণ তুলনা। ৪৮ ঘণ্টার verified attribution ও pilot-এর causality analysis CRM/voice bridge যুক্ত হওয়ার পরে করা যাবে।</p><table class="voice-table"><thead><tr><th>Group</th><th>Contacts</th><th>Registered</th><th>Rate</th></tr></thead><tbody>@foreach($groups as $group=>$result)<tr><td>{{ ucfirst($group) }}</td><td>{{ $result['total'] }}</td><td>{{ $result['registered'] }}</td><td>{{ $result['rate']===null?'—':$result['rate'].'%' }}</td></tr>@endforeach</tbody></table><p class="voice-muted">Recorded difference: {{ $lift===null?'Need contacts in both groups':$lift.' percentage points' }}</p></div>
        <div class="voice-notice">Text simulation-এর call count ও dry-run messages-কে real delivery হিসেবে গণনা করা হয় না। Provider billing যুক্ত না হলে token usage-এর নির্ভরযোগ্য ৳ হিসাব দেখানো হবে না।</div>
    </div>
</x-filament-panels::page>
