<?php

namespace App\Filament\Resources\Integrations;

use App\Filament\Resources\Integrations\Pages\ManageIntegrations;
use App\Filament\Resources\WorkspaceResource;
use App\Models\Integration;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IntegrationResource extends WorkspaceResource
{
    protected static ?string $model = Integration::class;

    protected static string $viewAbility = 'manage';

    protected static string $workspaceAbility = 'manage';

    protected static bool $recordsReadOnly = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Campaigns';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(180),
            Select::make('kind')->options(['sip' => 'SIP / voice bridge', 'sms' => 'SMS gateway', 'anthropic' => 'Anthropic brain', 'stt' => 'Speech recognition', 'tts' => 'Voice synthesis', 'crm_read_only' => 'Read-only CRM sync'])->required(),
            TextInput::make('secret_ref')->label('Environment variable reference')->regex('/\A[A-Z][A-Z0-9_]*\z/')->maxLength(120)->helperText('Store the real key in server configuration. Enter its variable name here.'),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('kind'),
            TextEntry::make('secret_ref'),
            TextEntry::make('status'),
            TextEntry::make('last_sync_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('kind'),
            TextColumn::make('secret_ref'),
            TextColumn::make('status')->badge(),
            TextColumn::make('last_sync_at')->dateTime('d M Y H:i')->timezone('Asia/Dhaka')->sortable(),
        ])->defaultSort('id', 'desc')->recordActions([

            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageIntegrations::route('/')];
    }
}
