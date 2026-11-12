<?php

namespace App\Filament\Resources\Messages;

use App\Filament\Resources\Messages\Pages\ManageMessages;
use App\Filament\Resources\WorkspaceResource;
use App\Models\Message;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessageResource extends WorkspaceResource
{
    protected static ?string $model = Message::class;

    protected static string $workspaceAbility = 'operate';

    protected static bool $recordsReadOnly = true;

    public static function getNavigationGroup(): ?string
    {
        return 'Calls & review';
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
            TextEntry::make('template_code'),
            TextEntry::make('body'),
            TextEntry::make('status'),
            TextEntry::make('created_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('contact.phone_e164'),
            TextColumn::make('channel'),
            TextColumn::make('template_code'),
            TextColumn::make('body')->limit(65)->wrap(),
            TextColumn::make('status')->badge(),
            TextColumn::make('created_at')->dateTime('d M Y H:i')->timezone('Asia/Dhaka')->sortable(),
        ])->defaultSort('id', 'desc')->recordActions([

            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMessages::route('/')];
    }
}
