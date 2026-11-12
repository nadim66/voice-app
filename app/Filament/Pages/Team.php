<?php

namespace App\Filament\Pages;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\TenantAccess;
use App\Support\TenantContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Team extends Page
{
    protected string $view = 'filament.pages.team';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'viewer';

    public static function getNavigationGroup(): ?string
    {
        return 'Client';
    }

    public static function canAccess(): bool
    {
        return TenantAccess::allows('manage');
    }

    public function saveMember(): void
    {
        abort_unless(TenantAccess::allows('manage'), 403);
        $this->email = mb_strtolower(trim($this->email));
        $this->validate(['name' => 'required|string|max:180', 'email' => 'required|email|max:180', 'role' => 'required|in:tenant_admin,content_reviewer,campaign_operator,viewer', 'password' => 'nullable|string|min:12|max:128']);
        DB::transaction(function (): void {
            $user = User::where('email', $this->email)->first();
            if (! $user) {
                if ($this->password === '') {
                    throw ValidationException::withMessages(['password' => 'Set an initial password of at least 12 characters for a new account.']);
                }
                $user = User::create(['name' => $this->name, 'email' => $this->email, 'password' => $this->password]);
            }
            $tenant = app(TenantContext::class)->requireTenant();
            if ($user->id === auth()->id() && ! auth()->user()->is_admin && $this->role !== 'tenant_admin') {
                throw ValidationException::withMessages(['role' => 'Keep your own administrator access.']);
            }
            $tenant->members()->syncWithoutDetaching([$user->id => ['role' => $this->role]]);
            AuditEvent::create(['user_id' => auth()->id(), 'action' => 'team.member_saved', 'target' => (string) $user->id, 'metadata' => ['role' => $this->role]]);
        });
        $this->reset('name', 'email', 'password', 'role');
        Notification::make()->title('Team access saved')->body('Existing account passwords were not changed.')->success()->send();
    }

    public function removeMember(int $id): void
    {
        abort_unless(TenantAccess::allows('manage') && $id !== auth()->id(), 403);
        $tenant = app(TenantContext::class)->requireTenant();
        $tenant->members()->whereKey($id)->firstOrFail();
        $tenant->members()->detach($id);
        AuditEvent::create(['user_id' => auth()->id(), 'action' => 'team.member_removed', 'target' => (string) $id]);
    }

    protected function getViewData(): array
    {
        return ['members' => app(TenantContext::class)->requireTenant()->members()->orderBy('name')->get()];
    }
}
