<?php

namespace App\Filament\Resources\FollowUpTasks;

use App\Filament\Resources\FollowUpTasks\Pages\ManageFollowUpTasks;
use App\Filament\Resources\WorkspaceResource;
use App\Models\FollowUpTask;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FollowUpTaskResource extends WorkspaceResource
{
    protected static ?string $model = FollowUpTask::class;

    protected static string $workspaceAbility = 'operate';

    protected static bool $recordsReadOnly = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Calls & review';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('contact_id')->relationship('contact', 'phone_e164')->searchable(),
            Select::make('type')->options(['callback' => 'Callback', 'review' => 'Review', 'check_registration' => 'Check registration'])->default('callback')->required(),
            Textarea::make('notes')->rows(3),
            DateTimePicker::make('due_at')->timezone('Asia/Dhaka'),
            Select::make('status')->options(['open' => 'Open', 'in_progress' => 'In progress', 'done' => 'Done', 'cancelled' => 'Cancelled'])->default('open')->required(),
            Toggle::make('is_simulation')->default(false)->label('Test / simulation task'),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('contact.phone_e164'),
            TextEntry::make('type'),
            TextEntry::make('status'),
            TextEntry::make('is_simulation'),
            TextEntry::make('due_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('contact.phone_e164'),
            TextColumn::make('type'),
            TextColumn::make('status')->badge(),
            IconColumn::make('is_simulation')->boolean(),
            TextColumn::make('due_at')->dateTime('d M Y H:i')->timezone('Asia/Dhaka')->sortable(),
        ])->defaultSort('id', 'desc')->recordActions([

            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFollowUpTasks::route('/')];
    }
}
