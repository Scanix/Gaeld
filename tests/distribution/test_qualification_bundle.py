import copy
import json
from pathlib import Path
import sys
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts/distribution"))
from qualification_bundle import build_inventory, IMAGES
from verify_promotion import verify_qualification


class QualificationBundleTest(unittest.TestCase):
    def test_managed_redis_is_bound_to_the_patch_inventory(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            version = "3.12.1-rc.4"
            (root / ("gaeld-ce-" + version + ".tar.gz")).write_bytes(b"patch")
            args = (root, version, "a" * 40, "refs/heads/release/v" + version, "12", "1")
            images = {role: repo + "@sha256:" + "b" * 64 for role, repo in IMAGES.items()}
            with self.assertRaisesRegex(ValueError, "image repository"):
                build_inventory(*args, images, source_only=True)
            images["redis"] = "ghcr.io/scanix/gaeld-redis@sha256:" + "d" * 64
            self.assertEqual(images, build_inventory(*args, images, source_only=True)["candidate_images"])

    def test_candidate_digest_and_ee_identity_survive_promotion_without_rebuilding(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            version = "3.12.0-rc.1"
            commit = "a" * 40
            images = {name: repo + "@sha256:" + "b" * 64 for name, repo in IMAGES.items()}
            source = root / ("gaeld-ce-" + version + ".tar.gz")
            source.write_bytes(b"exact bytes retained by the build")
            arguments = (root, version, commit, "refs/heads/release/v" + version, "1234", "1", images)
            initial = build_inventory(*arguments, source_only=True)
            self.assertEqual({source.name}, set(initial["files"]))
            for name in ("provenance.json", "sbom.spdx.json", "native-runtime.json"):
                (root / name).write_text("{}")
            inventory = build_inventory(*arguments)
            self.assertEqual(initial["candidate_images"], inventory["candidate_images"])
            self.assertEqual(initial["files"][source.name], inventory["files"][source.name])
            (root / "qualification-inputs.json").write_text(json.dumps(inventory))
            receipt = {
                "ce_version": version,
                "ce_commit": commit,
                "ee_versions": ["2.10.1"],
                "ee_packages": [{"version": "2.10.1", "commit": "c" * 40, "content_sha256": "d" * 64}],
                "source_sha256": inventory["files"][source.name],
                "candidate_images": images,
                "status": "passed",
                "boundary_contract": "1.0.0",
            }
            target = root / "ee-acceptance.json"
            target.write_text(json.dumps(receipt))
            verify_qualification(root)
            modified = copy.deepcopy(receipt)
            modified["ee_packages"][0].pop("content_sha256")
            target.write_text(json.dumps(modified))
            with self.assertRaisesRegex(ValueError, "package commit or content digest"):
                verify_qualification(root)
            modified = dict(receipt, source_sha256="e" * 64)
            target.write_text(json.dumps(modified))
            with self.assertRaisesRegex(ValueError, "immutable build"):
                verify_qualification(root)
            source.write_bytes(b"rebuilt bytes")
            self.assertNotEqual(inventory, build_inventory(*arguments))
