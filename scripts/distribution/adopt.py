#!/usr/bin/env python3
"""Adopt an unmodified CE v3.8.28 Sail installation without replacing its files or volumes."""

import argparse
import base64
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import sys
import tempfile

from manage import Installation, OperationError, digest, env_values, run, verify_release, version


class LegacySail:
    def __init__(self, source, project=None, runner=run):
        self.source = Path(source).resolve()
        self.project = project
        self.runner = runner
        self.configuration = {}
        self.environment = {}
        self.stopped = False

    def compose(self, *arguments, **kwargs):
        command = ['docker', 'compose']
        if self.project:
            command += ['--project-name', self.project]
        command += ['--env-file', str(self.source / '.env'), '-f', str(self.source / 'docker-compose.yml'), *arguments]
        return self.runner(command, cwd=self.source, **kwargs)

    def preflight(self, allow_stopped=False):
        if not (self.source / '.git').exists() or not (self.source / '.env').is_file():
            raise OperationError('Adoption requires the existing CE Git checkout and its original .env.')
        changes = self.runner(['git', 'status', '--porcelain', '--untracked-files=all'], cwd=self.source, capture=True).splitlines()
        maintenance_files = {'?? storage/framework/down', '?? storage/framework/maintenance.php'}
        if any(change not in maintenance_files for change in changes):
            raise OperationError('Locally modified installations require the documented source installation procedure.')
        commit = self.runner(['git', 'rev-parse', 'HEAD'], cwd=self.source, capture=True).strip()
        baseline = self.runner(['git', 'rev-parse', 'v3.8.28^{commit}'], cwd=self.source, capture=True).strip()
        if commit != baseline:
            raise OperationError('First upgrade the original installation to the exact CE v3.8.28 tag.')
        if (self.source / 'plugins/gaeld-ee/composer.json').exists():
            raise OperationError('This adoption command is for CE. An EE installation requires its separately validated edition pair.')
        self.environment = env_values(self.source / '.env')
        key = self.environment.get('APP_KEY', '')
        try:
            valid_key = key.startswith('base64:') and len(base64.b64decode(key[7:], validate=True)) == 32
        except ValueError:
            valid_key = False
        if not valid_key:
            raise OperationError('The existing APP_KEY is missing or invalid. It will never be regenerated.')
        if self.environment.get('DB_CONNECTION', 'pgsql') != 'pgsql' or self.environment.get('DB_HOST') != 'pgsql' or self.environment.get('REDIS_HOST') != 'redis':
            raise OperationError('Adoption supports the bundled PostgreSQL and Redis services only.')
        if any(self.environment.get(key) for key in ('DB_URL', 'DB_READ_HOST', 'REDIS_URL')):
            raise OperationError('External or replicated database connections require a separately reviewed migration.')
        if self.environment.get('FILESYSTEM_DISK', 'local') not in ('local', 'public'):
            raise OperationError('External document storage requires an explicit backup adapter before adoption.')
        if self.environment.get('REDIS_CLIENT', 'phpredis') != 'phpredis':
            raise OperationError('The verified Redis recovery format requires PhpRedis.')
        self.configuration = json.loads(self.compose('config', '--format', 'json', capture=True))
        services = self.configuration.get('services', {})
        if not {'laravel.test', 'worker', 'pgsql', 'redis'}.issubset(services):
            raise OperationError('The source does not match the supported Sail service layout.')
        volumes = services['laravel.test'].get('volumes', [])
        if not any(item.get('type') == 'bind' and Path(item.get('source', '')).resolve() == self.source and item.get('target') == '/var/www/html' for item in volumes):
            raise OperationError('The running application must use this exact source checkout.')
        if not allow_stopped and not self.compose('ps', '-q', 'laravel.test', capture=True).strip():
            raise OperationError('Start and validate the legacy installation before adopting it.')
        postgres = self.compose('exec', '-T', 'pgsql', 'postgres', '--version', capture=True)
        redis = self.compose('exec', '-T', 'redis', 'redis-server', '--version', capture=True)
        if not re.search(r'\b16\.', postgres) or not re.search(r'\bv=7\.', redis):
            raise OperationError('Adoption requires PostgreSQL 16 and Redis 7. Major-version migrations are separate.')
        return {'source': str(self.source), 'source_commit': commit, 'source_version': '3.8.28',
                'legacy_project': self.project or self.configuration.get('name'), 'environment_sha256': digest(self.source / '.env')}

    def stop_writers(self):
        if self.compose('ps', '-q', 'laravel.test', capture=True).strip():
            self.compose('exec', '-T', 'laravel.test', 'php', 'artisan', 'down', '--retry=60')
        else:
            self.compose('run', '--rm', '--no-deps', '-T', '--entrypoint', 'php', 'laravel.test', 'artisan', 'down', '--retry=60')
        writers = [name for name in self.configuration['services'] if name not in ('pgsql', 'redis')]
        containers = (self.compose('ps', '-q', *writers, capture=True) or '').split()
        self.compose('stop', '--timeout', '2100', *writers)
        for container in containers:
            state = json.loads(self.runner(['docker', 'inspect', '--format', '{{json .State}}', container], capture=True))
            if state.get('Running') or state.get('OOMKilled') or state.get('ExitCode') not in (0, 143):
                raise OperationError('A legacy writer failed to drain cleanly. Adoption remains stopped for investigation.')
        self.stopped = True

    def dump(self, output):
        if not self.stopped:
            raise OperationError('Legacy writers must be stopped before capturing the database.')
        self.compose('exec', '-T', 'pgsql', 'pg_dump', '--format=custom', '--no-owner', '--no-acl',
                     '--username=' + self.environment['DB_USERNAME'], '--dbname=' + self.environment['DB_DATABASE'], output=output)

    def redis_snapshot(self, command_path):
        if not self.stopped:
            raise OperationError('Legacy writers must be stopped before capturing queued work.')
        # The command comes from the verified new release; the old checkout is not edited.
        bootstrap = (
            'require "/var/www/html/vendor/autoload.php";'
            '$app=require "/var/www/html/bootstrap/app.php";'
            '$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();'
            'require "/tmp/gaeld-redis-snapshot.php";'
            '$command=$app->make(App\\Console\\Commands\\GaeldRedisSnapshotCommand::class);'
            '$command->setLaravel($app);'
            'exit($command->run(new Symfony\\Component\\Console\\Input\\ArrayInput(["operation"=>"export","--path"=>"-"]),new Symfony\\Component\\Console\\Output\\ConsoleOutput));'
        )
        return self.compose('run', '--rm', '--no-deps', '-T', '--entrypoint', 'php', '--volume',
                            str(command_path) + ':/tmp/gaeld-redis-snapshot.php:ro', 'laravel.test', '-r', bootstrap, capture=True)


