<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Self-hosting diagnostic: checks the most common misconfigurations reported
 * by self-hosters running Gäld behind a reverse proxy (Pangolin, Traefik,
 * nginx, Cloudflare Tunnel, ...), plus a handful of foundational checks
 * (app key, database, cache, storage permissions, pending migrations).
 *
 * This does not replace `php artisan about` — it specifically targets the
 * class of "login works but redirects/cookies misbehave" issues that stem
 * from APP_URL / TRUSTED_PROXIES / SESSION_* mismatches.
 */
class GaeldDoctorCommand extends Command
{
    protected $signature = 'gaeld:doctor';

    protected $description = 'Diagnose common self-hosting misconfigurations (reverse proxy, HTTPS, sessions, database)';

    /** @var array<int, string> */
    private array $warnings = [];

    /** @var array<int, string> */
    private array $errors = [];

    public function handle(): int
    {
        $this->components->info('Gäld Doctor — self-hosting diagnostics');
        $this->newLine();

        $this->checkAppKey();
        $this->checkDebugMode();
        $this->checkReverseProxyConfig();
        $this->checkSessionConfig();
        $this->checkDatabase();
        $this->checkCache();
        $this->checkStorage();
        $this->checkPendingMigrations();

        $this->newLine();

        if ($this->errors === [] && $this->warnings === []) {
            $this->components->info('All checks passed. No issues detected.');

            return self::SUCCESS;
        }

        foreach ($this->warnings as $warning) {
            $this->components->warn($warning);
        }

        foreach ($this->errors as $error) {
            $this->components->error($error);
        }

        $this->newLine();
        $this->line(sprintf(
            '<fg=yellow>%d warning(s)</>, <fg=red>%d error(s)</>.',
            count($this->warnings),
            count($this->errors),
        ));

        return $this->errors === [] ? self::SUCCESS : self::FAILURE;
    }

    private function checkAppKey(): void
    {
        if (empty(config('app.key'))) {
            $this->errors[] = 'APP_KEY is not set. Run `php artisan key:generate` — without it sessions, cookies, and encrypted data cannot be secured.';

            return;
        }

        $this->components->task('APP_KEY is set', fn () => true);
    }

    private function checkDebugMode(): void
    {
        if (config('app.env') === 'production' && config('app.debug') === true) {
            $this->errors[] = 'APP_DEBUG=true in a production environment. This leaks stack traces, environment variables, and query bindings to visitors — set APP_DEBUG=false.';

            return;
        }

        $this->components->task('APP_DEBUG is appropriate for this environment', fn () => true);
    }

    private function checkReverseProxyConfig(): void
    {
        $appUrl = (string) config('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME);
        $isHttps = $scheme === 'https';

        $trustedProxies = config('proxy.trusted_proxies');
        $forceHttps = config('proxy.force_https');
        $forceHttpsEffective = $forceHttps !== null && $forceHttps !== ''
            ? filter_var($forceHttps, FILTER_VALIDATE_BOOLEAN)
            : $isHttps;

        if ($isHttps && empty($trustedProxies)) {
            $this->warnings[] = "APP_URL ({$appUrl}) is https:// but TRUSTED_PROXIES is not set. If Gäld sits behind a TLS-terminating reverse proxy (Pangolin, Traefik, nginx, Cloudflare Tunnel, ...), request scheme/IP detection will be wrong until you set TRUSTED_PROXIES (see .env.example).";
        }

        if ($trustedProxies === '*') {
            $this->warnings[] = "TRUSTED_PROXIES=* trusts ANY upstream. This is only safe if the app port is NOT reachable directly from outside your reverse proxy's network. Prefer a specific IP or CIDR range (see .env.example).";
        }

        if ($isHttps && ! $forceHttpsEffective) {
            $this->warnings[] = 'APP_URL is https:// but URL generation is not forced to https:// (FORCE_HTTPS is explicitly disabled). Generated redirect/asset URLs may fall back to http://.';
        }

        if (! $isHttps && filter_var($forceHttps ?? '', FILTER_VALIDATE_BOOLEAN)) {
            $this->warnings[] = 'FORCE_HTTPS=true is set but APP_URL is not https://. Requests will be redirected to an https:// URL that may not actually be served, causing a redirect loop or unreachable page.';
        }

        $this->components->task('Reverse proxy / HTTPS configuration', fn () => true);
    }

    private function checkSessionConfig(): void
    {
        $appUrl = (string) config('app.url');
        $appHost = parse_url($appUrl, PHP_URL_HOST);
        $sessionDomain = config('session.domain');
        $sessionSecure = config('session.secure');
        $isHttps = parse_url($appUrl, PHP_URL_SCHEME) === 'https';

        if ($sessionDomain !== null && $appHost !== null) {
            $normalizedSessionDomain = ltrim((string) $sessionDomain, '.');
            if ($normalizedSessionDomain !== $appHost && ! str_ends_with($appHost, ".{$normalizedSessionDomain}")) {
                $this->warnings[] = "SESSION_DOMAIN ({$sessionDomain}) does not match the APP_URL host ({$appHost}). The session cookie will not be sent back by the browser, and users will appear logged out after login.";
            }
        }

        if ($sessionSecure && ! $isHttps) {
            $this->warnings[] = 'SESSION_SECURE_COOKIE=true but APP_URL is not https://. Browsers will refuse to store/send the session cookie over plain HTTP, and login will silently fail.';
        }

        $this->components->task('Session cookie configuration', fn () => true);
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->components->task('Database connection', fn () => true);
        } catch (\Throwable $e) {
            $this->errors[] = 'Could not connect to the database: '.$e->getMessage();
        }
    }

    private function checkCache(): void
    {
        try {
            $key = 'gaeld-doctor-'.uniqid();
            Cache::put($key, true, 5);
            $ok = Cache::pull($key) === true;
            if (! $ok) {
                $this->warnings[] = 'Cache store did not return the expected value on a write/read round-trip. Check your CACHE_STORE / Redis configuration.';
            } else {
                $this->components->task('Cache store', fn () => true);
            }
        } catch (\Throwable $e) {
            $this->warnings[] = 'Could not reach the cache store: '.$e->getMessage();
        }
    }

    private function checkStorage(): void
    {
        try {
            $disk = Storage::disk(config('filesystems.default'));
            $path = 'gaeld-doctor-'.uniqid().'.tmp';
            $disk->put($path, 'ok');
            $ok = $disk->exists($path);
            $disk->delete($path);

            if (! $ok) {
                $this->errors[] = 'The default storage disk is not writable. Check filesystem permissions on the storage/ directory.';
            } else {
                $this->components->task('Storage is writable', fn () => true);
            }
        } catch (\Throwable $e) {
            $this->errors[] = 'Could not write to the default storage disk: '.$e->getMessage();
        }
    }

    private function checkPendingMigrations(): void
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');

        try {
            if (! $migrator->repositoryExists()) {
                $this->warnings[] = 'The migrations table does not exist yet. Run `php artisan migrate --force`.';

                return;
            }

            $files = $migrator->getMigrationFiles($migrator->paths() ?: [database_path('migrations')]);
            $ran = $migrator->getRepository()->getRan();
            $pending = array_diff(array_keys($files), $ran);
        } catch (\Throwable $e) {
            $this->warnings[] = 'Could not determine migration status: '.$e->getMessage();

            return;
        }

        if ($pending !== []) {
            $this->warnings[] = 'There are pending database migrations. Run `php artisan migrate --force`.';

            return;
        }

        $this->components->task('Database migrations are up to date', fn () => true);
    }
}
