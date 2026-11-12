<?php

namespace App\Filament\Resources\KnowledgePages;

use App\Filament\Resources\KnowledgePages\Pages\ManageKnowledgePages;
use App\Filament\Resources\WorkspaceResource;
use App\Models\KnowledgePage;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KnowledgePageResource extends WorkspaceResource
{
    protected static ?string $model = KnowledgePage::class;

    protected static string $viewAbility = 'brain';

    protected static string $workspaceAbility = 'brain';

    protected static bool $recordsReadOnly = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Brain';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->regex('/\AF_[A-Z0-9_]+\z/')->scopedUnique(ignoreRecord: true),
            TextInput::make('title')->required()->maxLength(180),
            Textarea::make('body')->required()->maxLength(12000)->rows(8)->columnSpanFull(),
            TextInput::make('source')->required()->maxLength(255),
            Select::make('status')->options(['draft' => 'Draft', 'review' => 'In review', 'approved' => 'Approved'])->default('draft')->required(),
            DatePicker::make('effective_from'),
            DatePicker::make('expires_at')->afterOrEqual(fn (Get $get) => $get('effective_from') ?: ''),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('code'),
            TextEntry::make('title'),
            TextEntry::make('status'),
            TextEntry::make('expires_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->searchable(),
            TextColumn::make('title')->searchable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('expires_at')->dateTime('d M Y H:i')->timezone('Asia/Dhaka')->sortable(),
        ])->defaultSort('id', 'desc')->recordActions([

            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageKnowledgePages::route('/')];
    }
}
