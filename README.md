# MRC Voice

Laravel 13 + Filament 5 admin panel, built for PHP 8.4 and cPanel hosting without SSH. Initial scope: admin login, dashboard, profile/password changes, and automatic deployment. Voice generation features are not implemented yet.

## Local development

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan app:install-admin
php artisan filament:assets
php artisan serve
```

Open `/admin`. Read initial credentials from `storage/app/private/initial-admin.json`, change the password in **My profile**, then delete that file. There is no public registration. Only users with `is_admin = true` may enter the panel.

Validation: `php artisan test` and `vendor/bin/pint --test`.

## Production setup

- Application/FTP root: `/home/mrctradi/voice.mrctrading.com`
- Domain document root: `/home/mrctradi/voice.mrctrading.com/public`
- Existing website in `/home/mrctradi/public_html` is outside this deployment.
- Create `.env` in the application root with `DB_CONNECTION=mysql`, `DB_HOST=localhost`, `DB_PORT=3306`, `DB_DATABASE=mrctradi_voice`, `DB_USERNAME=mrctradi_mrctradi`, and the real quoted `DB_PASSWORD`. Never commit credentials.
- Grant the database user privileges on that database.
- Hosting must provide PHP 8.4 with Laravel/Filament extensions, including `intl`, `mbstring`, `pdo_mysql`, `zip`, `bcmath`, `dom`, `fileinfo`, `curl`, and support for `proc_open` and symlinks. Apache must permit links owned by the same hosting user. Enable missing extensions through cPanel or ask the provider.
- The first deployment expects `public/` to be empty except cPanel files (`.well-known`, `cgi-bin`, `.htaccess`, `.user.ini`, `php.ini`). It preserves this directory as `public.before-first-deploy` before linking the active release.

GitHub repository **Settings → Secrets and variables → Actions**:

| Type | Name | Value |
| --- | --- | --- |
| Secret | `FTP_HOST` | `ftp.mrctrading.com` |
| Secret | `FTP_USERNAME` | New FTP account restricted to the application root |
| Secret | `FTP_PASSWORD` | FTP account password |
| Variable | `FTP_PORT` | `21` |
| Variable | `FTP_SERVER_DIR` | `/` |
| Variable | `APP_URL` | `https://voice.mrctrading.com` |

Use certificate-verified explicit FTPS. If certificate verification fails, ask the host for the FTP hostname matching its valid TLS certificate; do not disable verification.

Create a cPanel Cron Job every minute (`* * * * *`):

```sh
/opt/cpanel/ea-php84/root/usr/bin/php /home/mrctradi/voice.mrctrading.com/deploy/run.php >> /home/mrctradi/voice.mrctrading.com/deploy-cron.log 2>&1
```

This PHP binary is the usual cPanel EasyApache PHP 8.4 location. If cron reports “No such file”, obtain the correct PHP 8.4 CLI path from the provider (CloudLinux installations can differ).

## How deployment works

Push to `main`, or run **Actions → Test and deploy → Run workflow**. Pull requests run tests only.

1. CI runs PHP tests and formatting checks on PHP 8.4.
2. A clean job installs locked production dependencies and publishes Filament CSS/JS/fonts; no Composer or Node is needed on hosting.
3. CI creates a ZIP excluding `.env`, SQLite databases, runtime storage, local tooling, and development dependencies.
4. FTPS uploads the CLI installer and immutable archive. A SHA-256 manifest is renamed into place last, so cron never consumes a partial upload.
5. Cron verifies and extracts into `releases/<commit>`. `.env` and `storage/` are shared at the application root. It fills missing production settings and generates `APP_KEY` once, retaining existing DB values and keys.
6. Cron temporarily enables maintenance mode on subsequent deploys, applies migrations, creates the first admin if needed, builds release-specific caches, and atomically switches `current`. `public` points to `current/public`. This requires same-owner symlink support; there is brief maintenance downtime.
7. CI waits for `/health` to report the exact commit and a working DB, then checks `/admin/login`. Upload alone does not count as deployment success.

The app runs inside a release directory while `.env`, uploads, sessions, logs, and credentials persist at the application root. Read first-login credentials using **File Manager → `voice.mrctrading.com/storage/app/private/initial-admin.json`**. Change the password, then delete this private file. Do not post it in chat or commit it.

## Troubleshooting and recovery

- Check Actions first. For cron/install errors, inspect `deploy-cron.log` in the application root. Application errors are in `storage/logs/laravel.log`. Do not share credentials from logs.
- A pending manifest is retried by cron after configuration/extension issues are fixed. To cancel a broken pending release, rename its `deploy/incoming/<commit>.json` to a name without the `.json` suffix.
- Pre-activation failures retain current code. Code-switch failures restore the previous symlink where possible. Database migrations are **not automatically rolled back**; use backward-compatible migrations and take hosting DB backups before schema changes.
- For code rollback, revert the Git commit and push to `main` to deploy a new release. Extracted releases are retained for manual recovery; remove old inactive release folders in File Manager when disk space is low, retaining current and previous working versions.
- No long-running queue worker is configured. Jobs run synchronously for this initial app.
- Completed ZIPs are removed automatically. Rotate the cron log and prune inactive releases periodically.
