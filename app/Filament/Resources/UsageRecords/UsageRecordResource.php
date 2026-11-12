<?php

namespace App\Filament\Resources\UsageRecords;

use App\Filament\Resources\UsageRecords\Pages\ManageUsageRecords;
use App\Filament\Resources\WorkspaceResource;
use App\Models\UsageRecord;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsageRecordResource extends WorkspaceResource
{
    protected static ?string $model = UsageRecord::class;

    protected static string $workspaceAbility = 'manage';

    protected static bool $recordsReadOnly = true;

    public static function getNavigationGroup(): ?string
    {
        return 'Results';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('voice_call_id'),
            TextEntry::make('provider'),
            TextEntry::make('telephony_sec'),
            TextEntry::make('llm_input_tokens'),
            TextEntry::make('llm_output_tokens'),
            TextEntry::make('cost_tk'),
            TextEntry::make('created_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('voice_call_id'),
            TextColumn::make('provider'),
            TextColumn::make('telephony_sec'),
            TextColumn::make('llm_input_tokens'),
            TextColumn::make('llm_output_tokens'),
            TextColumn::make('cost_tk')->label('Cost')->formatStateUsing(fn () => 'Not priced'),
            TextColumn::make('created_at')->dateTime('d M Y H:i')->timezone('Asia/Dhaka')->sortable(),
        ])->defaultSort('id', 'desc')->recordActions([

            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsageRecords::route('/')];
    }
}
