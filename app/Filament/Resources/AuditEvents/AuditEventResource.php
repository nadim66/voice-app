<?php

namespace App\Filament\Resources\AuditEvents;

use App\Filament\Resources\AuditEvents\Pages\ManageAuditEvents;
use App\Filament\Resources\WorkspaceResource;
use App\Models\AuditEvent;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditEventResource extends WorkspaceResource
{
    protected static ?string $model = AuditEvent::class;

    protected static string $viewAbility = 'manage';

    protected static string $workspaceAbility = 'manage';

    protected static bool $recordsReadOnly = true;

    public static function getNavigationGroup(): ?string
    {
        return 'Audit';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('user_id'),
            TextEntry::make('action'),
            TextEntry::make('target'),
            TextEntry::make('created_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user_id'),
            TextColumn::make('action')->badge(),
            TextColumn::make('target'),
            TextColumn::make('created_at')->dateTime('d M Y H:i')->timezone('Asia/Dhaka')->sortable(),
        ])->defaultSort('id', 'desc')->recordActions([

            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditEvents::route('/')];
    }
}
