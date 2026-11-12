<?php

namespace App\Filament\Resources\CallJobs\Pages;

use App\Filament\Resources\CallJobs\CallJobResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCallJobs extends ManageRecords
{
    protected static string $resource = CallJobResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
