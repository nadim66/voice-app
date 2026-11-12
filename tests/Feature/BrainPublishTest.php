<?php

namespace Tests\Feature;

use App\Models\KnowledgePage;
use App\Models\ReplyCard;
use App\Services\BrainPublisher;
use App\Services\PlatformSetup;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BrainPublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_content_and_open_questions_block_publication(): void
    {
        $tenant = app(PlatformSetup::class)->seed();
        app(TenantContext::class)->run($tenant, function (): void {
            $this->expectException(ValidationException::class);
            app(BrainPublisher::class)->publish();
        });
    }

    public function test_reviewed_content_publishes_immutable_version_with_test_evidence(): void
    {
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 12));
        $tenant = app(PlatformSetup::class)->seed();
        $tenant->update(['settings' => array_merge($tenant->settings, ['open_questions' => ''])]);
        app(TenantContext::class)->run($tenant, function (): void {
            foreach ([KnowledgePage::class, ReplyCard::class] as $model) {
                foreach ($model::all() as $record) {
                    $record->update(['status' => 'approved']);
                }
            }
            $release = app(BrainPublisher::class)->publish();
            $this->assertSame(1, $release->version);
            $this->assertSame(35, $release->test_results['safety']['passed']);
            $original = $release->snapshot;
            ReplyCard::where('code', 'R_WHO')->firstOrFail()->update(['text' => 'Changed draft text', 'status' => 'draft']);
            $this->assertSame($original, $release->fresh()->snapshot);
            $this->expectException(\LogicException::class);
            $release->update(['snapshot' => []]);
        });
    }
}
