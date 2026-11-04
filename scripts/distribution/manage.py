#!/usr/bin/env python3
"""Managed CE/EE installations. Host-side, Python 3.10+, no pip packages required."""

import argparse
import base64
import contextlib
import datetime
import fcntl
import getpass
import hashlib
import json
import os
from pathlib import Path
import platform
import re
import secrets
import shutil
import subprocess
import sys
import tarfile
import tempfile
import time
import urllib.parse
import urllib.request


class OperationError(RuntimeError):
    def __init__(self, message, diagnostics=None):
        super().__init__(message)
        self.diagnostics = diagnostics


def run(command, *, cwd=None, env=None, output=None, capture=False, input_file=None):
    with contextlib.ExitStack() as stack:
        source = stack.enter_context(Path(input_file).open('rb')) if input_file else None
        result = subprocess.run(command, cwd=cwd, env={**os.environ, **(env or {})}, stdin=source,
                                stdout=output if output else (subprocess.PIPE if capture else None),
                                stderr=subprocess.PIPE, text=not bool(output or source))
    if result.returncode:
        # Process output can contain database credentials; keep diagnostics local.
        details = result.stderr.decode(errors='replace') if isinstance(result.stderr, bytes) else result.stderr
        raise OperationError(f"{Path(command[0]).name} failed (exit {result.returncode}). Installation remains stopped; inspect the protected diagnostic log.", details)
    return result.stdout if capture else None


def digest(path):
    with Path(path).open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest() if hasattr(hashlib, 'file_digest') else _digest(stream)


def _digest(stream):
    hasher = hashlib.sha256()
    for block in iter(lambda: stream.read(1024 * 1024), b''):
        hasher.update(block)
    return hasher.hexdigest()


def write_json(path, value):
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
    temporary = path.with_suffix(path.suffix + '.new')
    with temporary.open('w') as stream:
        os.chmod(temporary, 0o600)
        json.dump(value, stream, indent=2, sort_keys=True)
        stream.flush()
        os.fsync(stream.fileno())
    temporary.replace(path)
    directory = os.open(path.parent, os.O_RDONLY)
    try:
        os.fsync(directory)
    finally:
        os.close(directory)


def read_json(path):
    with Path(path).open() as stream:
        return json.load(stream)


def version(value):
    if not re.fullmatch(r'\d+\.\d+\.\d+(?:-rc\.\d+)?', value):
        raise OperationError('Use an exact release version, for example 3.9.0; floating tags are not supported.')
    numbers = value.split('-')[0].split('.')
    prerelease = value.split('-rc.', 1)
    return (*map(int, numbers), 0 if len(prerelease) == 2 else 1, int(prerelease[1]) if len(prerelease) == 2 else 0)


def download(url, destination, allowed_origin=None):
    if urllib.parse.urlparse(url).scheme != 'https':
        raise OperationError('Release downloads must use HTTPS.')
    if allowed_origin and origin(url) != allowed_origin:
        raise OperationError('Release download is outside the operator-approved distribution origin.')
    request = urllib.request.Request(url, headers={'User-Agent': 'Gaeld-Installer/1'})
    with urllib.request.urlopen(request, timeout=60) as source:
        if urllib.parse.urlparse(source.url).scheme != 'https':
            raise OperationError('Insecure download redirect refused.')
        if allowed_origin and origin(source.url) != allowed_origin:
            raise OperationError('Download redirect left the operator-approved distribution origin.')
        with Path(destination).open('wb') as target:
            shutil.copyfileobj(source, target)


def extract_tar(archive, destination):
    """Do not trust paths, links, devices or duplicate entries, even in a signed archive."""
    with tarfile.open(archive, 'r:*') as tar:
        seen = set()
        members = tar.getmembers()
        for item in members:
            path = Path(item.name)
            if path.is_absolute() or '..' in path.parts or item.name in seen or not (item.isfile() or item.isdir()):
                raise OperationError('Unsafe archive member refused.')
            seen.add(item.name)
        for item in members:
            target = Path(destination) / item.name
            if item.isdir():
                target.mkdir(parents=True, exist_ok=True, mode=0o755)
                os.chmod(target, 0o755)
            else:
                target.parent.mkdir(parents=True, exist_ok=True, mode=0o755)
                with tar.extractfile(item) as source, target.open('xb') as output:
                    shutil.copyfileobj(source, output)
                os.chmod(target, 0o644 | (item.mode & 0o111))


def env_values(path):
    """Read the restricted managed env format without shell evaluation."""
    values = {}
    for line in Path(path).read_text().splitlines():
        if not line.strip() or line.lstrip().startswith('#'):
            continue
        key, separator, value = line.partition('=')
        if not separator or not re.fullmatch(r'[A-Z][A-Z0-9_]*', key):
            raise OperationError('Invalid managed .env line.')
        values[key] = value.strip().strip('"').strip("'")
    return values


def valid_application_key(value):
    try:
        decoded = base64.b64decode(value[7:], validate=True) if value.startswith('base64:') else value.encode()
        return len(decoded) == 32
    except (ValueError, TypeError):
        return False


OFFICIAL_IMAGES = {'app': 'ghcr.io/scanix/gaeld', 'web': 'ghcr.io/scanix/gaeld-web',
                   'postgres': 'docker.io/library/postgres', 'redis': 'docker.io/library/redis'}


def origin(url):
    parsed = urllib.parse.urlparse(url)
    return parsed.scheme + '://' + parsed.netloc


