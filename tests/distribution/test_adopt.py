import base64
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'scripts/distribution'))
import adopt
import manage


class SailAdoptionTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        self.source = self.root / 'legacy'
        self.source.mkdir()
        (self.source / '.gitignore').write_text('.env\nstorage/\n')
        (self.source / 'docker-compose.yml').write_text('services: {}\n')
        self.app_key = 'base64:' + base64.b64encode(b'k' * 32).decode()
        (self.source / '.env').write_text('APP_NAME=Gaeld\nAPP_KEY=' + self.app_key + '\nDB_CONNECTION=pgsql\nDB_HOST=pgsql\nDB_USERNAME=legacy\nDB_DATABASE=legacy\nDB_PASSWORD=test-secret\nREDIS_HOST=redis\n')
        (self.source / 'storage/app').mkdir(parents=True)
        (self.source / 'storage/app/original.pdf').write_bytes(b'%PDF original retained')
        self.git('init', '-q')
        self.git('config', 'user.name', 'Test')
        self.git('config', 'user.email', 'fixture@example.test')
        self.git('add', '.')
        self.git('commit', '-qm', 'Baseline')
        self.git('tag', 'v3.8.28')
        self.calls = []
        self.exit_code = 0
        self.legacy = adopt.LegacySail(self.source, runner=self.runner)

    def git(self, *args):
        return subprocess.run(['git', *args], cwd=self.source, check=True, capture_output=True, text=True).stdout

    def runner(self, command, **kwargs):
        self.calls.append(command)
        if command[0] in ('git', 'age', 'age-keygen', 'openssl'):
            return manage.run(command, **kwargs)
        if command[:2] == ['docker', 'inspect']:
            return json.dumps({'Running': False, 'OOMKilled': False, 'ExitCode': self.exit_code})
        if 'config' in command:
            return json.dumps({'name': 'legacy', 'services': {'laravel.test': {'volumes': [{'type': 'bind', 'source': str(self.source), 'target': '/var/www/html'}]}, 'worker': {}, 'pgsql': {}, 'redis': {}}})
        if 'ps' in command:
            return 'legacy-container\n'
        if 'postgres' in command:
            return 'postgres (PostgreSQL) 16.4'
        if 'redis-server' in command:
            return 'Redis server v=7.4.0'
        if 'pg_dump' in command:
            kwargs['output'].write(b'PGDMP fixture-only database')
            return None
        if '-r' in command:
            return json.dumps({'schema': 1, 'created_at_ms': 1000000000000, 'connections': {}})
        return '' if kwargs.get('capture') else None

    def test_preflight_requires_clean_exact_baseline_without_changing_checkout(self):
        initial = (self.source / '.env').read_bytes()
        origin = self.legacy.preflight()
        self.assertEqual('3.8.28', origin['source_version'])
        self.assertEqual(initial, (self.source / '.env').read_bytes())
        (self.source / 'docker-compose.yml').write_text('locally modified')
        with self.assertRaisesRegex(manage.OperationError, 'modified'):
            self.legacy.preflight()

    def test_preflight_refuses_other_tag_and_never_generates_a_key(self):
        (self.source / 'new.txt').write_text('new commit')
        self.git('add', 'new.txt')
        self.git('commit', '-qm', 'Later version')
        with self.assertRaisesRegex(manage.OperationError, '3.8.28'):
            self.legacy.preflight()
        self.git('checkout', '-q', 'v3.8.28')
        (self.source / '.env').write_text('APP_KEY=\n')
        with self.assertRaisesRegex(manage.OperationError, 'APP_KEY'):
            self.legacy.preflight()
        self.assertEqual('APP_KEY=\n', (self.source / '.env').read_text())

    def test_killed_legacy_writer_blocks_snapshot(self):
        self.legacy.preflight()
        self.exit_code = 137
        with self.assertRaisesRegex(manage.OperationError, 'drain'):
            self.legacy.stop_writers()
        self.assertFalse(self.legacy.stopped)
        self.assertFalse(any('pg_dump' in call for call in self.calls))

    @unittest.skipUnless(shutil.which('age') and shutil.which('age-keygen'), 'Real age tools required')
    def test_adoption_uses_verified_encrypted_backup_new_volumes_and_original_key(self):
        identity = self.root / 'identity.txt'
        manage.run(['age-keygen', '-o', str(identity)])
        recipient = manage.run(['age-keygen', '-y', str(identity)], capture=True).strip()
        public = self.root / 'release.pub'
        public.write_text('fixture trust key')
        destination = self.root / 'managed'
        installation = StubInstallation(destination, runner=self.runner)
        installation.identity_file = identity
        with installation.lock():
            adopt.SailAdoption(installation, self.legacy).adopt('3.9.0', 'https://example.test', public, recipient)
        self.assertEqual('ready', installation.state['status'])
        self.assertNotEqual('legacy', installation.state['project'])
        self.assertTrue(installation.state['adoption']['legacy_stopped'])
        self.assertEqual(self.app_key, manage.env_values(installation.shared / '.env')['APP_KEY'])
        self.assertEqual(b'%PDF original retained', (installation.shared / 'storage/app/original.pdf').read_bytes())
        self.assertEqual(b'%PDF original retained', (self.source / 'storage/app/original.pdf').read_bytes())
        self.assertEqual('', self.git('status', '--porcelain'))
        self.assertEqual(['restore', 'down', 'restore-redis', 'migrate', 'activate', 'health'], installation.transitions)
        backup = installation.state['backup']
        self.assertIn('verified_at', backup)
        self.assertTrue(Path(backup['path']).read_bytes().startswith(b'age-encryption.org/v1'))
        self.assertEqual('3.8.28', backup['version'])
        self.assertFalse(any('rm' in call or 'down' in call and '--volumes' in call for call in self.calls))

    @unittest.skipUnless(shutil.which('age') and shutil.which('age-keygen'), 'Real age tools required')
    def test_migration_failure_leaves_both_copies_stopped_and_preserves_recovery_backup(self):
        identity = self.root / 'identity.txt'
        manage.run(['age-keygen', '-o', str(identity)])
        recipient = manage.run(['age-keygen', '-y', str(identity)], capture=True).strip()
        public = self.root / 'release.pub'
        public.write_text('fixture trust key')
        installation = StubInstallation(self.root / 'managed', runner=self.runner)
        installation.identity_file = identity
        installation.reject_migration = True
        with installation.lock(), self.assertRaisesRegex(manage.OperationError, 'fixture migration'):
            adopt.SailAdoption(installation, self.legacy).adopt('3.9.0', 'https://example.test', public, recipient)
        self.assertEqual('adoption-failed', installation.state['status'])
        self.assertTrue(self.legacy.stopped)
        self.assertNotIn('health', installation.transitions)
        self.assertTrue(Path(installation.state['backup']['path']).is_file())
        self.assertEqual(self.app_key, manage.env_values(self.source / '.env')['APP_KEY'])
        first_project = installation.state['project']
        first_backup = installation.state['backup']['path']
        (self.source / 'storage/app/new-after-failure.pdf').write_bytes(b'%PDF later source evidence')
        installation.reject_migration = False
        with installation.lock(), patch.object(adopt, 'verify_release', return_value=installation.release):
            adopt.SailAdoption(installation, self.legacy).adopt('3.9.0', 'https://example.test', public, recipient, resume=True)
        self.assertEqual('ready', installation.state['status'])
        self.assertNotEqual(first_project, installation.state['project'])
        self.assertIn(first_project, installation.state['abandoned_projects'])
        self.assertTrue(Path(first_backup).is_file())
        self.assertNotEqual(first_backup, installation.state['backup']['path'])
        self.assertEqual(b'%PDF later source evidence', (installation.shared / 'storage/app/new-after-failure.pdf').read_bytes())

    @unittest.skipUnless(shutil.which('age') and shutil.which('age-keygen'), 'Real age tools required')
    def test_retry_after_possible_new_writes_requires_explicit_reconciliation_confirmation(self):
        identity = self.root / 'identity.txt'
        manage.run(['age-keygen', '-o', str(identity)])
        recipient = manage.run(['age-keygen', '-y', str(identity)], capture=True).strip()
        public = self.root / 'release.pub'
        public.write_text('fixture trust key')
        installation = StubInstallation(self.root / 'managed', runner=self.runner)
        installation.identity_file = identity
        installation.reject_health = True
        with installation.lock(), self.assertRaisesRegex(manage.OperationError, 'fixture health'):
            adopt.SailAdoption(installation, self.legacy).adopt('3.9.0', 'https://example.test', public, recipient)
        self.assertTrue(installation.state['adoption_may_have_writes'])
        with installation.lock(), self.assertRaisesRegex(manage.OperationError, 'accepted writes'):
            adopt.SailAdoption(installation, self.legacy).adopt('3.9.0', 'https://example.test', public, recipient, resume=True)
        self.assertNotIn('abandoned_projects', installation.state)


