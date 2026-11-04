import importlib.util
import io
import json
import os
from pathlib import Path
import shutil
import subprocess
import tarfile
import tempfile
import unittest
from unittest.mock import patch

MODULE = Path(__file__).resolve().parents[2] / 'scripts/distribution/manage.py'
spec = importlib.util.spec_from_file_location('manager', MODULE)
manager = importlib.util.module_from_spec(spec)
spec.loader.exec_module(manager)


class ManagerTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        self.installation = manager.Installation(self.root)
        self.installation.meta.mkdir()
        self.installation.shared.mkdir()
        (self.installation.shared / '.env').write_text('APP_KEY=base64:' + 'A' * 43 + '=\nDB_DATABASE=test\nDB_USERNAME=test\nDB_PASSWORD=test\nDB_HOST=localhost\n')
        (self.installation.shared / 'storage/app').mkdir(parents=True)
        (self.installation.shared / 'storage/app/invoice.pdf').write_bytes(b'%PDF preserved original')
        self.installation.state = {'mode': 'native', 'version': '3.9.0', 'status': 'ready',
                                   'backup_recipient': 'age1' + 'a' * 58, 'ee_version': None}
        self.operations = []

    def release(self, version='3.10.0'):
        return {'schema': 1, 'version': version, 'commit': 'a' * 40, 'edition': 'ce',
                'minimum_version': '3.8.28', 'allowed_from': ['3.8.28', '3.9.0'], 'boundary_contract': '1.0.0', 'migration_policy': 'forward-only', 'postgres_major': 16, 'redis_major': 7,
                'compatible_ee_versions': [], 'images': {'app': 'ghcr.io/scanix/gaeld@sha256:' + 'a' * 64,
                                                       'web': 'ghcr.io/scanix/gaeld-web@sha256:' + 'b' * 64,
                                                       'postgres': 'docker.io/library/postgres@sha256:' + 'c' * 64,
                                                       'redis': 'docker.io/library/redis@sha256:' + 'd' * 64},
                'source': {'url': 'https://example.test/source.tar.gz', 'sha256': 'c' * 64},
                'provenance': {'url': 'https://example.test/provenance.json', 'sha256': 'd' * 64},
                'sbom': {'url': 'https://example.test/sbom.json', 'sha256': 'e' * 64}}

    def test_rejects_floating_targets(self):
        for target in ['latest', 'stable', 'v3.9.0', '../3.9.0', '3.9', '3.9.0;id']:
            with self.subTest(target=target), self.assertRaises(manager.OperationError):
                manager.version(target)

    def test_release_candidates_can_progress_to_the_stable_release(self):
        self.assertLess(manager.version('3.9.0-rc.1'), manager.version('3.9.0-rc.2'))
        self.assertLess(manager.version('3.9.0-rc.2'), manager.version('3.9.0'))

    def test_rejects_archive_traversal_links_and_duplicate_paths(self):
        for kind in ['path', 'link', 'duplicate']:
            archive = self.root / (kind + '.tar')
            with tarfile.open(archive, 'w') as tar:
                entry = tarfile.TarInfo('../escape' if kind == 'path' else 'file')
                if kind == 'link':
                    entry.type = tarfile.SYMTYPE
                    entry.linkname = '/etc/passwd'
                tar.addfile(entry)
                if kind == 'duplicate':
                    tar.addfile(entry)
            with self.subTest(kind=kind), self.assertRaises(manager.OperationError):
                manager.extract_tar(archive, self.root / 'extracted')
        self.assertFalse((self.root.parent / 'escape').exists())

    def test_rejects_concurrent_operations(self):
        with self.installation.lock():
            other = manager.Installation(self.root)
            with self.assertRaises(manager.OperationError), other.lock():
                self.fail('Second operator acquired the lock')

    def test_rehearsal_policy_is_explicit_and_pinned_for_recovery(self):
        policy = {'schema': 1, 'channel': 'rehearsal', 'release_origin': 'https://localhost:4443',
                  'release_key_sha256': 'a' * 64,
                  'images': {role: 'registry.gaeld.test:5000/' + role for role in manager.OFFICIAL_IMAGES}}
        path = self.root / 'operator-policy.json'
        manager.write_json(path, policy)
        self.assertIsNone(self.installation.release_policy())
        self.installation.policy_path = path
        self.assertEqual(policy, self.installation.release_policy())
        # Operators may pass the same original file on resume. Preserve its bytes,
        # including formatting/newlines, instead of pinning a rewritten document.
        path.write_text(json.dumps(policy) + '\n')
        self.installation.pin_release_policy()
        self.assertEqual(path.read_bytes(), (self.installation.meta / 'distribution-policy.json').read_bytes())
        self.installation.policy_path = path
        self.assertEqual(policy, self.installation.release_policy())
        policy['images']['app'] = 'another.test/app'
        manager.write_json(path, policy)
        with self.assertRaisesRegex(manager.OperationError, 'policy changed'):
            self.installation.release_policy()

    def test_rehearsal_origin_is_enforced_before_network_io(self):
        with patch.object(manager.urllib.request, 'urlopen') as request:
            with self.assertRaisesRegex(manager.OperationError, 'origin'):
                manager.download('https://other.test/release.json', self.root / 'out', 'https://localhost:4443')
            request.assert_not_called()

    @unittest.skipUnless(shutil.which('openssl'), 'OpenSSL is required')
    def test_real_signature_does_not_override_registry_role_or_test_channel(self):
        private = self.root / 'private.pem'
        public = self.root / 'release.pub'
        subprocess.run(['openssl', 'genpkey', '-algorithm', 'ED25519', '-out', str(private)], check=True, capture_output=True)
        subprocess.run(['openssl', 'pkey', '-in', str(private), '-pubout', '-out', str(public)], check=True, capture_output=True)
        release = self.release('3.9.0-rc.1')
        def sign():
            manager.write_json(self.root / 'release.json', release)
            subprocess.run(['openssl', 'pkeyutl', '-sign', '-inkey', str(private), '-rawin', '-in',
                            str(self.root / 'release.json'), '-out', str(self.root / 'release.json.sig')], check=True)
        release['images']['app'] = release['images']['web']
        sign()
        with self.assertRaisesRegex(manager.OperationError, 'trust policy'):
            manager.verify_release(self.root, release['version'], public)
        policy = {'schema': 1, 'channel': 'rehearsal', 'release_origin': 'https://localhost:4443',
                  'release_key_sha256': manager.digest(public),
                  'images': {role: 'registry.gaeld.test:5000/' + role for role in manager.OFFICIAL_IMAGES}}
        release['images'] = {role: repository + '@sha256:' + 'a' * 64 for role, repository in policy['images'].items()}
        release['channel'] = 'rehearsal'
        sign()
        with self.assertRaisesRegex(manager.OperationError, 'independently supplied'):
            manager.verify_release(self.root, release['version'], public)
        self.assertEqual(release, manager.verify_release(self.root, release['version'], public, policy))
        policy['release_key_sha256'] = 'b' * 64
        with self.assertRaisesRegex(manager.OperationError, 'test key'):
            manager.verify_release(self.root, release['version'], public, policy)

    def test_rejects_insecure_download_without_request(self):
        with patch.object(manager.urllib.request, 'urlopen') as request:
            with self.assertRaises(manager.OperationError):
                manager.download('http://example.test/release.json', self.root / 'out')
            request.assert_not_called()

    @unittest.skipUnless(shutil.which('openssl'), 'OpenSSL is required')
    def test_actual_ed25519_signature_checks_manifest_version_and_tampering(self):
        private = self.root / 'private.pem'
        public = self.root / 'release.pub'
        subprocess.run(['openssl', 'genpkey', '-algorithm', 'ED25519', '-out', str(private)], check=True, capture_output=True)
        subprocess.run(['openssl', 'pkey', '-in', str(private), '-pubout', '-out', str(public)], check=True, capture_output=True)
        manager.write_json(self.root / 'release.json', self.release())
        subprocess.run(['openssl', 'pkeyutl', '-sign', '-inkey', str(private), '-rawin', '-in',
                        str(self.root / 'release.json'), '-out', str(self.root / 'release.json.sig')], check=True)
        self.assertEqual('3.10.0', manager.verify_release(self.root, '3.10.0', public)['version'])
        with self.assertRaises(manager.OperationError):
            manager.verify_release(self.root, '3.11.0', public)
        (self.root / 'release.json').write_text(json.dumps(self.release('3.11.0')))
        with self.assertRaises(manager.OperationError):
            manager.verify_release(self.root, '3.11.0', public)

    def instrument_update(self, fail=None):
        self.installation.verify_source_tree = lambda: None
        def step(name, result=None):
            def execute(*args, **kwargs):
                self.operations.append(name)
                if name == fail:
                    raise manager.OperationError('Injected ' + name + ' failure')
                return result
            return execute
        self.installation.stage = step('verify', self.release())
        self.installation.stop_writers = step('drain')
        self.installation.backup_stopped = step('backup', {'path': 'encrypted.age'})
        self.installation.configure_runtime = step('configure')
        self.installation.fix_permissions = step('permissions')
        self.installation.migrate = step('migrate')
        self.installation.activate = step('activate')
        self.installation.start = step('start')
        self.installation.stop_runtime = step('stop')

    def test_update_requires_backup_before_migration_and_preserves_key(self):
        before = (self.installation.shared / '.env').read_bytes()
        self.instrument_update()
        self.installation.update('3.10.0', 'https://example.test', 'trust.pub')
        self.assertLess(self.operations.index('backup'), self.operations.index('migrate'))
        self.assertLess(self.operations.index('drain'), self.operations.index('backup'))
        self.assertEqual('ready', manager.read_json(self.installation.meta / 'state.json')['status'])
        self.assertEqual('3.10.0', self.installation.state['version'])
        self.assertEqual(before, (self.installation.shared / '.env').read_bytes())

    def test_backup_failure_never_migrates_or_reopens_application(self):
        self.instrument_update(fail='backup')
        with self.assertRaises(manager.OperationError):
            self.installation.update('3.10.0', 'https://example.test', 'trust.pub')
        self.assertNotIn('migrate', self.operations)
        self.assertNotIn('start', self.operations)
        self.assertEqual('stop', self.operations[-1])
        self.assertEqual('failed', self.installation.state['status'])
        self.assertEqual('3.9.0', self.installation.state['version'])

    def test_failed_migration_is_recorded_and_never_automatically_rolled_back(self):
        self.instrument_update(fail='migrate')
        with self.assertRaises(manager.OperationError):
            self.installation.update('3.10.0', 'https://example.test', 'trust.pub')
        self.assertNotIn('activate', self.operations)
        self.assertNotIn('start', self.operations)
        self.assertEqual('3.10.0', self.installation.state['target'])
        with self.assertRaises(manager.OperationError):
            self.installation.resume()

    def test_health_failure_stops_all_services(self):
        self.instrument_update(fail='start')
        with self.assertRaises(manager.OperationError):
            self.installation.update('3.10.0', 'https://example.test', 'trust.pub')
        self.assertEqual('stop', self.operations[-1])
        self.assertEqual('failed', self.installation.state['status'])

    def test_backup_failure_resume_starts_services_before_health_checks(self):
        self.installation.state['status'] = 'failed'
        with patch.object(self.installation, 'start') as start, patch.object(self.installation, 'artisan') as artisan:
            self.installation.resume()
            start.assert_called_once_with()
            artisan.assert_not_called()
        self.assertEqual('ready', self.installation.state['status'])

    def test_resume_health_failure_keeps_the_installation_stopped(self):
        self.installation.state['status'] = 'failed'
        with patch.object(self.installation, 'start', side_effect=manager.OperationError('Unhealthy')), patch.object(self.installation, 'stop_runtime') as stop:
            with self.assertRaises(manager.OperationError):
                self.installation.resume()
            stop.assert_called_once_with()
        self.assertEqual('failed', self.installation.state['status'])

    def test_ce_migrations_do_not_require_an_absent_ee_plugin(self):
        with patch.object(self.installation, 'artisan') as artisan:
            self.installation.migrate('3.9.0-rc.1')
        self.assertEqual(('migrate', '--force', '--no-interaction'), artisan.call_args_list[0].args)
        self.assertFalse(any(call.args[0] == 'edition:verify' for call in artisan.call_args_list))

    def test_ee_migration_verifies_the_exact_pair_before_touching_the_database(self):
        self.installation.state['ee_version'] = '2.9.31'
        with patch.object(self.installation, 'artisan', side_effect=manager.OperationError('Pair rejected')) as artisan:
            with self.assertRaises(manager.OperationError):
                self.installation.migrate('3.9.0-rc.1')
        artisan.assert_called_once_with('edition:verify', '--ee-version=2.9.31', target='3.9.0-rc.1')

    def test_ce_migration_refuses_an_enabled_private_plugin_configuration(self):
        with (self.installation.shared / '.env').open('a') as environment:
            environment.write('PLUGINS_ENABLED=true\n')
        with patch.object(self.installation, 'artisan') as artisan:
            with self.assertRaisesRegex(manager.OperationError, 'PLUGINS_ENABLED'):
                self.installation.migrate('3.9.0-rc.1')
        artisan.assert_not_called()

    def test_restore_never_connects_to_system_or_template_databases(self):
        for name in ('postgres', 'template0', 'template1'):
            (self.installation.shared / '.env').write_text('DB_DATABASE=' + name + '\nDB_USERNAME=test\nDB_PASSWORD=test\nDB_HOST=localhost\n')
            with self.subTest(database=name), patch.object(self.installation, 'runner') as runner:
                with self.assertRaisesRegex(manager.OperationError, 'system or template'):
                    self.installation.postgres('psql')
                runner.assert_not_called()

    def test_native_empty_host_accepts_explicit_not_found_with_exit_one(self):
        completed = subprocess.CompletedProcess([], 1, stdout='LoadState=not-found\nFragmentPath=\nDropInPaths=\n', stderr='')
        with patch.object(manager.subprocess, 'run', return_value=completed) as process:
            self.installation.preflight_native_services()
            self.assertEqual(4, process.call_count)

    def test_restore_refuses_when_writers_are_not_stopped(self):
        with patch.object(self.installation, 'postgres') as postgres:
            with self.assertRaisesRegex(manager.OperationError, 'Stop all writers'):
                self.installation.database('pg_restore', archive=self.root / 'dump')
            postgres.assert_not_called()

    def test_native_install_refuses_foreign_units_before_secrets_or_source_changes(self):
        fresh = manager.Installation(self.root / 'new-installation')
        unit = self.root / 'foreign.service'
        unit.write_text('WorkingDirectory=/srv/other-gaeld/current\n')
        fresh.runner = lambda *args, **kwargs: 'LoadState=loaded\nFragmentPath=' + str(unit) + '\nDropInPaths=\n'
        with patch.object(manager.platform, 'system', return_value='Linux'), patch.object(manager.os, 'geteuid', return_value=0):
            with self.assertRaisesRegex(manager.OperationError, 'another installation'):
                fresh.install('3.9.0-rc.2', 'https://example.test', '', 'native', '', 'http://localhost:8080', 8080)
        self.assertFalse(fresh.meta.exists())
        self.assertFalse(fresh.state)

    def test_native_cli_collision_is_checked_before_prompt_or_temporary_secret(self):
        with patch.object(manager.sys, 'argv', ['gaeld', 'install', '--mode', 'native', '--root', str(self.root / 'new')]), \
             patch.object(manager.platform, 'system', return_value='Linux'), patch.object(manager.os, 'geteuid', return_value=0), \
             patch.object(manager.Installation, 'preflight_native_services', side_effect=manager.OperationError('Foreign service')), \
             patch('builtins.input') as prompt, patch.object(manager.tempfile, 'mkstemp') as secret:
            self.assertEqual(1, manager.main())
            prompt.assert_not_called()
            secret.assert_not_called()
        self.assertFalse((self.root / 'new').exists())

    def test_native_preflight_accepts_only_attributable_units_without_overrides(self):
        def existing_unit(command, **kwargs):
            name = command[2].split('.')[0]
            unit = self.root / (name + '.service')
            service = name.removeprefix('gaeld-')
            lines = {'fpm': 'ExecStart=/usr/sbin/php-fpm8.4 --fpm-config ' + str(self.installation.root) + '/.gaeld/fpm.conf --nodaemonize',
                     'web': 'ExecStart=/usr/sbin/nginx -c ' + str(self.installation.root) + '/.gaeld/nginx-native.conf',
                     'horizon': 'WorkingDirectory=' + str(self.installation.root) + '/current',
                     'scheduler': 'WorkingDirectory=' + str(self.installation.root) + '/current'}
            unit.write_text(lines[service] + '\n')
            return 'LoadState=loaded\nFragmentPath=' + str(unit) + '\nDropInPaths=\n'
        self.installation.runner = existing_unit
        self.installation.preflight_native_services()
        self.installation.runner = lambda command, **kwargs: existing_unit(command, **kwargs) + 'DropInPaths=/etc/systemd/system/foreign.conf\n'
        with self.assertRaises(manager.OperationError):
            self.installation.preflight_native_services()

    def test_restore_requires_explicit_confirmation_before_any_io(self):
        with self.assertRaises(manager.OperationError):
            self.installation.restore(self.root / 'missing', self.root / 'missing-key', False)
        self.assertEqual('ready', self.installation.state['status'])

    def test_local_source_edits_block_updates_without_touching_data(self):
        directory = self.installation.release_dir()
        directory.mkdir(parents=True)
        source = directory / 'artisan'
        source.write_text('original')
        manager.write_json(self.installation.meta / 'releases/3.9.0.files.json', self.installation.source_inventory(directory))
        self.installation.verify_source_tree()
        source.write_text('local modification')
        with self.assertRaises(manager.OperationError):
            self.installation.update('3.10.0', 'https://example.test', 'trust.pub')
        self.assertEqual('ready', self.installation.state['status'])

    def test_failed_database_restore_never_activates_or_reopens_services(self):
        self.installation.state['status'] = 'failed'
        self.installation.verify_backup = lambda *args: ({'version': '3.9.0', 'ee_version': None}, self.root)
        self.installation.stop_runtime = lambda: self.operations.append('stop')
        self.installation.activate = lambda target: self.operations.append('activate')
        self.installation.start = lambda target: self.operations.append('start')
        def fail_database(*args, **kwargs):
            raise manager.OperationError('Injected pg_restore failure')
        self.installation.database = fail_database
        before = (self.installation.shared / '.env').read_bytes()
        with patch.object(manager, 'verify_release', return_value=self.release('3.9.0')):
            with self.assertRaises(manager.OperationError):
                self.installation.restore(self.root / 'backup.age', self.root / 'identity', True)
        self.assertEqual(['stop', 'stop'], self.operations)
        self.assertEqual('restore-failed', self.installation.state['status'])
        self.assertEqual(before, (self.installation.shared / '.env').read_bytes())

    @unittest.skipUnless(shutil.which('age') and shutil.which('age-keygen'), 'age is required for real encryption round-trip')
    def test_real_encrypted_backup_restores_manifest_database_and_documents(self):
        identity = self.root.parent / (self.root.name + '-recovery.agekey')
        self.addCleanup(lambda: identity.unlink(missing_ok=True))
        subprocess.run(['age-keygen', '-o', str(identity)], check=True, capture_output=True)
        recipient = subprocess.run(['age-keygen', '-y', str(identity)], check=True, capture_output=True, text=True).stdout.strip()
        self.installation.state.update(status='stopped', backup_recipient=recipient)
        self.installation.identity_file = identity
        self.installation.database = lambda command, output=None, **kwargs: output.write(b'PGDMP real fixture')
        self.installation.artisan = lambda *args, **kwargs: json.dumps({'schema': 1, 'connections': {}})
        backup = self.installation.backup_stopped()
        self.assertTrue(Path(backup['path']).read_bytes().startswith(b'age-encryption.org/v1'))
        self.assertNotIn(b'APP_KEY', Path(backup['path']).read_bytes())
        verify = self.root / 'verify'
        verify.mkdir()
        manifest, content = self.installation.verify_backup(Path(backup['path']), identity, verify)
        self.assertEqual('3.9.0', manifest['version'])
        self.assertEqual(b'PGDMP real fixture', (content / 'database.dump').read_bytes())
        self.assertEqual(b'%PDF preserved original', (content / 'files/invoice.pdf').read_bytes())
        tampered = bytearray(Path(backup['path']).read_bytes())
        tampered[-1] ^= 1
        Path(backup['path']).write_bytes(tampered)
        verify_bad = self.root / 'bad'
        verify_bad.mkdir()
        with self.assertRaises(manager.OperationError):
            self.installation.verify_backup(Path(backup['path']), identity, verify_bad)


if __name__ == '__main__':
    unittest.main()
