import json
from pathlib import Path
from types import SimpleNamespace
import subprocess
import sys
import tempfile
import unittest

BUILDER = Path(__file__).resolve().parents[2] / 'scripts/distribution/release_manifest.py'
sys.path.insert(0, str(BUILDER.parent))
from adopt import SAIL_RELEASES
from release_manifest import verify_archive_upgrade
from unittest.mock import patch
from verify_adoption_receipt import CHECKS, sha256


class ReleaseManifestTest(unittest.TestCase):
    def test_archive_receipt_binds_both_editions_to_the_same_predecessor_and_complete_recovery(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / 'app.tar.gz'
            source.write_bytes(b'exact release payload')
            path = root / 'integrated.json'
            manifest = root / 'release.json'
            args = SimpleNamespace(source=source, ee_receipt=path, ee_receipt_signature='sig', ee_receipt_key='key', baseline_manifest=[manifest], version='3.12.0')
            images = {'app': 'test@sha256:' + 'd' * 64}
            baseline_images = {name: 'original@sha256:' + 'c' * 64 for name in ('app','web','postgres','redis')}
            predecessor = {'version':'3.11.0','commit':'a' * 40,'edition':'ce','source':{'sha256':'f' * 64},'images':baseline_images}
            manifest.write_text(json.dumps(predecessor))
            key = root / 'test.key'
            public = root / 'test.pub'
            subprocess.run(['openssl','genpkey','-algorithm','ED25519','-out',key],check=True)
            subprocess.run(['openssl','pkey','-in',key,'-pubout','-out',public],check=True)
            subprocess.run(['openssl','pkeyutl','-sign','-inkey',key,'-rawin','-in',manifest,
                            '-out',manifest.with_name('release.json.sig')],check=True)
            checks = dict.fromkeys(('capture','deduplication','missing_source_incident','retry','export','access_controls',
                                    'existing_rows_unchanged','existing_files_unchanged','recovery_rows_unchanged','recovery_files_unchanged'), True)
            row = {'baseline_version':'3.11.0','baseline_commit':'a' * 40,'checks':checks}
            report = {'source_sha256':sha256(source),'candidate_images':images,'baseline_source_sha256':'f' * 64,'baseline_images':baseline_images,
                      'ee_packages':[{'version':'2.10.0', 'commit':'b' * 40, 'content_sha256':'c' * 64}],
                      'archive_upgrades':[dict(row, edition='CE'),dict(row,edition='EE',ee_version='2.10.0',
                                                                     ee_commit='b' * 40,ee_content_sha256='c' * 64)]}
            with (patch('release_manifest.subprocess.check_output',return_value='a' * 40),
                  patch('release_manifest.RELEASE_PUBLIC_KEY',public),
                  patch('release_manifest.RELEASE_KEY_SHA256',sha256(public))):
                path.write_text(json.dumps(report))
                verify_archive_upgrade(args,['3.11.0'],images,['2.10.0'])
                report['baseline_source_sha256'] = 'e' * 64
                path.write_text(json.dumps(report))
                with self.assertRaisesRegex(ValueError,'signed predecessor'):
                    verify_archive_upgrade(args,['3.11.0'],images,['2.10.0'])
                report['baseline_source_sha256'] = 'f' * 64
                report['archive_upgrades'][1]['ee_commit'] = 'e' * 40
                path.write_text(json.dumps(report))
                with self.assertRaisesRegex(ValueError,'signed commit'):
                    verify_archive_upgrade(args,['3.11.0'],images,['2.10.0'])
                report['archive_upgrades'][1]['ee_commit'] = 'b' * 40
                report['archive_upgrades'][1]['checks'] = dict(checks,recovery_files_unchanged=False)
                path.write_text(json.dumps(report))
                with self.assertRaisesRegex(ValueError,'incomplete'):
                    verify_archive_upgrade(args,['3.11.0'],images,['2.10.0'])
                report['archive_upgrades'].pop()
                path.write_text(json.dumps(report))
                with self.assertRaisesRegex(ValueError,'missing an exact'):
                    verify_archive_upgrade(args,['3.11.0'],images,['2.10.0'])

    def test_patch_binds_both_signed_baselines_and_the_previous_stable_rollback(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source = root / 'source'
            source.write_bytes(b'patched source')
            key, public = root / 'test.key', root / 'test.pub'
            subprocess.run(['openssl', 'genpkey', '-algorithm', 'ED25519', '-out', key], check=True)
            subprocess.run(['openssl', 'pkey', '-in', key, '-pubout', '-out', public], check=True)
            allowed = ['3.11.0', '3.12.0']
            images = {'app': 'candidate@sha256:' + 'd' * 64}
            package = {'version': '2.10.2', 'commit': 'c' * 40, 'content_sha256': 'd' * 64}
            report = {'source_sha256': sha256(source), 'candidate_images': images,
                      'ee_packages': [package], 'archive_paths': {}}
            args = SimpleNamespace(version='3.12.1-rc.1', commit='e' * 40, source=source,
                                   ee_receipt=root / 'receipt.json', ee_receipt_signature='sig',
                                   ee_receipt_key='key', baseline_manifest=[])
            for version, prefix in zip(allowed, ('a', 'b'), strict=True):
                manifest = root / version
                previous = {'version': version, 'commit': prefix * 40, 'edition': 'ce',
                            'source': {'sha256': prefix * 64}, 'images': {'app': 'published@sha256:' + prefix * 64}}
                manifest.write_text(json.dumps(previous))
                subprocess.run(['openssl', 'pkeyutl', '-sign', '-inkey', key, '-rawin', '-in', manifest,
                                '-out', root / (version + '.sig')], check=True)
                args.baseline_manifest.append(manifest)
                checks = dict.fromkeys(('capture', 'deduplication', 'missing_source_incident', 'retry', 'export',
                                        'access_controls', 'existing_rows_unchanged', 'existing_files_unchanged',
                                        'recovery_rows_unchanged', 'recovery_files_unchanged'), True)
                if version == '3.12.0':
                    checks.update(rollback_to_predecessor=True, rollback_login_and_data=True)
                row = {'baseline_version': version, 'baseline_commit': previous['commit'], 'candidate_version': args.version,
                       'candidate_commit': args.commit, 'checks': checks}
                report['archive_paths'][version] = {
                    'baseline_source_sha256': previous['source']['sha256'], 'baseline_images': previous['images'],
                    'archive_upgrades': [dict(row, edition='CE'),
                                         dict(row, edition='EE', ee_version=package['version'],
                                              ee_commit=package['commit'], ee_content_sha256=package['content_sha256'])]}

            def verify():
                args.ee_receipt.write_text(json.dumps(report))
                verify_archive_upgrade(args, allowed, images, [package['version']])

            revisions = {'refs/tags/v3.11.0^{commit}': 'a' * 40, 'refs/tags/v3.12.0^{commit}': 'b' * 40}
            with (patch('release_manifest.subprocess.check_output', side_effect=lambda cmd, **kwargs: revisions[cmd[-1]]),
                  patch('release_manifest.RELEASE_PUBLIC_KEY', public),
                  patch('release_manifest.RELEASE_KEY_SHA256', sha256(public))):
                verify()
                rows = report['archive_paths']['3.12.0']['archive_upgrades']
                rows[1]['checks'] = dict(rows[1]['checks'], rollback_login_and_data=False)
                with self.assertRaisesRegex(ValueError, 'incomplete'):
                    verify()
                rows[1]['checks']['rollback_login_and_data'] = True
                rows[0]['candidate_commit'] = 'f' * 40
                with self.assertRaisesRegex(ValueError, 'exact patch target'):
                    verify()
                rows[0]['candidate_commit'] = args.commit
                args.baseline_manifest.reverse()
                with self.assertRaisesRegex(ValueError, 'signed predecessor'):
                    verify()
                args.baseline_manifest.reverse()
                del report['archive_paths']['3.11.0']
                with self.assertRaisesRegex(ValueError, 'both published'):
                    verify()

    def test_requires_attestations_and_exact_ee_acceptance_before_declaring_compatibility(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            for name in ('source.tar.gz', 'provenance.json', 'sbom.json'):
                (root / name).write_text('{}')
            subprocess.run(['git', 'init', '--quiet', directory], check=True)
            subprocess.run(['git', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.invalid',
                            'commit', '--allow-empty', '--quiet', '-m', 'Test baseline'], cwd=root, check=True)
            previous_commit = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=root, text=True).strip()
            for baseline in SAIL_RELEASES:
                subprocess.run(['git', 'tag', 'v' + baseline], cwd=root, check=True)
            receipt = {'schema': 1, 'status': 'passed', 'candidate_version': '3.11.0-rc.2',
                       'baseline_commit': previous_commit, 'host_system': 'Linux',
                       'candidate_commit': 'a' * 40, 'source_tree_clean': True,
                       'source_sha256': sha256(root / 'source.tar.gz'), 'platform': 'linux/amd64',
                       'candidate_images': {'app': 'ghcr.io/scanix/gaeld@sha256:' + 'b' * 64,
                                            'web': 'ghcr.io/scanix/gaeld-web@sha256:' + 'b' * 64,
                                            'postgres': 'docker.io/library/postgres@sha256:' + 'b' * 64,
                                            'redis': 'docker.io/library/redis@sha256:' + 'b' * 64},
                       'checks': dict.fromkeys(CHECKS, True)}
            receipts = [dict(receipt, baseline_version=previous, source_kind='git', platform=arch)
                        for previous in SAIL_RELEASES for arch in ('linux/amd64', 'linux/arm64')]
            receipts.extend(dict(receipt, baseline_version='3.9.0', source_kind='archive', platform=arch)
                            for arch in ('linux/amd64', 'linux/arm64'))
            (root / 'adoption.json').write_text(json.dumps(receipts))
            command = [sys.executable, str(BUILDER), '--version', '3.11.0-rc.2', '--commit', 'a' * 40,
                       '--source', str(root / 'source.tar.gz'), '--allowed-from', json.dumps(SAIL_RELEASES),
                       '--provenance', str(root / 'provenance.json'), '--sbom', str(root / 'sbom.json'),
                       '--adoption-receipt', str(root / 'adoption.json'),
                       '--output', str(root / 'release.json')]
            for image in ('app', 'web', 'postgres', 'redis'):
                command += ['--' + image + '-digest', 'sha256:' + 'b' * 64]
            subprocess.run(command, check=True, capture_output=True, cwd=root)
            manifest = json.loads((root / 'release.json').read_text())
            self.assertEqual([], manifest['compatible_ee_versions'])
            self.assertEqual(list(SAIL_RELEASES), manifest['allowed_from'])
            self.assertEqual(4, len(manifest['images']))
            self.assertEqual(sha256(root / 'adoption.json'), manifest['adoption_acceptance']['sha256'])
            result = subprocess.run(command + ['--compatible-ee-versions', '["2.9.31"]'], capture_output=True, cwd=root)
            self.assertNotEqual(0, result.returncode)
            self.assertIn(b'signed exact-pair acceptance receipt', result.stderr)
            (root / 'adoption.json').write_text(json.dumps(receipts[1:]))
            result = subprocess.run(command, capture_output=True, cwd=root)
            self.assertNotEqual(0, result.returncode)
            self.assertIn(b'missing a supported Git baseline', result.stderr)
            (root / 'adoption.json').write_text(json.dumps(receipts[:-1]))
            result = subprocess.run(command, capture_output=True, cwd=root)
            self.assertNotEqual(0, result.returncode)
            self.assertIn(b'archive must pass on each architecture', result.stderr)
            receipts[0]['checks']['restore'] = False
            (root / 'adoption.json').write_text(json.dumps(receipts))
            result = subprocess.run(command, capture_output=True, cwd=root)
            self.assertNotEqual(0, result.returncode)
            self.assertIn(b'missing or failed recovery checks', result.stderr)


if __name__ == '__main__':
    unittest.main()
