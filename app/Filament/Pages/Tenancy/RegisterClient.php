<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Tenant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;

class RegisterClient extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'New client';
    }

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('slug')->required()->regex('/\A[a-z0-9_\-]+\z/')->maxLength(80)->unique(Tenant::class, 'slug'),
            Select::make('industry')->options(['education' => 'Education', 'real_estate' => 'Real estate', 'diagnostic' => 'Diagnostic', 'ecommerce' => 'E-commerce'])->required()->default('education'),
            TextInput::make('timezone')->default('Asia/Dhaka')->required()->rules(['timezone']),
        ]);
    }

    protected function handleRegistration(array $data): Tenant
    {
        abort_unless(static::canView(), 403);

        return Tenant::create($data + ['status' => 'pilot', 'settings' => ['calling_start' => '10:00', 'calling_end' => '20:00', 'daily_cap' => 300, 'max_concurrent_calls' => 5]]);
    }
}