class LegacyBackup(Installation):
    """Reuse the managed encrypted-backup implementation against stopped legacy services."""

    def __init__(self, destination, legacy, command_path):
        super().__init__(destination.root, runner=destination.runner)
        self.state = dict(destination.state, version='3.8.28', status='stopped')
        self.identity_file = destination.identity_file
        self.shared = legacy.source
        self.legacy = legacy
        self.command_path = command_path

    def database(self, command, *, output=None, archive=None):
        if command != 'pg_dump' or archive is not None:
            raise OperationError('Legacy adoption never restores over the original database.')
        self.legacy.dump(output)

    def artisan(self, *arguments, **kwargs):
        if arguments != ('gaeld:redis-snapshot', 'export', '--path=-'):
            raise OperationError('Unsupported legacy backup command.')
        return self.legacy.redis_snapshot(self.command_path)


class SailAdoption:
    def __init__(self, installation, legacy):
        self.installation = installation
        self.legacy = legacy

    def adopt(self, target, release_url, trust_key, recipient, port=8080, resume=False, confirm_replace_new_data=False):
        install = self.installation
        source = self.legacy.source
        if not resume and (install.state or (install.root.exists() and any(p.name != '.gaeld' for p in install.root.iterdir()))):
            raise OperationError('Adoption requires a new, empty destination. Existing installations are never overwritten.')
        if source == install.root or install.root.is_relative_to(source) or source.is_relative_to(install.root):
            raise OperationError('Source and destination must be separate, non-nested directories.')
        if version(target) <= version('3.8.28'):
            raise OperationError('Choose a signed release newer than CE v3.8.28.')
        identity = Path(install.identity_file or '').resolve()
        if not identity.is_file() or identity.is_relative_to(install.root) or identity.is_relative_to(source):
            raise OperationError('Keep the age recovery identity in a separate location outside both installations.')
        if not re.fullmatch(r'age1[0-9a-z]{58}', recipient) or install.runner(['age-keygen', '-y', str(identity)], capture=True).strip() != recipient:
            raise OperationError('The recovery identity must match the configured age recipient.')
        origin = self.legacy.preflight(allow_stopped=resume)
        if resume:
            if install.state.get('status') != 'adoption-failed' or install.state.get('target') != target or install.state.get('adoption') != origin:
                raise OperationError('Only the recorded failed adoption with an unchanged legacy source can be resumed.')
            if install.state.get('adoption_may_have_writes') and not confirm_replace_new_data:
                raise OperationError('The new installation may have accepted writes. Reconcile its retained volumes before retrying with --confirm-replace-new-data; retrying restores the legacy snapshot.')
            release = verify_release(install.release_dir(target), target, install.meta / 'release.pub', install.release_policy(install.meta / 'release.pub'))
            if digest(trust_key) != install.state['trust_key_sha256'] or recipient != install.state['backup_recipient']:
                raise OperationError('Resuming adoption cannot change release trust or backup identity.')
            install.stop_runtime()
            previous_projects = install.state.get('abandoned_projects', []) + [install.state['project']]
            if install.shared.exists():
                install.shared.rename(install.meta / ('failed-shared-' + secrets.token_hex(8)))
            install.save('adoption-prepared', project='gaeld-' + secrets.token_hex(5), abandoned_projects=previous_projects, adoption_may_have_writes=False)
        else:
            install.state = {'mode': 'docker', 'version': '3.8.28', 'target': target, 'project': 'gaeld-' + secrets.token_hex(5),
                             'port': port, 'backup_recipient': recipient, 'recovery_identity': str(identity), 'ee_version': None, 'adoption': origin}
            release = install.stage(target, release_url, trust_key)
            install.save('adoption-prepared')
        try:
            self.legacy.stop_writers()
            install.save('adoption-stopped')
            if digest(source / '.env') != origin['environment_sha256']:
                raise OperationError('The legacy environment changed while preparing adoption.')
            # An explicit retry captures again: the operator may have resumed the
            # old system after the failure. Never silently discard those writes.
            legacy_backup = LegacyBackup(install, self.legacy, install.release_dir(target) / 'app/Console/Commands/GaeldRedisSnapshotCommand.php')
            backup = legacy_backup.backup_stopped()
            install.save('adoption-backed-up', backup=backup)
            with tempfile.TemporaryDirectory(prefix='adoption-recovery-', dir=install.meta) as temporary:
                manifest, content = install.verify_backup(Path(backup['path']), identity, Path(temporary))
                if manifest['version'] != '3.8.28':
                    raise OperationError('The adoption backup does not identify the baseline version.')
                install.shared.mkdir(mode=0o700, parents=True, exist_ok=True)
                shutil.copytree(content / 'files', install.shared / 'storage/app')
                if (content / 'secrets').is_dir():
                    shutil.copytree(content / 'secrets', install.shared / 'secrets')
                else:
                    (install.shared / 'secrets').mkdir(mode=0o700)
                for name in ('framework/cache/data', 'framework/sessions', 'framework/views', 'logs'):
                    (install.shared / 'storage' / name).mkdir(parents=True, exist_ok=True)
                environment = (content / 'environment').read_text()
                replacements = {'APP_ENV': 'production', 'APP_DEBUG': 'false', 'APP_MAINTENANCE_DRIVER': 'file',
                                'DB_HOST': 'pgsql', 'DB_PORT': '5432', 'DB_USERNAME': 'gaeld', 'DB_DATABASE': 'gaeld',
                                'REDIS_HOST': 'redis', 'REDIS_PORT': '6379', 'REDIS_USERNAME': 'null', 'REDIS_PASSWORD': 'null',
                                'GAELD_MANAGED_INSTALLATION': 'true', 'LOG_CHANNEL': 'stderr', 'SCOUT_DRIVER': 'null'}
                lines = [line for line in environment.splitlines() if line.partition('=')[0] not in replacements]
                (install.shared / '.env').write_text('\n'.join(lines) + '\n' + ''.join(f'{key}={value}\n' for key, value in replacements.items()))
                os.chmod(install.shared / '.env', 0o600)
                if env_values(install.shared / '.env')['APP_KEY'] != self.legacy.environment['APP_KEY']:
                    raise OperationError('The original APP_KEY was not preserved.')
                install.state['version'] = target
                install.configure_runtime(release)
                install.compose('pull', target=target)
                install.compose('up', '-d', '--wait', 'pgsql', 'redis', target=target)
                install.database('pg_restore', archive=content / 'database.dump')
                install.fix_permissions(target)
                install.artisan('down', '--retry=60', target=target)
                install.artisan('gaeld:redis-snapshot', 'restore', '--path=-', target=target, input_file=content / 'redis.json')
                install.migrate(target)
                install.activate(target)
                install.save('adoption-starting', adoption_may_have_writes=True)
                install.start(target)
            install.save('ready', target=None, adoption={**origin, 'legacy_stopped': True, 'backup': backup})
        except BaseException:
            # Retain both sets of volumes and the original checkout. No automatic old-code restart.
            install.fail_closed('adoption-failed')
            raise


