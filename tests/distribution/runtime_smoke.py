#!/usr/bin/env python3
"""Exercise the actual production services in isolated, disposable Docker volumes."""
import argparse
import base64
import contextlib
import http.cookiejar
import importlib.util
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import sys
import tempfile
import urllib.parse
import urllib.request

REPOSITORY = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('manager', REPOSITORY / 'scripts/distribution/manage.py')
manager = importlib.util.module_from_spec(spec)
spec.loader.exec_module(manager)


@contextlib.contextmanager
def cleanup_after(action, *arguments):
    try:
        yield
    except BaseException:
        try:
            action(*arguments)
        except Exception as error:
            print(f'Smoke test cleanup also failed: {error}', file=sys.stderr)
        raise
    else:
        action(*arguments)


def main():
    parser = argparse.ArgumentParser()
    for option in ('app-image', 'web-image', 'postgres-image', 'redis-image', 'platform', 'version'):
        parser.add_argument('--' + option, required=True)
    args = parser.parse_args()
    with contextlib.ExitStack() as cleanup:
        directory = tempfile.mkdtemp(prefix='gaeld-runtime-test-')
        cleanup.enter_context(cleanup_after(shutil.rmtree, directory))
        installation = manager.Installation(directory)
        installation.meta.mkdir()
        installation.shared.mkdir()
        for path in ('app/private', 'app/public', 'framework/cache/data', 'framework/views', 'framework/sessions', 'logs'):
            (installation.shared / 'storage' / path).mkdir(parents=True, exist_ok=True)
        (installation.shared / 'secrets').mkdir()
        with socket.socket() as listener:
            listener.bind(('127.0.0.1', 0))
            port = listener.getsockname()[1]
        installation.state = {'mode': 'docker', 'project': 'gaeld-runtime-' + secrets.token_hex(6), 'version': args.version, 'port': port}
        runtime = installation.release_dir() / 'docker/production'
        shutil.copytree(REPOSITORY / 'docker/production', runtime)
        compose = runtime / 'compose.yml'
        text = compose.read_text().replace('x-app: &app\n', 'x-app: &app\n  platform: ' + args.platform + '\n')
        services, networks = text.split('\nnetworks:\n', 1)
        for service in ('web', 'pgsql', 'redis'):
            services = services.replace('  ' + service + ':\n', '  ' + service + ':\n    platform: ' + args.platform + '\n')
        compose.write_text(services + '\nnetworks:\n' + networks)
        environment = {'APP_NAME': 'Gaeld QA', 'APP_ENV': 'production', 'APP_DEBUG': 'false',
                       'APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
                       'APP_URL': f'http://localhost:{port}', 'DB_CONNECTION': 'pgsql', 'DB_HOST': 'pgsql',
                       'DB_PORT': '5432', 'DB_DATABASE': 'gaeld', 'DB_USERNAME': 'gaeld', 'DB_PASSWORD': secrets.token_hex(24),
                       'REDIS_HOST': 'redis', 'REDIS_PREFIX': 'runtime-test-', 'HORIZON_PREFIX': 'runtime-test-horizon:',
                       'CACHE_STORE': 'redis', 'QUEUE_CONNECTION': 'redis', 'SESSION_DRIVER': 'redis',
                       'SESSION_SECURE_COOKIE': 'false', 'MAIL_MAILER': 'log', 'FILESYSTEM_DISK': 'local',
                       'PLUGINS_ENABLED': 'false', 'SCOUT_DRIVER': 'null', 'APP_MAINTENANCE_DRIVER': 'file'}
        (installation.shared / '.env').write_text(''.join(f'{key}={value}\n' for key, value in environment.items()))
        release = {'version': args.version, 'images': {'app': args.app_image, 'web': args.web_image,
                                                'postgres': args.postgres_image, 'redis': args.redis_image}}
        installation.configure_runtime(release)
        administrator = {'name': 'QA Administrator', 'email': 'runtime-qa@example.invalid',
                         'password': secrets.token_urlsafe(32) + 'A7', 'organization': 'Runtime QA', 'currency': 'CHF', 'locale': 'en'}
        secret = installation.meta / 'admin.json'
        secret.write_text(json.dumps(administrator))
        secret.chmod(0o400)
        # The real installer gives container services ownership of their bind mounts.
        # Return only this test's temporary tree to the invoking user after shutdown.
        cleanup.enter_context(cleanup_after(manager.run, [
            'docker', 'run', '--rm', '--network', 'none', '--read-only', '--platform', args.platform,
            '--user', '0:0', '--mount', f'type=bind,source={directory},target=/smoke',
            '--entrypoint', 'chown', args.app_image, '-Rh', f'{os.getuid()}:{os.getgid()}', '/smoke',
        ]))
        with cleanup_after(installation.compose, 'down', '--volumes', '--remove-orphans'):
            installation.compose('up', '-d', '--wait', '--wait-timeout', '120', 'pgsql', 'redis')
            installation.fix_permissions(args.version)
            installation.artisan('gaeld:install', '--admin-secret-file=/run/gaeld-admin.json', '--no-interaction', secret_file=secret)
            secret.unlink()
            installation.artisan('down')
            installation.start()
            cookie_jar = http.cookiejar.CookieJar()
            client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))
            with client.open(environment['APP_URL'] + '/login', timeout=15) as response:
                html = response.read().decode()
            token = re.search(r'<meta name="csrf-token" content="([^"]+)"', html).group(1)
            fields = urllib.parse.urlencode({'email': administrator['email'], 'password': administrator['password'], '_token': token}).encode()
            with client.open(urllib.request.Request(environment['APP_URL'] + '/login', data=fields), timeout=15) as response:
                landing = urllib.parse.urlparse(response.url).path
                if response.status != 200 or landing in ('/login', '/email/verify'):
                    raise AssertionError('The installation owner cannot access the application: ' + landing)
            installation.stop_writers()
            print(json.dumps({'platform': args.platform, 'install': 'passed', 'login': 'passed', 'drain': 'passed'}))


if __name__ == '__main__':
    try:
        main()
    except manager.OperationError as error:
        if error.diagnostics:
            print(error.diagnostics, file=sys.stderr)
        raise
