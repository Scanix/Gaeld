#!/usr/bin/env python3
"""Qualify one released baseline on its architecture using immutable published inputs."""

import hashlib
import json
import os
from pathlib import Path
import platform
import re
import shutil
import subprocess
import sys
import tempfile
import time
import zipfile


def result(*args, cwd=None):
    return subprocess.check_output(args, cwd=cwd, text=True).strip()


def execute(*args, cwd=None):
    subprocess.run(args, cwd=cwd, check=True)


def save(path, data):
    path.write_text(json.dumps(data, indent=2, sort_keys=True) + "\n")


def sha(path):
    with path.open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


def require(condition, message):
    if not condition:
        raise RuntimeError(message)


def github(endpoint):
    return json.loads(result("gh", "api", "repos/Scanix/Gaeld/" + endpoint))


def prepare_baseline(candidate, inputs, cache):
    q = json.loads(inputs.read_text())
    arch = os.environ["QA_ARCH"]
    baseline = os.environ["QA_BASELINE"]
    cpu = {"amd64": "x86_64", "arm64": "aarch64"}[arch]
    require(platform.system() == "Linux" and platform.machine() == cpu, "Native Linux runner required")
    require(
        result("git", "rev-parse", "HEAD", cwd=candidate) == q["source"], "Candidate checkout mismatch"
    )
    commit = result("git", "rev-parse", "v" + baseline + "^{commit}", cwd=candidate)
    require(commit == q["baselines"][baseline], "Immutable baseline tag differs")
    tag = "gaeld-native-legacy:" + baseline + "-" + arch
    cache.mkdir(parents=True, exist_ok=True)
    saved = cache / "legacy-image.tar.zst"
    record = cache / "legacy-image.json"
    if saved.is_file() and record.is_file():
        metadata = json.loads(record.read_text())
        require(
            metadata["commit"] == commit
            and metadata["arch"] == arch
            and sha(saved) == metadata["sha256"],
            "Historical image cache integrity mismatch",
        )
        execute("docker", "load", "-i", str(saved))
        restored = json.loads(result("docker", "image", "inspect", tag))[0]
        require(
            restored["Id"] == metadata["image_id"]
            and restored["Architecture"] == arch
            and restored["Config"]["Labels"]["org.gaeld.qa.baseline-commit"] == commit,
            "Restored historical image identity mismatch",
        )
        print("Restored and verified the native Sail baseline", flush=True)
        return
    with tempfile.TemporaryDirectory(dir=os.environ["RUNNER_TEMP"]) as temp:
        old = Path(temp) / "baseline"
        execute("git", "worktree", "add", "--detach", str(old), commit, cwd=candidate)
        execute(
            "docker",
            "build",
            "--platform",
            "linux/" + arch,
            "--label",
            "org.gaeld.qa.baseline-commit=" + commit,
            "-f",
            str(old / "docker/php/Dockerfile"),
            "-t",
            tag,
            str(old),
        )
    print("Built the pinned Sail baseline on native " + arch, flush=True)
    with subprocess.Popen(["docker", "save", tag], stdout=subprocess.PIPE) as stream:
        subprocess.run(["zstd", "-q", "-T0", "-3", "-o", str(saved)], stdin=stream.stdout, check=True)
        require(stream.wait() == 0, "Could not export the historical runtime")
    image = json.loads(result("docker", "image", "inspect", tag))[0]
    save(record, {"arch": arch, "commit": commit, "sha256": sha(saved), "image_id": image["Id"]})


