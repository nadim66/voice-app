<?php

namespace App\Filament\Resources\BrainTestCases;

use App\Filament\Resources\BrainTestCases\Pages\ManageBrainTestCases;
use App\Filament\Resources\WorkspaceResource;
use App\Models\BrainTestCase;
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

class BrainTestCaseResource extends WorkspaceResource
{
    protected static ?string $model = BrainTestCase::class;

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
            TextInput::make('name')->required()->scopedUnique(ignoreRecord: true),
            Select::make('suite')->options(['intent' => 'Intent', 'conversation' => 'Conversation', 'safety' => 'Safety'])->required(),
            Textarea::make('input')->rows(2),
            TextInput::make('expected_reply'),
            TextInput::make('expected_action'),
            Select::make('expected_end')->options([1 => 'Must end', 0 => 'Must continue']),
            Repeater::make('turns')->schema([Textarea::make('input')->required(), TextInput::make('expected_reply')->required(), TextInput::make('expected_action')])->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('suite'),
            TextEntry::make('input'),
            TextEntry::make('expected_reply'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('suite')->badge(),
            TextColumn::make('input'),
            TextColumn::make('expected_reply'),
        ])->defaultSort('id', 'desc')->recordActions([

            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBrainTestCases::route('/')];
    }
}
