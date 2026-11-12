<?php

namespace App\Support;

use Illuminate\Contracts\Console\Kernel;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;
use ZipArchive;

/** Installs only a verified release; never launches a subprocess or follows a symlink. */
class DeploymentInstaller
{
    private string $stage = 'authorization';

    public function __construct(private string $root, private string $revision) {}

    public function serve(): never
    {
        ini_set('display_errors', '0');
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
            || ! preg_match('/\A[a-f0-9]{40}\z/', $this->revision)
            || (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
            $this->respond(404, ['error' => 'Not found']);
        }

        $authorizationPath = $this->root.'/deploy/incoming/'.$this->revision.'.auth';
        $authorization = is_file($authorizationPath) ? json_decode(file_get_contents($authorizationPath), true) : null;
        $request = json_decode(file_get_contents('php://input', false, null, 0, 4097), true);
        $token = $request['token'] ?? null;
        if (! is_array($authorization) || ! is_string($token) || strlen($token) > 256
            || ($authorization['expires'] ?? 0) < time()
            || ! is_string($authorization['hash'] ?? null)
            || ! hash_equals($authorization['hash'], hash('sha256', $token))) {
            $this->respond(404, ['error' => 'Not found']);
        }

        $lock = fopen($this->root.'/deploy/deploy.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->respond(409, ['error' => 'A deployment is already running']);
        }
        // Recheck after taking the lock: a concurrent request may have consumed the token.
        if (! is_file($authorizationPath)) {
            $this->respond(404, ['error' => 'Not found']);
        }
        if (! unlink($authorizationPath)) {
            $this->respond(500, ['error' => 'Cannot consume deployment authorization']);
        }

        ob_start();
        $bufferLevel = ob_get_level();
        $maintenance = $this->root.'/deploy/maintenance';
        $previous = null;
        $switched = false;
        $publishedAssets = false;
        $shutdownComplete = false;
        register_shutdown_function(function () use ($maintenance, &$shutdownComplete): void {
            if (! $shutdownComplete && is_file($maintenance)) {
                // The active pointer is switched only after migrations and asset publishing.
                @unlink($maintenance);
            }
        });

        try {
            @set_time_limit(180);
            umask(0022);
            $this->stage = 'preflight';
            if (PHP_VERSION_ID < 80400) {
                throw new RuntimeException('Web PHP must be 8.4 or newer');
            }
            foreach (['zip', 'intl', 'mbstring', 'bcmath', 'fileinfo', 'dom'] as $extension) {
                if (! extension_loaded($extension)) {
                    throw new RuntimeException('Missing PHP extension: '.$extension);
                }
            }
            if (is_link($this->root.'/public')) {
                throw new RuntimeException('The document root must be a real directory');
            }
            $activePath = $this->root.'/deploy/active.json';
            if (is_file($activePath)) {
                $previous = json_decode(file_get_contents($activePath), true, flags: JSON_THROW_ON_ERROR);
                if (! preg_match('/\A[a-f0-9]{40}\z/', $previous['revision'] ?? '')) {
                    throw new RuntimeException('Invalid active release state');
                }
            }

            $this->stage = 'archive';
            $manifestPath = $this->root.'/deploy/incoming/'.$this->revision.'.json';
            $archive = $this->root.'/deploy/incoming/'.$this->revision.'.zip';
            $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
            if (($manifest['revision'] ?? '') !== $this->revision || ! is_file($archive)
                || ! hash_equals($manifest['sha256'] ?? '', hash_file('sha256', $archive))) {
                throw new RuntimeException('Archive checksum or revision does not match');
            }
            if (($previous['revision'] ?? null) === $this->revision) {
                $this->respond(200, ['status' => 'ok', 'revision' => $this->revision]);
            }
            $release = $this->root.'/releases/'.$this->revision;
            $this->directory($release);
            $zip = new ZipArchive;
            if ($zip->open($archive) !== true) {
                throw new RuntimeException('Cannot open release archive');
            }
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                $zip->getExternalAttributesIndex($index, $system, $attributes);
                if ($name === false || str_starts_with($name, '/') || str_contains($name, '\\')
                    || in_array('..', explode('/', $name), true)
                    || ! preg_match('#\A(app/|bootstrap/|config/|database/|public/|resources/|routes/|vendor/|artisan\z|composer\.(json|lock)\z|\.env\.example\z|REVISION\z)#', $name)
                    || (($attributes >> 16) & 0170000) === 0120000) {
                    throw new RuntimeException('Unsafe archive entry');
                }
            }
            if (! $zip->extractTo($release)) {
                throw new RuntimeException('Cannot extract release');
            }
            $zip->close();
            if (trim(file_get_contents($release.'/REVISION')) !== $this->revision) {
                throw new RuntimeException('Extracted revision does not match');
            }

            $this->stage = 'environment';
            $this->prepareEnvironment();
            foreach (['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $path) {
                $this->directory($this->root.'/storage/'.$path, $path === 'app/private' ? 0700 : 0755);
            }
            $this->directory($release.'/bootstrap/cache');
            $this->atomicWrite($release.'/SHARED_ROOT', $this->root."\n");

            $this->stage = 'bootstrap';
            require $release.'/vendor/autoload.php';
            $app = require $release.'/bootstrap/app.php';
            $kernel = $app->make(Kernel::class);
            $kernel->bootstrap();
            $this->atomicWrite($maintenance, $this->revision);

            $this->stage = 'migrations';
            $this->command($kernel, 'migrate', ['--force' => true]);
            $this->stage = 'administrator';
            $this->command($kernel, 'app:install-admin');
            $this->stage = 'workspace setup';
            $this->command($kernel, 'app:prepare-platform');
            // Keep config uncached: no server secrets are copied into release artifacts.
            // HTTP requests load the shared .env and runtime storage via SHARED_ROOT.
            $this->stage = 'view cache';
            $this->command($kernel, 'view:cache');

            $this->stage = 'public assets';
            $publishedAssets = true;
            $this->publishPublicFiles($release);
            $this->stage = 'activation';
            $this->atomicWrite($activePath, json_encode([
                'revision' => $this->revision,
                'previous' => $previous['revision'] ?? null,
                'activated_at' => gmdate('c'),
            ], JSON_THROW_ON_ERROR));
            $switched = true;
            if (! unlink($maintenance)) {
                throw new RuntimeException('Cannot leave maintenance mode');
            }
            $shutdownComplete = true;
            @rename($manifestPath, $manifestPath.'.done');
            @unlink($archive);
            $this->log('Activated '.$this->revision);
            while (ob_get_level() >= $bufferLevel) {
                ob_end_clean();
            }
            $this->respond(200, ['status' => 'ok', 'revision' => $this->revision]);
        } catch (Throwable $error) {
            try {
                if ($switched && $previous !== null) {
                    $this->atomicWrite($this->root.'/deploy/active.json', json_encode($previous, JSON_THROW_ON_ERROR));
                } elseif ($switched) {
                    @unlink($this->root.'/deploy/active.json');
                }
                if ($publishedAssets && $previous !== null) {
                    $this->publishPublicFiles($this->root.'/releases/'.$previous['revision']);
                }
            } catch (Throwable $recoveryError) {
                $this->log('Recovery failed: '.$recoveryError->getMessage());
            }
            @unlink($maintenance);
            $shutdownComplete = true;
            $this->log('Failed during '.$this->stage.': '.$error->getMessage());
            while (ob_get_level() >= $bufferLevel) {
                ob_end_clean();
            }
            // Only preflight messages are our fixed, non-secret diagnostics.
            $message = $this->stage === 'preflight' ? $error->getMessage() : 'Read deploy/activation.log in cPanel';
            $this->respond(500, ['error' => 'Activation failed during '.$this->stage, 'detail' => $message]);
        }
    }

