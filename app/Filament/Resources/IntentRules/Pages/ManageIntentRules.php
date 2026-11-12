<?php

namespace App\Filament\Resources\IntentRules\Pages;

use App\Filament\Resources\IntentRules\IntentRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageIntentRules extends ManageRecords
{
    protected static string $resource = IntentRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
