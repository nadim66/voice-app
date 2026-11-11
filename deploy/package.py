"""Create an allowlisted release archive without credentials or runtime data."""
import hashlib
import json
import os
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

root = Path(__file__).resolve().parents[1]
revision = os.environ['GITHUB_SHA']
if len(revision) != 40 or any(c not in '0123456789abcdef' for c in revision):
    raise SystemExit('Invalid GITHUB_SHA')
(root / 'REVISION').write_text(revision + '\n')
output = root / 'dist'
output.mkdir(exist_ok=True)
archive = output / f'{revision}.zip'
with ZipFile(archive, 'w', ZIP_DEFLATED) as bundle:
    for name in ('app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'vendor'):
        for path in sorted((root / name).rglob('*')):
            relative = path.relative_to(root).as_posix()
            if path.is_symlink() or not path.is_file():
                continue
            if relative.startswith(('bootstrap/cache/', 'public/storage/')):
                continue
            if path.name.endswith(('.sqlite', '.sqlite-shm', '.sqlite-wal')) or path.name == 'hot':
                continue
            bundle.write(path, relative)
    for name in ('artisan', 'composer.json', 'composer.lock', '.env.example', 'REVISION'):
        bundle.write(root / name, name)
digest = hashlib.sha256()
with archive.open('rb') as source:
    for block in iter(lambda: source.read(1024 * 1024), b''):
        digest.update(block)
checksum = digest.hexdigest()
(output / f'{revision}.json').write_text(json.dumps({'revision': revision, 'sha256': checksum}))
print(f'Packaged {revision}: {archive.stat().st_size:,} bytes')
