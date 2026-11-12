<?php

namespace App\Providers;

use App\Models\TenantRecord;
use App\Policies\TenantRecordPolicy;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (glob(app_path('Models/*.php')) as $path) {
            $class = 'App\\Models\\'.basename($path, '.php');
            if (is_subclass_of($class, TenantRecord::class)) {
                Gate::policy($class, TenantRecordPolicy::class);
            }
        }
    }
}
