<?php

namespace App\Filament\Resources\AuditEvents\Pages;

use App\Filament\Resources\AuditEvents\AuditEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAuditEvents extends ManageRecords
{
    protected static string $resource = AuditEventResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