def main(argv=None):
    parser = argparse.ArgumentParser(description='Adopt a clean CE v3.8.28 Sail installation into a separate managed destination')
    parser.add_argument('--source', required=True)
    parser.add_argument('--root', required=True)
    parser.add_argument('--to', required=True)
    parser.add_argument('--legacy-project')
    parser.add_argument('--release-url', default='https://github.com/Scanix/Gaeld/releases/download')
    parser.add_argument('--trust-key', required=True)
    parser.add_argument('--distribution-policy', help='Independently supplied, pinned policy for an isolated RC rehearsal')
    parser.add_argument('--backup-recipient', required=True)
    parser.add_argument('--identity-file', required=True)
    parser.add_argument('--port', type=int, default=8080)
    parser.add_argument('--resume', action='store_true', help='Explicitly retry a failed adoption into fresh managed database/queue volumes')
    parser.add_argument('--confirm-replace-new-data', action='store_true', help='Acknowledge loss of any new-installation writes after reconciling its retained volumes')
    args = parser.parse_args(argv)
    os.umask(0o077)
    installation = Installation(args.root)
    installation.policy_path = args.distribution_policy
    installation.identity_file = args.identity_file
    try:
        with installation.lock():
            installation.release_policy()
            SailAdoption(installation, LegacySail(args.source, args.legacy_project)).adopt(args.to, args.release_url, args.trust_key, args.backup_recipient, args.port, args.resume, args.confirm_replace_new_data)
    except (OperationError, OSError, ValueError) as error:
        if isinstance(error, OperationError) and error.diagnostics and installation.meta.is_dir():
            log = installation.meta / 'last-error.log'
            log.write_text(error.diagnostics[-65536:])
            os.chmod(log, 0o600)
        print('Gäld adoption: ' + str(error), file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
