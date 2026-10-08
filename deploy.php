<?php

/**
 * Gäld — Production SaaS deployment configuration.
 *
 * This file is tracked on the `production` branch only (GitLab).
 * It is gitignored on `main` (the open-source branch on GitHub).
 *
 * Deploys from GitLab private repo, clones EE plugin, runs SaaS-specific tasks.
 */

namespace Deployer;

require 'recipe/laravel.php';
require 'contrib/sentry.php';

// --- Project ---
set('application', 'gaeld');
set('repository', 'git@gitlab.nectoria.com:nectoria/products/gaeld/api.git');
set('branch', 'production');
set('keep_releases', 5);
set('horizon_service', getenv('DEPLOY_HORIZON_SERVICE') ?: 'gaeld-horizon');

// --- Shared files/dirs (persisted across releases) ---
add('shared_files', ['.env']);
add('shared_dirs', ['storage']);

// --- Writable dirs ---
add('writable_dirs', ['bootstrap/cache', 'storage']);
set('writable_mode', 'chmod');
set('writable_use_sudo', true);

// --- Host ---
host('production')
    ->setLabels(['stage' => 'production'])
    ->setHostname(getenv('DEPLOY_HOST') ?: 'nectoria')
    ->setRemoteUser(getenv('DEPLOY_USER') ?: 'deploy')
    ->set('http_user', 'www-data')
    ->setDeployPath(getenv('DEPLOY_PATH') ?: '/data/www/gaeld_app')
    ->setForwardAgent(true);

host('staging')
    ->setLabels(['stage' => 'staging'])
    ->set('stage', 'staging')
    ->setHostname(getenv('DEPLOY_STAGING_HOST') ?: 'build')
    ->setRemoteUser(getenv('DEPLOY_STAGING_USER') ?: 'deploy')
    ->set('http_user', 'www-data')
    ->set('horizon_service', getenv('DEPLOY_STAGING_HORIZON_SERVICE') ?: 'gaeld-horizon')
    ->setDeployPath(getenv('DEPLOY_STAGING_PATH') ?: '~/gaeld_app')
    ->setForwardAgent(true);

// --- Tasks ---

task('deploy:verify:inputs', function () {
    $eeRef = getenv('DEPLOY_EE_REF') ?: '';
    $eeSha = getenv('DEPLOY_EE_SHA') ?: '';
    $eeDigest = getenv('DEPLOY_EE_CONTENT_DIGEST') ?: '';

    if (! preg_match('/^v[0-9]+\.[0-9]+\.[0-9]+$/', $eeRef)
        || ! preg_match('/^[a-f0-9]{40}$/', $eeSha)
        || ! preg_match('/^[a-f0-9]{64}$/', $eeDigest)) {
        throw new \RuntimeException('DEPLOY_EE_REF, DEPLOY_EE_SHA and DEPLOY_EE_CONTENT_DIGEST must identify the tested immutable EE release.');
    }
})->desc('Require the tested EE tag, commit and content digest');

// Build frontend assets on the server so Vite reads the production .env
// (incl. VITE_COOKIE_DOMAIN) that is already symlinked via deploy:shared.
task('assets:build', function () {
    run('source ~/.nvm/nvm.sh && cd {{release_path}} && nvm use && CI=true pnpm install --frozen-lockfile && pnpm build');
})->desc('Build frontend assets on the server');

task('deploy:fpm:restart', function () {
    run('sudo systemctl reload php8.4-fpm');
})->desc('Gracefully reload PHP-FPM workers');

task('deploy:permissions', function () {
    run('sudo chown -R {{remote_user}}:{{http_user}} {{deploy_path}}/shared/storage');
    run('sudo chmod -R 2775 {{deploy_path}}/shared/storage');
    run('sudo chown -R {{remote_user}}:{{http_user}} {{release_path}}/bootstrap/cache');
    run('sudo chmod -R 2775 {{release_path}}/bootstrap/cache');
})->desc('Fix storage & cache permissions');

task('deploy:storage:link', function () {
    run('rm -f {{release_path}}/public/storage');
    run('ln -s {{deploy_path}}/shared/storage/app/public {{release_path}}/public/storage');
})->desc('Symlink public/storage to shared storage');

task('deploy:verify:assets', function () {
    if (! test('[ -s {{release_path}}/public/build/manifest.json ] && [ ! -L {{release_path}}/public/build ]')) {
        throw new \RuntimeException('The release must contain its own built frontend assets.');
    }
})->desc('Verify frontend assets are retained with this release');

