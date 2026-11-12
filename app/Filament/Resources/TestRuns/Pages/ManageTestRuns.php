<?php

namespace App\Filament\Resources\TestRuns\Pages;

use App\Filament\Resources\TestRuns\TestRunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTestRuns extends ManageRecords
{
    protected static string $resource = TestRunResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
