#!/usr/bin/env python3
"""Build the document signed by the protected public-release workflow."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import subprocess

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
    verify_adoption_receipt(args.adoption_receipt, version=args.version, commit=args.commit,
                            source=args.source, images=images)
    allowed = json.loads(args.allowed_from)
    if not isinstance(allowed, list) or not allowed or not all(re.fullmatch(r'\d+\.\d+\.\d+(?:-rc\.\d+)?', item) for item in allowed):
        raise ValueError('An exact tested upgrade path is required')
    compatible_ee = json.loads(args.compatible_ee_versions)
    if not isinstance(compatible_ee, list) or not all(re.fullmatch(r'\d+\.\d+\.\d+(?:-rc\.\d+)?', item) for item in compatible_ee):
        raise ValueError('EE versions must be exact')
    if compatible_ee:
        if not all((args.ee_receipt, args.ee_receipt_signature, args.ee_receipt_key)):
            raise ValueError('A signed exact-pair acceptance receipt is required before declaring EE compatibility')
        subprocess.run(['openssl', 'pkeyutl', '-verify', '-pubin', '-inkey', args.ee_receipt_key,
                        '-rawin', '-in', args.ee_receipt, '-sigfile', args.ee_receipt_signature], check=True)
        receipt = json.loads(Path(args.ee_receipt).read_text())
        if receipt.get('ce_version') != args.version or receipt.get('ce_commit') != args.commit or receipt.get('status') != 'passed' or receipt.get('boundary_contract') != '1.0.0' or sorted(receipt.get('ee_versions', [])) != sorted(compatible_ee):
            raise ValueError('EE receipt does not prove these exact CE/EE pairs')
    base = f'https://github.com/Scanix/Gaeld/releases/download/v{args.version}/'
    result = {'schema': 1, 'version': args.version, 'commit': args.commit, 'edition': 'ce',
              'boundary_contract': '1.0.0', 'minimum_version': '3.8.28', 'allowed_from': allowed,
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
