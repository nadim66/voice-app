<?php

namespace Tests\Feature;

use App\Filament\Pages\BrainStudio;
use App\Filament\Pages\Team;
use App\Filament\Pages\TestLab;
use App\Models\BrainRelease;
use App\Models\DndEntry;
use App\Models\FollowUpTask;
use App\Models\ReviewItem;
use App\Models\User;
use App\Models\VoiceCall;
use App\Services\PlatformSetup;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_render_every_workspace_page(): void
    {
        $tenant = app(PlatformSetup::class)->seed();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach (['', 'brain-studio', 'test-lab', 'contact-import', 'results', 'team', 'profile',
            'contacts', 'contact-lists', 'campaigns', 'knowledge-pages', 'reply-cards', 'intent-rules', 'brain-test-cases', 'brain-releases',
            'voice-calls', 'follow-up-tasks', 'messages', 'outcomes', 'review-items', 'phone-numbers', 'integrations', 'consent-events', 'dnd-entries', 'audit-events', 'usage-records', 'test-runs', 'call-jobs'] as $path) {
            $this->get('/admin/'.$tenant->slug.($path ? '/'.$path : ''))->assertOk();
        }
    }

    public function test_test_lab_saves_explainable_conversations_without_real_side_effects(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12));
        $tenant = app(PlatformSetup::class)->seed();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($tenant);
        app(TenantContext::class)->run($tenant, function (): void {
            $test = Livewire::test(TestLab::class)->set('message', 'টাকা লাগবে?')->call('sendMessage')->assertHasNoErrors()->assertSet('lastReply.reply_code', 'R_PRICE_2026');
            $test->set('message', 'আর কল দিবেন না')->call('sendMessage')->assertHasNoErrors()->assertSet('lastReply.action', 'mark_dnd');
            $this->assertSame(1, VoiceCall::where('is_simulation', true)->count());
            $this->assertSame(0, DndEntry::count());
            $test->call('newConversation')->set('message', 'একটি অজানা নতুন প্রশ্ন')->call('sendMessage')->assertSet('lastReply.reply_code', 'R_HUMAN');
            $this->assertSame(1, ReviewItem::count());
            $this->assertSame(1, FollowUpTask::where('is_simulation', true)->count());
        });
    }

    public function test_content_reviewer_cannot_publish_or_manage_team_access(): void
    {
        $tenant = app(PlatformSetup::class)->seed();
        $user = User::factory()->create();
        $tenant->members()->attach($user, ['role' => 'content_reviewer']);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($tenant);
        app(TenantContext::class)->run($tenant, function (): void {
            Livewire::test(BrainStudio::class)->call('publish')->assertForbidden();
            $this->assertSame(0, BrainRelease::count());
            Livewire::test(Team::class)->assertForbidden();
        });
    }

    public function test_adding_an_existing_team_member_cannot_reset_their_password_or_grant_super_admin(): void
    {
        $tenant = app(PlatformSetup::class)->seed();
        $owner = User::factory()->create(['is_admin' => true]);
        $existing = User::factory()->create();
        $originalPassword = $existing->password;
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($tenant);
        app(TenantContext::class)->run($tenant, function () use ($existing, $originalPassword): void {
            Livewire::test(Team::class)->set('name', 'Existing user')->set('email', $existing->email)->set('password', 'attempted-reset-password')->set('role', 'viewer')->call('saveMember')->assertHasNoErrors();
            $this->assertSame($originalPassword, $existing->fresh()->password);
            $this->assertFalse((bool) $existing->fresh()->is_admin);
            Livewire::test(Team::class)->set('name', 'New user')->set('email', 'new@example.test')->set('password', 'long-enough-password')->set('role', 'super_admin')->call('saveMember')->assertHasErrors(['role']);
        });
    }
}
