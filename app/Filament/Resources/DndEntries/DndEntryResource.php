<?php

namespace App\Filament\Resources\DndEntries;

use App\Filament\Resources\DndEntries\Pages\ManageDndEntries;
use App\Filament\Resources\WorkspaceResource;
use App\Models\DndEntry;
use App\Services\ContactImporter;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DndEntryResource extends WorkspaceResource
{
    protected static bool $recordsEditable = false;

    protected static ?string $model = DndEntry::class;

    protected static string $workspaceAbility = 'operate';

    protected static bool $recordsReadOnly = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Contacts';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('phone_e164')->regex('/\A8801[3-9][0-9]{8}\z/')->helperText('Use 8801… format. CSV import also accepts local numbers.')->required()->dehydrateStateUsing(fn ($state) => app(ContactImporter::class)->phone($state))->scopedUnique(ignoreRecord: true),
            TextInput::make('reason')->required()->maxLength(255),
            TextInput::make('source')->default('manual')->required()->maxLength(180),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('phone_e164'),
            TextEntry::make('reason'),
            TextEntry::make('source'),
            TextEntry::make('created_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('phone_e164')->searchable(),
            TextColumn::make('reason'),
            TextColumn::make('source'),
            TextColumn::make('created_at')->dateTime('d M Y H:i')->timezone('Asia/Dhaka')->sortable(),
        ])->defaultSort('id', 'desc')->recordActions([

            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDndEntries::route('/')];
    }
}
