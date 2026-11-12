<?php

namespace App\Filament\Pages;

use App\Models\ContactList;
use App\Services\ContactImporter;
use App\Support\TenantAccess;
use App\Support\TenantContext;
use Filament\Pages\Page;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactImport extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.contact-import';

    public $upload;

    public string $csv = '';

    public ?int $listId = null;

    #[Locked]
    public array $result = [];

    public static function getNavigationGroup(): ?string
    {
        return 'Contacts';
    }

    public static function canAccess(): bool
    {
        return TenantAccess::allows('operate');
    }

    public function import(): void
    {
        abort_unless(TenantAccess::allows('operate'), 403);
        $this->validate(['csv' => 'nullable|string|max:2097152', 'upload' => 'nullable|file|max:2048|mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel',
            'listId' => ['nullable', 'integer', Rule::exists('contact_lists', 'id')->where('tenant_id', app(TenantContext::class)->id())]]);
        if (! $this->upload && trim($this->csv) === '') {
            $this->addError('csv', 'Choose a CSV file or paste its contents.');

            return;
        }
        $contents = $this->upload ? file_get_contents($this->upload->getRealPath()) : $this->csv;
        $this->result = app(ContactImporter::class)->import($contents, $this->listId);
        if ($this->upload) {
            $this->upload->delete();
            $this->upload = null;
        }
        $this->csv = '';
    }

    public function template(): StreamedResponse
    {
        abort_unless(TenantAccess::allows('operate'), 403);

        return response()->streamDownload(fn () => print ("phone,parent_name,student_class,version,source,lead_at,consent_calls,consent_marketing,consent_text_version,experiment_group,dnd\n"), 'contacts-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function getViewData(): array
    {
        return ['lists' => ContactList::orderBy('name')->get()];
    }
}
