<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_see_login_but_must_authenticate_for_dashboard(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('MRC Voice');
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_only_administrators_can_access_the_panel(): void
    {
        $tenant = Tenant::factory()->create();
        $panel = Filament::getPanel('admin');
        $user = User::factory()->create();
        $this->assertFalse($user->canAccessPanel($panel));
        $this->actingAs($user)->get('/admin')->assertForbidden();

        $user->is_admin = true;
        $user->save();
        $this->assertTrue($user->canAccessPanel($panel));
        $this->actingAs($user)->get('/admin/'.$tenant->slug)->assertOk();
        $this->actingAs($user)->get('/admin/profile')->assertOk();
    }

    public function test_health_checks_the_database_and_returns_the_release(): void
    {
        $this->get('/health')->assertOk()->assertJson(['status' => 'ok', 'revision' => 'local']);
    }

    public function test_first_admin_credentials_are_private_and_not_reset_on_redeployment(): void
    {
        $originalStorage = $this->app->storagePath();
        $temporaryStorage = sys_get_temp_dir().'/voice-admin-test-'.bin2hex(random_bytes(8));
        $this->app->useStoragePath($temporaryStorage);
        $path = $temporaryStorage.'/app/private/initial-admin.json';

        try {
            $this->artisan('app:install-admin')->assertSuccessful();
            $credentials = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $user = User::where('email', $credentials['email'])->sole();
            $this->assertTrue($user->is_admin);
            $this->assertTrue(Hash::check($credentials['password'], $user->password));
            $this->assertSame(0600, fileperms($path) & 0777);
            $originalHash = $user->password;

            $this->artisan('app:install-admin')->assertSuccessful();
            $this->assertSame($originalHash, $user->fresh()->password);
            $this->assertSame(1, User::where('is_admin', true)->count());
        } finally {
            $this->app->useStoragePath($originalStorage);
            if (is_file($path)) {
                unlink($path);
            }
            foreach (['/app/private', '/app', ''] as $directory) {
                if (is_dir($temporaryStorage.$directory)) {
                    rmdir($temporaryStorage.$directory);
                }
            }
        }
    }
}
