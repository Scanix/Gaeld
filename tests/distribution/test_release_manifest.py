import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

BUILDER = Path(__file__).resolve().parents[2] / 'scripts/distribution/release_manifest.py'
sys.path.insert(0, str(BUILDER.parent))
from verify_adoption_receipt import CHECKS, sha256
from release_manifest import validated_upgrade_path


class ReleaseManifestTest(unittest.TestCase):
    def test_release_stages_require_exact_explicit_predecessors(self):
        self.assertEqual('3.8.28', validated_upgrade_path('3.9.0-rc.1', ['3.8.28']))
        self.assertEqual('3.9.0-rc.1', validated_upgrade_path('3.10.0-rc.1', ['3.9.0', '3.9.0-rc.1']))
        self.assertEqual('3.9.0', validated_upgrade_path('3.10.0', ['3.9.0']))
        self.assertEqual('3.10.0', validated_upgrade_path('3.11.0', ['3.10.0']))
        rejected = [('3.10.0-rc.1', ['3.8.28']), ('3.11.0-rc.1', ['3.9.0']),
                    ('3.11.0', ['3.10.0-rc.1']), ('3.10.0', ['3.9.0-rc.1']),
                    ('3.10.0-rc.1', ['3.9.0', '3.8.28']), ('3.10.0-rc.1', []),
                    ('3.10.0-rc.1', ['3.9.0', '3.9.0']), ('3.10.0-rc.1', [None]),
                    ('3.12.0', ['3.11.0'])]
        for target, allowed in rejected:
            with self.subTest(target=target, allowed=allowed), self.assertRaises(ValueError):
                validated_upgrade_path(target, allowed)

    def test_requires_attestations_and_exact_ee_acceptance_before_declaring_compatibility(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            for name in ('source.tar.gz', 'provenance.json', 'sbom.json'):
                (root / name).write_text('{}')
            receipt = {'schema': 1, 'status': 'passed', 'candidate_version': '3.9.0',
                       'candidate_commit': 'a' * 40, 'source_tree_clean': True,
                       'source_sha256': sha256(root / 'source.tar.gz'), 'platform': 'linux/amd64',
                       'candidate_images': {'app': 'ghcr.io/scanix/gaeld@sha256:' + 'b' * 64,
                                            'web': 'ghcr.io/scanix/gaeld-web@sha256:' + 'b' * 64,
                                            'postgres': 'docker.io/library/postgres@sha256:' + 'b' * 64,
                                            'redis': 'docker.io/library/redis@sha256:' + 'b' * 64},
                       'checks': dict.fromkeys(CHECKS, True)}
            (root / 'adoption.json').write_text(json.dumps(receipt))
            command = [sys.executable, str(BUILDER), '--version', '3.9.0', '--commit', 'a' * 40,
                       '--source', str(root / 'source.tar.gz'), '--allowed-from', '["3.8.28"]',
                       '--provenance', str(root / 'provenance.json'), '--sbom', str(root / 'sbom.json'),
                       '--adoption-receipt', str(root / 'adoption.json'),
                       '--output', str(root / 'release.json')]
            for image in ('app', 'web', 'postgres', 'redis'):
                command += ['--' + image + '-digest', 'sha256:' + 'b' * 64]
            subprocess.run(command, check=True, capture_output=True)
            manifest = json.loads((root / 'release.json').read_text())
            self.assertEqual([], manifest['compatible_ee_versions'])
            self.assertEqual(['3.8.28'], manifest['allowed_from'])
            self.assertEqual(4, len(manifest['images']))
            self.assertEqual(sha256(root / 'adoption.json'), manifest['adoption_acceptance']['sha256'])
            result = subprocess.run(command + ['--compatible-ee-versions', '["2.9.31"]'], capture_output=True)
            self.assertNotEqual(0, result.returncode)
            self.assertIn(b'signed exact-pair acceptance receipt', result.stderr)
            receipt['candidate_version'] = '3.10.0-rc.1'
            (root / 'adoption.json').write_text(json.dumps(receipt))
            r2_command = command.copy()
            r2_command[r2_command.index('--version') + 1] = '3.10.0-rc.1'
            result = subprocess.run(r2_command, capture_output=True)
            self.assertNotEqual(0, result.returncode)
            self.assertIn(b'Skipping a preservation release stage', result.stderr)
            r2_command[r2_command.index('--allowed-from') + 1] = '["3.9.0-rc.1"]'
            subprocess.run(r2_command, check=True, capture_output=True)
            manifest = json.loads((root / 'release.json').read_text())
            self.assertEqual('3.9.0-rc.1', manifest['minimum_version'])
            self.assertEqual(['3.9.0-rc.1'], manifest['allowed_from'])
            self.assertEqual('legacy-sail-3.8.28-regression-only', manifest['adoption_acceptance']['scope'])
            receipt['candidate_version'] = '3.9.0'
            receipt['checks']['restore'] = False
            (root / 'adoption.json').write_text(json.dumps(receipt))
            result = subprocess.run(command, capture_output=True)
            self.assertNotEqual(0, result.returncode)
            self.assertIn(b'missing or failed recovery checks', result.stderr)


if __name__ == '__main__':
    unittest.main()
