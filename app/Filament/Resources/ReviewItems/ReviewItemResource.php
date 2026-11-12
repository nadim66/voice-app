<?php

namespace App\Filament\Resources\ReviewItems;

use App\Filament\Resources\ReviewItems\Pages\ManageReviewItems;
use App\Filament\Resources\WorkspaceResource;
use App\Models\ReviewItem;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReviewItemResource extends WorkspaceResource
{
    protected static ?string $model = ReviewItem::class;

    protected static string $workspaceAbility = 'review';

    protected static bool $recordsReadOnly = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Calls & review';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('question')->required()->rows(3),
            TextInput::make('issue')->required()->maxLength(180),
            Textarea::make('suggested_fix')->rows(3),
            Select::make('status')->options(['open' => 'Open', 'reviewing' => 'Reviewing', 'resolved' => 'Resolved'])->default('open')->required(),
            TextInput::make('fixed_in_version')->numeric()->minValue(1),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('question'),
            TextEntry::make('issue'),
            TextEntry::make('status'),
            TextEntry::make('fixed_in_version'),
            TextEntry::make('created_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('question')->searchable()->limit(65)->wrap(),
            TextColumn::make('issue'),
            TextColumn::make('status')->badge(),
            TextColumn::make('fixed_in_version'),
            TextColumn::make('created_at')->dateTime('d M Y H:i')->timezone('Asia/Dhaka')->sortable(),
        ])->defaultSort('id', 'desc')->recordActions([

            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageReviewItems::route('/')];
    }
}
