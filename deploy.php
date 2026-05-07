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

// --- Project ---
set('application', 'gaeld');
set('repository', 'git@gitlab.nectoria.com:nectoria/products/gaeld/api.git');
set('branch', 'production');
set('keep_releases', 5);

// --- Shared files/dirs (persisted across releases) ---
add('shared_files', ['.env']);
add('shared_dirs', ['storage', 'public/build']);

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

// --- Tasks ---

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

task('deploy:build:link', function () {
    run('rm -rf {{release_path}}/public/build');
    run('ln -s {{deploy_path}}/shared/public/build {{release_path}}/public/build');
})->desc('Symlink public/build to shared build assets');

task('deploy:horizon:restart', function () {
    run('sudo systemctl restart gaeld-horizon');
})->desc('Restart Horizon after deploy');

// --- SaaS-specific tasks ---

task('deploy:ee:plugin', function () {
    $eeRepo = getenv('EE_REPO') ?: 'git@gitlab.nectoria.com:nectoria/products/gaeld/gaeld-ee.git';
    $cachePath = '{{deploy_path}}/shared/gaeld-ee';
    $pluginPath = '{{release_path}}/plugins/gaeld-ee';

    // Clone or update cached EE repo in shared dir
    if (test("[ -d {$cachePath} ]")) {
        run("cd {$cachePath} && git fetch origin && git reset --hard origin/main");
    } else {
        run("git clone {$eeRepo} {$cachePath}");
    }

    // Copy cached repo into the release
    run("cp -a {$cachePath} {$pluginPath}");

    // Install EE plugin dependencies
    run("cd {$pluginPath} && {{bin/composer}} install --no-dev --no-interaction --prefer-dist --optimize-autoloader");
})->desc('Clone and install gaeld-ee plugin from private GitLab');

task('deploy:sync:permissions', function () {
    run('cd {{release_path}} && {{bin/php}} artisan gaeld:sync-permissions');
})->desc('Sync RBAC permissions');

task('deploy:opcache:clear', function () {
    run('cd {{release_path}} && {{bin/php}} artisan opcache:clear 2>/dev/null || true');
})->desc('Clear OPcache after deploy');

task('deploy:meilisearch:sync', function () {
    run('cd {{release_path}} && {{bin/php}} artisan scout:sync-index-settings 2>/dev/null || true');
})->desc('Sync MeiliSearch index settings');

task('deploy:sentry:release', function () {
    $version = runLocally('git describe --tags --abbrev=0');
    run("cd {{release_path}} && {{bin/php}} artisan sentry:publish {$version} 2>/dev/null || true");

    $token = run('grep "^SENTRY_AUTH_TOKEN=" {{deploy_path}}/shared/.env | cut -d= -f2');
    $org = run('grep "^SENTRY_ORG=" {{deploy_path}}/shared/.env | cut -d= -f2');
    $project = run('grep "^SENTRY_PROJECT=" {{deploy_path}}/shared/.env | cut -d= -f2');

    if (empty($token) || empty($org) || empty($project)) {
        warning('Sentry env vars incomplete — skipping release notification');

        return;
    }

    // Create release
    run(sprintf(
        'curl -sS -X POST "https://sentry.io/api/0/organizations/%s/releases/" '
        .'-H "Authorization: Bearer %s" '
        .'-H "Content-Type: application/json" '
        .'-d \'{"version":"%s","projects":["%s"]}\'',
        $org, $token, $version, $project,
    ));

    // Mark deploy
    run(sprintf(
        'curl -sS -X POST "https://sentry.io/api/0/organizations/%s/releases/%s/deploys/" '
        .'-H "Authorization: Bearer %s" '
        .'-H "Content-Type: application/json" '
        .'-d \'{"environment":"production"}\'',
        $org, $version, $token,
    ));

    info("Sentry release {$version} created & deploy marked");
})->desc('Notify Sentry of new release and mark deploy');

// --- Deployment flow ---
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'deploy:ee:plugin',
    'assets:build',
    'deploy:build:link',
    'deploy:storage:link',
    'artisan:migrate',
    'artisan:config:cache',
    'artisan:route:cache',
    'artisan:view:cache',
    'artisan:event:cache',
    'deploy:sync:permissions',
    'deploy:meilisearch:sync',
    'deploy:permissions',
    'deploy:fpm:restart',
    'deploy:publish',
    'deploy:sentry:release',
    'deploy:opcache:clear',
    'deploy:horizon:restart',
])->desc('Deploy the SaaS application');

// --- Hooks ---
after('deploy:failed', 'deploy:unlock');
