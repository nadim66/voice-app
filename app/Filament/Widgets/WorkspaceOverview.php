<?php

namespace App\Filament\Widgets;

use App\Models\Contact;
use App\Models\ReviewItem;
use App\Models\VoiceCall;
use App\Support\TenantAccess;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WorkspaceOverview extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return TenantAccess::allows('view');
    }

    protected function getStats(): array
    {
        return [Stat::make('Contacts', Contact::count())->description('Current client only'),
            Stat::make('Text test sessions', VoiceCall::where('is_simulation', true)->count())->description('No phone calls placed'),
            Stat::make('Open reviews', ReviewItem::where('status', 'open')->count())->description('Questions needing attention')];
    }
}
