"""Opt-in destructive round-trip restricted to the isolated testing_r1 database."""
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('manager', Path(__file__).resolve().parents[2] / 'scripts/distribution/manage.py')
manager = importlib.util.module_from_spec(spec)
spec.loader.exec_module(manager)


@unittest.skipUnless(os.environ.get('GAELD_R1_RESTORE_TEST') == 'testing_r1', 'Requires the isolated testing_r1 database')
class PostgresRecoveryTest(unittest.TestCase):
    def test_database_queues_documents_and_application_key_survive_encrypted_restore(self):
        self.assertEqual('testing_r1', os.environ.get('DB_DATABASE'))
        with tempfile.TemporaryDirectory(prefix='gaeld-real-restore-') as temporary:
            root = Path(temporary)
            installation = manager.Installation(root / 'installation')
            installation.meta.mkdir(parents=True)
            for child in ('app/private', 'framework/cache/data', 'framework/views', 'framework/sessions', 'logs'):
                (installation.shared / 'storage' / child).mkdir(parents=True, exist_ok=True)
            (installation.shared / 'secrets').mkdir()
            document = installation.shared / 'storage/app/private/original.pdf'
            document.write_bytes(b'%PDF Original accounting evidence')
            (installation.shared / 'secrets/signer.pem').write_text('test signer material')
            prefix = 'r1-recovery-' + os.urandom(8).hex() + '-'
            environment = {'APP_ENV': 'testing', 'APP_KEY': 'base64:' + 'A' * 43 + '=', 'APP_DEBUG': 'false',
                           'DB_CONNECTION': 'pgsql', 'DB_HOST': 'pgsql', 'DB_PORT': '5432', 'DB_DATABASE': 'testing_r1',
                           'DB_USERNAME': 'gaeld', 'DB_PASSWORD': 'password', 'REDIS_HOST': 'redis',
                           'REDIS_PREFIX': prefix, 'HORIZON_PREFIX': prefix + 'horizon:', 'CACHE_STORE': 'array',
                           'QUEUE_CONNECTION': 'redis', 'SESSION_DRIVER': 'array', 'PLUGINS_ENABLED': 'false',
                           'LARAVEL_STORAGE_PATH': str(installation.shared / 'storage'),
                           'APP_CONFIG_CACHE': str(root / 'config.php'), 'APP_ROUTES_CACHE': str(root / 'routes.php'),
                           'APP_EVENTS_CACHE': str(root / 'events.php')}
            (installation.shared / '.env').write_text(''.join(f'{k}={v}\n' for k, v in environment.items()))
            original_environment = (installation.shared / '.env').read_bytes()
            key = root / 'recovery.key'
            subprocess.run(['age-keygen', '-o', str(key)], check=True, capture_output=True)
            recipient = subprocess.run(['age-keygen', '-y', str(key)], check=True, text=True, capture_output=True).stdout.strip()
            installation.identity_file = key
            installation.state = {'mode': 'native', 'version': '3.9.0', 'status': 'stopped', 'backup_recipient': recipient, 'ee_version': None}
            app = Path(__file__).resolve().parents[2]

            def artisan(*arguments, target=None, **kwargs):
                return manager.run(['php', str(app / 'artisan'), *arguments], cwd=app, env=environment, **kwargs)

            def sql(statement):
                return subprocess.run(['psql', '-h', 'pgsql', '-U', 'gaeld', '-d', 'testing_r1', '-At', '-v', 'ON_ERROR_STOP=1', '-c', statement],
                                      env={**os.environ, 'PGPASSWORD': 'password'}, check=True, text=True, capture_output=True).stdout.strip()

            installation.artisan = artisan
            installation.stop_runtime = lambda: None
            installation.configure_runtime = lambda release: None
            installation.fix_permissions = lambda target: None
            installation.activate = lambda target: None
            installation.start = lambda target=None: artisan('up')
            sql('CREATE TABLE r1_recovery_probe (id integer PRIMARY KEY, evidence text NOT NULL)')
            try:
                sql("INSERT INTO r1_recovery_probe VALUES (1, 'original')")
                artisan('down')
                backup = installation.backup_stopped()
                self.assertIn('verified_at', backup)
                sql("UPDATE r1_recovery_probe SET evidence = 'changed'")
                document.write_bytes(b'damaged')
                (installation.shared / 'secrets/signer.pem').write_text('wrong signer')
                with patch.object(manager, 'verify_release', return_value={'version': '3.9.0'}):
                    installation.restore(Path(backup['path']), key, True)
                self.assertEqual('original', sql('SELECT evidence FROM r1_recovery_probe WHERE id = 1'))
                self.assertEqual(b'%PDF Original accounting evidence', document.read_bytes())
                self.assertEqual('test signer material', (installation.shared / 'secrets/signer.pem').read_text())
                self.assertEqual(original_environment, (installation.shared / '.env').read_bytes())
                self.assertEqual('ready', installation.state['status'])
            finally:
                sql('DROP TABLE IF EXISTS r1_recovery_probe')
                artisan('up')


if __name__ == '__main__':
    unittest.main()
