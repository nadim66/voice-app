<?php

namespace App\Filament\Resources\Outcomes;

use App\Filament\Resources\Outcomes\Pages\ManageOutcomes;
use App\Filament\Resources\WorkspaceResource;
use App\Models\Outcome;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OutcomeResource extends WorkspaceResource
{
    protected static ?string $model = Outcome::class;

    protected static string $workspaceAbility = 'operate';

    protected static bool $recordsReadOnly = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Results';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('contact_id')->relationship('contact', 'phone_e164')->searchable()->required(),
            Select::make('type')->options(['registered' => 'Registered', 'classroom_joined' => 'Joined classroom', 'appointment' => 'Appointment', 'site_visit' => 'Site visit'])->default('registered')->required(),
            TextInput::make('source')->required()->maxLength(180),
            DateTimePicker::make('occurred_at')->timezone('Asia/Dhaka')->default(now())->maxDate(now())->required(),
            TextInput::make('external_ref')->maxLength(180),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('contact.phone_e164'),
            TextEntry::make('type'),
            TextEntry::make('source'),
            TextEntry::make('occurred_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('contact.phone_e164'),
            TextColumn::make('type'),
            TextColumn::make('source'),
            TextColumn::make('occurred_at')->dateTime('d M Y H:i')->timezone('Asia/Dhaka')->sortable(),
        ])->defaultSort('id', 'desc')->recordActions([

            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageOutcomes::route('/')];
    }
}
