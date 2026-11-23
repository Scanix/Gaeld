#!/usr/bin/env python3
"""Rehearse the managed distribution on native Linux using the retained build inputs."""

import argparse
import json
import os
from pathlib import Path
import platform
import re
import subprocess
import sys

from docker_platform import inspect_image
from qualification_bundle import IMAGES, digest
from verify_adoption_receipt import verify

ROOT = Path(__file__).resolve().parents[2]
HOSTS = {"amd64": ("ubuntu-24.04", "x86_64", "X64"), "arm64": ("ubuntu-24.04-arm", "aarch64", "ARM64")}
BASELINES = {
    "3.8.28": "2da064e1e5630c48c508f5af669a5d42009620d3",
    "3.9.0": "319cb1199b725d25b03461d8a31e7814860f8e4c",
    "3.10.0": "5fa1ce7f006e4fe9cfbf4de294bbcc69695cb600",
}
ARCHIVE_SHA256 = "537bdd64d100b1483ea559b8239585ec60ad3c6b5df2ae9f6e0cc3824fd0cc83"


def output(*args):
    return subprocess.check_output(args, text=True).strip()


def run(*args, **kwargs):
    subprocess.run(args, check=True, **kwargs)


def require(condition, message):
    if not condition:
        raise ValueError(message)


def matrix(version):
    cases = [("smoke", "")]
    if version.split("-")[0] == "3.11.0":
        cases.extend(("git", baseline) for baseline in BASELINES)
        cases.append(("archive", "3.9.0"))
    return [
        {"arch": arch, "runner": runner, "kind": kind, "baseline": baseline, "baseline_commit": BASELINES.get(baseline, ""),
         "case": "-".join(filter(None, (arch, baseline, kind)))}
        for arch, (runner, _, _) in HOSTS.items() for kind, baseline in cases
    ]


def ensure_native(arch):
    _, cpu, runner = HOSTS[arch]
    require(platform.system() == "Linux" and platform.machine() == cpu and os.environ["RUNNER_ARCH"] == runner,
            "A native Linux runner for the requested architecture is required.")
    require(output("docker", "info", "--format", "{{.OSType}}/{{.Architecture}}") == "linux/" + cpu,
            "The Docker engine is running on a different architecture.")


def pull_images(images, arch):
    qualified = {}
    for role, repo in IMAGES.items():
        image = images[role]
        require(re.fullmatch(re.escape(repo) + r"@sha256:[0-9a-f]{64}", image), "Unexpected image: " + role)
        index = json.loads(output("docker", "buildx", "imagetools", "inspect", image, "--raw"))
        children = [m["digest"] for m in index["manifests"]
                    if m.get("platform", {}).get("os") == "linux" and m["platform"].get("architecture") == arch]
        require(len(children) == 1 and re.fullmatch(r"sha256:[0-9a-f]{64}", children[0]),
                "Expected one immutable native platform manifest: " + role)
        leaf = json.loads(output("docker", "buildx", "imagetools", "inspect", repo + "@" + children[0], "--raw"))
        run("docker", "pull", "--platform", "linux/" + arch, image)
        local = inspect_image(image, "linux/" + arch)
        require(local["Os"] == "linux" and local["Architecture"] == arch and local["Id"] == leaf["config"]["digest"],
                "Local image does not match the native platform manifest: " + role)
        qualified[role] = {"index": image, "platform_manifest": children[0], "config": local["Id"]}
    return qualified


def legacy_image(arch, version):
    return "gaeld-native-legacy:" + version + "-" + arch