class StubInstallation(manage.Installation):
    def __init__(self, root, runner):
        super().__init__(root, runner)
        self.transitions = []
        self.reject_migration = False
        self.reject_health = False

    def stage(self, target, release_url, trust_key):
        directory = self.release_dir(target)
        directory.mkdir(parents=True)
        self.state['trust_key_sha256'] = manage.digest(trust_key)
        shutil.copy2(trust_key, self.meta / 'release.pub')
        self.release = {'version': target, 'images': {'app': 'app@sha256:fixture', 'web': 'web@sha256:fixture', 'postgres': 'postgres@sha256:fixture', 'redis': 'redis@sha256:fixture'}}
        return self.release

    def database(self, command, **kwargs):
        self.transitions.append('restore')
        assert Path(kwargs['archive']).read_bytes().startswith(b'PGDMP')

    def artisan(self, *arguments, **kwargs):
        self.transitions.append('restore-redis' if arguments[0] == 'gaeld:redis-snapshot' else 'down')

    def fix_permissions(self, target):
        pass

    def migrate(self, target):
        self.transitions.append('migrate')
        if self.reject_migration:
            raise manage.OperationError('fixture migration failure')

    def activate(self, target):
        self.transitions.append('activate')

    def start(self, target=None):
        self.transitions.append('health')
        if self.reject_health:
            raise manage.OperationError('fixture health failure')


if __name__ == '__main__':
    unittest.main()
