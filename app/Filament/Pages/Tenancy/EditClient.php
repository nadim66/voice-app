<?php

namespace App\Filament\Pages\Tenancy;

use App\Support\TenantAccess;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditClient extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Client settings';
    }

    public static function canView(Model $tenant): bool
    {
        return TenantAccess::allows('manage');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            Select::make('status')->options(['pilot' => 'Pilot', 'active' => 'Active', 'paused' => 'Paused'])->required(),
            TextInput::make('timezone')->required()->rules(['timezone']),
            TextInput::make('settings.calling_start')->label('Calling window starts')->type('time')->default('10:00')->required(),
            TextInput::make('settings.calling_end')->label('Calling window ends')->type('time')->default('20:00')->required(),
            TextInput::make('settings.daily_cap')->label('Daily call limit')->numeric()->minValue(1)->maxValue(10000)->default(300)->required(),
            TextInput::make('settings.max_concurrent_calls')->label('Maximum concurrent calls')->numeric()->minValue(1)->maxValue(30)->default(5)->required(),
            Textarea::make('settings.open_questions')->label('Open questions before publishing')->rows(5)->columnSpanFull(),
            KeyValue::make('settings.lexicon')->label('Pronunciation dictionary')->columnSpanFull(),
        ]);
    }
}
