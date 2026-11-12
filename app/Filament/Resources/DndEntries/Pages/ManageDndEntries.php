<?php

namespace App\Filament\Resources\DndEntries\Pages;

use App\Filament\Resources\DndEntries\DndEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDndEntries extends ManageRecords
{
    protected static string $resource = DndEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
