"""Upload via certificate-verified explicit FTPS; publish the manifest last."""
from ftplib import FTP_TLS, error_perm
from pathlib import Path
import os
import ssl

root = Path(__file__).resolve().parents[1]
revision = os.environ['GITHUB_SHA']
ftp = FTP_TLS(context=ssl.create_default_context(), timeout=180)
try:
    ftp.connect(os.environ['FTP_HOST'], int(os.environ.get('FTP_PORT') or '21'))
    # The provider's certificate uses its server name, while FTP_HOST is a hosted domain.
    # Keep the original network endpoint and verify both TLS channels against that name.
    ftp.host = os.environ.get('FTP_TLS_SERVER_NAME') or ftp.host
    ftp.login(os.environ['FTP_USERNAME'], os.environ['FTP_PASSWORD'])
    ftp.prot_p()
    ftp.cwd(os.environ.get('FTP_SERVER_DIR') or '/')
    # Refuse an account accidentally rooted at the hosting home or public_html.
    entries = set(ftp.nlst())
    names = {Path(entry.rstrip('/')).name for entry in entries}
    if names.intersection({'public_html', 'public_ftp', '.cpanel', 'mail', 'etc'}) or ftp.pwd().rstrip('/').endswith('/public_html'):
        raise RuntimeError('FTP account must be restricted to voice.mrctrading.com')
    for directory in ('deploy', 'deploy/incoming'):
        try:
            ftp.mkd(directory)
        except error_perm:
            ftp.cwd(directory)
            ftp.cwd('../..' if directory == 'deploy/incoming' else '..')

    def put(local, remote):
        temporary = remote + '.uploading'
        with local.open('rb') as source:
            ftp.storbinary('STOR ' + temporary, source, blocksize=1024 * 256)
        ftp.rename(temporary, remote)

    put(root / 'deploy/run.php', 'deploy/run.php')
    put(root / 'dist' / f'{revision}.zip', f'deploy/incoming/{revision}.zip')
    put(root / 'dist' / f'{revision}.json', f'deploy/incoming/{revision}.json')
    print('Release uploaded. Waiting for the cPanel cron installer.')
finally:
    ftp.close()
