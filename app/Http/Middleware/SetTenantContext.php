<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();
        abort_unless($tenant instanceof Tenant && $request->user()?->canAccessTenant($tenant), 403);

        return app(TenantContext::class)->run($tenant, fn () => $next($request));
    }
}
