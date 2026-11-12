<?php

namespace App\Filament\Resources\CallJobs;

use App\Filament\Resources\CallJobs\Pages\ManageCallJobs;
use App\Filament\Resources\WorkspaceResource;
use App\Models\CallJob;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CallJobResource extends WorkspaceResource
{
    protected static ?string $model = CallJob::class;

    protected static string $workspaceAbility = 'operate';

    protected static bool $recordsReadOnly = true;

    public static function getNavigationGroup(): ?string
    {
        return 'Campaigns';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('campaign_id'),
            TextEntry::make('contact.phone_e164'),
            TextEntry::make('attempt_no'),
            TextEntry::make('status'),
            TextEntry::make('reason'),
            TextEntry::make('next_attempt_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('campaign_id'),
            TextColumn::make('contact.phone_e164'),
            TextColumn::make('attempt_no'),
            TextColumn::make('status')->badge(),
            TextColumn::make('reason'),
            TextColumn::make('next_attempt_at')->dateTime('d M Y H:i')->timezone('Asia/Dhaka')->sortable(),
        ])->defaultSort('id', 'desc')->recordActions([

            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCallJobs::route('/')];
    }
}