    private function command(Kernel $kernel, string $command, array $parameters = []): void
    {
        $output = new BufferedOutput;
        $exitCode = $kernel->call($command, $parameters + ['--no-interaction' => true], $output);
        if ($exitCode !== 0) {
            $this->log($output->fetch());
            throw new RuntimeException('Command failed: '.$command);
        }
    }

    private function prepareEnvironment(): void
    {
        $path = $this->root.'/.env';
        if (! is_file($path)) {
            throw new RuntimeException('Create the server .env with database credentials');
        }
        $contents = file_get_contents($path);
        $defaults = [
            'APP_NAME' => '"MRC Voice"', 'APP_ENV' => 'production', 'APP_DEBUG' => 'false',
            'APP_URL' => 'https://voice.mrctrading.com',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'APP_MAINTENANCE_DRIVER' => 'file', 'LOG_CHANNEL' => 'single', 'LOG_LEVEL' => 'warning',
            'SESSION_DRIVER' => 'file', 'SESSION_SECURE_COOKIE' => 'true',
            'CACHE_STORE' => 'file', 'QUEUE_CONNECTION' => 'sync', 'FILESYSTEM_DISK' => 'local', 'MAIL_MAILER' => 'log',
        ];
        foreach ($defaults as $key => $value) {
            if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $match)) {
                $contents .= "\n".$key.'='.$value;
            } elseif (trim($match[1], " \t\r\"'") === '') {
                $contents = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $key.'='.$value, $contents);
            }
        }
        foreach (['APP_ENV' => 'production', 'APP_DEBUG' => 'false'] as $key => $value) {
            $contents = preg_replace('/^'.$key.'=.*$/m', $key.'='.$value, $contents);
        }
        $this->atomicWrite($path, rtrim($contents)."\n", 0600);
    }

    private function publishPublicFiles(string $release): void
    {
        $source = $release.'/public';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $relative = substr($file->getPathname(), strlen($source) + 1);
            if ($file->isLink() || ! $file->isFile() || $relative === 'index.php' || $relative === '.htaccess'
                || preg_match('#\A(\.well-known/|storage/|uploads/|\.user\.ini\z|php\.ini\z)#', $relative)) {
                continue;
            }
            $target = $this->root.'/public/'.$relative;
            $this->directory(dirname($target));
            $this->atomicWrite($target, file_get_contents($file->getPathname()));
        }
        $htaccess = $this->root.'/public/.htaccess';
        $existing = is_file($htaccess) ? file_get_contents($htaccess) : '';
        if (! str_contains($existing, '# BEGIN MRC VOICE') && ! preg_match('/RewriteRule\s+\^\s+index\.php/', $existing)) {
            $this->atomicWrite($htaccess, "# BEGIN MRC VOICE\n".file_get_contents($source.'/.htaccess')."\n# END MRC VOICE\n".$existing);
        }
        $this->atomicWrite($this->root.'/public/index.php', file_get_contents($source.'/index.php'));
    }

    private function atomicWrite(string $path, string $contents, int $mode = 0644): void
    {
        $temporary = tempnam(dirname($path), '.voice-deploy-');
        if ($temporary === false || file_put_contents($temporary, $contents) !== strlen($contents)) {
            throw new RuntimeException('Cannot write deployment file');
        }
        chmod($temporary, $mode);
        if (! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Cannot publish deployment file');
        }
        if (str_ends_with($path, '.php') && function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    private function directory(string $path, int $mode = 0755): void
    {
        if (! is_dir($path) && ! mkdir($path, $mode, true)) {
            throw new RuntimeException('Cannot create deployment directory');
        }
    }

    private function log(string $message): void
    {
        file_put_contents($this->root.'/deploy/activation.log', '['.gmdate('c').'] '.$message."\n", FILE_APPEND | LOCK_EX);
    }

    private function respond(int $status, array $body): never
    {
        http_response_code($status);
        echo json_encode($body, JSON_THROW_ON_ERROR);
        exit;
    }
}
