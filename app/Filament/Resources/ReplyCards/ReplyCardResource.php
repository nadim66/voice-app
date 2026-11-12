<?php

namespace App\Filament\Resources\ReplyCards;

use App\Filament\Resources\ReplyCards\Pages\ManageReplyCards;
use App\Filament\Resources\WorkspaceResource;
use App\Models\KnowledgePage;
use App\Models\ReplyCard;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReplyCardResource extends WorkspaceResource
{
    protected static ?string $model = ReplyCard::class;

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
            TextInput::make('code')->required()->regex('/\AR_[A-Z0-9_]+\z/')->scopedUnique(ignoreRecord: true),
            Textarea::make('text')->required()->rows(3)->maxLength(1000)->columnSpanFull(),
            TagsInput::make('fact_codes')->label('Evidence fact codes')->suggestions(fn () => KnowledgePage::pluck('code')->all()),
            Select::make('action')->options(['none' => 'Reply only', 'send_sms_link' => 'Offer SMS link', 'create_callback' => 'Human callback', 'mark_dnd' => 'Do not call', 'end_call' => 'End conversation'])->default('none')->required(),
            Select::make('status')->options(['draft' => 'Draft', 'review' => 'In review', 'approved' => 'Approved'])->default('draft')->required(),
            DatePicker::make('effective_from'),
            DatePicker::make('expires_at')->afterOrEqual(fn (Get $get) => $get('effective_from') ?: ''),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('code'),
            TextEntry::make('text'),
            TextEntry::make('status'),
            TextEntry::make('action'),
            TextEntry::make('expires_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->searchable(),
            TextColumn::make('text')->limit(65)->wrap(),
            TextColumn::make('status')->badge(),
            TextColumn::make('action')->badge(),
            TextColumn::make('expires_at')->dateTime('d M Y H:i')->timezone('Asia/Dhaka')->sortable(),
        ])->defaultSort('id', 'desc')->recordActions([

            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageReplyCards::route('/')];
    }
}
