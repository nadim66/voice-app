<?php

namespace App\Filament\Resources\ConsentEvents\Pages;

use App\Filament\Resources\ConsentEvents\ConsentEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageConsentEvents extends ManageRecords
{
    protected static string $resource = ConsentEventResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
