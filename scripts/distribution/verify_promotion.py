#!/usr/bin/env python3
"""Verify signed qualification content against the attested build before promotion."""

import argparse
import json
from pathlib import Path
import re
import subprocess

from adopt import SAIL_RELEASES
from verify_adoption_receipt import verify

NATIVE_CHECKS = (
    "fresh_systemd_host",
    "fresh_install",
    "owner_login",
    "all_four_services",
    "encrypted_backup",
    "restore",
    "existing_owner_login",
    "original_file_recovered",
)


def verify_qualification(root):
    build = json.loads((root / "qualification-inputs.json").read_text())
    receipt = json.loads((root / "ee-acceptance.json").read_text())
    source = root / ("gaeld-ce-" + build["version"] + ".tar.gz")
    source_sha = build["files"][source.name]
    images = build["candidate_images"]
    versions = receipt.get("ee_versions")
    pairs = receipt.get("ee_packages")
    if (
        receipt.get("ce_commit") != build["commit"]
        or receipt.get("ce_version") != build["version"]
        or receipt.get("source_sha256") != source_sha
        or receipt.get("candidate_images") != images
        or receipt.get("status") != "passed"
        or receipt.get("boundary_contract") != "1.0.0"
    ):
        raise ValueError("Signed acceptance does not belong to the selected immutable build.")
    if not isinstance(versions, list) or not versions or not all(isinstance(v, str) for v in versions):
        raise ValueError("The exact EE version list is missing.")
    if (
        not isinstance(pairs, list)
        or len(pairs) != len(versions)
        or sorted(p["version"] for p in pairs) != sorted(versions)
    ):
        raise ValueError("An immutable private package is required for every EE version.")
    for pair in pairs:
        if not re.fullmatch(r"[a-f0-9]{40}", pair.get("commit", "")) or not re.fullmatch(
            r"[a-f0-9]{64}", pair.get("content_sha256", "")
        ):
            raise ValueError("The signed EE package commit or content digest is missing.")
    if build["version"].split("-")[0] != "3.11.0":
        return
    native = receipt.get("native_systemd", {})
    if (
        native.get("kind") != "native-systemd"
        or native.get("status") != "passed"
        or native.get("candidate_commit") != build["commit"]
        or native.get("candidate_version") != build["version"]
        or native.get("source_sha256") != source_sha
        or native.get("candidate_images") != images
        or any(native.get("checks", {}).get(key) is not True for key in NATIVE_CHECKS)
    ):
        raise ValueError(
            "The clean native systemd qualification is incomplete or covers different artifacts."
        )
    observed = set()
    for report in receipt.get("apple_docker", []):
        baseline = report.get("baseline_version")
        if (
            baseline not in SAIL_RELEASES
            or report.get("host_system") != "Darwin"
            or report.get("host_machine") != "arm64"
        ):
            raise ValueError("An Apple Silicon Docker rehearsal is required.")
        expected_commit = subprocess.check_output(
            ["git", "rev-parse", f"refs/tags/v{baseline}^{{commit}}"], text=True
        ).strip()
        verify(
            report,
            version=build["version"],
            commit=build["commit"],
            source=source,
            images=images,
            baseline_version=baseline,
            baseline_commit=expected_commit,
            platform="linux/arm64",
        )
        observed.add(baseline)
    if observed != set(SAIL_RELEASES):
        raise ValueError("An Apple Silicon Docker adoption path is missing.")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("directory", type=Path)
    verify_qualification(parser.parse_args().directory)
