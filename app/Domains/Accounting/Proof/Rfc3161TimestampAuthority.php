<?php

namespace App\Domains\Accounting\Proof;

use App\Domains\Accounting\Proof\Contracts\TimestampAuthority;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class Rfc3161TimestampAuthority implements TimestampAuthority
{
    public function __construct(private readonly ProcessRunner $runner) {}

    public function timestamp(string $source, string $evidencePrefix): array
    {
        $url = (string) config('archiving.timestamp.url');
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! parse_url($url, PHP_URL_HOST) || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
            throw new RuntimeException('A configured HTTPS RFC 3161 authority is required.');
        }
        $trust = EvidenceFiles::required((string) config('archiving.timestamp.trust_store'));
        $crl = EvidenceFiles::required((string) config('archiving.timestamp.crl'));
        $openssl = (string) config('archiving.openssl');
        $query = $evidencePrefix.'.tsq';
        $response = $evidencePrefix.'.tsr';
        if (! is_file($query)) {
            $queryBytes = $this->runner->run([$openssl, 'ts', '-query', '-data', EvidenceFiles::required($source), '-sha256', '-cert']);
            EvidenceFiles::writeOnce($query, $queryBytes);
        }
        if (! is_file($response)) {
            $request = Http::connectTimeout(10)->timeout((int) config('archiving.timestamp.timeout', 30))
                ->withOptions(['allow_redirects' => false])
                ->accept('application/timestamp-reply')->withBody(EvidenceFiles::read($query), 'application/timestamp-query');
            if (config('archiving.timestamp.tls_ca_bundle')) {
                $request = $request->withOptions(['verify' => EvidenceFiles::required((string) config('archiving.timestamp.tls_ca_bundle'))]);
            }
            $request = $this->authenticate($request);
            try {
                $reply = $request->send('POST', $url);
            } catch (Throwable) {
                throw new RuntimeException('The timestamp authority could not be reached securely.');
            }
            if (! $reply->successful()) {
                throw new RuntimeException('The timestamp authority rejected the request (HTTP '.$reply->status().').');
            }
            if (! str_starts_with(strtolower($reply->header('Content-Type')), 'application/timestamp-reply') || strlen($reply->body()) > 1024 * 1024) {
                throw new RuntimeException('The timestamp authority returned an unexpected response.');
            }
            $temporary = $response.'.'.bin2hex(random_bytes(8)).'.tmp';
            EvidenceFiles::writeOnce($temporary, $reply->body());
            try {
                $this->verify($source, $query, $temporary, $trust, $crl);
                EvidenceFiles::writeOnce($response, $reply->body());
            } finally {
                @unlink($temporary);
            }
        }
        $this->verify($source, $query, $response, $trust, $crl);
        EvidenceFiles::writeOnce($evidencePrefix.'.crl.pem', EvidenceFiles::read($crl));
        if (config('archiving.timestamp.chain')) {
            EvidenceFiles::writeOnce($evidencePrefix.'.chain.pem', EvidenceFiles::read(EvidenceFiles::required((string) config('archiving.timestamp.chain'))));
        }
        $token = EvidenceFiles::temporary('gaeld-ts-token-');
        try {
            file_put_contents($token, $this->runner->run([$openssl, 'ts', '-reply', '-in', $response, '-token_out']));
            $certificates = $this->runner->run([$openssl, 'pkcs7', '-inform', 'DER', '-in', $token, '-print_certs']);
            EvidenceFiles::writeOnce($evidencePrefix.'.certificates.pem', $certificates);
        } finally {
            @unlink($token);
        }

        return ['response' => basename($response), 'sha256' => EvidenceFiles::hash($response), 'protocol' => 'RFC3161'];
    }

    private function authenticate(PendingRequest $request): PendingRequest
    {
        $token = (string) config('archiving.timestamp.bearer_token');
        $tokenFile = (string) config('archiving.timestamp.bearer_token_file');
        $usernameFile = (string) config('archiving.timestamp.basic_username_file');
        $passwordFile = (string) config('archiving.timestamp.basic_password_file');
        if (($token !== '' && $tokenFile !== '') || (($token !== '' || $tokenFile !== '') && ($usernameFile !== '' || $passwordFile !== ''))) {
            throw new RuntimeException('Configure exactly one timestamp authentication method.');
        }
        if ($usernameFile !== '' || $passwordFile !== '') {
            if ($usernameFile === '' || $passwordFile === '') {
                throw new RuntimeException('Timestamp Basic authentication requires both credential files.');
            }
            $username = $this->secret($usernameFile);
            $password = $this->secret($passwordFile);
            if (str_contains($username, ':')) {
                throw new RuntimeException('Timestamp Basic authentication has an invalid username.');
            }

            return $request->withBasicAuth($username, $password);
        }
        if ($tokenFile !== '') {
            $token = $this->secret($tokenFile);
        }

        return $token === '' ? $request : $request->withToken($token);
    }

    private function secret(string $path): string
    {
        $value = rtrim(EvidenceFiles::read(EvidenceFiles::required($path)), "\r\n");
        if ($value === '' || str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new RuntimeException('A timestamp credential file is empty or invalid.');
        }

        return $value;
    }

    private function verify(string $source, string $query, string $response, string $trust, string $crl): void
    {
        $bundle = EvidenceFiles::temporary('gaeld-ts-trust-');
        $emptyTrust = $bundle.'.empty';
        mkdir($emptyTrust, 0700);
        file_put_contents($bundle, EvidenceFiles::read($trust)."\n".EvidenceFiles::read($crl));
        try {
            $arguments = [(string) config('archiving.openssl'), 'ts', '-verify', '-in', $response, '-CAfile', $bundle, '-CApath', $emptyTrust, '-CAstore', $emptyTrust, '-crl_check_all'];
            if (config('archiving.timestamp.chain')) {
                $arguments = [...$arguments, '-untrusted', EvidenceFiles::required((string) config('archiving.timestamp.chain'))];
            }
            $this->runner->run([...$arguments, '-data', $source]);
            $this->runner->run([...$arguments, '-queryfile', $query]);
        } finally {
            @unlink($bundle);
            @rmdir($emptyTrust);
        }
    }
}
