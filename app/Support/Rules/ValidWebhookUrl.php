<?php

namespace App\Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidWebhookUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::transportOptions($value) === null) {
            $fail('The :attribute must be an HTTPS URL resolving to a public address.');
        }
    }

    /**
     * Pin the validated address for this HTTP attempt. Keep TLS hostname checks
     * on the original URL and prevent redirects or a proxy from resolving it again.
     *
     * @return array{allow_redirects: false, curl: array<int, mixed>}|null
     */
    public static function transportOptions(string $url): ?array
    {
        if (! extension_loaded('curl') || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $host = trim($parts['host'] ?? '', '[]');
        $literal = filter_var($host, FILTER_VALIDATE_IP) !== false;
        if (! $literal && ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return null;
        }

        $records = $literal ? [['ip' => $host]] : dns_get_record($host, DNS_A | DNS_AAAA);
        foreach ($records ?: [] as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? '';
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                continue;
            }

            $curl = [CURLOPT_PROXY => ''];
            if (! $literal) {
                $port = $parts['port'] ?? 443;
                $address = str_contains($ip, ':') ? "[{$ip}]" : $ip;
                $curl[CURLOPT_RESOLVE] = ["{$host}:{$port}:{$address}"];
            }

            return ['allow_redirects' => false, 'curl' => $curl];
        }

        return null;
    }
}
