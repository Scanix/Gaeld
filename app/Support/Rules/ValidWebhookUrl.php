<?php

namespace App\Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidWebhookUrl implements ValidationRule
{
    /**
     * Blocked IPv4 ranges (RFC 1918, loopback, link-local, cloud metadata).
     */
    private const BLOCKED_CIDRS = [
        '127.0.0.0/8',       // Loopback
        '10.0.0.0/8',        // RFC 1918
        '172.16.0.0/12',     // RFC 1918
        '192.168.0.0/16',    // RFC 1918
        '169.254.0.0/16',    // Link-local / cloud metadata
        '0.0.0.0/8',         // "This" network
        '100.64.0.0/10',     // Shared address space (RFC 6598)
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $host = parse_url($value, PHP_URL_HOST);

        if (! $host) {
            $fail('The :attribute must contain a valid host.');

            return;
        }

        // Block common localhost names
        $lower = strtolower($host);
        if (in_array($lower, ['localhost', 'host.docker.internal', 'kubernetes.default.svc'], true)) {
            $fail('The :attribute must not point to an internal address.');

            return;
        }

        // Resolve hostname to IP
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        // If DNS resolution failed gethostbyname returns the input
        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            $fail('The :attribute could not be resolved to an IP address.');

            return;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // For IPv6, block loopback ::1 and link-local fe80::/10
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                if ($ip === '::1' || str_starts_with(strtolower($ip), 'fe80')) {
                    $fail('The :attribute must not point to an internal address.');
                }
            }

            return;
        }

        foreach (self::BLOCKED_CIDRS as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                $fail('The :attribute must not point to an internal or reserved address.');

                return;
            }
        }
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = -1 << (32 - (int) $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
