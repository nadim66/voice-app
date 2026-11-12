<?php

namespace App\Filament\Resources\VoiceCalls\Pages;

use App\Filament\Resources\VoiceCalls\VoiceCallResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVoiceCalls extends ManageRecords
{
    protected static string $resource = VoiceCallResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
