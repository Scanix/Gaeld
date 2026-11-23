#!/usr/bin/env python3
"""Create and verify the inventories retained by the managed distribution workflow."""

import argparse
import hashlib
import json
import os
from pathlib import Path
import re

IMAGES = {
    "app": "ghcr.io/scanix/gaeld",
    "web": "ghcr.io/scanix/gaeld-web",
    "postgres": "docker.io/library/postgres",
    "redis": "docker.io/library/redis",
}


def digest(file):
    with file.open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


def build_inventory(root, version, commit, source_ref, run, attempt, images, *, source_only=False):
    if not re.fullmatch(r"\d+\.\d+\.\d+(?:-rc\.\d+)?", version) or not re.fullmatch(r"[0-9a-f]{40}", commit):
        raise ValueError("An exact version and source commit are required.")
    if source_ref not in ("refs/tags/v" + version, "refs/heads/release/v" + version):
        raise ValueError("The build must use the corresponding release branch or tag.")
    if set(images) != set(IMAGES) or not all(
        re.fullmatch(re.escape(repo) + r"@sha256:[a-f0-9]{64}", images[key]) for key, repo in IMAGES.items()
    ):
        raise ValueError("Unexpected image repository or digest.")
    required = ["gaeld-ce-" + version + ".tar.gz"]
    if not source_only:
        required.extend(["provenance.json", "sbom.spdx.json", "native-runtime.json"])
        if version.split("-")[0] == "3.11.0":
            required.append("adoption-acceptance.json")
    files = {name: digest(root / name) for name in required}
    return {
        "schema": 1,
        "version": version,
        "commit": commit,
        "source_ref": source_ref,
        "build_run": str(run),
        "build_attempt": str(attempt),
        "candidate_images": images,
        "files": files,
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("mode", choices=("inputs", "verify-inputs", "create", "verify"))
    parser.add_argument("directory", type=Path)
    args = parser.parse_args()
    root = args.directory
    file = root / "qualification-inputs.json"
    env = os.environ
    if args.mode != "verify":
        images = {
            name: repository + "@" + env[name.upper() + "_DIGEST"] for name, repository in IMAGES.items()
        }
        data = build_inventory(
            root,
            env["RELEASE_VERSION"],
            env["GITHUB_SHA"],
            env["GITHUB_REF"],
            env["GITHUB_RUN_ID"],
            env.get("BUILD_ATTEMPT", env["GITHUB_RUN_ATTEMPT"]),
            images,
            source_only=args.mode in ("inputs", "verify-inputs"),
        )
        if args.mode == "verify-inputs":
            if data != json.loads(file.read_text()):
                raise ValueError("Retained source bytes, images or build identity changed.")
            return
        if args.mode == "create":
            source = "gaeld-ce-" + data["version"] + ".tar.gz"
            initial = dict(data, files={source: data["files"][source]})
            if initial != json.loads(file.read_text()):
                raise ValueError("The final inventory differs from the original build inputs.")
        file.write_text(json.dumps(data, indent=2, sort_keys=True) + "\n")
    else:
        saved = json.loads(file.read_text())
        observed = build_inventory(
            root,
            env["RELEASE_VERSION"],
            env["GITHUB_SHA"],
            env["BUILD_REF"],
            env["BUILD_RUN"],
            env["BUILD_ATTEMPT"],
            saved["candidate_images"],
        )
        if observed != saved:
            raise ValueError("Retained artifact bytes, version, build identity or source commit changed.")


if __name__ == "__main__":
    main()
