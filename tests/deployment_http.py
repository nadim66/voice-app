"""Exercise real HTTP activation with shell/process/link functions disabled in PHP."""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import secrets
import shutil
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.request
from zipfile import ZipFile, ZIP_DEFLATED

ROOT = Path(__file__).resolve().parents[1]


class DeploymentHttpTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temporary = tempfile.TemporaryDirectory(prefix='voice-http-deploy-')
        cls.server_root = Path(cls.temporary.name)
        for directory in ('public', 'deploy/incoming'):
            (cls.server_root / directory).mkdir(parents=True)
        (cls.server_root / 'database.sqlite').touch()
        cls.base_revision = '1' * 40
        environment = dict(os.environ, GITHUB_SHA=cls.base_revision)
        subprocess.run(['python3', str(ROOT / 'deploy/package.py')], check=True, env=environment, stdout=subprocess.DEVNULL)
        cls.base_archive = ROOT / 'dist' / f'{cls.base_revision}.zip'
        shutil.copy(ROOT / 'app/Support/DeploymentInstaller.php', cls.server_root / 'deploy/Installer.php')
        with socket.socket() as available:
            available.bind(('127.0.0.1', 0))
            cls.port = available.getsockname()[1]
        cls.url = f'http://127.0.0.1:{cls.port}'
        (cls.server_root / '.env').write_text(
            f'DB_CONNECTION=sqlite\nDB_DATABASE={cls.server_root}/database.sqlite\n'
            f'DB_PASSWORD="preserve-this-fixture-password"\nAPP_URL={cls.url}\nSESSION_SECURE_COOKIE=false\n'
        )
        (cls.server_root / 'public/.well-known').mkdir()
        (cls.server_root / 'public/.well-known/preserved.txt').write_text('certificate verification')
        (cls.server_root / 'public/.user.ini').write_text('; preserve hosting settings\n')
        cls.php_log = (cls.server_root / 'php-test.log').open('w')
        cls.server = subprocess.Popen([
            'php', '-d', 'disable_functions=proc_open,popen,exec,shell_exec,system,passthru,symlink,link',
            '-S', f'127.0.0.1:{cls.port}', '-t', str(cls.server_root / 'public'),
        ], stdout=cls.php_log, stderr=cls.php_log)
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1', cls.port), timeout=.1):
                    break
            except OSError:
                time.sleep(.05)
        else:
            raise RuntimeError('PHP test server failed to start')

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=10)
        cls.php_log.close()
        (ROOT / 'REVISION').unlink(missing_ok=True)
        cls.temporary.cleanup()

    def prepare(self, digit, *, unsafe=False, failing=False, expired=False):
        revision = str(digit) * 40
        incoming = self.server_root / 'deploy/incoming'
        archive = incoming / f'{revision}.zip'
        with ZipFile(self.base_archive) as source, ZipFile(archive, 'w', ZIP_DEFLATED) as target:
            for name in source.namelist():
                target.writestr(name, revision + '\n' if name == 'REVISION' else source.read(name))
            if unsafe:
                target.writestr('../escaped.txt', 'must not extract')
            if failing:
                target.writestr('database/migrations/2026_11_12_000000_failure.php',
                    '<?php return new class extends \\Illuminate\\Database\\Migrations\\Migration {'
                    'public function up(): void { throw new \\RuntimeException("Intentional test failure"); }};')
        (incoming / f'{revision}.json').write_text(json.dumps({
            'revision': revision, 'sha256': hashlib.sha256(archive.read_bytes()).hexdigest(),
        }))
        token = secrets.token_urlsafe(48)
        (incoming / f'{revision}.auth').write_text(json.dumps({
            'hash': hashlib.sha256(token.encode()).hexdigest(),
            'expires': int(time.time()) + (-10 if expired else 600),
        }))
        endpoint = f'voice-deploy-test-{digit}.php'
        (self.server_root / 'public' / endpoint).write_text(
            "<?php require dirname(__DIR__).'/deploy/Installer.php';\n"
            f"(new \\App\\Support\\DeploymentInstaller(dirname(__DIR__), '{revision}'))->serve();\n"
        )
        return endpoint, token, revision

    def request(self, endpoint, token=None, *, method='POST'):
        payload = json.dumps({'token': token}).encode() if method == 'POST' else None
        request = urllib.request.Request(self.url + '/' + endpoint, data=payload, method=method,
                                         headers={'Content-Type': 'application/json'})
        try:
            response = urllib.request.urlopen(request, timeout=120)
        except urllib.error.HTTPError as error:
            response = error
        body = response.read().decode()
        try:
            return response.code, json.loads(body)
        except ValueError:
            self.fail(f'Non-JSON response {response.code}: {body[:200]}; inspect {self.server_root}/php-test.log')

    def test_http_deployment_and_security(self):
        endpoint, token, revision = self.prepare(1)
        self.assertEqual(self.request(endpoint, token, method='GET')[0], 404)
        self.assertEqual(self.request(endpoint, 'incorrect')[0], 404)
        self.assertFalse((self.server_root / 'releases').exists())
        lock = (self.server_root / 'deploy/deploy.lock').open('w')
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            self.assertEqual(self.request(endpoint, token)[0], 409)
        finally:
            lock.close()
        status, body = self.request(endpoint, token)
        self.assertEqual((status, body), (200, {'status': 'ok', 'revision': revision}),
                         (self.server_root / 'deploy/activation.log').read_text())
        self.assertEqual(self.request(endpoint, token)[0], 404)
        self.assertFalse((self.server_root / 'public').is_symlink())
        with urllib.request.urlopen(self.url + '/health') as response:
            self.assertEqual(json.load(response), {'status': 'ok', 'revision': revision})
        with urllib.request.urlopen(self.url + '/admin/login') as response:
            self.assertIn('MRC Voice', response.read().decode())
        credentials = (self.server_root / 'storage/app/private/initial-admin.json').read_bytes()
        environment = (self.server_root / '.env').read_bytes()
        self.assertEqual((self.server_root / '.env').stat().st_mode & 0o777, 0o600)
        (self.server_root / 'storage/app/public/upload.txt').write_text('keep upload')
        (self.server_root / 'public/uploads').mkdir()
        (self.server_root / 'public/uploads/user.txt').write_text('keep public upload')

        endpoint, token, revision = self.prepare(2)
        self.assertEqual(self.request(endpoint, token)[0], 200)
        self.assertEqual((self.server_root / 'storage/app/private/initial-admin.json').read_bytes(), credentials)
        self.assertEqual((self.server_root / '.env').read_bytes(), environment)
        self.assertEqual((self.server_root / 'storage/app/public/upload.txt').read_text(), 'keep upload')
        self.assertEqual((self.server_root / 'public/uploads/user.txt').read_text(), 'keep public upload')
        self.assertEqual((self.server_root / 'public/.well-known/preserved.txt').read_text(), 'certificate verification')
        self.assertEqual((self.server_root / 'public/.user.ini').read_text(), '; preserve hosting settings\n')
        with urllib.request.urlopen(self.url + '/health') as response:
            self.assertEqual(json.load(response)['revision'], revision)

        endpoint, token, _ = self.prepare(3, failing=True)
        self.assertEqual(self.request(endpoint, token)[0], 500)
        self.assertFalse((self.server_root / 'deploy/maintenance').exists())
        with urllib.request.urlopen(self.url + '/health') as response:
            self.assertEqual(json.load(response)['revision'], revision)

        endpoint, token, _ = self.prepare(4, unsafe=True)
        self.assertEqual(self.request(endpoint, token)[0], 500)
        self.assertFalse((self.server_root / 'releases/escaped.txt').exists())
        endpoint, token, _ = self.prepare(5, expired=True)
        self.assertEqual(self.request(endpoint, token)[0], 404)


if __name__ == '__main__':
    unittest.main()
