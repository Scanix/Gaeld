"""Compare image contents across Docker stores without equating OCI envelopes.

Configuration bytes and ordered uncompressed layer digests identify the payload.
The compressed layer descriptors of the HTTPS registry manifest are recorded
separately; Docker verifies those blobs when the rehearsal pulls that manifest.
"""
import gzip
import hashlib
import json
from pathlib import PurePosixPath
import re
import tarfile
import urllib.error
import urllib.parse
import urllib.request


DIGEST = re.compile(r'sha256:[a-f0-9]{64}')
MANIFEST_TYPES = ('application/vnd.oci.image.manifest.v1+json',
                  'application/vnd.docker.distribution.manifest.v2+json')


def _digest(data):
    return 'sha256:' + hashlib.sha256(data).hexdigest()


def _stream_digest(stream):
    hasher = hashlib.sha256()
    for block in iter(lambda: stream.read(1024 * 1024), b''):
        hasher.update(block)
    return 'sha256:' + hasher.hexdigest()


def _path(name):
    if not isinstance(name, str) or not name or '\\' in name:
        raise ValueError('Invalid saved image member path.')
    path = PurePosixPath(name)
    if path.is_absolute() or '..' in path.parts:
        raise ValueError('Unsafe saved image member path.')
    return str(path)


def _config_payload(content):
    config = json.loads(content)
    rootfs = config.get('rootfs', {})
    diffs = rootfs.get('diff_ids')
    if rootfs.get('type') != 'layers' or not isinstance(diffs, list) or any(not isinstance(value, str) or not DIGEST.fullmatch(value) for value in diffs):
        raise ValueError('Image configuration has invalid ordered layer DiffIDs.')
    os_name, architecture = config.get('os'), config.get('architecture')
    if not isinstance(os_name, str) or not isinstance(architecture, str) or not os_name or not architecture:
        raise ValueError('Image configuration has no target platform.')
    return {'config_digest': _digest(content), 'diff_ids': diffs, 'platform': os_name + '/' + architecture}


def saved_image_payload(archive):
    """Read and verify a single-platform docker save tar without extracting it."""
    with tarfile.open(archive, 'r:*') as saved:
        members = {}
        for member in saved.getmembers():
            name = _path(member.name)
            if name in members or not (member.isfile() or member.isdir()):
                raise ValueError('Saved image contains duplicate or unsupported members.')
            members[name] = member

        def read_json_blob(name):
            member = members.get(_path(name))
            if not member or not member.isfile() or member.size > 16 * 1024 * 1024:
                raise ValueError('Saved image metadata is missing or too large.')
            return saved.extractfile(member).read()

        manifest = json.loads(read_json_blob('manifest.json'))
        if not isinstance(manifest, list) or len(manifest) != 1:
            raise ValueError('Save exactly one platform image before comparing payloads.')
        entry = manifest[0]
        config_name = _path(entry['Config'])
        config = read_json_blob(config_name)
        payload = _config_payload(config)
        config_file = PurePosixPath(config_name)
        declared_config = config_file.stem if config_file.suffix == '.json' else config_file.name
        if payload['config_digest'] != 'sha256:' + declared_config:
            raise ValueError('Saved image configuration does not match its content address.')
        layers = entry.get('Layers')
        if not isinstance(layers, list) or len(layers) != len(payload['diff_ids']):
            raise ValueError('Saved layers do not match the configuration.')
        compressed = []
        for name, expected in zip(layers, payload['diff_ids']):
            member = members.get(_path(name))
            if not member or not member.isfile():
                raise ValueError('Saved image layer is missing.')
            with saved.extractfile(member) as stream:
                digest = _stream_digest(stream)
                stream.seek(0)
                magic = stream.read(4)
                stream.seek(0)
                if magic.startswith(b'\x1f\x8b'):
                    with gzip.GzipFile(fileobj=stream) as uncompressed:
                        diff = _stream_digest(uncompressed)
                elif magic == b'\x28\xb5\x2f\xfd':
                    raise ValueError('Zstandard saved layers require a separate validated decoder.')
                else:
                    diff = digest
            if diff != expected:
                raise ValueError('Saved image layer does not match its uncompressed DiffID.')
            if _path(name).startswith('blobs/sha256/') and PurePosixPath(name).name != digest.removeprefix('sha256:'):
                raise ValueError('Saved image layer does not match its content address.')
            compressed.append(digest)
        return {**payload, 'saved_layer_digests': compressed}


class _NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, response, code, message, headers, new_url):
        raise ValueError('Registry proof requests must not redirect away from the configured TLS endpoint.')


def _https_read(url, accept):
    if urllib.parse.urlsplit(url).scheme != 'https':
        raise ValueError('Registry proof requests require HTTPS.')
    # The standard HTTPS handler validates certificates using the process trust
    # store, including an explicitly configured SSL_CERT_FILE in the rehearsal.
    opener = urllib.request.build_opener(_NoRedirect())
    request = urllib.request.Request(url, headers={'Accept': accept})
    with opener.open(request, timeout=60) as response:
        content = response.read(16 * 1024 * 1024 + 1)
    if len(content) > 16 * 1024 * 1024:
        raise ValueError('Registry image metadata is too large.')
    return content


def registry_image_payload(image, expected):
    """Verify registry manifest/config bytes against a previously saved payload."""
    match = re.fullmatch(r'([a-zA-Z0-9.-]+(?::[0-9]+)?)/([a-z0-9._/-]+)@(sha256:[a-f0-9]{64})', image)
    if not match or any(part in ('', '.', '..') for part in match[2].split('/')):
        raise ValueError('Use a full registry/repository reference pinned by SHA-256.')
    authority, repository, manifest_digest = match.groups()
    base = 'https://' + authority + '/v2/' + repository
    manifest_bytes = _https_read(base + '/manifests/' + manifest_digest, ', '.join(MANIFEST_TYPES))
    if _digest(manifest_bytes) != manifest_digest:
        raise ValueError('Registry manifest bytes do not match the pinned digest.')
    manifest = json.loads(manifest_bytes)
    if manifest.get('schemaVersion') != 2 or manifest.get('mediaType') not in MANIFEST_TYPES:
        raise ValueError('Registry proof requires a single-platform OCI or Docker v2 manifest.')
    descriptor = manifest.get('config', {})
    config_digest = descriptor.get('digest', '')
    if not DIGEST.fullmatch(config_digest):
        raise ValueError('Registry configuration descriptor has no SHA-256 digest.')
    config = _https_read(base + '/blobs/' + config_digest, 'application/octet-stream')
    if _digest(config) != config_digest or descriptor.get('size') != len(config):
        raise ValueError('Registry configuration bytes do not match the descriptor.')
    payload = _config_payload(config)
    if any(payload[key] != expected.get(key) for key in ('config_digest', 'diff_ids', 'platform')):
        raise ValueError('Imported registry image differs from the saved functional payload.')
    layers = manifest.get('layers')
    if not isinstance(layers, list) or len(layers) != len(payload['diff_ids']):
        raise ValueError('Registry layer descriptors do not match the image configuration.')
    for layer in layers:
        if not isinstance(layer, dict) or not DIGEST.fullmatch(layer.get('digest', '')) or type(layer.get('size')) is not int or layer['size'] < 0:
            raise ValueError('Registry layer descriptor is invalid.')
    return {**payload, 'image': image, 'manifest_digest': manifest_digest,
            'oci_layer_digests': [layer['digest'] for layer in layers],
            'oci_layers': layers, 'layer_blobs_verified_by': 'docker-pull'}
