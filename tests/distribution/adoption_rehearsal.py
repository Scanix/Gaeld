#!/usr/bin/env python3
"""Opt-in real CE v3.8.28 -> RC adoption and recovery, in a disposable Docker daemon.

No registry publication or global daemon trust changes. Only synthetic data is used.
The nested daemon performs genuine TLS registry pulls by digest and the actual CLI.
"""
import argparse
import base64
import functools
import hashlib
import http.cookiejar
import http.server
import importlib.util
import json
import os
from pathlib import Path
import secrets
import re
import shutil
import socket
import ssl
import subprocess
import sys
import tarfile
import tempfile
import threading
import time
import urllib.request
import urllib.parse

HERE = Path(__file__).resolve().parent


def execute(command, *, cwd=None, capture=True, env=None, stdout=None, stdin=None):
    result = subprocess.run([str(part) for part in command], cwd=cwd, env={**os.environ, **(env or {})},
                            stdout=stdout or (subprocess.PIPE if capture else None), stderr=subprocess.PIPE, stdin=stdin)
    if result.returncode:
        raise RuntimeError('Command failed: ' + str(command[0]) + '\n' + result.stderr.decode(errors='replace')[-8000:])
    return result.stdout.decode() if capture and not stdout else None


def sha(path):
    with Path(path).open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


def json_file(path, data):
    Path(path).write_text(json.dumps(data, sort_keys=True, indent=2) + '\n')


def wait_until(check, timeout=90):
    end = time.monotonic() + timeout
    while time.monotonic() < end:
        try:
            result = check()
            if result:
                return result
        except (RuntimeError, OSError):
            pass
        time.sleep(1)
    raise RuntimeError('Timed out waiting for an isolated rehearsal service.')


def login(url, credentials):
    cookies = http.cookiejar.CookieJar()
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies))
    with client.open(url + '/login', timeout=20) as response:
        html = response.read().decode()
    match = re.search(r'<meta name="csrf-token" content="([^"]+)"', html)
    if not match:
        raise AssertionError('The login page has no CSRF token.')
    data = urllib.parse.urlencode({**credentials, '_token': match.group(1)}).encode()
    with client.open(urllib.request.Request(url + '/login', data=data), timeout=20) as response:
        if response.status != 200 or urllib.parse.urlparse(response.url).path in ('/login', '/email/verify'):
            raise AssertionError('The pre-existing verified owner cannot log in after migration.')


