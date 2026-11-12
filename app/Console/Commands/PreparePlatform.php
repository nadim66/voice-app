<?php

namespace App\Console\Commands;

use App\Services\PlatformSetup;
use Illuminate\Console\Command;

class PreparePlatform extends Command
{
    protected $signature = 'app:prepare-platform';

    protected $description = 'Create the first client and its unapproved starter brain without overwriting existing data';

    public function handle(PlatformSetup $setup): int
    {
        $setup->seed();
        $this->info('AI Tutor workspace is ready. Drafts require review; no calls or SMS were sent.');

        return self::SUCCESS;
    }
}