def prepare_baseline(cache, arch, version):
    ensure_native(arch)
    commit = BASELINES[version]
    peeled = output("git", "rev-parse", "refs/tags/v" + version + "^{commit}")
    require(peeled == commit, "Historical tag differs from the released commit.")
    image = legacy_image(arch, version)
    cache.mkdir(parents=True, exist_ok=True)
    saved = cache / "legacy-image.tar.zst"
    record = cache / "legacy-image.json"
    if saved.exists() or record.exists():
        metadata = json.loads(record.read_text())
        require(metadata["commit"] == commit and metadata["arch"] == arch and metadata["sha256"] == digest(saved),
                "Cached historical image failed integrity verification.")
        run("docker", "load", "-i", saved)
    else:
        baseline = Path(os.environ["RUNNER_TEMP"]) / ("sail-" + version)
        run("git", "worktree", "add", "--detach", baseline, commit)
        run("docker", "build", "--platform", "linux/" + arch, "--label", "org.gaeld.qa.baseline-commit=" + commit,
            "-f", baseline / "docker/php/Dockerfile", "-t", image, baseline)
        with subprocess.Popen(["docker", "save", image], stdout=subprocess.PIPE) as exported:
            subprocess.run(["zstd", "-q", "-T0", "-3", "-o", saved], stdin=exported.stdout, check=True)
            require(exported.wait() == 0, "Could not save the historical image.")
        metadata = {"arch": arch, "commit": commit, "sha256": digest(saved),
                    "image_id": output("docker", "image", "inspect", "--format", "{{.Id}}", image)}
        record.write_text(json.dumps(metadata) + "\n")
    local = json.loads(output("docker", "image", "inspect", image))[0]
    require(local["Architecture"] == arch and local["Id"] == metadata["image_id"]
            and local["Config"]["Labels"]["org.gaeld.qa.baseline-commit"] == commit,
            "Historical runtime identity differs from the reviewed version.")


def run_case(directory, destination, row):
    data = json.loads((directory / "qualification-inputs.json").read_text())
    arch, kind, baseline = row["arch"], row["kind"], row["baseline"]
    ensure_native(arch)
    require(output("git", "rev-parse", "HEAD") == data["commit"], "Candidate checkout differs from the build.")
    qualified = pull_images(data["candidate_images"], arch)
    image_args = [part for role, image in data["candidate_images"].items() for part in ("--" + role + "-image", image)]
    common = ["--version", data["version"], "--platform", "linux/" + arch, *image_args]
    destination.mkdir(parents=True, exist_ok=True)
    receipt = destination / (row["case"] + ".json")
    rehearsal_env = {k: v for k, v in os.environ.items() if k not in ("GH_TOKEN", "GITHUB_TOKEN")}
    if kind == "smoke":
        run("docker", "run", "--rm", "--platform", "linux/" + arch, "--entrypoint", "php", data["candidate_images"]["app"],
            "-r", 'require "vendor/autoload.php"; exit(PHP_VERSION_ID >= 80400 && extension_loaded("pdo_pgsql") && extension_loaded("redis") ? 0 : 1);')
        smoke = subprocess.run([sys.executable, ROOT / "tests/distribution/runtime_smoke.py", *common], env=rehearsal_env,
                               stdout=subprocess.PIPE, text=True, check=True)
        observed = [json.loads(line) for line in smoke.stdout.splitlines() if line.startswith("{")]
        checks = ["install", "login", "drain"]
        require(any(row.get("platform") == "linux/" + arch and all(row.get(check) == "passed" for check in checks)
                    for row in observed), "The platform installation, login or drain receipt is missing.")
        result = {"status": "passed", "checks": checks}
    else:
        source = directory / ("gaeld-ce-" + data["version"] + ".tar.gz")
        args = []
        if kind == "archive":
            archive = directory / "gaeld-ce-v3.9.0.tar.gz"
            run("gh", "release", "download", "v3.9.0", "--repo", os.environ["GITHUB_REPOSITORY"],
                "--pattern", archive.name, "--dir", directory)
            require(digest(archive) == ARCHIVE_SHA256, "Published v3.9.0 archive checksum mismatch.")
            args = ["--baseline-archive", archive, "--baseline-sha256", ARCHIVE_SHA256]
        run(sys.executable, ROOT / "tests/distribution/adoption_rehearsal.py", *common,
            "--candidate", ROOT, "--baseline-version", baseline, "--baseline-image", legacy_image(arch, baseline),
            "--source-artifact", source, "--report", receipt, *args, env=rehearsal_env)
        result = verify(receipt, version=data["version"], commit=data["commit"], source=source, images=data["candidate_images"],
                        baseline_version=baseline, baseline_commit=BASELINES[baseline], platform="linux/" + arch)
        require(result["source_kind"] == kind, "Wrong adoption format.")
    result["native"] = {"host": platform.machine(), "platform": "linux/" + arch, "images": qualified,
                        "version": data["version"], "commit": data["commit"], "build_run": data["build_run"],
                        "build_attempt": data["build_attempt"], "source_sha256": data["files"]["gaeld-ce-" + data["version"] + ".tar.gz"]}
    receipt.write_text(json.dumps(result, indent=2, sort_keys=True) + "\n")