def resolve_inputs(candidate, inputs, output):
    q = json.loads(inputs.read_text())
    require(
        result("git", "rev-parse", "HEAD", cwd=candidate) == q["source"], "Candidate checkout mismatch"
    )
    artifact = None
    for _ in range(120):
        attempt = github("actions/runs/" + str(q["build_run"]) + "/attempts/" + str(q["build_attempt"]))
        require(
            attempt["head_sha"] == q["source"]
            and attempt["path"] == ".github/workflows/distribution.yml",
            "Official build attempt source mismatch",
        )
        items = github("actions/runs/" + str(q["build_run"]) + "/artifacts")["artifacts"]
        found = [a for a in items if a["name"] == q["artifact_name"] and not a["expired"]]
        require(len(found) <= 1, "Official input artifact is ambiguous")
        if found:
            artifact = found[0]
            break
        require(
            attempt["status"] != "completed", "Reviewed build ended without its exact source artifact"
        )
        time.sleep(25)
    require(artifact is not None, "Timed out waiting for the official source and image digests")
    require(
        artifact["workflow_run"]["head_sha"] == q["source"]
        and artifact["workflow_run"]["id"] == q["build_run"],
        "Resolved artifact belongs to another build",
    )
    file = Path(os.environ["RUNNER_TEMP"]) / ("official-" + str(artifact["id"]) + ".zip")
    with file.open("wb") as stream:
        subprocess.run(
            ["gh", "api", "repos/Scanix/Gaeld/actions/artifacts/" + str(artifact["id"]) + "/zip"],
            check=True,
            stdout=stream,
        )
    require(
        "sha256:" + sha(file) == artifact["digest"],
        "Downloaded official input artifact checksum mismatch",
    )
    source_name = "gaeld-ce-" + q["version"] + ".tar.gz"
    with zipfile.ZipFile(file) as z:
        names = [n for n in z.namelist() if Path(n).name == "qualification-inputs.json"]
        sources = [n for n in z.namelist() if Path(n).name == source_name]
        require(len(names) == len(sources) == 1, "Official source archive or inventory is missing")
        inventory = json.loads(z.read(names[0]))
        with z.open(sources[0]) as stream:
            source_sha = hashlib.file_digest(stream, "sha256").hexdigest()
    require(
        inventory["commit"] == q["source"]
        and inventory["version"] == q["version"]
        and inventory["source_ref"] == q["build_ref"],
        "Inventory source identity mismatch",
    )
    require(
        int(inventory["build_run"]) == q["build_run"]
        and int(inventory["build_attempt"]) == q["build_attempt"]
        and inventory["files"][source_name] == source_sha,
        "Inventory does not describe the exact retained bytes or attempt",
    )
    q.update(
        artifact_id=artifact["id"],
        artifact_sha256=sha(file),
        source_sha256=source_sha,
        images=inventory["candidate_images"],
    )
    save(output, q)
    print("Verified official source and image inventory", flush=True)


