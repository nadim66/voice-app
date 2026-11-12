# MRC Voice

Laravel 13 + Filament 5 admin panel for PHP 8.4 and cPanel hosting without SSH. Includes admin login, dashboard, profile/password changes, and automatic FTPS deployment. Voice generation features are not implemented yet.

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

Validation: `php artisan test` and `vendor/bin/pint --dirty --format agent`. The HTTP deployment integration test is `python3 tests/deployment_http.py -v`; it starts an isolated local PHP server and disables process, shell, and symlink functions in PHP.

## Production setup

- Application/FTP root: `/home/mrctradi/voice.mrctrading.com`
- Domain document root: `/home/mrctradi/voice.mrctrading.com/public`
- The existing site in `/home/mrctradi/public_html` is outside this deployment.
- Create `.env` in the application root with `DB_CONNECTION=mysql`, `DB_HOST=localhost`, `DB_PORT=3306`, `DB_DATABASE=mrctradi_voice`, `DB_USERNAME=mrctradi_mrctradi`, and the real quoted `DB_PASSWORD`. Never commit credentials.
- Grant the database user privileges on that database.
- Web PHP must be 8.4 with Laravel/Filament extensions, including `intl`, `mbstring`, `pdo_mysql`, `zip`, `bcmath`, `dom`, `fileinfo`, `curl`. PHP needs file write permissions for the app folder and an adequate request time/memory limit to unpack the archive and run migrations.
- No server terminal, Composer, Node, cron, shell commands, `proc_open`, or symlinks are required.
- Remove the old deployment cron from cPanel if previously added. The uploaded `deploy/run.php` is deliberately inert, so an old job cannot run the retired installer.

Existing GitHub repository **Settings → Secrets and variables → Actions** names remain unchanged:

| Type | Name | Value |
| --- | --- | --- |
| Secret | `FTP_HOST` | `ftp.mrctrading.com` |
| Secret | `FTP_USERNAME` | FTP account restricted to the application root |
| Secret | `FTP_PASSWORD` | FTP account password |
| Variable | `FTP_PORT` | `21` |
| Variable | `FTP_TLS_SERVER_NAME` | `bdix4.ebnhost.com` (default) |
| Variable | `FTP_SERVER_DIR` | `/` |
| Variable | `APP_URL` | `https://voice.mrctrading.com` |

Explicit FTPS verifies the provider's certificate for `bdix4.ebnhost.com` while connecting to the original `ftp.mrctrading.com` address. Certificate verification is never disabled. `APP_URL` must use HTTPS and must not redirect activation requests.

## How deployment works

Push to `main`, or run **Actions → Test and deploy → Run workflow**. Pull requests run PHP tests only.

1. CI tests on PHP 8.4. A clean deployment job installs locked production dependencies and publishes Filament CSS/JS/fonts.
2. CI packages an allowlisted ZIP excluding `.env`, SQLite databases, runtime storage, tooling, and development dependencies. It tests real HTTP activation with disabled PHP process/shell/link functions before upload.
3. FTPS uploads the installer, ZIP, checksum manifest, and the hash of a fresh random activation token. No new GitHub Secret is needed.
4. A temporary PHP endpoint with a random filename is uploaded into `public/`. CI sends its token only in a verified HTTPS POST body. Redirects are rejected. The token expires after 10 minutes and is consumed before installation. Wrong/expired/replayed tokens return 404; concurrent installs are locked.
5. The installer checks the archive and extracts into `releases/<commit>`. It preserves root `.env` values, fills missing production settings, and generates `APP_KEY` only if missing. Shared runtime storage stays in root `storage/`.
6. `SHARED_ROOT` tells each release's bootstrap to use the root `.env`, runtime storage, and real `public/`. Migrations and initial admin creation run inside the PHP request through Laravel's console kernel. No subprocess is started.
7. During activation, the front controller returns temporary maintenance responses. Built public assets are copied atomically, preserving `.well-known`, `.user.ini`, `php.ini`, `public/storage`, and `public/uploads`. Existing cPanel `.htaccess` directives are retained. The active release is switched using an atomically replaced JSON pointer, without symlinks.
8. CI removes the temporary endpoint even after errors. A failed cleanup leaves an endpoint whose authorization is already consumed or expires within 10 minutes. The installer code and authorization hash are outside the web root.
9. CI verifies `/health` against the exact commit and working database, and checks `/admin/login`. Upload alone is not considered success.

Initial credentials are in **File Manager → `voice.mrctrading.com/storage/app/private/initial-admin.json`**. Change the password after logging in, then delete that file. Credentials are not printed in Actions logs. Existing administrator passwords are preserved on redeployment.

## Troubleshooting and recovery

- Inspect the Actions **Upload over FTPS and activate over HTTPS** step for fixed stage diagnostics. Private details are in `deploy/activation.log`; Laravel errors are in `storage/logs/laravel.log`. Never post passwords or entire `.env` files.
- If activation fails, fix the extension/database/configuration issue, then rerun the workflow. Each attempt receives fresh, expiring authorization.
- If HTTP times out after activation, check `/health` and the final verification step. A shared hosting request limit can interrupt long migrations; migrations must fit the provider's web request limits.
- Failures before activation retain the previous code pointer and restore its assets where possible. Database migrations are **not automatically rolled back**. Use backward-compatible migrations and hosting database backups before schema changes.
- For code rollback, revert the Git commit and push `main` to deploy a new release. Existing extracted releases remain available for recovery. `deploy/active.json` records the active and previous commit.
- Completed ZIPs are deleted automatically. Prune failed incoming archives, old inactive releases, and logs periodically, preserving current and previous working releases.
- The initial app uses synchronous jobs; there is no long-running queue worker or scheduler requirement.
