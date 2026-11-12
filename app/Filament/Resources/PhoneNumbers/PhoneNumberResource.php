<?php

namespace App\Filament\Resources\PhoneNumbers;

use App\Filament\Resources\PhoneNumbers\Pages\ManagePhoneNumbers;
use App\Filament\Resources\WorkspaceResource;
use App\Models\PhoneNumber;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PhoneNumberResource extends WorkspaceResource
{
    protected static ?string $model = PhoneNumber::class;

    protected static string $viewAbility = 'manage';

    protected static string $workspaceAbility = 'manage';

    protected static bool $recordsReadOnly = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Campaigns';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('number')->required()->maxLength(30),
            TextInput::make('trunk')->maxLength(180)->helperText('Reference only. SIP credentials belong on the voice server.'),
            Select::make('direction')->options(['both' => 'Inbound and outbound', 'inbound' => 'Inbound', 'outbound' => 'Outbound'])->default('both')->required(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('number'),
            TextEntry::make('trunk'),
            TextEntry::make('direction'),
            TextEntry::make('status'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number'),
            TextColumn::make('trunk'),
            TextColumn::make('direction'),
            TextColumn::make('status')->badge(),
        ])->defaultSort('id', 'desc')->recordActions([

            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePhoneNumbers::route('/')];
    }
}
