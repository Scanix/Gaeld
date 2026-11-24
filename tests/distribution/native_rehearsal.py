#!/usr/bin/env python3
"""Exercise the public native installer on a fresh, disposable systemd host."""

import argparse
import functools
import hashlib
import http.server
import json
import os
from pathlib import Path
import secrets
import shutil
import ssl
import subprocess
import sys
import tarfile
import threading


def execute(*args, **options):
    return subprocess.run([str(item) for item in args], check=True, text=True, **options)


def output(*args):
    return execute(*args, capture_output=True).stdout.strip()


def write(path, value):
    path.write_text(json.dumps(value, indent=2) + chr(10))


def digest(path):
    with Path(path).open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    for name in (
        "candidate",
        "version",
        "commit",
        "source",
        "images",
        "root",
        "working",
        "environment",
        "report",
    ):
        parser.add_argument("--" + name, required=True)
    args = parser.parse_args()
    os.umask(0o077)
    if os.geteuid() != 0 or Path("/proc/1/comm").read_text().strip() != "systemd":
        raise RuntimeError("A disposable Linux host running systemd as PID 1 is required.")
    units = ["gaeld-" + name + ".service" for name in ("fpm", "web", "horizon", "scheduler")]
    if any(
        output("systemctl", "show", unit, "--property=LoadState", "--value") != "not-found" for unit in units
    ):
        raise RuntimeError("Existing managed service units are present. Choose a clean host.")
    candidate, root = Path(args.candidate).resolve(), Path(args.root).resolve()
    if root.exists():
        raise RuntimeError("The installation root must be new.")
    sys.path.insert(0, str(candidate / "tests/distribution"))
    sys.path.insert(0, str(candidate / "scripts/distribution"))
    from adoption_rehearsal import login

    work = Path(args.working).resolve()
    work.mkdir(mode=0o700)
    base = work / "served"
    release = base / ("v" + args.version)
    release.mkdir(parents=True)
    source = release / "gaeld-ce.tar.gz"
    shutil.copyfile(args.source, source)
    images = json.loads(Path(args.images).read_text())
    key, pub, cert, tls = work / "release.key", work / "release.pub", work / "https.crt", work / "https.key"
    execute("openssl", "genpkey", "-algorithm", "ED25519", "-out", key)
    execute("openssl", "pkey", "-in", key, "-pubout", "-out", pub)
    execute(
        "openssl",
        "req",
        "-new",
        "-x509",
        "-newkey",
        "rsa:2048",
        "-noenc",
        "-days",
        "2",
        "-subj",
        "/CN=localhost",
        "-addext",
        "subjectAltName=DNS:localhost,IP:127.0.0.1",
        "-keyout",
        tls,
        "-out",
        cert,
        stderr=subprocess.DEVNULL,
    )

    class Handler(http.server.SimpleHTTPRequestHandler):
        def log_message(self, *args):
            pass

    server = http.server.ThreadingHTTPServer(
        ("127.0.0.1", 0), functools.partial(Handler, directory=str(base))
    )
    context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    context.minimum_version = ssl.TLSVersion.TLSv1_2
    context.load_cert_chain(cert, tls)
    server.socket = context.wrap_socket(server.socket, server_side=True)
    origin = "https://localhost:" + str(server.server_address[1])
    os.environ["SSL_CERT_FILE"] = str(cert)
    threading.Thread(target=server.serve_forever, daemon=True).start()
    write(
        release / "provenance.json",
        {
            "channel": "native-rehearsal",
            "commit": args.commit,
            "source_sha256": digest(source),
            "images": images,
        },
    )
    with tarfile.open(source) as packed:
        packages = json.load(packed.extractfile("./composer.lock"))["packages"]
    write(
        release / "sbom.json",
        {
            "spdxVersion": "SPDX-2.3",
            "packages": [{"name": i["name"], "versionInfo": i["version"]} for i in packages],
        },
    )
    manifest = {
        "schema": 1,
        "channel": "rehearsal",
        "version": args.version,
        "commit": args.commit,
        "edition": "ce",
        "boundary_contract": "1.0.0",
        "minimum_version": "3.8.28",
        "allowed_from": ["3.8.28", "3.9.0", "3.10.0"],
        "migration_policy": "forward-only",
        "compatible_ee_versions": [],
        "postgres_major": 16,
        "redis_major": 7,
        "images": images,
    }
    repositories = {role: reference.split("@")[0] for role, reference in images.items()}
    managed_redis = repositories["redis"] == "ghcr.io/scanix/gaeld-redis"
    if managed_redis:
        manifest["redis_distribution"] = "gaeld"
        repositories["redis"] = "docker.io/library/redis"
    for role, name in [("source", source.name), ("provenance", "provenance.json"), ("sbom", "sbom.json")]:
        manifest[role] = {"url": origin + "/v" + args.version + "/" + name, "sha256": digest(release / name)}
    write(release / "release.json", manifest)
    execute(
        "openssl",
        "pkeyutl",
        "-sign",
        "-inkey",
        key,
        "-rawin",
        "-in",
        release / "release.json",
        "-out",
        release / "release.json.sig",
    )
    policy = work / "policy.json"
    write(
        policy,
        {
            "schema": 1,
            "channel": "rehearsal",
            "release_origin": origin,
            "release_key_sha256": digest(pub),
            "images": repositories,
            **({"managed_redis_repository": "ghcr.io/scanix/gaeld-redis"} if managed_redis else {}),
        },
    )
    identity = work / "recovery.agekey"
    execute("age-keygen", "-o", identity, stderr=subprocess.DEVNULL)
    recipient = output("age-keygen", "-y", identity)
    admin = {
        "name": "Qualification Owner",
        "email": "native-owner@example.invalid",
        "password": secrets.token_urlsafe(28) + "a7",
        "organization": "Native qualification",
        "currency": "CHF",
        "locale": "en",
    }
    admin_file = work / "admin.json"
    write(admin_file, admin)
    admin_file.chmod(0o600)
    launcher = candidate / "gaeld"
    url = "http://127.0.0.1:18281"
    execute(
        launcher,
        "install",
        "--to",
        args.version,
        "--mode",
        "native",
        "--root",
        root,
        "--release-url",
        origin,
        "--trust-key",
        pub,
        "--distribution-policy",
        policy,
        "--environment-file",
        args.environment,
        "--backup-recipient",
        recipient,
        "--identity-file",
        identity,
        "--admin-secret-file",
        admin_file,
        "--port",
        "18281",
        "--url",
        url,
    )
    execute(launcher, "doctor", "--root", root)
    assert all(output("systemctl", "is-active", unit) == "active" for unit in units)
    credentials = {field: admin[field] for field in ("email", "password")}
    login(url, credentials)
    marker = root / "shared/storage/app/private/qualification.txt"
    marker.write_text(secrets.token_hex(40))
    checksum = digest(marker)
    execute(launcher, "backup", "--root", root, "--identity-file", identity)
    archive = max((root / "backups").glob("*.tar.age"), key=lambda path: path.stat().st_mtime_ns)
    marker.write_text("changed after verified backup")
    execute(
        launcher,
        "restore",
        "--root",
        root,
        "--archive",
        archive,
        "--identity-file",
        identity,
        "--confirm-replace-data",
    )
    assert digest(marker) == checksum
    execute(launcher, "doctor", "--root", root)
    assert all(output("systemctl", "is-active", unit) == "active" for unit in units)
    login(url, credentials)
    write(
        Path(args.report),
        {
            "schema": 1,
            "status": "passed",
            "kind": "native-systemd",
            "candidate_version": args.version,
            "candidate_commit": args.commit,
            "source_sha256": digest(source),
            "candidate_images": images,
            "architecture": output("uname", "-m"),
            "os": Path("/etc/os-release").read_text().splitlines()[0],
            "checks": dict.fromkeys(
                [
                    "fresh_systemd_host",
                    "fresh_install",
                    "owner_login",
                    "all_four_services",
                    "encrypted_backup",
                    "restore",
                    "existing_owner_login",
                    "original_file_recovered",
                ],
                True,
            ),
        },
    )
    server.shutdown()


if __name__ == "__main__":
    main()
