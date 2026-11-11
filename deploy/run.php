<?php

// CLI only. cPanel cron runs this independently of Laravel and Composer.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

umask(0022);
$root = dirname(__DIR__);
$lock = fopen(__DIR__.'/deploy.lock', 'c');
if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
    exit;
}

function artisan(string $release, array $arguments): void
{
    $process = proc_open(
        array_merge([PHP_BINARY, $release.'/artisan'], $arguments, ['--no-interaction']),
        [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR],
        $pipes,
        $release,
    );
    if (! is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Artisan command failed: '.$arguments[0]);
    }
}

function directory(string $path, int $mode = 0755): void
{
    if (! is_dir($path) && ! mkdir($path, $mode, true)) {
        throw new RuntimeException('Cannot create directory: '.$path);
    }
}

function atomicLink(string $target, string $path): void
{
    $temporary = $path.'.next';
    if (is_link($temporary)) {
        unlink($temporary);
    }
    if (! symlink($target, $temporary) || ! rename($temporary, $path)) {
        throw new RuntimeException('Cannot switch symlink: '.$path);
    }
}

$pending = glob(__DIR__.'/incoming/*.json') ?: [];
usort($pending, fn ($a, $b) => filemtime($a) <=> filemtime($b));
foreach ($pending as $manifestPath) {
    $previous = is_link($root.'/current') ? readlink($root.'/current') : null;
    $release = null;
    $maintenance = false;
    $switched = false;

    try {
        echo '['.date('c')."] Deployment started\n";
        if (PHP_VERSION_ID < 80400) {
            throw new RuntimeException('Cron must use PHP 8.4 or newer.');
        }
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Enable the PHP zip extension in cPanel.');
        }
        if (! function_exists('proc_open') || ! function_exists('symlink')) {
            throw new RuntimeException('Hosting must allow proc_open and symlink for deployment.');
        }

        $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        $revision = $manifest['revision'] ?? '';
        if (! preg_match('/\A[a-f0-9]{40}\z/', $revision)) {
            throw new RuntimeException('Invalid release revision.');
        }
        $archive = __DIR__.'/incoming/'.$revision.'.zip';
        if (! is_file($archive) || ! hash_equals($manifest['sha256'] ?? '', hash_file('sha256', $archive))) {
            throw new RuntimeException('Release archive checksum does not match.');
        }

        // Never replace an arbitrary populated document root.
        if (! is_link($root.'/public') && is_dir($root.'/public')) {
            $unexpected = array_diff(scandir($root.'/public'), [
                '.', '..', '.well-known', 'cgi-bin', '.htaccess', '.user.ini', 'php.ini',
            ]);
            if ($unexpected) {
                throw new RuntimeException('public/ contains existing files; inspect them before first deployment.');
            }
        }

        $release = $root.'/releases/'.$revision;
        directory($release);
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Cannot open release archive.');
        }
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            $zip->getExternalAttributesIndex($index, $system, $attributes);
            if ($name === false || str_starts_with($name, '/') || str_contains($name, '\\')
                || in_array('..', explode('/', $name), true)
                || ! preg_match('#\A(app/|bootstrap/|config/|database/|public/|resources/|routes/|vendor/|artisan\z|composer\.(json|lock)\z|\.env\.example\z|REVISION\z)#', $name)
                || (($attributes >> 16) & 0170000) === 0120000) {
                throw new RuntimeException('Unsafe archive entry.');
            }
        }
        if (! $zip->extractTo($release)) {
            throw new RuntimeException('Could not extract release.');
        }
        $zip->close();

        $environment = $root.'/.env';
        if (! is_file($environment)) {
            throw new RuntimeException('Create the server .env with database credentials before deploying.');
        }
        $contents = file_get_contents($environment);
        $defaults = [
            'APP_NAME' => '"MRC Voice"',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://voice.mrctrading.com',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_MAINTENANCE_DRIVER' => 'file',
            'LOG_CHANNEL' => 'single',
            'LOG_LEVEL' => 'warning',
            'SESSION_DRIVER' => 'file',
            'SESSION_SECURE_COOKIE' => 'true',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
            'FILESYSTEM_DISK' => 'local',
            'MAIL_MAILER' => 'log',
        ];
        foreach ($defaults as $key => $value) {
            // Fill missing/empty values; retain the existing encryption key and DB secrets.
            if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $match)) {
                $contents .= "\n".$key.'='.$value;
            } elseif (trim($match[1], " \t\r\"'") === '') {
                $contents = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $key.'='.$value, $contents);
            }
        }
        // Production settings are mandatory even if a local .env was copied accidentally.
        foreach (['APP_ENV' => 'production', 'APP_DEBUG' => 'false'] as $key => $value) {
            $contents = preg_replace('/^'.$key.'=.*$/m', $key.'='.$value, $contents);
        }
        $environmentTemporary = tempnam($root, '.env.deploy-');
        if ($environmentTemporary === false || file_put_contents($environmentTemporary, rtrim($contents)."\n", LOCK_EX) === false) {
            throw new RuntimeException('Cannot update server .env.');
        }
        chmod($environmentTemporary, 0600);
        if (! rename($environmentTemporary, $environment)) {
            throw new RuntimeException('Cannot save server .env.');
        }

        foreach (['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $path) {
            directory($root.'/storage/'.$path, str_starts_with($path, 'app/private') ? 0700 : 0755);
        }
        directory($release.'/bootstrap/cache');
        foreach (['.env' => $environment, 'storage' => $root.'/storage'] as $name => $target) {
            if (! is_link($release.'/'.$name) && ! symlink($target, $release.'/'.$name)) {
                throw new RuntimeException('Cannot link shared '.$name);
            }
        }
        if (! is_link($release.'/public/storage') && ! symlink($root.'/storage/app/public', $release.'/public/storage')) {
            throw new RuntimeException('Cannot link public storage.');
        }
        if (is_dir($root.'/public/.well-known') && ! file_exists($release.'/public/.well-known')) {
            // Copy certificate verification files, because the original public/ will be preserved separately.
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/public/.well-known', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            directory($release.'/public/.well-known');
            foreach ($files as $file) {
                $target = $release.'/public/.well-known/'.substr($file->getPathname(), strlen($root.'/public/.well-known/'));
                $file->isDir() ? directory($target) : copy($file->getPathname(), $target);
            }
        }

        artisan($release, ['--version']);
        if ($previous !== null) {
            artisan($root.'/current', ['down', '--retry=30']);
            $maintenance = true;
        }
        artisan($release, ['migrate', '--force']);
        artisan($release, ['app:install-admin']);
        artisan($release, ['config:cache']);
        artisan($release, ['route:cache']);
        artisan($release, ['view:cache']);

        if (! is_link($root.'/public') && is_dir($root.'/public')) {
            if (! rename($root.'/public', $root.'/public.before-first-deploy')) {
                throw new RuntimeException('Cannot preserve the initial document root.');
            }
        }
        atomicLink($release, $root.'/current');
        $switched = true;
        if (! is_link($root.'/public')) {
            atomicLink($root.'/current/public', $root.'/public');
        }
        artisan($release, ['up']);
        $maintenance = false;
        rename($manifestPath, $manifestPath.'.done');
        unlink($archive);
        echo '['.date('c').'] Deployed '.$revision."\n";
    } catch (Throwable $error) {
        if ($switched && $previous !== null) {
            atomicLink($previous, $root.'/current');
        }
        if ($maintenance && $previous !== null) {
            try {
                artisan($root.'/current', ['up']);
            } catch (Throwable) {
                // Keep the original error visible below.
            }
        }
        fwrite(STDERR, '['.date('c').'] Deployment failed: '.$error->getMessage()."\n");
        // Keep the manifest so correcting PHP settings or .env can be retried by cron.
        exit(1);
    }
}
