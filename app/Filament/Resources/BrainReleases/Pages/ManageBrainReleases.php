<?php

namespace App\Filament\Resources\BrainReleases\Pages;

use App\Filament\Resources\BrainReleases\BrainReleaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBrainReleases extends ManageRecords
{
    protected static string $resource = BrainReleaseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
