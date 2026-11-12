<?php

namespace App\Filament\Resources\BrainReleases;

use App\Filament\Resources\BrainReleases\Pages\ManageBrainReleases;
use App\Filament\Resources\WorkspaceResource;
use App\Models\AuditEvent;
use App\Models\BrainRelease;
use App\Support\TenantAccess;
use App\Support\TenantContext;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BrainReleaseResource extends WorkspaceResource
{
    protected static ?string $model = BrainRelease::class;

    protected static string $viewAbility = 'brain';

    protected static string $workspaceAbility = 'publish';

    protected static bool $recordsReadOnly = true;

    public static function getNavigationGroup(): ?string
    {
        return 'Brain';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('version'),
            TextEntry::make('checksum'),
            TextEntry::make('published_at'),
            TextEntry::make('test_results')->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('version'),
            TextColumn::make('checksum')->limit(65)->wrap(),
            TextColumn::make('published_at')->dateTime('d M Y H:i')->timezone('Asia/Dhaka')->sortable(),
        ])->defaultSort('id', 'desc')->recordActions([
            Action::make('download')->label('Download brain')->action(function ($record) {
                abort_unless(TenantAccess::allows('view'), 403);

                return response()->streamDownload(fn () => print (json_encode($record->snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), 'brain-v'.$record->version.'.json', ['Content-Type' => 'application/json']);
            }),
            Action::make('activate')->label('Use this version')->requiresConfirmation()->visible(fn () => TenantAccess::allows('publish'))->action(function ($record): void {
                abort_unless(TenantAccess::allows('publish'), 403);
                $tenant = app(TenantContext::class)->requireTenant();
                $tenant->update(['settings' => array_merge($tenant->settings ?? [], ['active_brain_release_id' => $record->id])]);
                AuditEvent::create(['user_id' => auth()->id(), 'action' => 'brain.activated', 'target' => (string) $record->id]);
                Notification::make()->title('Active brain changed')->success()->send();
            }),
            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBrainReleases::route('/')];
    }
}
