<?php

namespace Tests\Support;

use App\Domains\Accounting\Proof\ProcessRunner;

trait ArchiveProofFixtures
{
    private function configureProofCertificates(string $directory): void
    {
        mkdir($directory.'/certificates', 0700, true);
        $certs = $directory.'/certificates';
        file_put_contents($certs.'/index.txt', '');
        file_put_contents($certs.'/serial', "1000\n");
        file_put_contents($certs.'/crlnumber', "1000\n");
        file_put_contents($certs.'/tsa.serial', "1000\n");
        file_put_contents($certs.'/ca.cnf', "[ca]\ndefault_ca = ca_default\n[ca_default]\ndatabase = $certs/index.txt\nnew_certs_dir = $certs\ncertificate = $certs/ca.pem\nprivate_key = $certs/ca.key\nserial = $certs/serial\ncrlnumber = $certs/crlnumber\ndefault_md = sha256\ndefault_days = 365\ndefault_crl_days = 30\npolicy = policy\nunique_subject = no\n[policy]\ncommonName = supplied\n[signer]\nbasicConstraints = critical,CA:false\nkeyUsage = critical,digitalSignature\nsubjectKeyIdentifier = hash\nauthorityKeyIdentifier = keyid,issuer\n[tsa]\nbasicConstraints = critical,CA:false\nkeyUsage = critical,digitalSignature\nextendedKeyUsage = critical,timeStamping\nsubjectKeyIdentifier = hash\nauthorityKeyIdentifier = keyid,issuer\n");
        $runner = new ProcessRunner;
        $runner->run(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-noenc', '-keyout', $certs.'/ca.key', '-out', $certs.'/ca.pem', '-days', '3650', '-subj', '/CN=Archive Test CA', '-addext', 'basicConstraints=critical,CA:true', '-addext', 'keyUsage=critical,keyCertSign,cRLSign']);
        foreach (['signer', 'tsa'] as $identity) {
            $runner->run(['openssl', 'req', '-new', '-newkey', 'rsa:2048', '-noenc', '-keyout', $certs.'/'.$identity.'.key', '-out', $certs.'/'.$identity.'.csr', '-subj', '/CN=Archive Test '.$identity]);
            $runner->run(['openssl', 'ca', '-batch', '-config', $certs.'/ca.cnf', '-extensions', $identity, '-in', $certs.'/'.$identity.'.csr', '-out', $certs.'/'.$identity.'.pem']);
        }
        $runner->run(['openssl', 'ca', '-gencrl', '-config', $certs.'/ca.cnf', '-out', $certs.'/ca.crl.pem']);
        file_put_contents($certs.'/tsa.cnf', "[tsa]\ndefault_tsa = tsa_config\n[tsa_config]\nserial = $certs/tsa.serial\ncrypto_device = builtin\nsigner_cert = $certs/tsa.pem\ncerts = $certs/ca.pem\nsigner_key = $certs/tsa.key\nsigner_digest = sha256\ndefault_policy = 1.2.3.4.1\ndigests = sha256\naccuracy = secs:1\nordering = yes\ntsa_name = yes\ness_cert_id_chain = yes\ness_cert_id_alg = sha256\n");
        config([
            'archiving.signing.certificate' => $certs.'/signer.pem',
            'archiving.signing.private_key' => $certs.'/signer.key',
            'archiving.signing.chain' => $certs.'/ca.pem',
            'archiving.signing.trust_store' => $certs.'/ca.pem',
            'archiving.signing.crl' => $certs.'/ca.crl.pem',
            'archiving.signing.allowed_fingerprint' => openssl_x509_fingerprint(file_get_contents($certs.'/signer.pem'), 'sha256'),
            'archiving.timestamp.url' => 'https://tsa.example.test',
            'archiving.timestamp.trust_store' => $certs.'/ca.pem',
            'archiving.timestamp.chain' => $certs.'/ca.pem',
            'archiving.timestamp.crl' => $certs.'/ca.crl.pem',
        ]);
    }

    /** @return array<string, mixed> */
    private function fixtureManifest(string $package): array
    {
        mkdir($package, 0700, true);
        file_put_contents($package.'/journal.pdf', 'pdf-content-for-inventory-unit-tests');
        $manifest = ['schema_version' => '1.0.0', 'proof_profile' => 'gaeld-sealed-package/1.0.0', 'package_id' => 'package-one', 'organization_id' => 'organization-one', 'fiscal_year' => ['id' => 'fiscal-one', 'start_date' => '2026-07-08', 'end_date' => '2027-12-31'], 'version' => 1, 'retention_until' => '2038-01-01T00:00:00+00:00', 'files' => [['path' => 'journal.pdf', 'size' => filesize($package.'/journal.pdf'), 'sha256' => hash_file('sha256', $package.'/journal.pdf'), 'role' => 'report']]];
        file_put_contents($package.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));

        return $manifest;
    }
}
