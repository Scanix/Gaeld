import gzip
import hashlib
import io
import json
from pathlib import Path
import sys
import tarfile
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parent))
import image_payload


def digest(content):
    return 'sha256:' + hashlib.sha256(content).hexdigest()


class ImagePayloadTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.archive = Path(self.temporary.name) / 'image.tar'
        self.layer = b'unchanged uncompressed layer bytes'
        self.compressed = gzip.compress(self.layer)
        self.config = json.dumps({'os': 'linux', 'architecture': 'arm64',
                                 'config': {'Entrypoint': ['/production-entrypoint']},
                                 'rootfs': {'type': 'layers', 'diff_ids': [digest(self.layer)]}}).encode()
        self.config_name = digest(self.config).removeprefix('sha256:') + '.json'
        self.layer_name = 'blobs/sha256/' + digest(self.compressed).removeprefix('sha256:')
        self.manifest = [{'Config': self.config_name, 'RepoTags': ['test/image:input'], 'Layers': [self.layer_name]}]

    def save(self, *, manifest=None, config=None, layer=None, extra=None):
        entries = [('manifest.json', json.dumps(self.manifest if manifest is None else manifest).encode()),
                   (self.config_name, self.config if config is None else config),
                   (self.layer_name, self.compressed if layer is None else layer)]
        if extra:
            entries.append(extra)
        with tarfile.open(self.archive, 'w') as archive:
            for name, content in entries:
                member = tarfile.TarInfo(name)
                member.size = len(content)
                archive.addfile(member, io.BytesIO(content))
        return self.archive

    def registry_manifest(self, config=None, layers=None):
        content = self.config if config is None else config
        manifest = {'schemaVersion': 2, 'mediaType': 'application/vnd.oci.image.manifest.v1+json',
                    'config': {'mediaType': 'application/vnd.oci.image.config.v1+json', 'digest': digest(content), 'size': len(content)},
                    'layers': [{'mediaType': 'application/vnd.oci.image.layer.v1.tar+gzip',
                                'digest': digest(self.compressed), 'size': len(self.compressed)}] if layers is None else layers}
        return json.dumps(manifest).encode()

    def test_saved_payload_verifies_configuration_and_uncompressed_layer_bytes(self):
        payload = image_payload.saved_image_payload(self.save())
        self.assertEqual(digest(self.config), payload['config_digest'])
        self.assertEqual([digest(self.layer)], payload['diff_ids'])
        self.assertEqual([digest(self.compressed)], payload['saved_layer_digests'])
        self.assertEqual('linux/arm64', payload['platform'])

    def test_saved_payload_rejects_tampered_config_and_layer(self):
        with self.assertRaisesRegex(ValueError, 'configuration does not match'):
            image_payload.saved_image_payload(self.save(config=self.config + b' '))
        with self.assertRaisesRegex(ValueError, 'uncompressed DiffID'):
            image_payload.saved_image_payload(self.save(layer=gzip.compress(b'different image contents')))

    def test_saved_payload_rejects_ambiguity_and_unsafe_members(self):
        with self.assertRaisesRegex(ValueError, 'exactly one platform'):
            image_payload.saved_image_payload(self.save(manifest=self.manifest * 2))
        for extra in [('manifest.json', b'[]'), ('../escape', b'file')]:
            with self.subTest(name=extra[0]), self.assertRaises(ValueError):
                image_payload.saved_image_payload(self.save(extra=extra))

    def test_registry_verifies_real_config_and_records_distinct_oci_envelope(self):
        expected = image_payload.saved_image_payload(self.save())
        manifest = self.registry_manifest()
        reference = 'registry.example:5000/test/app@' + digest(manifest)
        with patch.object(image_payload, '_https_read', side_effect=[manifest, self.config]) as request:
            result = image_payload.registry_image_payload(reference, expected)
        self.assertEqual(expected['config_digest'], result['config_digest'])
        self.assertEqual(expected['diff_ids'], result['diff_ids'])
        self.assertEqual([digest(self.compressed)], result['oci_layer_digests'])
        self.assertEqual(digest(manifest), result['manifest_digest'])
        self.assertTrue(all(call.args[0].startswith('https://registry.example:5000/v2/test/app/') for call in request.call_args_list))

    def test_registry_rejects_manifest_config_and_payload_substitution(self):
        expected = image_payload.saved_image_payload(self.save())
        manifest = self.registry_manifest()
        reference = 'registry.example/test/app@' + digest(manifest)
        with patch.object(image_payload, '_https_read', return_value=manifest + b' '), self.assertRaisesRegex(ValueError, 'manifest bytes'):
            image_payload.registry_image_payload(reference, expected)
        with patch.object(image_payload, '_https_read', side_effect=[manifest, self.config + b' ']), self.assertRaisesRegex(ValueError, 'configuration bytes'):
            image_payload.registry_image_payload(reference, expected)
        changed_config = self.config.replace(b'production-entrypoint', b'different-entrypoint')
        changed_manifest = self.registry_manifest(config=changed_config)
        reference = 'registry.example/test/app@' + digest(changed_manifest)
        with patch.object(image_payload, '_https_read', side_effect=[changed_manifest, changed_config]), self.assertRaisesRegex(ValueError, 'functional payload'):
            image_payload.registry_image_payload(reference, expected)

    def test_registry_rejects_missing_layers_and_changed_diffid_expectation(self):
        expected = image_payload.saved_image_payload(self.save())
        manifest = self.registry_manifest(layers=[])
        reference = 'registry.example/test/app@' + digest(manifest)
        with patch.object(image_payload, '_https_read', side_effect=[manifest, self.config]), self.assertRaisesRegex(ValueError, 'layer descriptors'):
            image_payload.registry_image_payload(reference, expected)
        manifest = self.registry_manifest()
        reference = 'registry.example/test/app@' + digest(manifest)
        expected['diff_ids'] = [digest(b'different base layer')]
        with patch.object(image_payload, '_https_read', side_effect=[manifest, self.config]), self.assertRaisesRegex(ValueError, 'functional payload'):
            image_payload.registry_image_payload(reference, expected)

    def test_network_requests_reject_floating_references_and_insecure_redirects(self):
        with patch.object(image_payload, '_https_read') as request:
            for reference in ('registry.example/test/app:latest', 'http://registry.example/app@sha256:' + 'a' * 64,
                              'registry.example/../app@sha256:' + 'a' * 64):
                with self.subTest(reference=reference), self.assertRaises(ValueError):
                    image_payload.registry_image_payload(reference, {})
            request.assert_not_called()
        with patch.object(image_payload.urllib.request, 'build_opener') as opener, self.assertRaisesRegex(ValueError, 'HTTPS'):
            image_payload._https_read('http://registry.example/v2/test', '*/*')
        opener.assert_not_called()
        with self.assertRaisesRegex(ValueError, 'must not redirect'):
            image_payload._NoRedirect().redirect_request(None, None, 302, '', {}, 'http://untrusted.example')


if __name__ == '__main__':
    unittest.main()