def main(candidate, inputs, output):
    q = json.loads(inputs.read_text())
    output.mkdir(parents=True, exist_ok=True)
    arch = os.environ["QA_ARCH"]
    kind = os.environ["QA_KIND"]
    baseline = os.environ["QA_BASELINE"]
    cpu = {"amd64": "x86_64", "arm64": "aarch64"}[arch]
    target = "linux/" + arch
    engine = result("docker", "info", "--format", "{{.OSType}}/{{.Architecture}}")
    require(
        platform.system() == "Linux" and platform.machine() == cpu and engine == "linux/" + cpu,
        "Expected matching native Linux runner and Docker CPU identities",
    )
    require(
        os.environ["RUNNER_ARCH"] == {"amd64": "X64", "arm64": "ARM64"}[arch],
        "Runner architecture mismatch",
    )
    require(
        result("git", "rev-parse", "HEAD", cwd=candidate) == q["source"],
        "Candidate is not the expected source",
    )
    require(
        not result("git", "status", "--porcelain", "--untracked-files=all", cwd=candidate),
        "Candidate checkout is dirty",
    )
    require(
        kind in {"git", "archive"} and baseline in {"3.8.28", "3.9.0", "3.10.0"},
        "Unexpected adoption route",
    )
    require(re.fullmatch(r"3\.11\.0(?:-rc\.[0-9]+)?", q["version"]), "Invalid qualification version")
    tag = "v" + baseline
    commit = result("git", "rev-parse", tag + "^{commit}", cwd=candidate)
    require(commit == q["baselines"][baseline], "Baseline tag differs from approved commit")
    release = github("releases/tags/" + tag)
    require(
        release["tag_name"] == tag and not release["draft"] and not release["prerelease"],
        "Baseline must be a published stable release",
    )
    artifact = github("actions/artifacts/" + str(q["artifact_id"]))
    require(
        not artifact["expired"]
        and artifact["digest"] == "sha256:" + q["artifact_sha256"]
        and artifact["workflow_run"]["head_sha"] == q["source"]
        and artifact["workflow_run"]["id"] == q["build_run"],
        "Build artifact identity mismatch",
    )
    state = {
        "architecture": target,
        "host_system": platform.system(),
        "host_machine": platform.machine(),
        "runner_os": os.environ["RUNNER_OS"],
        "runner_arch": os.environ["RUNNER_ARCH"],
        "docker_server": engine,
        "docker_version": result("docker", "version", "--format", "{{.Server.Version}}"),
        "emulation": False,
        "workflow_run": os.environ["GITHUB_RUN_ID"],
        "workflow_attempt": os.environ["GITHUB_RUN_ATTEMPT"],
        "source": q["source"],
        "version": q["version"],
        "baseline": baseline,
        "baseline_source": commit,
        "kind": kind,
        "official_build_artifact_id": artifact["id"],
        "official_build_run": q["build_run"],
        "official_artifact_sha256": q["artifact_sha256"],
    }
    save(output / "runner.json", state)
    fixed = {
        "app": "ghcr.io/scanix/gaeld",
        "web": "ghcr.io/scanix/gaeld-web",
        "postgres": "docker.io/library/postgres",
        "redis": "docker.io/library/redis",
    }
    image_args = []
    identities = {}
    for role, repository in fixed.items():
        ref = q["images"][role]
        require(
            re.fullmatch(re.escape(repository) + r"@sha256:[0-9a-f]{64}", ref),
            "Unexpected image reference",
        )
        index = json.loads(result("docker", "buildx", "imagetools", "inspect", ref, "--raw"))
        matches = [
            r
            for r in index["manifests"]
            if r.get("platform", {}).get("os") == "linux" and r["platform"].get("architecture") == arch
        ]
        require(len(matches) == 1, "Expected one platform manifest")
        child = matches[0]["digest"]
        require(re.fullmatch(r"sha256:[0-9a-f]{64}", child), "Malformed platform digest")
        execute("docker", "pull", "--platform", target, ref)
        inspect = json.loads(result("docker", "image", "inspect", "--platform", target, ref))[0]
        require(
            inspect["Architecture"] == arch and inspect["Os"] == "linux",
            "Pulled image has the wrong architecture",
        )
        manifest = json.loads(
            result("docker", "buildx", "imagetools", "inspect", repository + "@" + child, "--raw")
        )
        require(
            inspect["Id"] == manifest["config"]["digest"],
            "Pulled payload differs from the selected platform manifest",
        )
        identities[role] = {"index": ref, "platform_manifest": child, "config": inspect["Id"]}
        image_args += ["--" + role + "-image", ref]
    save(output / "images.json", identities)
    with tempfile.TemporaryDirectory(prefix="gaeld-native-", dir=os.environ["RUNNER_TEMP"]) as temp:
        work = Path(temp)
        zipped = Path(os.environ["RUNNER_TEMP"]) / ("official-" + str(artifact["id"]) + ".zip")
        require(sha(zipped) == q["artifact_sha256"], "Retained qualification artifact checksum mismatch")
        source = work / ("gaeld-ce-" + q["version"] + ".tar.gz")
        with zipfile.ZipFile(zipped) as bundle:
            matching = [f for f in bundle.namelist() if Path(f).name == source.name]
            require(len(matching) == 1, "Official source archive missing or ambiguous")
            with bundle.open(matching[0]) as reader, source.open("wb") as writer:
                shutil.copyfileobj(reader, writer)
        require(
            sha(source) == q["source_sha256"],
            "Candidate source archive differs from the signed build input",
        )
        state["source_archive_sha256"] = q["source_sha256"]
        baseline_image = "gaeld-native-legacy:" + baseline + "-" + arch
        original = json.loads(result("docker", "image", "inspect", baseline_image))[0]
        require(
            original["Config"]["Labels"]["org.gaeld.qa.baseline-commit"] == commit
            and original["Architecture"] == arch,
            "Prepared baseline image identity mismatch",
        )
        state["baseline_runtime_image_id"] = result(
            "docker", "image", "inspect", baseline_image, "--format", "{{.Id}}"
        )
        opts = []
        if kind == "archive":
            require(baseline == "3.9.0", "Unexpected archive baseline")
            archived = "gaeld-ce-v3.9.0.tar.gz"
            official = next(asset for asset in release["assets"] if asset["name"] == archived)
            require(
                official["digest"] == "sha256:" + q["baseline_archive_sha256"],
                "Published archive digest mismatch",
            )
            execute(
                "gh", "release", "download", tag, "-R", "Scanix/Gaeld", "-p", archived, "-D", str(work)
            )
            require(
                sha(work / archived) == q["baseline_archive_sha256"],
                "Downloaded baseline source archive failed integrity check",
            )
            opts = [
                "--baseline-archive",
                str(work / archived),
                "--baseline-sha256",
                q["baseline_archive_sha256"],
            ]
            state["baseline_archive_sha256"] = q["baseline_archive_sha256"]
        save(output / "runner.json", state)
        env = os.environ.copy()
        # The nested daemon has only synthetic databases and the candidate images.
        # Repository access is needed for the preceding official downloads only.
        env.pop("GH_TOKEN", None)
        env.pop("GITHUB_TOKEN", None)
        suite = candidate / "tests/distribution"
        smoke = subprocess.run(
            [
                sys.executable,
                str(suite / "runtime_smoke.py"),
                "--version",
                q["version"],
                "--platform",
                target,
                *image_args,
            ],
            check=True,
            env=env,
            stdout=subprocess.PIPE,
            text=True,
        )
        rows = [
            json.loads(line)
            for line in smoke.stdout.splitlines()
            if line.startswith("{") and line.endswith("}")
        ]
        require(
            any(
                row.get("platform") == target
                and all(row.get(k) == "passed" for k in ("install", "login", "drain"))
                for row in rows
            ),
            "Fresh installation, login, or drain was not proven",
        )
        save(output / "fresh-runtime.json", rows[-1])
        receipt = output / "adoption.json"
        subprocess.run(
            [
                sys.executable,
                str(suite / "adoption_rehearsal.py"),
                "--candidate",
                str(candidate),
                "--version",
                q["version"],
                "--platform",
                target,
                "--baseline-version",
                baseline,
                "--baseline-image",
                baseline_image,
                *opts,
                *image_args,
                "--source-artifact",
                str(source),
                "--report",
                str(receipt),
            ],
            env=env,
            check=True,
        )
        execute(
            sys.executable,
            str(candidate / "scripts/distribution/verify_adoption_receipt.py"),
            "--receipt",
            str(receipt),
            "--version",
            q["version"],
            "--commit",
            q["source"],
            "--source",
            str(source),
            "--platform",
            target,
            "--baseline-version",
            baseline,
            "--baseline-commit",
            commit,
            *image_args
        )
        proof = json.loads(receipt.read_text())
        require(
            proof["source_kind"] == kind
            and proof["host_system"] == "Linux"
            and proof["host_machine"] == cpu
            and proof["source_sha256"] == q["source_sha256"],
            "Adoption receipt identity mismatch",
        )
        state["status"] = "passed"
        state["receipt_sha256"] = sha(receipt)
        save(output / "runner.json", state)


if __name__ == "__main__":
    if sys.argv[1] == "--prepare-baseline":
        prepare_baseline(*(Path(a).resolve() for a in sys.argv[2:]))
    elif sys.argv[1] == "--resolve-inputs":
        resolve_inputs(*(Path(a).resolve() for a in sys.argv[2:]))
    else:
        main(*(Path(a).resolve() for a in sys.argv[1:]))
