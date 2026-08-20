<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/**
 * Configures HTTPS URL generation for deployments behind a TLS-terminating
 * reverse proxy (Pangolin, Traefik, nginx, Cloudflare Tunnel, ...).
 *
 * Enable by setting FORCE_HTTPS=true or by using an https:// APP_URL.
 * See {@see config/proxy.php} for the full contract.
 */
class HttpsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->shouldForceHttps()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Resolve the force_https setting, falling back to APP_URL auto-detection
     * when the user did not opt in or out explicitly.
     *
     * Uses config() (not env()) so behaviour is stable under `config:cache`.
     */
    private function shouldForceHttps(): bool
    {
        $configured = config('proxy.force_https');

        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        return str_starts_with((string) config('app.url'), 'https://');
    }
}