def distribution_policy(path):
    policy = read_json(path)
    if policy.get('schema') != 1 or policy.get('channel') != 'rehearsal':
        raise OperationError('An explicit rehearsal distribution policy must use schema 1 and channel rehearsal.')
    location = policy.get('release_origin', '')
    parsed = urllib.parse.urlparse(location)
    if parsed.scheme != 'https' or not parsed.hostname or parsed.username or parsed.password or location != origin(location):
        raise OperationError('The rehearsal release origin must be an exact HTTPS origin without credentials or a path.')
    if not re.fullmatch(r'[a-f0-9]{64}', policy.get('release_key_sha256', '')):
        raise OperationError('The rehearsal policy must pin its independently supplied release key.')
    images = policy.get('images', {})
    if set(images) != set(OFFICIAL_IMAGES) or any(not isinstance(value, str) or not re.fullmatch(r'[a-z0-9.-]+(?::[0-9]+)?/[a-z0-9._/-]+', value) or '..' in value for value in images.values()):
        raise OperationError('The rehearsal policy must specify one exact OCI repository per service.')
    if len(set(images.values())) != 4:
        raise OperationError('The four rehearsal image repositories must be distinct.')
    return policy


def verify_release(directory, expected_version, trust_key, policy=None):
    pem = Path(trust_key).read_text()
    try:
        der = base64.b64decode(''.join(line for line in pem.splitlines() if not line.startswith('---')), validate=True)
    except ValueError as error:
        raise OperationError('Invalid release verification key.') from error
    if len(der) != 44 or not der.startswith(bytes.fromhex('302a300506032b6570032100')):
        raise OperationError('The independently configured release key must be Ed25519.')
    run(['openssl', 'pkeyutl', '-verify', '-pubin', '-inkey', str(trust_key),
         '-rawin', '-in', str(directory / 'release.json'), '-sigfile', str(directory / 'release.json.sig')])
    release = read_json(directory / 'release.json')
    if release.get('schema') != 1 or release.get('version') != expected_version:
        raise OperationError('Signed release does not match the requested version/schema.')
    version(expected_version)
    if not re.fullmatch(r'[a-f0-9]{40}', release.get('commit', '')):
        raise OperationError('Release must identify its exact source commit.')
    repositories = policy['images'] if policy else OFFICIAL_IMAGES
    if policy:
        if '-rc.' not in expected_version or release.get('channel') != 'rehearsal' or digest(trust_key) != policy['release_key_sha256']:
            raise OperationError('A rehearsal requires an RC manifest explicitly identified as rehearsal and its pinned test key.')
    elif release.get('channel', 'public') != 'public':
        raise OperationError('A rehearsal release requires an independently supplied distribution policy.')
    for image, repository in repositories.items():
        if not re.fullmatch(re.escape(repository) + r'@sha256:[a-f0-9]{64}', release.get('images', {}).get(image, '')):
            raise OperationError('Release image repository or immutable digest does not match the operator trust policy: ' + image)
    if release.get('postgres_major') != 16 or release.get('redis_major') != 7:
        raise OperationError('Database major-version upgrades require a separate migration procedure.')
    source = release.get('source', {})
    if not re.fullmatch(r'[a-f0-9]{64}', source.get('sha256', '')):
        raise OperationError('Release source digest is missing.')
    if release.get('boundary_contract') != '1.0.0' or release.get('minimum_version') is None:
        raise OperationError('Release compatibility contract is missing or unsupported.')
    version(release['minimum_version'])
    if not isinstance(release.get('allowed_from'), list) or not release['allowed_from']:
        raise OperationError('Release must declare the exact supported upgrade paths.')
    for previous in release['allowed_from']:
        version(previous)
    if release.get('edition') != 'ce' or release.get('migration_policy') != 'forward-only':
        raise OperationError('Unsupported release edition or migration policy.')
    for name in ('provenance', 'sbom'):
        if not re.fullmatch(r'[a-f0-9]{64}', release.get(name, {}).get('sha256', '')):
            raise OperationError('Release provenance and SBOM must be present and immutable.')
    return release


