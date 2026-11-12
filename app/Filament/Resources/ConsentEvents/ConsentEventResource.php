<?php

namespace App\Filament\Resources\ConsentEvents;

use App\Filament\Resources\ConsentEvents\Pages\ManageConsentEvents;
use App\Filament\Resources\WorkspaceResource;
use App\Models\ConsentEvent;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConsentEventResource extends WorkspaceResource
{
    protected static ?string $model = ConsentEvent::class;

    protected static string $workspaceAbility = 'operate';

    protected static bool $recordsReadOnly = true;

    public static function getNavigationGroup(): ?string
    {
        return 'Contacts';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('contact.phone_e164'),
            TextEntry::make('channel'),
            TextEntry::make('purpose'),
            TextEntry::make('granted'),
            TextEntry::make('source'),
            TextEntry::make('text_version'),
            TextEntry::make('occurred_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('contact.phone_e164'),
            TextColumn::make('channel'),
            TextColumn::make('purpose'),
            IconColumn::make('granted')->boolean(),
            TextColumn::make('source'),
            TextColumn::make('text_version'),
            TextColumn::make('occurred_at')->dateTime('d M Y H:i')->timezone('Asia/Dhaka')->sortable(),
        ])->defaultSort('id', 'desc')->recordActions([

            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageConsentEvents::route('/')];
    }
}
