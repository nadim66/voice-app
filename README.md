# MRC Voice

Laravel 13 + Filament 5 admin panel for PHP 8.4 and cPanel hosting without SSH. Includes client workspaces, team roles, a reviewed knowledge/answer bank, Brain Studio, a Bangla/Banglish text Test Lab, consent-aware contacts, campaign simulation, transcripts, review inbox, outcome reporting, and automatic FTPS deployment. Real phone calls and streaming voice are not connected.

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

## Using the platform

The deployment creates an **AI Tutor** client and imports `resources/brain/aitutor.json` as **draft** content. It never overwrites an existing brain, publishes content automatically, creates real customer contacts, or sends calls/messages.

1. Sign in with the existing administrator account and choose the AI Tutor workspace. MRC super admins may add clients; other users only see clients where they have membership.
2. In **Knowledge pages** and **Reply cards**, check each source, date, claim and action before setting its status to approved. The PDF is starter material, not proof that client content is approved. App availability and callback/support details need confirmation.
3. Use **Client settings** to resolve the open questions and confirm calling hours/limits. The pilot defaults are 10:00–20:00 Asia/Dhaka, at most 3 attempts with at least 240 minutes between attempts.
4. Use **Brain Studio → Run draft tests**. The starter pack has 100 intent, 20 conversation and 35 safety cases. These are regression tests, not a claim of real-call accuracy. Publishing re-runs tests using only approved, currently effective material and requires the configured minimum counts and pass rates (90% intent/conversation, 100% safety). Facts/answers are checked again for expiry on every reply.
5. A published version is an immutable database snapshot plus private JSON/Markdown exports under `storage/app/private/tenants/<id>/brain/`. The pilot limits the compiled fact/reply payload to 30,000 UTF-8 bytes; this is a conservative size limit, not an exact token count. Source templates are versioned in this repository; runtime releases use database versions rather than Git tags on hosting.
6. Use **Test Lab** in draft or published mode. It shows reply code, intent, action preview, evidence and timing. Browser audio preview works only if the browser supplies a Bangla voice. Unknown/unsafe questions use a human fallback. Common mobile-number/OTP patterns and password-labelled messages are redacted before persistence/provider use; avoid entering personal data. Simulator actions remain previews; a simulated opt-out does not alter a real contact's DND state.
7. Import contacts using **Contact import**. CSV headers are required; local and Bangla-digit Bangladesh numbers normalize to `8801…`. Granted consent requires a timestamp and records source/text version. A newer withdrawal is not overwritten by an older CSV grant. Control-group contacts, DND numbers, missing consent, calling-hour/retry/limit violations are blocked during campaign simulation. Each dry run is limited to 1,000 contacts and sends no calls or SMS.
8. Use **Team** for client admins, content reviewers, campaign operators and viewers. Existing account passwords are never reset by adding a membership. Root super-admin status cannot be granted through a client form. Views, actions, relationships and queries are checked against the active workspace. Consent, opt-out, audit and published release records cannot be edited through the app.
9. **Results** separates simulated sessions from real calls and shows recorded registration outcomes by call/control group. It does not claim verified 48-hour attribution or causal lift. API token usage is recorded when an optional provider is used; unpriced usage is labelled rather than presenting an invented bill.

### Optional AI selection

Offline matching requires no API key. To let the Test Lab select a card for unmatched questions, set `ANTHROPIC_API_KEY` and a supported `ANTHROPIC_MODEL` in the server root `.env`, then opt into AI in the Test Lab. Model access and provider charges depend on the account. Keys are not stored in client forms or the database. The adapter sends only the selected client's curated bundle and redacted test message, uses an ephemeral cache, and accepts a reply code rather than model-generated claims. Every selection passes expiry/evidence/action/wording guards. Provider failure falls back to a human reply.

### Hosting limits and remaining integrations

This implementation follows the existing Laravel/Filament/MySQL hosting environment. MySQL isolation uses application scopes, tenant membership checks and relationship validation, tested across clients; PostgreSQL RLS from the PDF is not installed. Existing FTPS Secrets and the cron-free activation mechanism are retained.

A VPS/voice bridge, Asterisk/IPTSP SIP trunk, streaming STT/TTS, recordings, barge-in, a real dialer/queue, SMS/WhatsApp delivery, read-only AI Tutor CRM sync and verified 48-hour attribution remain external integration work. Integration/phone-number screens currently store configuration references and show unconfigured status. Live calling is disabled in code. No VPS, DNS or existing `public_html` configuration is changed by this app.

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
