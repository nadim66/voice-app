<?php

namespace App\Filament\Resources\Contacts;

use App\Filament\Resources\Contacts\Pages\ManageContacts;
use App\Filament\Resources\WorkspaceResource;
use App\Models\Contact;
use App\Models\DndEntry;
use App\Services\ContactImporter;
use App\Support\TenantAccess;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactResource extends WorkspaceResource
{
    protected static ?string $model = Contact::class;

    protected static string $workspaceAbility = 'operate';

    protected static bool $recordsReadOnly = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Contacts';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('phone_e164')->regex('/\A8801[3-9][0-9]{8}\z/')->helperText('Use 8801… format. CSV import also accepts local numbers.')->label('Mobile number')->required()->maxLength(30)->dehydrateStateUsing(fn ($state) => app(ContactImporter::class)->phone($state))->scopedUnique(ignoreRecord: true),
            TextInput::make('name')->label('Parent/contact name')->maxLength(180),
            Select::make('student_class')->options(array_combine(range(1, 5), range(1, 5))),
            Select::make('version')->options(['Bangla' => 'Bangla version', 'English' => 'English version']),
            TextInput::make('source')->maxLength(180),
            TextInput::make('external_ref')->maxLength(180),
            Select::make('experiment_group')->options(['call' => 'Call group', 'control' => 'Control group (never dial)'])->default('call')->required(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('phone_e164'),
            TextEntry::make('name'),
            TextEntry::make('student_class'),
            TextEntry::make('source'),
            TextEntry::make('experiment_group'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('phone_e164')->searchable(),
            TextColumn::make('name')->searchable(),
            TextColumn::make('student_class'),
            TextColumn::make('source'),
            TextColumn::make('experiment_group')->badge(),
        ])->defaultSort('id', 'desc')->recordActions([
            Action::make('consent')->label('Record consent')->schema([Select::make('purpose')->options(['service' => 'Service', 'marketing' => 'Marketing'])->default('service')->required(), Toggle::make('granted')->default(false), TextInput::make('source')->required()->maxLength(180), TextInput::make('text_version')->required()->maxLength(120)])->visible(fn () => TenantAccess::allows('operate'))->action(function ($record, array $data): void {
                abort_unless(TenantAccess::allows('operate'), 403);
                app(ContactImporter::class)->consent($record, (bool) $data['granted'], $data['purpose'], $data['source'], $data['text_version']);
                Notification::make()->title('Consent event recorded')->success()->send();
            }),
            Action::make('dnd')->label('Do not call')->color('danger')->requiresConfirmation()->visible(fn () => TenantAccess::allows('operate'))->action(function ($record): void {
                abort_unless(TenantAccess::allows('operate'), 403);
                DndEntry::firstOrCreate(['phone_e164' => $record->phone_e164], ['reason' => 'Manual opt-out', 'source' => 'operator']);
                Notification::make()->title('Number blocked for this client')->success()->send();
            }),
            ViewAction::make(), EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageContacts::route('/')];
    }
}
