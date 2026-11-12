<?php

namespace App\Filament\Resources\FollowUpTasks\Pages;

use App\Filament\Resources\FollowUpTasks\FollowUpTaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFollowUpTasks extends ManageRecords
{
    protected static string $resource = FollowUpTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
