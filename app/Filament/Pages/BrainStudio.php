<?php

namespace App\Filament\Pages;

use App\Models\BrainTestCase;
use App\Models\IntentRule;
use App\Models\KnowledgePage;
use App\Models\ReplyCard;
use App\Services\BrainPublisher;
use App\Support\TenantAccess;
use App\Support\TenantContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class BrainStudio extends Page
{
    protected string $view = 'filament.pages.brain-studio';

    protected static ?int $navigationSort = 1;

    #[Locked]
    public array $results = [];

    public static function getNavigationGroup(): ?string
    {
        return 'Brain';
    }

    public static function canAccess(): bool
    {
        return TenantAccess::allows('brain');
    }

    public function runTests(): void
    {
        abort_unless(TenantAccess::allows('brain'), 403);
        $this->results = app(BrainPublisher::class)->test(app(BrainPublisher::class)->snapshot(true));
        Notification::make()->title('Draft tests completed')->body('These results do not approve or publish content.')->success()->send();
    }

    public function publish(): void
    {
        abort_unless(TenantAccess::allows('publish'), 403);
        try {
            $release = app(BrainPublisher::class)->publish();
            Notification::make()->title('Brain v'.$release->version.' published')->success()->send();
        } catch (ValidationException $error) {
            $this->addError('brain', collect($error->errors())->flatten()->implode(' '));
        }
    }

    protected function getViewData(): array
    {
        return ['tenant' => app(TenantContext::class)->requireTenant(),
            'counts' => ['Facts' => KnowledgePage::count(), 'Approved facts' => KnowledgePage::where('status', 'approved')->count(), 'Replies' => ReplyCard::count(), 'Approved replies' => ReplyCard::where('status', 'approved')->count(), 'Intents' => IntentRule::count(), 'Test cases' => BrainTestCase::count()],
            'release' => app(BrainPublisher::class)->currentRelease(),
            'canPublish' => TenantAccess::allows('publish')];
    }
}
