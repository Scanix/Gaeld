#!/usr/bin/env python3
"""Bind a real rehearsal result to the exact candidate being signed.

The workflow runs the reviewed rehearsal itself; a receipt is not a substitute
for running it, nor an independently authenticated claim from an arbitrary URL.
"""
import argparse
import hashlib
import json
from pathlib import Path
import re


CHECKS = (
    'baseline_3_8_28', 'signed_https_manifest', 'digest_pinned_pull',
    'coherent_encrypted_backup', 'adoption', 'app_key_preserved',
    'documents_preserved', 'queued_jobs_preserved', 'legacy_stopped',
    'managed_health', 'existing_owner_http_login', 'restore',
)


def sha256(path):
    hasher = hashlib.sha256()
    with Path(path).open('rb') as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b''):
            hasher.update(block)
    return hasher.hexdigest()


def verify(receipt_path, *, version, commit, source, images, platform=None):
    receipt = json.loads(Path(receipt_path).read_text())
    if not isinstance(receipt, dict) or receipt.get('schema') != 1 or receipt.get('status') != 'passed':
        raise ValueError('A successful real adoption rehearsal receipt is required.')
    if not re.fullmatch(r'[a-f0-9]{40}', commit) or receipt.get('candidate_commit') != commit:
        raise ValueError('Adoption receipt belongs to a different source commit.')
    if receipt.get('candidate_version') != version or receipt.get('source_tree_clean') is not True:
        raise ValueError('Adoption receipt must cover this exact version and a clean checkout.')
    if receipt.get('source_sha256') != sha256(source):
        raise ValueError('Adoption receipt does not cover the source archive being released.')
    if set(images) != {'app', 'web', 'postgres', 'redis'}:
        raise ValueError('All four exact candidate images are required.')
    if any(not re.fullmatch(r'[^\s@]+@sha256:[a-f0-9]{64}', image) for image in images.values()):
        raise ValueError('Candidate images must be pinned to immutable OCI digests.')
    if receipt.get('candidate_images') != images:
        raise ValueError('Adoption receipt does not cover the exact candidate image digests.')
    actual_platform = receipt.get('platform')
    if actual_platform not in ('linux/amd64', 'linux/arm64') or (platform and actual_platform != platform):
        raise ValueError('Adoption receipt does not cover the required runtime architecture.')
    checks = receipt.get('checks', {})
    if not isinstance(checks, dict) or any(checks.get(check) is not True for check in CHECKS):
        raise ValueError('Adoption receipt has missing or failed recovery checks.')
    return receipt


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    for name in ('receipt', 'version', 'commit', 'source', 'app-image', 'web-image', 'postgres-image', 'redis-image'):
        parser.add_argument('--' + name, required=True)
    parser.add_argument('--platform', choices=('linux/amd64', 'linux/arm64'))
    args = parser.parse_args()
    receipt = verify(args.receipt, version=args.version, commit=args.commit, source=args.source,
                     images={role: getattr(args, role + '_image') for role in ('app', 'web', 'postgres', 'redis')},
                     platform=args.platform)
    print(json.dumps({'status': 'passed', 'platform': receipt['platform'], 'receipt_sha256': sha256(args.receipt)}))


if __name__ == '__main__':
    main()
