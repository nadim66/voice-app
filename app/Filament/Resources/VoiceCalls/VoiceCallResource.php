<?php

namespace App\Filament\Resources\VoiceCalls;

use App\Filament\Resources\VoiceCalls\Pages\ManageVoiceCalls;
use App\Filament\Resources\WorkspaceResource;
use App\Models\VoiceCall;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VoiceCallResource extends WorkspaceResource
{
    protected static ?string $model = VoiceCall::class;

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
            TextEntry::make('call_uuid'),
            TextEntry::make('direction'),
            TextEntry::make('is_simulation'),
            TextEntry::make('result'),
            TextEntry::make('outcome'),
            TextEntry::make('started_at'),
            TextEntry::make('cost_tk'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('call_uuid')->searchable(),
            TextColumn::make('direction'),
            IconColumn::make('is_simulation')->boolean(),
            TextColumn::make('result')->badge(),
            TextColumn::make('outcome'),
            TextColumn::make('started_at')->dateTime('d M Y H:i')->timezone('Asia/Dhaka')->sortable(),
            TextColumn::make('cost_tk'),
        ])->defaultSort('id', 'desc')->recordActions([
            Action::make('transcript')->label('Transcript')->modalSubmitAction(false)->modalContent(fn ($record) => view('filament.partials.transcript', ['call' => $record, 'turns' => $record->turns()->orderBy('turn_no')->get()])),
            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageVoiceCalls::route('/')];
    }
}