task('deploy:assets:retain', function () {
    if (test('[ -d {{current_path}}/public/build/assets ]')) {
        $currentManifest = trim(run('sha256sum {{current_path}}/public/build/manifest.json'));
        $releaseManifest = trim(run('sha256sum {{release_path}}/public/build/manifest.json'));

        run('cp -an {{current_path}}/public/build/assets/. {{release_path}}/public/build/assets/');
        run('cp -an {{release_path}}/public/build/assets/. {{current_path}}/public/build/assets/');

        if (! hash_equals($currentManifest, trim(run('sha256sum {{current_path}}/public/build/manifest.json')))
            || ! hash_equals($releaseManifest, trim(run('sha256sum {{release_path}}/public/build/manifest.json')))) {
            throw new \RuntimeException('Asset retention must not alter either release manifest.');
        }
    }
})->desc('Retain asset URLs in both active and rollback releases');

task('deploy:legacy-worker:disable', function () {
    if (test("systemctl list-unit-files | grep -q '^gaeld-worker\\.service'")) {
        run('sudo systemctl disable --now gaeld-worker');
    }
})->desc('Keep the legacy queue worker disabled when Horizon is active');

task('deploy:horizon:restart', function () {
    if (test("systemctl list-unit-files | grep -q '^{{horizon_service}}\\.service'")) {
        run('sudo systemctl restart {{horizon_service}}');
    } else {
        warning('Skipping queue service restart: {{horizon_service}}.service not found.');
    }
})->desc('Restart queue service after deploy');

task('deploy:scheduler:restart', function () {
    if (test("systemctl list-unit-files | grep -q '^gaeld-schedule\\.service'")) {
        run('sudo systemctl restart gaeld-schedule');
    } else {
        warning('Skipping scheduler restart: gaeld-schedule.service not found.');
    }
})->desc('Restart Laravel scheduler after deploy');

// --- SaaS-specific tasks ---

task('deploy:ee:plugin', function () {
    $eeRepo = getenv('EE_REPO') ?: 'git@gitlab.nectoria.com:nectoria/products/gaeld/gaeld-ee.git';
    $eeRef = getenv('DEPLOY_EE_REF');
    $eeSha = getenv('DEPLOY_EE_SHA');
    $eeDigest = getenv('DEPLOY_EE_CONTENT_DIGEST');
    $eeRefArg = escapeshellarg($eeRef);
    $cachePath = '{{deploy_path}}/shared/gaeld-ee';
    $pluginPath = '{{release_path}}/plugins/gaeld-ee';

    // Clone or update the cached EE repository, then pin the release to an
    // immutable tag or commit instead of deploying a moving branch head.
    if (test("[ -d {$cachePath} ]")) {
        run("cd {$cachePath} && git fetch --tags origin && git fetch origin {$eeRefArg} && git checkout --detach {$eeRefArg}");
    } else {
        run('git clone --branch '.$eeRefArg.' --single-branch '.escapeshellarg($eeRepo).' '.$cachePath);
    }

    $actualSha = trim(run("git -C {$cachePath} rev-parse HEAD"));

    if (! hash_equals($eeSha, $actualSha)) {
        throw new \RuntimeException('The EE tag does not resolve to the tested commit.');
    }

    writeln('<info>EE release:</info> '.trim(run("git -C {$cachePath} describe --tags --always --dirty")));

    // Copy cached repo into the release
    run("cp -a {$cachePath} {$pluginPath}");

    $actualDigest = trim(run("{{bin/php}} {$pluginPath}/scripts/content-digest.php {$pluginPath}"));

    if (! hash_equals($eeDigest, $actualDigest)) {
        throw new \RuntimeException('The installed EE source does not match the tested content digest.');
    }

    run('printf %s '.escapeshellarg($actualSha).' > {{release_path}}/EE_REVISION');
    run('printf %s '.escapeshellarg($actualDigest).' > {{release_path}}/EE_CONTENT_DIGEST');

    // Ensure PHP-FPM (www-data) can read the plugin tree. The shared cache may
    // have been cloned with a restrictive umask (resulting in 750 perms), which
    // would prevent PluginServiceProvider from reading plugin.json at runtime
    // and silently disable the EE plugin (causing BindingResolutionException
    // on plugin-provided middleware/controllers).
    run("chmod -R o+rX {$pluginPath}");

    // Install EE plugin dependencies
    run("cd {$pluginPath} && {{bin/composer}} install --no-dev --no-interaction --prefer-dist --optimize-autoloader");

    // Re-apply world-read after composer install creates vendor/.
    run("chmod -R o+rX {$pluginPath}");

    // Regenerate main app classmap so it picks up newly-installed EE plugin
    // classes (e.g. Plugins\GaeldEE\Http\Middleware\EnforceRegistrationGate).
    // Without this the original `deploy:vendors` classmap — built before the
    // plugin was cloned — does not know about plugin classes and route
    // resolution fails with BindingResolutionException on first request.
    run('cd {{release_path}} && {{bin/composer}} dump-autoload --no-dev --optimize --classmap-authoritative');
})->desc('Clone and install gaeld-ee plugin from private GitLab');

