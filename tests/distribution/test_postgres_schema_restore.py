"""Real PostgreSQL 16 restore tests; each test owns a new randomly named database.

Opt in with GAELD_PG_RECOVERY_TEST=isolated and PGTEST_HOST/PORT/USER/PASSWORD.
The account must be able to create the disposable databases. No existing database
is restored or reset by this module.
"""
import importlib.util
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('manager', Path(__file__).resolve().parents[2] / 'scripts/distribution/manage.py')
manager = importlib.util.module_from_spec(spec)
spec.loader.exec_module(manager)


@unittest.skipUnless(os.environ.get('GAELD_PG_RECOVERY_TEST') == 'isolated', 'Requires an explicitly isolated PostgreSQL 16 server')
class PostgresSchemaRestoreTest(unittest.TestCase):
    def setUp(self):
        self.host = os.environ['PGTEST_HOST']
        self.port = os.environ.get('PGTEST_PORT', '5432')
        self.user = os.environ['PGTEST_USER']
        self.password = os.environ['PGTEST_PASSWORD']
        self.name = 'gaeld_restore_test_' + secrets.token_hex(6)
        self.connection = ['--host=' + self.host, '--port=' + self.port, '--username=' + self.user]
        self.env = {**os.environ, 'PGPASSWORD': self.password}
        self.temp = tempfile.TemporaryDirectory(prefix='gaeld-pg-schema-')
        self.root = Path(self.temp.name)
        self.process(['createdb', *self.connection, self.name])
        self.addCleanup(self.cleanup_database)
        self.installation = manager.Installation(self.root / 'installation')
        self.installation.meta.mkdir(parents=True)
        self.installation.shared.mkdir()
        self.environment = {'DB_HOST': self.host, 'DB_PORT': self.port, 'DB_DATABASE': self.name,
                            'DB_USERNAME': self.user, 'DB_PASSWORD': self.password}
        self.write_environment()
        self.installation.state = {'mode': 'native', 'status': 'stopped', 'version': '3.9.0-rc.2'}
        self.sql("CREATE TABLE journal (id integer PRIMARY KEY, evidence text NOT NULL); INSERT INTO journal VALUES (1, 'original');")
        self.dump = self.root / 'original.dump'
        self.process(['pg_dump', *self.connection, '--dbname=' + self.name, '--format=custom', '--no-owner', '--no-acl', '--file=' + str(self.dump)])

    def process(self, command):
        result = subprocess.run(command, env=self.env, text=True, capture_output=True)
        if result.returncode:
            self.fail(Path(command[0]).name + ' failed: ' + result.stderr)
        return result.stdout.strip()

    def sql(self, statement):
        return self.process(['psql', *self.connection, '--dbname=' + self.name, '-At', '--no-psqlrc', '-v', 'ON_ERROR_STOP=1', '-c', statement])

    def write_environment(self):
        (self.installation.shared / '.env').write_text(''.join(f'{key}={value}\n' for key, value in self.environment.items()))

    def cleanup_database(self):
        self.process(['dropdb', *self.connection, self.name])
        self.temp.cleanup()

    def add_new_schema_objects(self):
        self.sql("CREATE TABLE newer_fk (id integer PRIMARY KEY, journal_id integer REFERENCES journal(id)); "
                 "INSERT INTO newer_fk VALUES (1, 1); CREATE TABLE newer_independent (id integer); "
                 "INSERT INTO newer_independent VALUES (9); UPDATE journal SET evidence = 'newer-data';")

    def test_restore_removes_new_dependent_and_independent_tables(self):
        self.add_new_schema_objects()
        self.installation.database('pg_restore', archive=self.dump)
        self.assertEqual('original', self.sql('SELECT evidence FROM journal'))
        self.assertEqual('t|t', self.sql("SELECT to_regclass('public.newer_fk') IS NULL, to_regclass('public.newer_independent') IS NULL"))
        self.assertEqual(self.user, self.sql("SELECT pg_get_userbyid(nspowner) FROM pg_namespace WHERE nspname='public'"))

    def test_sql_error_after_schema_drop_rolls_back_all_existing_objects_and_rows(self):
        self.add_new_schema_objects()
        def fail_after_restored_data(command, **kwargs):
            if command[0] == 'psql' and '--single-transaction' in command:
                with Path(kwargs['input_file']).open('ab') as output:
                    output.write(b'\nSELECT 1 / 0;\n')
            return manager.run(command, **kwargs)
        self.installation.runner = fail_after_restored_data
        with self.assertRaises(manager.OperationError) as error:
            self.installation.database('pg_restore', archive=self.dump)
        self.assertIn('division by zero', error.exception.diagnostics)
        self.assertEqual('newer-data', self.sql('SELECT evidence FROM journal'))
        self.assertEqual('1|1', self.sql('SELECT (SELECT count(*) FROM newer_fk), (SELECT count(*) FROM newer_independent)'))

    def test_other_application_schema_is_refused_without_changes(self):
        self.add_new_schema_objects()
        self.sql('CREATE SCHEMA unrelated_customer')
        with self.assertRaises(manager.OperationError) as error:
            self.installation.database('pg_restore', archive=self.dump)
        self.assertIn('other application schemas', error.exception.diagnostics)
        self.assertEqual('newer-data', self.sql('SELECT evidence FROM journal'))
        self.assertEqual('1', self.sql("SELECT count(*) FROM pg_namespace WHERE nspname='unrelated_customer'"))

    def test_remaining_client_session_is_refused_without_changes(self):
        self.add_new_schema_objects()
        process = subprocess.Popen(['psql', *self.connection, '--dbname=' + self.name, '-qAt', '--no-psqlrc'],
                                   env=self.env, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        try:
            process.stdin.write('SELECT 1;\n')
            process.stdin.flush()
            self.assertEqual('1', process.stdout.readline().strip())
            with self.assertRaises(manager.OperationError) as error:
                self.installation.database('pg_restore', archive=self.dump)
            self.assertIn('other database clients', error.exception.diagnostics)
            self.assertEqual('newer-data', self.sql('SELECT evidence FROM journal'))
        finally:
            process.stdin.write('\\q\n')
            process.stdin.flush()
            process.communicate(timeout=10)

    def test_non_owner_is_refused_before_schema_changes(self):
        role = 'gaeld_restore_role_' + secrets.token_hex(6)
        password = secrets.token_hex(24)
        self.sql("CREATE ROLE " + role + " LOGIN PASSWORD '" + password + "'")
        try:
            self.add_new_schema_objects()
            self.environment.update(DB_USERNAME=role, DB_PASSWORD=password)
            self.write_environment()
            with self.assertRaises(manager.OperationError) as error:
                self.installation.database('pg_restore', archive=self.dump)
            self.assertIn('ownership of the dedicated application database', error.exception.diagnostics)
            self.assertEqual('newer-data', self.sql('SELECT evidence FROM journal'))
        finally:
            self.sql('DROP ROLE ' + role)

    def test_public_extension_is_recreated_from_the_archive(self):
        if self.sql("SELECT count(*) FROM pg_available_extensions WHERE name='hstore'") != '1':
            self.skipTest('PostgreSQL hstore extension is not installed on the isolated server')
        self.sql("CREATE EXTENSION hstore WITH SCHEMA public; CREATE TABLE extended (value hstore); INSERT INTO extended VALUES ('key=>original');")
        self.process(['pg_dump', *self.connection, '--dbname=' + self.name, '--format=custom', '--no-owner', '--no-acl', '--file=' + str(self.dump)])
        self.add_new_schema_objects()
        self.installation.database('pg_restore', archive=self.dump)
        self.assertEqual('original', self.sql("SELECT value->'key' FROM extended"))
        self.assertEqual('1', self.sql("SELECT count(*) FROM pg_extension WHERE extname='hstore'"))


if __name__ == '__main__':
    unittest.main()
