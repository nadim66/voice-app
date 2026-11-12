<?php

namespace App\Filament\Resources\ContactLists\Pages;

use App\Filament\Resources\ContactLists\ContactListResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageContactLists extends ManageRecords
{
    protected static string $resource = ContactListResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