task('deploy:verify:edition', function () {
    $pluginPath = '{{release_path}}/plugins/gaeld-ee';
    $actualDigest = trim(run("{{bin/php}} {$pluginPath}/scripts/content-digest.php {$pluginPath}"));

    if (! hash_equals(getenv('DEPLOY_EE_CONTENT_DIGEST'), $actualDigest)) {
        throw new \RuntimeException('The installed EE source changed during dependency installation.');
    }

    run('cd {{release_path}} && {{bin/php}} artisan edition:verify --ee-version='.escapeshellarg(getenv('DEPLOY_EE_REF')));
})->desc('Verify the CE/EE edition compatibility before activation');

task('deploy:verify:migrations', function () {
    run('cd {{release_path}} && {{bin/php}} artisan migrate:status --no-ansi');
    run('cd {{release_path}} && {{bin/php}} artisan migrate --pretend --force --no-ansi');
})->desc('Record pending migrations and their SQL before execution');

task('deploy:sync:permissions', function () {
    run('cd {{release_path}} && {{bin/php}} artisan gaeld:sync-permissions');
})->desc('Sync RBAC permissions');

task('deploy:meilisearch:sync', function () {
    run('cd {{release_path}} && {{bin/php}} artisan scout:sync-index-settings 2>/dev/null || true');
})->desc('Sync MeiliSearch index settings');

set('sentry', [
    'organization' => static function () {
        return trim(run('grep "^SENTRY_ORG=" {{deploy_path}}/shared/.env | cut -d= -f2- 2>/dev/null || true'), " \t\n\r\0\x0B\"'");
    },
    'projects' => static function () {
        $project = trim(run('grep "^SENTRY_PROJECT=" {{deploy_path}}/shared/.env | cut -d= -f2- 2>/dev/null || true'), " \t\n\r\0\x0B\"'");

        return $project === '' ? [] : [$project];
    },
    // Read the token from the local deploy environment. Remote `run()` output
    // is logged by Deployer and must never print a server-side secret.
    'token' => getenv('DEPLOY_SENTRY_AUTH_TOKEN') ?: null,
    'environment' => static function () {
        return getenv('DEPLOY_SENTRY_ENVIRONMENT') ?: (get('stage') ?: 'production');
    },
    'git_version_command' => 'git describe --tags --abbrev=0',
]);

task('deploy:sentry:release', function () {
    try {
        invoke('deploy:sentry');
    } catch (\Throwable $exception) {
        warning('Sentry release notification skipped: '.$exception->getMessage());
    }
})->desc('Notify Sentry of new release and mark deploy');

// --- Deployment flow ---
task('deploy', [
    'deploy:verify:inputs',
    'deploy:prepare',
    'deploy:vendors',
    'deploy:ee:plugin',
    'deploy:verify:edition',
    'assets:build',
    'deploy:verify:assets',
    'deploy:assets:retain',
    'deploy:storage:link',
    'deploy:verify:migrations',
    'artisan:migrate',
    'deploy:permissions',      // fix storage ownership/mode before artisan caches write to it
    'artisan:config:cache',
    'artisan:route:cache',
    'artisan:view:cache',
    'artisan:event:cache',
    'deploy:sync:permissions',
    'deploy:meilisearch:sync',
    'deploy:publish',
    'deploy:sentry:release',
    'deploy:fpm:restart',
    'deploy:legacy-worker:disable',
    'deploy:scheduler:restart',
    'deploy:horizon:restart',
])->desc('Deploy the SaaS application');

// --- Hooks ---
after('deploy:failed', 'deploy:unlock');
after('rollback', 'deploy:fpm:restart');
after('rollback', 'deploy:scheduler:restart');
after('rollback', 'deploy:horizon:restart');
