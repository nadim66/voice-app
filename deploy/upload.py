"""Upload by verified FTPS, then activate by authenticated, single-use HTTPS POST."""
from ftplib import FTP_TLS, error_perm
from pathlib import Path
import hashlib
import io
import json
import os
import secrets
import ssl
import time
import urllib.error
import urllib.parse
import urllib.request


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        raise RuntimeError('Activation redirects are not allowed')


def connect():
    ftp = FTP_TLS(context=ssl.create_default_context(), timeout=180)
    ftp.connect(os.environ['FTP_HOST'], int(os.environ.get('FTP_PORT') or '21'))
    # Validate the provider certificate while keeping the original network endpoint.
    ftp.host = os.environ.get('FTP_TLS_SERVER_NAME') or ftp.host
    ftp.login(os.environ['FTP_USERNAME'], os.environ['FTP_PASSWORD'])
    ftp.prot_p()
    ftp.cwd(os.environ.get('FTP_SERVER_DIR') or '/')
    return ftp


def main():
    root = Path(__file__).resolve().parents[1]
    revision = os.environ['GITHUB_SHA']
    if len(revision) != 40 or any(c not in '0123456789abcdef' for c in revision):
        raise RuntimeError('Invalid release revision')
    url = (os.environ.get('APP_URL') or 'https://voice.mrctrading.com').rstrip('/')
    parsed = urllib.parse.urlsplit(url)
    if parsed.scheme != 'https' or not parsed.hostname or parsed.username or parsed.query or parsed.fragment:
        raise RuntimeError('APP_URL must be an HTTPS URL without credentials, query, or fragment')

    token = secrets.token_urlsafe(48)
    if os.environ.get('GITHUB_ACTIONS') == 'true':
        print('::add-mask::' + token, flush=True)
    entry_name = 'voice-deploy-' + secrets.token_hex(16) + '.php'
    entry_remote = 'public/' + entry_name
    auth_remote = 'deploy/incoming/' + revision + '.auth'
    ftp = connect()
    published = False
    try:
        entries = {Path(entry.rstrip('/')).name for entry in ftp.nlst()}
        if entries.intersection({'public_html', 'public_ftp', '.cpanel', 'mail', 'etc'}) or ftp.pwd().rstrip('/').endswith('/public_html'):
            raise RuntimeError('FTP account must be restricted to voice.mrctrading.com')
        for directory in ('deploy', 'deploy/incoming', 'public'):
            try:
                ftp.mkd(directory)
            except error_perm:
                current = ftp.pwd()
                ftp.cwd(directory)
                ftp.cwd(current)

        def put_bytes(contents, remote):
            temporary = remote + '.uploading'
            ftp.storbinary('STOR ' + temporary, io.BytesIO(contents), blocksize=1024 * 256)
            ftp.rename(temporary, remote)

        def put_file(local, remote):
            temporary = remote + '.uploading'
            with local.open('rb') as source:
                ftp.storbinary('STOR ' + temporary, source, blocksize=1024 * 256)
            ftp.rename(temporary, remote)

        # Disable the previous cron installer before publishing any activation material.
        put_file(root / 'deploy/run.php', 'deploy/run.php')
        put_file(root / 'app/Support/DeploymentInstaller.php', 'deploy/Installer.php')
        put_file(root / 'dist' / f'{revision}.zip', f'deploy/incoming/{revision}.zip')
        put_file(root / 'dist' / f'{revision}.json', f'deploy/incoming/{revision}.json')
        put_bytes(json.dumps({'hash': hashlib.sha256(token.encode()).hexdigest(), 'expires': int(time.time()) + 600}).encode(), auth_remote)
        bootstrap = (
            '<?php\n'
            "require dirname(__DIR__).'/deploy/Installer.php';\n"
            f"(new \\App\\Support\\DeploymentInstaller(dirname(__DIR__), '{revision}'))->serve();\n"
        )
        put_bytes(bootstrap.encode(), entry_remote)
        published = True
        print('FTPS upload complete. Activating through one-time HTTPS request...', flush=True)
        request = urllib.request.Request(
            url + '/' + entry_name,
            data=json.dumps({'token': token}).encode(),
            headers={'Content-Type': 'application/json', 'Cache-Control': 'no-store'},
            method='POST',
        )
        opener = urllib.request.build_opener(NoRedirect())
        try:
            with opener.open(request, timeout=210) as response:
                result = json.load(response)
        except urllib.error.HTTPError as error:
            try:
                detail = json.loads(error.read(4096))
            except (ValueError, UnicodeError):
                detail = {'error': 'Hosting returned a non-JSON response; check PHP errors and deploy/activation.log in cPanel'}
            # Server responses contain fixed stage diagnostics, never command output or credentials.
            raise RuntimeError(f'Activation HTTP {error.code}: {detail}') from None
        if result.get('status') != 'ok' or result.get('revision') != revision:
            raise RuntimeError('Activation response did not confirm the expected release')
        print('Release activated: ' + revision, flush=True)
    finally:
        ftp.close()
        if published:
            # Always remove the temporary public endpoint, including after activation failure.
            try:
                cleanup = connect()
                try:
                    for remote in (entry_remote, auth_remote):
                        try:
                            cleanup.delete(remote)
                        except error_perm as error:
                            if not str(error).startswith('550'):
                                raise
                finally:
                    cleanup.close()
                print('Temporary activation endpoint removed.', flush=True)
            except (OSError, error_perm):
                print('Endpoint cleanup could not connect. Its single-use token is consumed or expires within 10 minutes.', flush=True)


if __name__ == '__main__':
    main()
