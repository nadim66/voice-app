<?php

namespace App\Filament\Resources\BrainTestCases\Pages;

use App\Filament\Resources\BrainTestCases\BrainTestCaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBrainTestCases extends ManageRecords
{
    protected static string $resource = BrainTestCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