class Installation:
    def __init__(self, root, runner=run):
        self.root = Path(root).resolve()
        if any(character in str(self.root) for character in ('\n', '\r', ':', '$')):
            raise OperationError('Choose an installation path without newline, colon or dollar characters.')
        self.meta = self.root / '.gaeld'
        self.shared = self.root / 'shared'
        self.runner = runner
        self.identity_file = None
        self.admin_secret_file = None
        self.state = read_json(self.meta / 'state.json') if (self.meta / 'state.json').exists() else {}
        self.policy_path = None

    def release_policy(self, trust_key=None):
        recorded = self.state.get('distribution_policy_sha256')
        path = Path(self.policy_path) if self.policy_path else self.meta / 'distribution-policy.json'
        if not self.policy_path and not recorded:
            return None
        if not path.is_file() or recorded and digest(path) != recorded:
            raise OperationError('The recorded distribution policy changed or is missing; trust cannot change during recovery or update.')
        policy = distribution_policy(path)
        if trust_key and digest(trust_key) != policy['release_key_sha256']:
            raise OperationError('The release key does not match the independently supplied distribution policy.')
        return policy

    @contextlib.contextmanager
    def lock(self):
        self.meta.mkdir(parents=True, exist_ok=True, mode=0o700)
        with (self.meta / 'lock').open('a') as handle:
            try:
                fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
            except BlockingIOError:
                raise OperationError('Another installation operation is already running.')
            yield

    def save(self, status, **values):
        self.state.update(values, status=status, updated_at=datetime.datetime.now(datetime.timezone.utc).isoformat())
        write_json(self.meta / 'state.json', self.state)
        with (self.meta / 'operations.jsonl').open('a') as stream:
            stream.write(json.dumps({'at': self.state['updated_at'], 'status': status,
                                     'version': self.state.get('version'), 'target': self.state.get('target')}) + '\n')
            stream.flush()
            os.fsync(stream.fileno())

    def release_dir(self, target=None):
        return self.root / 'releases' / (target or self.state['version'])

    def compose(self, *arguments, target=None, **kwargs):
        directory = self.release_dir(target)
        return self.runner(['docker', 'compose', '--project-name', self.state['project'],
                            '--env-file', str(self.meta / 'compose.env'), '-f',
                            str(directory / 'docker/production/compose.yml'), *arguments], **kwargs)

    def artisan(self, *arguments, target=None, secret_file=None, **kwargs):
        if self.state['mode'] == 'docker':
            options = ['run', '--rm', '--no-deps', '-T']
            if secret_file:
                options += ['--user', 'root', '--volume', str(Path(secret_file).resolve()) + ':/run/gaeld-admin.json:ro']
            return self.compose(*options, 'app', 'php', 'artisan', *arguments, target=target, **kwargs)
        command = ['php', 'artisan', *arguments]
        if os.geteuid() == 0 and not secret_file:
            command = ['runuser', '-u', 'www-data', '--', *command]
        return self.runner(command, cwd=self.release_dir(target), **kwargs)

    def require_ready(self):
        if self.state.get('status') != 'ready':
            raise OperationError('Installation is not ready. Run doctor; after a failed update restore the recorded backup or use resume.')

    def source_inventory(self, directory):
        result = {}
        for path in Path(directory).rglob('*'):
            relative = path.relative_to(directory)
            if relative.parts[0] in ('storage', 'node_modules', '.env') or relative.parts[:2] == ('bootstrap', 'cache') or relative.parts[:2] == ('public', 'storage'):
                continue
            if path.is_file():
                if not path.resolve().is_relative_to(Path(directory).resolve()):
                    raise OperationError('Release source contains an external symlink.')
                result[str(relative)] = digest(path)
        return result

    def verify_source_tree(self):
        receipt = self.meta / 'releases' / (self.state['version'] + '.files.json')
        if not receipt.is_file() or read_json(receipt) != self.source_inventory(self.release_dir()):
            raise OperationError('Installed release files differ from their verified staging inventory. Preserve local changes and restore a clean release before upgrading.')

    def stage(self, target, release_url, trust_key):
        version(target)
        trust_key = Path(trust_key).resolve()
        if not trust_key.is_file():
            raise OperationError('An independently obtained release verification public key is required.')
        trusted = digest(trust_key)
        if self.state.get('trust_key_sha256', trusted) != trusted:
            raise OperationError('Release trust key changed. Key rotation requires a separate reviewed procedure.')
        if self.state.get('version') and version(target) <= version(self.state['version']):
            raise OperationError('Updates must move forward; use restore for recovery to an older release.')
        with tempfile.TemporaryDirectory(prefix='release-', dir=self.meta) as temporary:
            staging = Path(temporary)
            base = release_url.rstrip('/') + '/v' + target
            policy = self.release_policy(trust_key)
            allowed_origin = policy['release_origin'] if policy else None
            for name in ('release.json', 'release.json.sig'):
                download(base + '/' + name, staging / name, allowed_origin)
            release = verify_release(staging, target, trust_key, policy)
            for name in ('provenance', 'sbom'):
                download(release[name]['url'], staging / (name + '.json'), allowed_origin)
                if digest(staging / (name + '.json')) != release[name]['sha256']:
                    raise OperationError('Release ' + name + ' checksum mismatch.')
                read_json(staging / (name + '.json'))
            if self.state.get('version') and version(self.state['version']) < version(release['minimum_version']):
                raise OperationError('An intermediate release is required before this upgrade.')
            if self.state.get('version') and self.state['version'] not in release['allowed_from']:
                raise OperationError('This exact upgrade path has not been validated. Install the next supported release first.')
            if self.state.get('ee_version') and self.state['ee_version'] not in release.get('compatible_ee_versions', []):
                raise OperationError('This release has not been validated against the installed EE version.')
            if self.state.get('ee_version'):
                raise OperationError('The public CE updater cannot replace a private EE deployment. Use the validated private paired deployment workflow.')
            archive = staging / 'source.tar.gz'
            download(release['source']['url'], archive, allowed_origin)
            if digest(archive) != release['source']['sha256']:
                raise OperationError('Source artifact checksum mismatch.')
            content = staging / 'content'
            content.mkdir()
            extract_tar(archive, content)
            for required in ('artisan', 'vendor/autoload.php', 'public/build/manifest.json', 'docker/production/compose.yml'):
                if not (content / required).is_file():
                    raise OperationError('Release artifact is incomplete: ' + required)
            if self.state.get('mode') == 'native':
                shutil.rmtree(content / 'vendor')
                self.runner(['composer', 'install', '--no-dev', '--prefer-dist', '--no-interaction', '--optimize-autoloader'],
                            cwd=content, env={'COMPOSER_ALLOW_SUPERUSER': '1', 'SENTRY_AUTH_TOKEN': ''})
                self.runner(['pnpm', 'install', '--frozen-lockfile'], cwd=content, env={'SENTRY_AUTH_TOKEN': ''})
                self.runner(['pnpm', 'build'], cwd=content, env={'SENTRY_AUTH_TOKEN': ''})
                shutil.rmtree(content / 'node_modules')
                for path in content.rglob('*'):
                    if not path.is_symlink():
                        os.chmod(path, 0o755 if path.is_dir() else 0o644 | (path.stat().st_mode & 0o111))
            destination = self.release_dir(target)
            if destination.exists():
                raise OperationError('Target release directory already exists. Preserve it for diagnosis before retrying.')
            destination.parent.mkdir(parents=True, exist_ok=True)
            os.chmod(destination.parent, 0o755)
            os.chmod(content, 0o755)
            shutil.copy2(staging / 'release.json', content / 'release.json')
            shutil.copy2(staging / 'release.json.sig', content / 'release.json.sig')
            for name in ('provenance', 'sbom'):
                shutil.copy2(staging / (name + '.json'), content / ('release-' + name + '.json'))
            content.rename(destination)
            write_json(self.meta / 'releases' / (target + '.files.json'), self.source_inventory(destination))
            self.state['trust_key_sha256'] = trusted
            self.state['release_url'] = release_url
            if policy:
                write_json(self.meta / 'distribution-policy.json', policy)
                self.state['distribution_policy_sha256'] = digest(self.meta / 'distribution-policy.json')
                self.policy_path = None
            if trust_key != self.meta / 'release.pub':
                shutil.copy2(trust_key, self.meta / 'release.pub')
            return release

    def configure_runtime(self, release):
        if self.state['mode'] == 'docker':
            config = {'GAELD_ROOT': str(self.root), 'GAELD_IMAGE': release['images']['app'],
                      'GAELD_WEB_IMAGE': release['images']['web'], 'GAELD_PORT': str(self.state.get('port', 8080)),
                      'GAELD_POSTGRES_IMAGE': release['images']['postgres'], 'GAELD_REDIS_IMAGE': release['images']['redis'],
                      'GAELD_VERSION': release['version'],
                      'DB_PASSWORD': env_values(self.shared / '.env')['DB_PASSWORD']}
            text = ''.join(f'{key}={value}\n' for key, value in config.items())
            (self.meta / 'compose.env').write_text(text)
            os.chmod(self.meta / 'compose.env', 0o600)
            for directory in (self.meta / 'runtime', self.meta / 'cache' / release['version']):
                directory.mkdir(parents=True, exist_ok=True)
        else:
            directory = self.release_dir(release['version'])
            for name, path in (('.env', self.shared / '.env'), ('storage', self.shared / 'storage')):
                existing = directory / name
                if existing.is_dir() and not existing.is_symlink():
                    shutil.rmtree(existing)
                elif existing.exists() or existing.is_symlink():
                    existing.unlink()
                existing.symlink_to(path, target_is_directory=name == 'storage')
            public_storage = directory / 'public/storage'
            if not public_storage.exists():
                public_storage.symlink_to(self.shared / 'storage/app/public', target_is_directory=True)
            self.runner(['php', 'vendor/composer/platform_check.php'], cwd=directory)

    def activate(self, target):
        link = self.root / 'current.new'
        link.unlink(missing_ok=True)
        link.symlink_to(self.release_dir(target), target_is_directory=True)
        link.replace(self.root / 'current')

    def stop_writers(self):
        self.artisan('down', '--retry=60')
        self.save('draining')
        if self.state['mode'] == 'docker':
            # Docker stop waits for SIGTERM, then kills at timeout. A killed writer
            # must not be mistaken for a successfully drained installation.
            ids = (self.compose('ps', '-q', 'app', 'web', 'horizon', 'scheduler', capture=True) or '').split()
            self.compose('stop', '--timeout', '2100', 'web', 'scheduler', 'horizon', 'app')
            for container in ids:
                status = json.loads(self.runner(['docker', 'inspect', '--format', '{{json .State}}', container], capture=True))
                if status.get('OOMKilled') or status.get('ExitCode') not in (0, 143):
                    raise OperationError('A writer did not stop cleanly. Backup/update refused.')
        else:
            self.runner(['systemctl', 'stop', 'gaeld-web', 'gaeld-scheduler', 'gaeld-horizon', 'gaeld-fpm'])
            for service in ('gaeld-web', 'gaeld-scheduler', 'gaeld-horizon', 'gaeld-fpm'):
                if self.runner(['systemctl', 'show', service, '--property=Result', '--value'], capture=True).strip() != 'success':
                    raise OperationError('A native writer did not stop cleanly.')
        self.save('stopped')

    def start(self, target=None):
        if self.state['mode'] == 'docker':
            (self.meta / 'runtime/scheduler').unlink(missing_ok=True)
            self.compose('up', '-d', '--wait', '--wait-timeout', '120', 'app', 'web', 'horizon', 'scheduler', target=target)
        else:
            self.runner(['systemctl', 'start', 'gaeld-fpm', 'gaeld-web', 'gaeld-horizon', 'gaeld-scheduler'])
            for service in ('gaeld-fpm', 'gaeld-web', 'gaeld-horizon', 'gaeld-scheduler'):
                self.runner(['systemctl', 'is-active', '--quiet', service])
            for attempt in range(10):
                if Path('/run/gaeld/scheduler').exists():
                    break
                time.sleep(1)
        self.artisan('gaeld:doctor', target=target)
        self.artisan('up', target=target)
        try:
            with urllib.request.urlopen(f'http://127.0.0.1:{self.state.get("port", 8080)}/up', timeout=15) as response:
                if response.status != 200:
                    raise OperationError('Application HTTP health check failed.')
        except OSError as error:
            raise OperationError('Application HTTP health check failed; services remain stopped.') from error

    def fail_closed(self, status='failed'):
        self.save(status)
        try:
            self.stop_runtime()
        except (OperationError, OSError):
            self.save(status, service_stop_failed=True)

    def database(self, command, *, output=None, archive=None):
        if command == 'pg_dump':
            return self.artisan('gaeld:database-snapshot', output=output)
        env = env_values(self.shared / '.env')
        args = [command, '--username=' + env['DB_USERNAME'], '--dbname=' + env['DB_DATABASE']]
        if command == 'pg_dump':
            args += ['--format=custom', '--no-owner', '--no-acl']
        else:
            args += ['--clean', '--if-exists', '--exit-on-error', '--single-transaction', '--no-owner', '--no-acl']
        if self.state['mode'] == 'docker':
            if archive:
                # stdin is used by pg_restore; do not copy plaintext dumps into a running volume.
                self.runner(['docker', 'compose', '--project-name', self.state['project'], '--env-file',
                             str(self.meta / 'compose.env'), '-f', str(self.release_dir() / 'docker/production/compose.yml'),
                             'exec', '-T', 'pgsql', *args], input_file=archive)
            else:
                self.compose('exec', '-T', 'pgsql', *args, output=output)
        else:
            args += ['--host=' + env['DB_HOST'], '--port=' + env.get('DB_PORT', '5432')]
            if archive:
                args.append(str(archive))
            self.runner(args, env={'PGPASSWORD': env['DB_PASSWORD']}, output=output)

    def backup_stopped(self):
        if self.state.get('status') != 'stopped':
            raise OperationError('All writers must be stopped before a recovery backup.')
        if env_values(self.shared / '.env').get('FILESYSTEM_DISK', 'local') not in ('local', 'public'):
            raise OperationError('Remote document storage requires a verified backup adapter before managed backups or updates.')
        recipient = self.state.get('backup_recipient', '')
        if not re.fullmatch(r'age1[0-9a-z]{58}', recipient):
            raise OperationError('Configure an age X25519 recipient; keep its recovery identity separately.')
        identity = self.identity_file or self.state.get('recovery_identity')
        if not identity or not Path(identity).is_file():
            raise OperationError('A recovery identity file is required to decrypt and verify the backup before continuing.')
        if Path(identity).resolve().is_relative_to(self.root):
            raise OperationError('The recovery identity must remain outside the installation and its backups.')
        backup_dir = self.root / 'backups'
        backup_dir.mkdir(mode=0o700, exist_ok=True)
        with tempfile.TemporaryDirectory(prefix='backup-', dir=self.meta) as temporary:
            staging = Path(temporary)
            with (staging / 'database.dump').open('wb') as output:
                self.database('pg_dump', output=output)
            if (staging / 'database.dump').stat().st_size < 5:
                raise OperationError('Database dump is empty.')
            shutil.copy2(self.shared / '.env', staging / 'environment')
            release_metadata = staging / 'release'
            release_metadata.mkdir()
            for name in ('release.json', 'release.json.sig', 'release-provenance.json', 'release-sbom.json'):
                if (self.release_dir() / name).is_file():
                    shutil.copy2(self.release_dir() / name, release_metadata / name)
            redis_snapshot = self.artisan('gaeld:redis-snapshot', 'export', '--path=-', capture=True)
            write_json(staging / 'redis.json', json.loads(redis_snapshot))
            shutil.copytree(self.shared / 'storage/app', staging / 'files', symlinks=True)
            secrets_directory = self.shared / 'secrets'
            if secrets_directory.exists():
                shutil.copytree(secrets_directory, staging / 'secrets', symlinks=True)
            else:
                (staging / 'secrets').mkdir()
            manifest = {'schema': 1, 'version': self.state['version'], 'ee_version': self.state.get('ee_version'),
                        'created_at': datetime.datetime.now(datetime.timezone.utc).isoformat(), 'files': {}}
            for path in staging.rglob('*'):
                if path.is_symlink():
                    raise OperationError('Backup contains symlinks; external storage requires an explicit backup adapter.')
                if path.is_file():
                    manifest['files'][str(path.relative_to(staging))] = digest(path)
            write_json(staging / 'manifest.json', manifest)
            archive = staging / 'backup.tar'
            with tarfile.open(archive, 'w') as tar:
                for name in ('database.dump', 'environment', 'redis.json', 'files', 'secrets', 'release', 'manifest.json'):
                    tar.add(staging / name, arcname=name)
            filename = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S') + '-' + secrets.token_hex(4) + '.tar.age'
            temporary_archive = backup_dir / (filename + '.partial')
            self.runner(['age', '--encrypt', '--recipient', recipient, '--output', str(temporary_archive), str(archive)])
            if not temporary_archive.is_file() or temporary_archive.stat().st_size == 0:
                raise OperationError('Encrypted backup was not produced.')
            verification = staging / 'verification'
            verification.mkdir()
            verified_manifest, _ = self.verify_backup(temporary_archive, Path(identity), verification)
            if verified_manifest != manifest:
                raise OperationError('Encrypted backup contents do not match the captured recovery snapshot.')
            temporary_archive.rename(backup_dir / filename)
            result = {'path': str(backup_dir / filename), 'sha256': digest(backup_dir / filename),
                      'version': self.state['version'], 'created_at': manifest['created_at'],
                      'verified_at': datetime.datetime.now(datetime.timezone.utc).isoformat()}
            self.save('stopped', backup=result)
            write_json(backup_dir / (filename + '.json'), result)
            return result

    def migrate(self, target):
        self.save('migrating')
        self.artisan('edition:verify', target=target)
        self.artisan('migrate', '--force', '--no-interaction', target=target)
        for command in ('config:cache', 'route:cache', 'view:cache', 'event:cache'):
            self.artisan(command, target=target)

    def update(self, target, release_url, trust_key):
        self.require_ready()
        self.verify_source_tree()
        key_hash = digest(self.shared / '.env')
        release = self.stage(target, release_url, trust_key)
        self.save('prepared', target=target, previous_version=self.state['version'])
        try:
            self.stop_writers()
            self.backup_stopped()
            self.configure_runtime(release)
            if self.state['mode'] == 'docker':
                self.compose('pull', 'app', 'web', 'horizon', 'scheduler', target=target)
            self.fix_permissions(target)
            self.migrate(target)
            if digest(self.shared / '.env') != key_hash:
                raise OperationError('Environment changed during update. Restore the recovery backup before continuing.')
            self.activate(target)
            self.start(target)
            self.save('ready', version=target, target=None, previous_version=None)
        except BaseException:
            self.fail_closed()
            raise

    def backup(self):
        self.require_ready()
        try:
            self.stop_writers()
            backup = self.backup_stopped()
            self.start()
            self.save('ready')
            return backup
        except BaseException:
            self.fail_closed()
            raise

    def verify_backup(self, archive, identity, destination):
        plaintext = destination / 'backup.tar'
        self.runner(['age', '--decrypt', '--identity', str(identity), '--output', str(plaintext), str(archive)])
        content = destination / 'verified'
        content.mkdir()
        extract_tar(plaintext, content)
        manifest = read_json(content / 'manifest.json')
        if manifest.get('schema') != 1 or not manifest.get('files'):
            raise OperationError('Unsupported recovery backup.')
        actual = {str(p.relative_to(content)): digest(p) for p in content.rglob('*') if p.is_file() and p != content / 'manifest.json'}
        if manifest['files'] != actual or not {'database.dump', 'environment', 'redis.json'}.issubset(actual):
            raise OperationError('Backup integrity or completeness check failed.')
        if not (content / 'files').is_dir() or not (content / 'secrets').is_dir():
            raise OperationError('Backup document or signing-secret directories are missing.')
        if not valid_application_key(env_values(content / 'environment').get('APP_KEY', '')):
            raise OperationError('Backup is missing its original application key.')
        return manifest, content

    def restore(self, archive, identity, confirm):
        if not confirm:
            raise OperationError('Restoring replaces current database/files. Review the backup then pass --confirm-replace-data.')
        if not self.state.get('version'):
            raise OperationError('Restore requires an initialized managed installation with the original release available.')
        with tempfile.TemporaryDirectory(prefix='restore-', dir=self.meta) as temporary:
            manifest, content = self.verify_backup(archive, identity, Path(temporary))
            target = manifest['version']
            release = verify_release(self.release_dir(target), target, self.meta / 'release.pub', self.release_policy(self.meta / 'release.pub'))
            if manifest.get('ee_version') != self.state.get('ee_version'):
                raise OperationError('Restore requires the exact EE package from the recovery backup.')
            try:
                # Preserve current files before any destructive restoration. An existing failed
                # migration may not boot Artisan, so a stopped failed install is handled directly.
                if self.state.get('status') == 'ready':
                    self.stop_writers()
                    self.backup_stopped()
                else:
                    self.stop_runtime()
                self.save('restoring')
                self.database('pg_restore', archive=content / 'database.dump')
                infrastructure = {'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
                                  'REDIS_HOST', 'REDIS_PORT', 'REDIS_PASSWORD', 'REDIS_DB', 'REDIS_CACHE_DB'}
                current_lines = {line.partition('=')[0]: line for line in (self.shared / '.env').read_text().splitlines()
                                 if line.partition('=')[0] in infrastructure}
                restored_lines = [current_lines.get(line.partition('=')[0], line) for line in (content / 'environment').read_text().splitlines()]
                (self.shared / '.env').write_text('\n'.join(restored_lines) + '\n')
                self.configure_runtime(release)
                old_files = self.shared / ('files-before-restore-' + secrets.token_hex(4))
                (self.shared / 'storage/app').rename(old_files)
                shutil.copytree(content / 'files', self.shared / 'storage/app')
                if (self.shared / 'secrets').exists():
                    (self.shared / 'secrets').rename(self.shared / ('secrets-before-restore-' + secrets.token_hex(4)))
                shutil.copytree(content / 'secrets', self.shared / 'secrets')
                self.fix_permissions(target)
                self.activate(target)
                for command in ('config:cache', 'route:cache', 'view:cache', 'event:cache'):
                    self.artisan(command, target=target)
                self.artisan('gaeld:redis-snapshot', 'restore', '--path=-', target=target, input_file=content / 'redis.json')
                self.start(target)
                self.save('ready', version=target, target=None, previous_version=None)
            except BaseException:
                self.fail_closed('restore-failed')
                raise

    def stop_runtime(self):
        if self.state['mode'] == 'docker':
            self.compose('stop', '--timeout', '2100', 'web', 'scheduler', 'horizon', 'app', target=self.state.get('target'))
        else:
            self.runner(['systemctl', 'stop', 'gaeld-web', 'gaeld-scheduler', 'gaeld-horizon', 'gaeld-fpm'])

    def resume(self):
        if self.state.get('status') != 'failed' or self.state.get('target'):
            raise OperationError('Only a failed backup can be resumed. A failed update requires restore; migration replay is never automatic.')
        try:
            self.start()
            self.save('ready')
        except BaseException:
            self.fail_closed()
            raise

    def doctor(self):
        for binary in ('openssl', 'age', 'docker' if self.state.get('mode', 'docker') == 'docker' else 'php'):
            if not shutil.which(binary):
                raise OperationError('Missing required executable: ' + binary)
        if not self.state:
            raise OperationError('No managed installation found at this path.')
        print(json.dumps({key: self.state.get(key) for key in ('status', 'mode', 'version', 'ee_version', 'target', 'backup')}, indent=2))
        if self.state.get('status') != 'ready':
            raise OperationError('An incomplete operation is recorded. Services must remain stopped until recovery.')
        self.verify_source_tree()
        self.artisan('gaeld:doctor')
        if self.state['mode'] == 'docker':
            self.compose('ps', '--all')

    def install(self, target, release_url, trust_key, mode, recipient, url, port, environment_file=None):
        if self.state or (self.shared / '.env').exists():
            raise OperationError('Existing installation detected. APP_KEY and data will not be replaced.')
        if platform.system() == 'Linux' and os.geteuid() != 0:
            raise OperationError('Run managed Linux operations as root so recovery can read all application-owned files. macOS Docker uses the logged-in operator account.')
        if not self.admin_secret_file or not Path(self.admin_secret_file).is_file():
            raise OperationError('An administrator secret file is required; no generic production administrator is created.')
        admin = read_json(self.admin_secret_file)
        if not all(isinstance(admin.get(field), str) and admin[field] for field in ('name', 'email', 'password', 'organization', 'currency', 'locale')):
            raise OperationError('Administrator secret file is incomplete.')
        if len(admin['password']) < 12 or '@' not in admin['email']:
            raise OperationError('Administrator credentials require a valid email and a password of at least 12 characters.')
        if mode == 'native' and (platform.system() != 'Linux' or os.geteuid() != 0):
            raise OperationError('Native installation supports Linux with systemd and requires root for dedicated services.')
        if not re.fullmatch(r'age1[0-9a-z]{58}', recipient):
            raise OperationError('An age recovery recipient is required before installation.')
        if not self.identity_file or not Path(self.identity_file).is_file():
            raise OperationError('--identity-file is required to verify encrypted backups. Keep a separate off-site copy of this key.')
        identity = Path(self.identity_file).resolve()
        if identity.is_relative_to(self.root):
            raise OperationError('Keep the recovery identity outside the installation and backup directories.')
        if self.runner(['age-keygen', '-y', str(identity)], capture=True).strip() != recipient:
            raise OperationError('The recovery identity does not match the backup recipient.')
        if not re.fullmatch(r'https?://[^\s\"\x27$`]+', url):
            raise OperationError('Provide a valid application URL.')
        if mode == 'native' and not environment_file:
            raise OperationError('Native installation requires --environment-file with PostgreSQL/Redis credentials and APP_KEY.')
        if environment_file:
            provided_environment = env_values(environment_file)
            if provided_environment.get('PLUGINS_ENABLED', 'false').lower() not in ('false', '0', ''):
                raise OperationError('The public installer requires PLUGINS_ENABLED=false. Private EE deployments use their paired deployment workflow.')
            if provided_environment.get('APP_ENV') != 'production' or provided_environment.get('APP_DEBUG', 'false').lower() not in ('false', '0'):
                raise OperationError('A managed environment file must use APP_ENV=production and APP_DEBUG=false.')
        self.state = {'mode': mode, 'project': 'gaeld-' + secrets.token_hex(5), 'port': port,
                      'backup_recipient': recipient, 'recovery_identity': str(identity), 'version': target, 'ee_version': None}
        # A fresh installation has no previous release to compare.
        del self.state['version']
        release = self.stage(target, release_url, trust_key)
        self.state['version'] = target
        self.shared.mkdir(mode=0o700, exist_ok=True)
        (self.shared / 'secrets').mkdir(mode=0o700, exist_ok=True)
        for name in ('app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'):
            (self.shared / 'storage' / name).mkdir(parents=True, exist_ok=True)
        if environment_file:
            shutil.copy2(environment_file, self.shared / '.env')
            env = env_values(self.shared / '.env')
            if not valid_application_key(env.get('APP_KEY', '')):
                raise OperationError('Environment file requires an existing valid APP_KEY.')
        else:
            env = {'APP_NAME': 'Gaeld', 'APP_ENV': 'production', 'APP_DEBUG': 'false', 'APP_URL': url,
                   'APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
                   'APP_MAINTENANCE_DRIVER': 'file', 'DB_CONNECTION': 'pgsql', 'DB_HOST': 'pgsql',
                   'DB_PORT': '5432', 'DB_DATABASE': 'gaeld', 'DB_USERNAME': 'gaeld', 'DB_PASSWORD': secrets.token_hex(32),
                   'REDIS_HOST': 'redis', 'CACHE_STORE': 'redis', 'QUEUE_CONNECTION': 'redis', 'SESSION_DRIVER': 'redis',
                   'SESSION_ENCRYPT': 'true', 'MAIL_MAILER': 'log', 'SCOUT_DRIVER': 'null', 'PLUGINS_ENABLED': 'false',
                   'GAELD_MANAGED_INSTALLATION': 'true', 'LOG_CHANNEL': 'stderr'}
            env['SESSION_SECURE_COOKIE'] = 'true' if url.startswith('https://') else 'false'
            env['REDIS_PREFIX'] = self.state['project'] + '-database-'
            env['HORIZON_PREFIX'] = self.state['project'] + '-horizon:'
            (self.shared / '.env').write_text(''.join(f'{key}={value}\n' for key, value in env.items()))
        os.chmod(self.shared / '.env', 0o600)
        os.chmod(self.root, 0o755)
        self.save('installing')
        try:
            self.configure_runtime(release)
            self.activate(target)
            if mode == 'docker':
                self.compose('pull')
                self.compose('up', '-d', '--wait', 'pgsql', 'redis')
                self.fix_permissions(target)
            else:
                self.install_native_services()
            secret_path = '/run/gaeld-admin.json' if mode == 'docker' else str(Path(self.admin_secret_file).resolve())
            self.artisan('gaeld:install', '--admin-secret-file=' + secret_path, '--no-interaction', secret_file=self.admin_secret_file)
            self.fix_permissions(target)
            self.artisan('down', '--retry=60')
            self.start()
            self.save('ready')
        except BaseException:
            self.fail_closed('install-failed')
            raise

    def fix_permissions(self, target):
        if self.state['mode'] == 'docker':
            self.compose('run', '--rm', '--no-deps', '-T', '--user', 'root', '--volume',
                         str(self.shared / 'secrets') + ':/run/gaeld-secrets-admin:rw', 'app', 'sh', '-c',
                         'chown -R www-data:www-data storage bootstrap/cache /run/gaeld /run/gaeld-secrets-admin', target=target)
        else:
            self.runner(['chown', '-R', 'www-data:www-data', str(self.shared / 'storage'), str(self.release_dir(target) / 'bootstrap/cache')])

    def install_native_services(self):
        self.runner(['id', 'www-data'])
        self.runner(['chown', '-R', 'www-data:www-data', str(self.shared), str(self.release_dir() / 'bootstrap/cache')])
        for service in ('fpm', 'web', 'horizon', 'scheduler'):
            template = (self.release_dir() / f'docker/production/gaeld-{service}.service').read_text()
            content = template.replace('@ROOT@', str(self.root))
            Path(f'/etc/systemd/system/gaeld-{service}.service').write_text(content)
        for name in ('fpm.conf', 'nginx-native.conf'):
            source = self.release_dir() / 'docker/production' / name
            (self.meta / name).write_text(source.read_text().replace('@ROOT@', str(self.root)).replace('@PORT@', str(self.state['port'])))
        self.runner(['systemctl', 'daemon-reload'])
        self.runner(['systemctl', 'enable', 'gaeld-fpm', 'gaeld-web', 'gaeld-horizon', 'gaeld-scheduler'])


