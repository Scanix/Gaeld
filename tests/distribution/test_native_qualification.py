import json
from pathlib import Path
import sys
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts/distribution"))
from qualification_bundle import IMAGES, build_inventory
from qualify_native import BASELINES, HOSTS, collect, matrix
from verify_adoption_receipt import CHECKS


class NativeQualificationTest(unittest.TestCase):
    def test_installer_matrix_includes_each_released_route_and_architecture(self):
        rows = matrix("3.11.0-rc.5")
        expected = {(arch, "", "smoke") for arch in HOSTS}
        expected.update((arch, version, "git") for arch in HOSTS for version in BASELINES)
        expected.update((arch, "3.9.0", "archive") for arch in HOSTS)
        self.assertEqual(expected, {(r["arch"], r["baseline"], r["kind"]) for r in rows})
        self.assertEqual(len(expected), len({r["case"] for r in rows}))
        for version in ("3.12.0", "3.13.0-rc.4"):
            self.assertEqual({"amd64-smoke", "arm64-smoke"}, {r["case"] for r in matrix(version)})

    def test_assembly_requires_all_native_reports_bound_to_the_same_build(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            reports = root / "reports"
            reports.mkdir()
            version = "3.11.0"
            commit = "a" * 40
            source = root / ("gaeld-ce-" + version + ".tar.gz")
            source.write_bytes(b"original payload")
            images = {role: repo + "@sha256:" + "b" * 64 for role, repo in IMAGES.items()}
            inventory = build_inventory(root, version, commit, "refs/heads/release/v" + version, 100, 1, images, source_only=True)
            (root / "qualification-inputs.json").write_text(json.dumps(inventory))
            for row in matrix(version):
                arch, baseline = row["arch"], row["baseline"]
                record = {
                    "schema": 1, "status": "passed", "candidate_version": version, "candidate_commit": commit,
                    "baseline_version": baseline, "baseline_commit": row["baseline_commit"], "source_tree_clean": True,
                    "platform": "linux/" + arch, "candidate_images": images, "source_kind": row["kind"],
                    "source_sha256": inventory["files"][source.name], "host_machine": HOSTS[arch][1], "host_system": "Linux",
                    "checks": dict.fromkeys(CHECKS, True) if baseline else ["install", "login", "drain"],
                    "native": {"host": HOSTS[arch][1], "platform": "linux/" + arch, "version": version,
                               "commit": commit, "build_run": "100", "build_attempt": "1", "source_sha256": inventory["files"][source.name],
                               "images": {role: {"index": image, "config": "sha256:" + "c" * 64,
                                                 "platform_manifest": "sha256:" + "d" * 64} for role, image in images.items()}},
                }
                artifact = reports / ("ce-native-3.11.0-100-1-" + row["case"] + "-attempt-1")
                artifact.mkdir()
                (artifact / (row["case"] + ".json")).write_text(json.dumps(record))
            collect(root, reports)
            self.assertEqual(2, len(json.loads((root / "native-runtime.json").read_text())))
            self.assertEqual(8, len(json.loads((root / "adoption-acceptance.json").read_text())))
            first = reports / "ce-native-3.11.0-100-1-arm64-3.9.0-archive-attempt-1"
            retried = reports / "ce-native-3.11.0-100-1-arm64-3.9.0-archive-attempt-2"
            retried.mkdir()
            arm = retried / "arm64-3.9.0-archive.json"
            arm.write_bytes((first / arm.name).read_bytes())
            collect(root, reports)
            record = json.loads(arm.read_text())
            record["native"]["build_attempt"] = "2"
            arm.write_text(json.dumps(record))
            with self.assertRaisesRegex(ValueError, "identity"):
                collect(root, reports)
            arm.unlink()
            retried.rmdir()
            (first / arm.name).unlink()
            first.rmdir()
            with self.assertRaisesRegex(ValueError, "missing"):
                collect(root, reports)


if __name__ == "__main__":
    unittest.main()