def outer(args):
    candidate = Path(args.candidate).resolve()
    if execute(['git', 'status', '--porcelain', '--untracked-files=all'], cwd=candidate).strip():
        raise RuntimeError('The R1 candidate must be a clean committed checkout before this acceptance run.')
    commit = execute(['git', 'rev-parse', 'HEAD'], cwd=candidate).strip()
    workspace = Path(args.workspace).resolve() if args.workspace else Path(tempfile.mkdtemp(prefix='gaeld-adoption-'))
    workspace.mkdir(exist_ok=True, parents=True)
    if any(workspace.iterdir()):
        raise RuntimeError('Choose an empty rehearsal workspace.')
    identifier = 'gaeld-adoption-' + secrets.token_hex(6)
    daemon, registry, network = identifier + '-daemon', identifier + '-registry', identifier + '-network'
    certificate = workspace / 'certificates'
    certificate.mkdir()
    execute(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '2', '-subj', '/CN=Gaeld REHEARSAL ONLY CA',
             '-addext', 'basicConstraints=critical,CA:TRUE', '-keyout', certificate / 'ca.key', '-out', certificate / 'ca.crt'])
    execute(['openssl', 'req', '-newkey', 'rsa:2048', '-nodes', '-subj', '/CN=registry.gaeld.test',
             '-keyout', certificate / 'server.key', '-out', certificate / 'server.csr'])
    (certificate / 'extensions').write_text('subjectAltName=DNS:registry.gaeld.test,DNS:localhost,IP:127.0.0.1\nextendedKeyUsage=serverAuth\n')
    execute(['openssl', 'x509', '-req', '-in', certificate / 'server.csr', '-CA', certificate / 'ca.crt', '-CAkey', certificate / 'ca.key',
             '-CAcreateserial', '-days', '2', '-extfile', certificate / 'extensions', '-out', certificate / 'server.crt'])
    execute(['git', 'clone', '--quiet', '--no-local', '--single-branch', '--branch', 'v3.8.28', candidate, workspace / 'legacy'])
    if args.source_artifact:
        shutil.copy2(Path(args.source_artifact).resolve(), workspace / 'provided-source.tar.gz')
    images = {'app': args.app_image, 'web': args.web_image, 'postgres': args.postgres_image, 'redis': args.redis_image}
    image_ids = {role: execute(['docker', 'image', 'inspect', image, '--format', '{{.Id}}']).strip() for role, image in images.items()}
    baseline_id = execute(['docker', 'image', 'inspect', args.baseline_image, '--format', '{{.Id}}']).strip()
    json_file(workspace / 'input.json', {'candidate_commit': commit, 'version': args.version, 'images': images, 'image_ids': image_ids,
              'baseline_image': baseline_id, 'platform': execute(['docker', 'image', 'inspect', args.app_image, '--format', '{{.Os}}/{{.Architecture}}']).strip()})
    try:
        execute(['docker', 'network', 'create', '--label', 'gaeld.rehearsal=' + identifier, network])
        execute(['docker', 'run', '-d', '--name', registry, '--label', 'gaeld.rehearsal=' + identifier, '--network', network,
                 '--network-alias', 'registry.gaeld.test', '-v', str(certificate) + ':/certificates:ro',
                 '-e', 'REGISTRY_HTTP_TLS_CERTIFICATE=/certificates/server.crt', '-e', 'REGISTRY_HTTP_TLS_KEY=/certificates/server.key', 'registry:3'])
        execute(['docker', 'run', '-d', '--privileged', '--name', daemon, '--label', 'gaeld.rehearsal=' + identifier,
                 '--network', network, '-v', str(workspace) + ':/work', '-v', str(candidate) + ':/candidate:ro',
                 '-v', str(HERE) + ':/harness:ro', '--mount', 'type=bind,source=' + str(certificate) + ',target=/etc/docker/certs.d/registry.gaeld.test:5000,readonly',
                 '--entrypoint', 'dockerd', 'docker:29-dind', '--host=unix:///var/run/docker.sock'])
        wait_until(lambda: execute(['docker', 'exec', daemon, 'docker', 'info']))
        execute(['docker', 'exec', daemon, 'apk', 'add', '--no-cache', 'python3', 'age', 'openssl', 'git', 'bash', 'curl'])
        for image in dict.fromkeys([baseline_id, *image_ids.values()]):
            archive = workspace / 'image-import.tar'
            execute(['docker', 'save', '-o', archive, image])
            execute(['docker', 'exec', daemon, 'docker', 'load', '-i', '/work/image-import.tar'])
            archive.unlink()
        execute(['docker', 'exec', daemon, 'python3', '/harness/adoption_rehearsal.py', '--inside'], capture=False)
        report = json.loads((workspace / 'report.json').read_text())
        report['isolated_daemon'] = True
        report['global_docker_trust_modified'] = False
        json_file(args.report, report)
        print(json.dumps({'status': report['status'], 'report': str(Path(args.report).resolve()), 'workspace': str(workspace)}))
    finally:
        if not args.keep:
            for name in (daemon, registry):
                subprocess.run(['docker', 'rm', '-fv', name], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            subprocess.run(['docker', 'network', 'rm', network], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        else:
            print('Retained task-only resources for diagnosis: ' + daemon + ', ' + registry + ', ' + network)


def inside():
    workspace = Path('/work')
    inputs = json.loads((workspace / 'input.json').read_text())
    sys.path.insert(0, '/candidate/scripts/distribution')
    import manage
    import adopt
    certs = workspace / 'certificates'
    os.environ['SSL_CERT_FILE'] = str(certs / 'ca.crt')
    os.environ['GIT_CONFIG_COUNT'] = '1'
    os.environ['GIT_CONFIG_KEY_0'] = 'safe.directory'
    os.environ['GIT_CONFIG_VALUE_0'] = '/work/legacy'
    legacy = workspace / 'legacy'
    execute(['docker', 'tag', inputs['baseline_image'], 'gaeld-sail/app:latest'])
    old = adopt.LegacySail(legacy, 'gaeld-rehearsal-baseline')
    environment = {'APP_NAME': 'GaeldRehearsal', 'APP_ENV': 'local', 'APP_DEBUG': 'false', 'APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
                   'APP_URL': 'http://localhost:18080', 'APP_PORT': '18080', 'DB_CONNECTION': 'pgsql', 'DB_HOST': 'pgsql', 'DB_PORT': '5432',
                   'DB_DATABASE': 'legacy', 'DB_USERNAME': 'legacy', 'DB_PASSWORD': secrets.token_hex(24), 'REDIS_HOST': 'redis',
                   'REDIS_PREFIX': 'rehearsal_redis_', 'HORIZON_PREFIX': 'rehearsal_horizon:', 'CACHE_STORE': 'redis', 'QUEUE_CONNECTION': 'redis',
                   'SESSION_DRIVER': 'redis', 'SESSION_SECURE_COOKIE': 'false', 'FILESYSTEM_DISK': 'local', 'MAIL_MAILER': 'log',
                   'PLUGINS_ENABLED': 'false', 'SCOUT_DRIVER': 'null', 'APP_MAINTENANCE_DRIVER': 'file',
                   'FORWARD_DB_PORT': '15432', 'FORWARD_REDIS_PORT': '16379', 'FORWARD_MAILPIT_PORT': '11025',
                   'FORWARD_MAILPIT_DASHBOARD_PORT': '18025', 'FORWARD_MEILISEARCH_PORT': '17700'}
    (legacy / '.env').write_text(''.join(f'{key}={value}\n' for key, value in environment.items()))
    (legacy / 'secrets').mkdir()
    (legacy / 'secrets/rehearsal-signing-secret.txt').write_text(secrets.token_hex(32))
    # The baseline ignores runtime dependencies and data. Its tracked files stay byte-for-byte unchanged.
    with (legacy / '.git/info/exclude').open('a') as excluded:
        excluded.write('\nsecrets/\n')
    execute(['docker', 'run', '--rm', '-v', str(legacy) + ':/var/www/html', '-e', 'COMPOSER_ALLOW_SUPERUSER=1',
             '--entrypoint', 'composer', inputs['baseline_image'], 'install', '--prefer-dist', '--no-interaction'], capture=False)
    old.compose('run', '--rm', '--no-deps', '-T', '--entrypoint', 'sh', 'laravel.test', '-c', 'pnpm install --frozen-lockfile && SENTRY_AUTH_TOKEN= pnpm run build')
    old.compose('up', '-d', '--no-build', '--no-deps', '--wait', 'pgsql', 'redis')
    # Only the application and worker are needed; untouched compose still defines the optional legacy services.
    old.compose('run', '--rm', '--no-deps', '-T', '--entrypoint', 'php', 'laravel.test', 'artisan', 'migrate', '--force')
    fixture = '/harness/adoption_fixture.php'
    credentials = {'email': 'rehearsal@example.invalid', 'password': secrets.token_urlsafe(32) + 'A7'}
    json_file(workspace / 'rehearsal-owner.json', credentials)
    (workspace / 'rehearsal-owner.json').chmod(0o400)
    def legacy_fixture(operation):
        return json.loads(old.compose('run', '--rm', '--no-deps', '-T', '--entrypoint', 'php', '-v', fixture + ':/tmp/fixture.php:ro',
                          '-v', '/work/rehearsal-owner.json:/tmp/rehearsal-owner.json:ro', 'laravel.test', '/tmp/fixture.php', operation, capture=True))
    before = legacy_fixture('seed')
    old.compose('up', '-d', '--no-build', '--no-deps', '--wait', 'laravel.test', 'worker')
    login('http://localhost:18080', credentials)
    before = legacy_fixture('snapshot')
    origin = old.preflight()
    old_volumes = [json.loads(line) for line in execute(['docker', 'volume', 'ls', '--filter', 'label=com.docker.compose.project=gaeld-rehearsal-baseline', '--format', 'json']).splitlines()]
    identity = workspace / 'recovery.identity'
    execute(['age-keygen', '-o', identity])
    recipient = execute(['age-keygen', '-y', identity]).strip()
    release_root = workspace / 'https'
    release_dir = release_root / ('v' + inputs['version'])
    release_dir.mkdir(parents=True)
    private, public = workspace / 'TEST-ONLY-release.key', workspace / 'TEST-ONLY-release.pub'
    execute(['openssl', 'genpkey', '-algorithm', 'ED25519', '-out', private])
    execute(['openssl', 'pkey', '-in', private, '-pubout', '-out', public])
    images = {}
    for role, image in inputs['image_ids'].items():
        repository = 'registry.gaeld.test:5000/gaeld-rehearsal/' + role
        execute(['docker', 'tag', image, repository + ':candidate'])
        execute(['docker', 'push', repository + ':candidate'])
        reference = json.loads(execute(['docker', 'image', 'inspect', repository + ':candidate']))[0]['RepoDigests']
        images[role] = next(item for item in reference if item.startswith(repository + '@sha256:'))
        execute(['docker', 'pull', images[role]])
    source = release_dir / 'gaeld-ce.tar.gz'
    if (workspace / 'provided-source.tar.gz').is_file():
        shutil.copy2(workspace / 'provided-source.tar.gz', source)
    else:
        with source.open('wb') as output:
            execute(['docker', 'run', '--rm', '--entrypoint', 'tar', images['app'], '--hard-dereference', '-czhf', '-', '-C', '/var/www/html', '.'], stdout=output)
    json_file(release_dir / 'provenance.json', {'schema': 1, 'channel': 'rehearsal', 'commit': inputs['candidate_commit'],
              'platform': inputs['platform'], 'images': images, 'source_sha256': sha(source)})
    # Inventory actual packaged dependencies, not a placeholder SBOM.
    with tarfile.open(source) as archive:
        composer = json.load(archive.extractfile('./composer.lock'))
    json_file(release_dir / 'sbom.json', {'spdxVersion': 'SPDX-2.3', 'name': 'Gaeld R1 isolated rehearsal',
              'packages': [{'name': item['name'], 'versionInfo': item['version'], 'licenseConcluded': ' OR '.join(item.get('license', [])) or 'NOASSERTION'} for item in composer['packages']]})
    class QuietHandler(http.server.SimpleHTTPRequestHandler):
        def log_message(self, *_):
            pass
    server = http.server.ThreadingHTTPServer(('127.0.0.1', 18443), functools.partial(QuietHandler, directory=str(release_root)))
    context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    context.load_cert_chain(certs / 'server.crt', certs / 'server.key')
    server.socket = context.wrap_socket(server.socket, server_side=True)
    threading.Thread(target=server.serve_forever, daemon=True).start()
    origin_url = 'https://localhost:18443'
    policy = {'schema': 1, 'channel': 'rehearsal', 'release_origin': origin_url, 'release_key_sha256': sha(public),
              'images': {role: image.split('@')[0] for role, image in images.items()}}
    json_file(workspace / 'operator-policy.json', policy)
    release = {'schema': 1, 'version': inputs['version'], 'channel': 'rehearsal', 'commit': inputs['candidate_commit'], 'edition': 'ce',
               'boundary_contract': '1.0.0', 'minimum_version': '3.8.28', 'allowed_from': ['3.8.28'], 'migration_policy': 'forward-only',
               'compatible_ee_versions': [], 'postgres_major': 16, 'redis_major': 7, 'images': images}
    for name, file in [('source', source), ('provenance', release_dir / 'provenance.json'), ('sbom', release_dir / 'sbom.json')]:
        release[name] = {'url': origin_url + '/v' + inputs['version'] + '/' + file.name, 'sha256': sha(file)}
    json_file(release_dir / 'release.json', release)
    execute(['openssl', 'pkeyutl', '-sign', '-inkey', private, '-rawin', '-in', release_dir / 'release.json', '-out', release_dir / 'release.json.sig'])
    target = workspace / 'managed'
    command = ['/candidate/gaeld', 'adopt', '--source', legacy, '--root', target, '--to', inputs['version'], '--legacy-project', old.project,
               '--release-url', origin_url, '--trust-key', public, '--distribution-policy', workspace / 'operator-policy.json',
               '--backup-recipient', recipient, '--identity-file', identity, '--port', '18081']
    execute(command, capture=False)
    installation = manage.Installation(target)
    def managed_fixture(operation):
        return json.loads(installation.compose('run', '--rm', '--no-deps', '-T', '-v', fixture + ':/tmp/fixture.php:ro', 'app', 'php', '/tmp/fixture.php', operation, capture=True))
    after = managed_fixture('snapshot')
    if after != before:
        json_file(workspace / 'comparison.json', {'before': before, 'after': after})
        raise AssertionError('Adoption changed the synthetic database, documents, encryption or queued job; inspect comparison.json.')
    login('http://localhost:18081', credentials)
    if sha(legacy / '.env') != origin['environment_sha256'] or manage.env_values(target / 'shared/.env')['APP_KEY'] != environment['APP_KEY']:
        raise AssertionError('Adoption changed the legacy environment or APP_KEY.')
    if sha(legacy / 'secrets/rehearsal-signing-secret.txt') != sha(target / 'shared/secrets/rehearsal-signing-secret.txt'):
        raise AssertionError('Signing secret was not preserved.')
    if execute(['git', 'status', '--porcelain', '--untracked-files=all'], cwd=legacy).strip():
        raise AssertionError('Legacy tracked source changed.')
    for volume in old_volumes:
        execute(['docker', 'volume', 'inspect', volume['Name']])
    if old.compose('ps', '-q', 'laravel.test', 'worker', capture=True).strip():
        raise AssertionError('Legacy writers were restarted after adoption.')
    execute(['/candidate/gaeld', 'backup', '--root', target, '--identity-file', identity], capture=False)
    installation = manage.Installation(target)
    backup = installation.state['backup']['path']
    mutated = managed_fixture('mutate')
    if mutated == before:
        raise AssertionError('Recovery fixture failed to mutate data.')
    execute(['/candidate/gaeld', 'restore', '--root', target, '--archive', backup, '--identity-file', identity, '--confirm-replace-data'], capture=False)
    if managed_fixture('snapshot') != before:
        raise AssertionError('Real restore did not recover database, supporting files, encryption and queue.')
    login('http://localhost:18081', credentials)
    wrong_identity = workspace / 'wrong-recovery.identity'
    execute(['age-keygen', '-o', wrong_identity])
    refused = subprocess.run(['/candidate/gaeld', 'backup', '--root', str(target), '--identity-file', str(wrong_identity)],
                             stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    if refused.returncode == 0 or manage.Installation(target).state.get('status') != 'failed':
        raise AssertionError('An unverifiable backup did not fail closed.')
    execute(['/candidate/gaeld', 'resume', '--root', target], capture=False)
    if managed_fixture('snapshot') != before:
        raise AssertionError('Resuming after a refused backup changed accounting data.')
    execute(['/candidate/gaeld', 'doctor', '--root', target], capture=False)
    json_file(workspace / 'report.json', {'schema': 1, 'status': 'passed', 'candidate_commit': inputs['candidate_commit'], 'source_tree_clean': True, 'candidate_version': inputs['version'],
              'platform': inputs['platform'], 'baseline_commit': origin['source_commit'], 'channel': 'rehearsal', 'production_publication': False,
              'release_sha256': sha(release_dir / 'release.json'), 'source_sha256': sha(source), 'images': images, 'candidate_images': inputs['images'],
              'signing_key_sha256': sha(public), 'policy_sha256': sha(target / '.gaeld/distribution-policy.json'),
              'checks': {name: True for name in ('baseline_3_8_28', 'signed_https_manifest', 'digest_pinned_pull', 'coherent_encrypted_backup',
                         'adoption', 'app_key_preserved', 'documents_preserved', 'queued_jobs_preserved', 'legacy_stopped', 'managed_health', 'restore', 'existing_owner_http_login', 'failed_backup_resume')}})
    server.shutdown()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--inside', action='store_true', help=argparse.SUPPRESS)
    parser.add_argument('--candidate')
    parser.add_argument('--app-image')
    parser.add_argument('--web-image')
    parser.add_argument('--baseline-image', default='gaeld-sail/app:latest')
    parser.add_argument('--postgres-image', default='postgres:16-alpine')
    parser.add_argument('--redis-image', default='redis:7-alpine')
    parser.add_argument('--version', default='3.9.0-rc.1')
    parser.add_argument('--report')
    parser.add_argument('--source-artifact', help='Serve and verify these exact prebuilt source artifact bytes')
    parser.add_argument('--workspace')
    parser.add_argument('--keep', action='store_true', help='Retain only task-owned daemon/registry for investigation')
    args = parser.parse_args()
    if args.inside:
        inside()
    elif not all((args.candidate, args.app_image, args.web_image, args.report)):
        parser.error('--candidate, --app-image, --web-image and --report are required')
    else:
        outer(args)


if __name__ == '__main__':
    main()