def collect(directory, reports):
    data = json.loads((directory / "qualification-inputs.json").read_text())
    rows = matrix(data["version"])
    prefix = "ce-native-" + "-".join((data["version"], data["build_run"], data["build_attempt"])) + "-"
    cases = {row["case"] for row in rows}
    selected = {}
    for artifact in reports.iterdir():
        case, sep, attempt = artifact.name.removeprefix(prefix).rpartition("-attempt-")
        require(artifact.is_dir() and artifact.name.startswith(prefix) and sep and case in cases
                and re.fullmatch(r"[1-9][0-9]*", attempt), "Unexpected native qualification artifact.")
        file = artifact / (case + ".json")
        require(set(artifact.iterdir()) == {file} and file.is_file(), "Ambiguous native qualification artifact.")
        if case not in selected or int(attempt) > selected[case][0]:
            selected[case] = (int(attempt), file)
    require(set(selected) == cases, "A native qualification case is missing.")
    adoption = []
    runtime = []
    source = directory / ("gaeld-ce-" + data["version"] + ".tar.gz")
    for row in rows:
        report = json.loads(selected[row["case"]][1].read_text())
        native = report["native"]
        require(native["host"] == HOSTS[row["arch"]][1] and native["platform"] == "linux/" + row["arch"]
                and native["commit"] == data["commit"] and native["version"] == data["version"]
                and native["build_run"] == data["build_run"] and native["build_attempt"] == data["build_attempt"]
                and native["source_sha256"] == data["files"][source.name]
                and {role: proof["index"] for role, proof in native["images"].items()} == data["candidate_images"],
                "Native report identity differs from the retained build.")
        require(all(re.fullmatch(r"sha256:[0-9a-f]{64}", proof[k]) for proof in native["images"].values()
                    for k in ("platform_manifest", "config")), "Missing native image identity.")
        if row["kind"] == "smoke":
            require(report["status"] == "passed" and report["checks"] == ["install", "login", "drain"], "Failed runtime checks.")
            runtime.append(report)
        else:
            verify(report, version=data["version"], commit=data["commit"], source=source, images=data["candidate_images"],
                   baseline_version=row["baseline"], baseline_commit=row["baseline_commit"], platform=native["platform"])
            require(report["source_kind"] == row["kind"] and report["host_system"] == "Linux"
                    and report["host_machine"] == native["host"], "Wrong native baseline source or CPU.")
            adoption.append(report)
    (directory / "native-runtime.json").write_text(json.dumps(runtime, indent=2) + "\n")
    if adoption:
        (directory / "adoption-acceptance.json").write_text(json.dumps(adoption, indent=2) + "\n")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("operation", choices=("matrix", "baseline", "run", "collect"))
    parser.add_argument("path")
    parser.add_argument("reports", nargs="?", type=Path)
    args = parser.parse_args()
    if args.operation == "matrix":
        print(json.dumps({"include": matrix(args.path)}, separators=(",", ":")))
    elif args.operation == "baseline":
        prepare_baseline(Path(args.path), os.environ["NATIVE_ARCH"], os.environ["BASELINE"])
    elif args.operation == "collect":
        collect(Path(args.path), args.reports)
    else:
        directory = Path(args.path)
        rows = matrix(json.loads((directory / "qualification-inputs.json").read_text())["version"])
        row = next(row for row in rows if row["case"] == os.environ["NATIVE_CASE"])
        run_case(directory, args.reports, row)


if __name__ == "__main__":
    main()
