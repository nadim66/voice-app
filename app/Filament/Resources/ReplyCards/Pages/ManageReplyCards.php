<?php

namespace App\Filament\Resources\ReplyCards\Pages;

use App\Filament\Resources\ReplyCards\ReplyCardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageReplyCards extends ManageRecords
{
    protected static string $resource = ReplyCardResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
