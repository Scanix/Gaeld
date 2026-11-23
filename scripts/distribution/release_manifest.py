#!/usr/bin/env python3
"""Build the document signed by the protected public-release workflow."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import subprocess

from adopt import SAIL_RELEASES
from manage import EE_ACCEPTANCE_KEY_SHA256, version as version_order
from verify_adoption_receipt import verify as verify_adoption_receipt


def sha256(path):
    with Path(path).open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


def main():
    parser = argparse.ArgumentParser()
    for name in ('version', 'commit', 'source', 'app-digest', 'web-digest', 'postgres-digest', 'redis-digest', 'allowed-from', 'provenance', 'sbom', 'adoption-receipt', 'output'):
        parser.add_argument('--' + name, required=True)
    parser.add_argument('--compatible-ee-versions', default='[]')
    parser.add_argument('--ee-receipt')
    parser.add_argument('--ee-receipt-signature')
    parser.add_argument('--ee-receipt-key')
    args = parser.parse_args()
    if not re.fullmatch(r'\d+\.\d+\.\d+(?:-rc\.\d+)?', args.version) or not re.fullmatch(r'[0-9a-f]{40}', args.commit):
        raise ValueError('Exact version and source commit required')
    for value in (args.app_digest, args.web_digest, args.postgres_digest, args.redis_digest):
        if not re.fullmatch(r'sha256:[0-9a-f]{64}', value):
            raise ValueError('Every image must use an immutable OCI digest')
    images = {'app': 'ghcr.io/scanix/gaeld@' + args.app_digest,
              'web': 'ghcr.io/scanix/gaeld-web@' + args.web_digest,
              'postgres': 'docker.io/library/postgres@' + args.postgres_digest,
              'redis': 'docker.io/library/redis@' + args.redis_digest}
    allowed = json.loads(args.allowed_from)
    if args.version.split('-')[0] != '3.11.0' or not isinstance(allowed, list) or sorted(allowed) != sorted(SAIL_RELEASES):
        raise ValueError('The v3.11 installer requires exact rehearsals from every supported Sail release.')
    evidence = json.loads(Path(args.adoption_receipt).read_text())
    if not isinstance(evidence, list):
        raise ValueError('A complete adoption matrix is required.')
    observed = set()
    for receipt in evidence:
        if not isinstance(receipt, dict) or receipt.get('host_system') != 'Linux' or receipt.get('baseline_version') not in allowed:
            raise ValueError('The adoption matrix contains an unknown baseline.')
        previous = receipt['baseline_version']
        tag_commit = subprocess.check_output(['git', 'rev-parse', f'refs/tags/v{previous}^{{commit}}'], text=True).strip()
        verify_adoption_receipt(receipt, version=args.version, commit=args.commit, source=args.source,
                                images=images, baseline_version=previous, baseline_commit=tag_commit)
        observed.add((previous, receipt['platform'], receipt['source_kind']))
    required = {(previous, platform, 'git') for previous in allowed for platform in ('linux/amd64', 'linux/arm64')}
    if not required.issubset(observed):
        raise ValueError('The adoption matrix is missing a supported Git baseline or Docker architecture.')
    if not any(kind == 'archive' for _, _, kind in observed):
        raise ValueError('A published source-archive adoption rehearsal is required.')
    compatible_ee = json.loads(args.compatible_ee_versions)
    if not isinstance(compatible_ee, list) or not all(re.fullmatch(r'\d+\.\d+\.\d+(?:-rc\.\d+)?', item) for item in compatible_ee):
        raise ValueError('EE versions must be exact')
    if compatible_ee:
        if not all((args.ee_receipt, args.ee_receipt_signature, args.ee_receipt_key)):
            raise ValueError('A signed exact-pair acceptance receipt is required before declaring EE compatibility')
        if sha256(args.ee_receipt_key) != EE_ACCEPTANCE_KEY_SHA256:
            raise ValueError('The EE acceptance key fingerprint differs from the pinned project key.')
        subprocess.run(['openssl', 'pkeyutl', '-verify', '-pubin', '-inkey', args.ee_receipt_key,
                        '-rawin', '-in', args.ee_receipt, '-sigfile', args.ee_receipt_signature], check=True)
        receipt = json.loads(Path(args.ee_receipt).read_text())
        if receipt.get('ce_version') != args.version or receipt.get('ce_commit') != args.commit or receipt.get('status') != 'passed' or receipt.get('boundary_contract') != '1.0.0' or sorted(receipt.get('ee_versions', [])) != sorted(compatible_ee):
            raise ValueError('EE receipt does not prove these exact CE/EE pairs')
    base = f'https://github.com/Scanix/Gaeld/releases/download/v{args.version}/'
    result = {'schema': 1, 'version': args.version, 'commit': args.commit, 'edition': 'ce',
              'boundary_contract': '1.0.0', 'minimum_version': min(allowed, key=version_order), 'allowed_from': allowed,
              'migration_policy': 'forward-only', 'compatible_ee_versions': compatible_ee, 'postgres_major': 16, 'redis_major': 7,
              'images': images,
              'source': {'url': base + Path(args.source).name, 'sha256': sha256(args.source)},
              'provenance': {'url': base + Path(args.provenance).name, 'sha256': sha256(args.provenance)},
              'sbom': {'url': base + Path(args.sbom).name, 'sha256': sha256(args.sbom)},
              'adoption_acceptance': {'url': base + Path(args.adoption_receipt).name,
                                      'sha256': sha256(args.adoption_receipt)}}
    if compatible_ee:
        result['ee_acceptance'] = {'url': base + Path(args.ee_receipt).name, 'sha256': sha256(args.ee_receipt)}
    Path(args.output).write_text(json.dumps(result, sort_keys=True, indent=2) + '\n')


if __name__ == '__main__':
    main()
