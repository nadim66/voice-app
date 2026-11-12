<?php

namespace App\Filament\Resources\ContactLists;

use App\Filament\Resources\ContactLists\Pages\ManageContactLists;
use App\Filament\Resources\WorkspaceResource;
use App\Models\ContactList;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactListResource extends WorkspaceResource
{
    protected static ?string $model = ContactList::class;

    protected static string $workspaceAbility = 'operate';

    protected static bool $recordsReadOnly = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Contacts';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(180),
            Textarea::make('description')->rows(2),
            Repeater::make('members')->relationship()->schema([Select::make('contact_id')->relationship('contact', 'phone_e164')->searchable()->required()])->maxItems(1000)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('description'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('description')->limit(65)->wrap(),
        ])->defaultSort('id', 'desc')->recordActions([

            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageContactLists::route('/')];
    }
}
