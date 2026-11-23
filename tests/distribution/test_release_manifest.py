import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

BUILDER = Path(__file__).resolve().parents[2] / 'scripts/distribution/release_manifest.py'
sys.path.insert(0, str(BUILDER.parent))
from adopt import SAIL_RELEASES
from verify_adoption_receipt import CHECKS, sha256


class ReleaseManifestTest(unittest.TestCase):
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
