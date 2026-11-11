<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallAdmin extends Command
{
    protected $signature = 'app:install-admin';

    protected $description = 'Create the first administrator and save its credentials privately';

    public function handle(): int
    {
        if (User::where('is_admin', true)->exists()) {
            $this->info('An administrator already exists. No password changed.');

            return self::SUCCESS;
        }

        $email = 'admin@voice.mrctrading.com';
        if (User::where('email', $email)->exists()) {
            $this->error('The initial administrator email is already in use. No user was modified.');

            return self::FAILURE;
        }

        $password = Str::password(28, symbols: false);
        $path = storage_path('app/private/initial-admin.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }

        // Save before insertion so a failed write cannot lock the owner out.
        $oldMask = umask(0077);
        try {
            if (file_put_contents($path, json_encode([
                'url' => rtrim(config('app.url'), '/').'/admin',
                'email' => $email,
                'password' => $password,
                'instructions' => 'Change your password in My profile after login, then delete this file.',
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
                throw new \RuntimeException('Could not save initial administrator credentials.');
            }
            chmod($path, 0600);

            $user = new User(['name' => 'Administrator', 'email' => $email, 'password' => $password]);
            $user->is_admin = true;
            $user->save();
        } finally {
            umask($oldMask);
        }

        $this->info('Administrator created. Read storage/app/private/initial-admin.json in File Manager.');

        return self::SUCCESS;
    }
}
