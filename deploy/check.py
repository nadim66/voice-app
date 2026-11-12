"""Wait for the exact uploaded revision and check the admin login page."""
import json
import os
import time
import urllib.error
import urllib.request

url = (os.environ.get('APP_URL') or 'https://voice.mrctrading.com').rstrip('/')
if not url.startswith('https://'):
    raise SystemExit('APP_URL must use HTTPS')
revision = os.environ['GITHUB_SHA']
for attempt in range(18):
    try:
        request = urllib.request.Request(url + '/health?revision=' + revision, headers={'Cache-Control': 'no-cache'})
        with urllib.request.urlopen(request, timeout=15) as response:
            health = json.load(response)
        if health.get('status') == 'ok' and health.get('revision') == revision:
            with urllib.request.urlopen(url + '/admin/login', timeout=15) as response:
                if response.status != 200 or 'livewire' not in response.read().decode().lower():
                    raise RuntimeError('Admin login page did not render correctly')
            print('Database health and Filament login verified for revision ' + revision)
            break
    except (OSError, ValueError, RuntimeError):
        pass
    print(f'Verifying activated release ({attempt + 1}/18)...', flush=True)
    time.sleep(10)
else:
    raise SystemExit('Live verification failed. Check deploy/activation.log and storage/logs/laravel.log in cPanel; verify web PHP extensions, HTTPS, and DB permissions.')