def main():
    parser = argparse.ArgumentParser(description='Gäld managed installation and recovery')
    parser.add_argument('command', choices=['install', 'update', 'backup', 'restore', 'doctor', 'resume'])
    parser.add_argument('--root', default=os.environ.get('GAELD_ROOT', os.getcwd()))
    parser.add_argument('--to')
    parser.add_argument('--mode', choices=['docker', 'native'], default='docker')
    parser.add_argument('--release-url', default='https://github.com/Scanix/Gaeld/releases/download')
    parser.add_argument('--trust-key')
    parser.add_argument('--distribution-policy', help='Explicit operator-supplied policy for isolated RC rehearsals; never loaded from a release')
    parser.add_argument('--backup-recipient')
    parser.add_argument('--environment-file')
    parser.add_argument('--admin-secret-file')
    parser.add_argument('--url', default='http://localhost:8080')
    parser.add_argument('--port', type=int, default=8080)
    parser.add_argument('--archive')
    parser.add_argument('--identity-file')
    parser.add_argument('--confirm-replace-data', action='store_true')
    args = parser.parse_args()
    os.umask(0o077)
    installation = Installation(args.root)
    installation.policy_path = args.distribution_policy
    installation.identity_file = args.identity_file
    temporary_admin = None
    try:
        installation.admin_secret_file = args.admin_secret_file
        if args.command == 'install' and not args.admin_secret_file:
            if not sys.stdin.isatty():
                raise OperationError('Non-interactive installation requires --admin-secret-file.')
            admin = {'name': input('Administrator name: ').strip(), 'email': input('Administrator email: ').strip(),
                     'organization': input('First company name: ').strip(), 'currency': 'CHF',
                     'locale': input('Language (en/fr/de/it): ').strip()}
            admin['password'] = getpass.getpass('Administrator password (at least 12 characters): ')
            if admin['password'] != getpass.getpass('Confirm password: '):
                raise OperationError('Passwords do not match.')
            handle, temporary_admin = tempfile.mkstemp(prefix='gaeld-admin-', suffix='.json')
            with os.fdopen(handle, 'w') as output:
                json.dump(admin, output)
            os.chmod(temporary_admin, 0o400)
            installation.admin_secret_file = temporary_admin
        with installation.lock():
            installation.release_policy()
            if args.command in ('install', 'update') and not args.to:
                raise OperationError('--to must identify an immutable release.')
            if args.command == 'install':
                installation.install(args.to, args.release_url, args.trust_key or '', args.mode,
                                     args.backup_recipient or '', args.url, args.port, args.environment_file)
            elif args.command == 'update':
                installation.update(args.to, args.release_url, args.trust_key or installation.meta / 'release.pub')
            elif args.command == 'backup':
                print(json.dumps(installation.backup(), indent=2))
            elif args.command == 'restore':
                if not args.archive or not args.identity_file:
                    raise OperationError('Restore requires --archive and --identity-file.')
                installation.restore(Path(args.archive), Path(args.identity_file), args.confirm_replace_data)
            elif args.command == 'resume':
                installation.resume()
            else:
                installation.doctor()
    except (OperationError, OSError, ValueError) as error:
        if isinstance(error, OperationError) and error.diagnostics and installation.meta.is_dir():
            log = installation.meta / 'last-error.log'
            log.write_text(error.diagnostics[-65536:])
            os.chmod(log, 0o600)
        print('Gäld: ' + str(error), file=sys.stderr)
        return 1
    finally:
        if temporary_admin:
            Path(temporary_admin).unlink(missing_ok=True)
    return 0


if __name__ == '__main__':
    sys.exit(main())
