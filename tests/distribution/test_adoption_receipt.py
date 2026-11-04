import copy
import json
from pathlib import Path
import sys
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'scripts/distribution'))
from verify_adoption_receipt import CHECKS, sha256, verify


class AdoptionReceiptTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        self.source = self.root / 'source.tar.gz'
        self.source.write_bytes(b'exact candidate source bytes')
        self.path = self.root / 'adoption.json'
        self.images = {role: 'registry.example/' + role + '@sha256:' + 'b' * 64
                       for role in ('app', 'web', 'postgres', 'redis')}
        self.receipt = {'schema': 1, 'status': 'passed', 'candidate_version': '3.9.0-rc.1',
                        'candidate_commit': 'a' * 40, 'source_tree_clean': True,
                        'source_sha256': sha256(self.source), 'candidate_images': self.images,
                        'platform': 'linux/amd64', 'checks': dict.fromkeys(CHECKS, True)}

    def check(self, receipt=None):
        self.path.write_text(json.dumps(self.receipt if receipt is None else receipt))
        return verify(self.path, version='3.9.0-rc.1', commit='a' * 40, source=self.source,
                      images=self.images, platform='linux/amd64')

    def test_accepts_only_the_candidate_that_was_rehearsed(self):
        self.assertEqual(self.receipt, self.check())
        for key, replacement in (('candidate_commit', 'c' * 40), ('candidate_version', '3.9.0'),
                                 ('source_sha256', 'd' * 64), ('source_tree_clean', False),
                                 ('platform', 'linux/arm64'), ('status', 'failed')):
            with self.subTest(field=key):
                changed = copy.deepcopy(self.receipt)
                changed[key] = replacement
                with self.assertRaises(ValueError):
                    self.check(changed)
        self.source.write_bytes(b'archive rebuilt after successful rehearsal')
        with self.assertRaisesRegex(ValueError, 'source archive'):
            self.check()

    def test_rejects_substituted_image_and_skipped_restore(self):
        changed = copy.deepcopy(self.receipt)
        changed['candidate_images']['app'] = 'registry.example/app@sha256:' + 'c' * 64
        with self.assertRaisesRegex(ValueError, 'image digests'):
            self.check(changed)
        for value in (False, 'true', 1, None):
            with self.subTest(value=value):
                changed = copy.deepcopy(self.receipt)
                changed['checks']['restore'] = value
                with self.assertRaisesRegex(ValueError, 'recovery checks'):
                    self.check(changed)
        changed['checks'].pop('restore')
        with self.assertRaisesRegex(ValueError, 'recovery checks'):
            self.check(changed)

    def test_requires_real_payload_comparison_and_failed_backup_recovery(self):
        for check in ('candidate_payload_equivalence', 'failed_backup_resume'):
            with self.subTest(check=check):
                changed = copy.deepcopy(self.receipt)
                changed['checks'].pop(check)
                with self.assertRaisesRegex(ValueError, 'recovery checks'):
                    self.check(changed)


if __name__ == '__main__':
    unittest.main()
