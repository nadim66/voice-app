<?php

namespace App\Filament\Resources\KnowledgePages\Pages;

use App\Filament\Resources\KnowledgePages\KnowledgePageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKnowledgePages extends ManageRecords
{
    protected static string $resource = KnowledgePageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
