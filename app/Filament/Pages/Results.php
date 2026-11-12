<?php

namespace App\Filament\Pages;

use App\Models\Contact;
use App\Models\FollowUpTask;
use App\Models\ReviewItem;
use App\Models\VoiceCall;
use App\Support\TenantAccess;
use Filament\Pages\Page;

class Results extends Page
{
    protected string $view = 'filament.pages.results';

    public static function getNavigationGroup(): ?string
    {
        return 'Results';
    }

    public static function canAccess(): bool
    {
        return TenantAccess::allows('view');
    }

    protected function getViewData(): array
    {
        $groups = [];
        foreach (['call', 'control'] as $group) {
            $query = Contact::where('experiment_group', $group);
            $total = (clone $query)->count();
            $registered = (clone $query)->whereHas('outcomes', fn ($q) => $q->where('type', 'registered'))->count();
            $groups[$group] = ['total' => $total, 'registered' => $registered, 'rate' => $total ? round($registered * 100 / $total, 1) : null];
        }

        return ['stats' => [
            'Contacts' => Contact::count(), 'Real calls' => VoiceCall::where('is_simulation', false)->count(),
            'Text test sessions' => VoiceCall::where('is_simulation', true)->count(),
            'Recorded call cost (৳)' => number_format((float) VoiceCall::where('is_simulation', false)->sum('cost_tk'), 2),
            'Open review items' => ReviewItem::where('status', 'open')->count(), 'Pending real callbacks' => FollowUpTask::where('is_simulation', false)->where('status', 'open')->count(),
        ], 'groups' => $groups, 'lift' => $groups['call']['rate'] !== null && $groups['control']['rate'] !== null ? round($groups['call']['rate'] - $groups['control']['rate'], 1) : null];
    }
}
