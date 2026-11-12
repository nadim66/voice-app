<?php

namespace App\Filament\Resources\ReviewItems\Pages;

use App\Filament\Resources\ReviewItems\ReviewItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageReviewItems extends ManageRecords
{
    protected static string $resource = ReviewItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
