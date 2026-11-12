<?php

namespace App\Support;

class TenantAccess
{
    public static function allows(string $ability): bool
    {
        $user = auth()->user();
        $tenant = app(TenantContext::class)->tenant();
        if (! $user || ! $tenant || ! $user->canAccessTenant($tenant)) {
            return false;
        }
        if ($user->is_admin) {
            return true;
        }
        $role = $user->tenants()->whereKey($tenant->id)->first()?->pivot->role;

        return match ($ability) {
            'view' => $role !== null,
            'brain' => in_array($role, ['tenant_admin', 'content_reviewer']),
            'operate' => in_array($role, ['tenant_admin', 'campaign_operator']),
            'review' => in_array($role, ['tenant_admin', 'content_reviewer']),
            'manage', 'publish' => $role === 'tenant_admin',
            default => false,
        };
    }
}
