<?php

namespace App\Filament\Resources\IntentRules;

use App\Filament\Resources\IntentRules\Pages\ManageIntentRules;
use App\Filament\Resources\WorkspaceResource;
use App\Models\IntentRule;
use App\Models\ReplyCard;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IntentRuleResource extends WorkspaceResource
{
    protected static ?string $model = IntentRule::class;

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
            TextInput::make('name')->required()->regex('/\A[a-z0-9_]+\z/')->scopedUnique(ignoreRecord: true),
            Select::make('reply_code')->options(fn () => ReplyCard::pluck('code', 'code'))->searchable()->required(),
            TagsInput::make('examples')->required()->columnSpanFull(),
            TagsInput::make('keywords'),
            TagsInput::make('negatives'),
            TextInput::make('priority')->numeric()->default(50)->minValue(1)->maxValue(500)->required(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('reply_code'),
            TextEntry::make('priority'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('reply_code'),
            TextColumn::make('priority'),
        ])->defaultSort('id', 'desc')->recordActions([

            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageIntentRules::route('/')];
    }
}
