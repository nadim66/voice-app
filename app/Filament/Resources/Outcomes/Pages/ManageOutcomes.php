<?php

namespace App\Filament\Resources\Outcomes\Pages;

use App\Filament\Resources\Outcomes\OutcomeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOutcomes extends ManageRecords
{
    protected static string $resource = OutcomeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
