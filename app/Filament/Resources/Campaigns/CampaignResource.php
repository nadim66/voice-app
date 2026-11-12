<?php

namespace App\Filament\Resources\Campaigns;

use App\Filament\Resources\Campaigns\Pages\ManageCampaigns;
use App\Filament\Resources\WorkspaceResource;
use App\Models\Campaign;
use App\Services\CampaignPlanner;
use App\Support\TenantAccess;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CampaignResource extends WorkspaceResource
{
    protected static ?string $model = Campaign::class;

    protected static string $workspaceAbility = 'operate';

    protected static bool $recordsReadOnly = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Campaigns';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(180),
            Select::make('goal')->options(['registration' => 'Registration', 'callback' => 'Callback', 'appointment' => 'Appointment', 'site_visit' => 'Site visit', 'order_confirm' => 'Order confirmation'])->default('registration')->required(),
            Select::make('purpose')->options(['service' => 'Service consent required', 'marketing' => 'Marketing consent required'])->default('service')->required(),
            Select::make('mode')->options(['hybrid' => 'Hybrid', 'ai' => 'AI conversation', 'keypad' => 'Keypad reminder'])->default('hybrid')->required(),
            Select::make('contact_list_id')->relationship('contactList', 'name')->searchable()->helperText('Empty means all contacts in this client.'),
            Select::make('brain_release_id')->relationship('brainRelease', 'version')->label('Published brain version')->required(),
            DateTimePicker::make('scheduled_at')->timezone('Asia/Dhaka'),
            TextInput::make('max_attempts')->numeric()->minValue(1)->maxValue(3)->default(3)->required(),
            TextInput::make('retry_gap_minutes')->numeric()->minValue(240)->default(240)->required(),
            TextInput::make('daily_cap')->numeric()->minValue(1)->maxValue(10000)->default(300)->required(),
            TextInput::make('concurrency')->numeric()->minValue(1)->maxValue(30)->default(5)->required(),
            TextInput::make('budget_tk')->numeric()->minValue(0)->default(0)->prefix('৳')->required(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('goal'),
            TextEntry::make('mode'),
            TextEntry::make('status'),
            TextEntry::make('brainRelease.version'),
            TextEntry::make('scheduled_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('goal'),
            TextColumn::make('mode'),
            TextColumn::make('status')->badge(),
            TextColumn::make('brainRelease.version'),
            TextColumn::make('scheduled_at')->dateTime('d M Y H:i')->timezone('Asia/Dhaka')->sortable(),
        ])->defaultSort('id', 'desc')->recordActions([
            Action::make('dryRun')->label('Dry run')->requiresConfirmation()->modalDescription('Checks eligibility and creates simulation records. No phone call or SMS is sent.')->visible(fn () => TenantAccess::allows('operate'))->action(function ($record): void {
                abort_unless(TenantAccess::allows('operate'), 403);
                $result = app(CampaignPlanner::class)->dryRun($record);
                Notification::make()->title($result['eligible'].' eligible / '.$result['blocked'].' blocked')->body(json_encode($result['reasons'], JSON_UNESCAPED_UNICODE))->persistent()->success()->send();
            }),
            Action::make('pause')->color('warning')->visible(fn () => TenantAccess::allows('operate'))->action(function ($record): void {
                abort_unless(TenantAccess::allows('operate'), 403);
                $record->update(['status' => 'paused']);
            }),
            Action::make('resumeDraft')->label('Return to draft')->visible(fn ($record) => $record->status === 'paused' && TenantAccess::allows('operate'))->action(function ($record): void {
                abort_unless(TenantAccess::allows('operate'), 403);
                $record->update(['status' => 'draft']);
            }),
            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCampaigns::route('/')];
    }
}
