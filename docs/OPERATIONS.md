# Operations Runbook

## Scope

This document covers production operations for the API application:

- backup and restore,
- Redis rebuild,
- Horizon and worker recovery,
- deployment smoke checks,
- disaster recovery targets.

## Service Baseline

- Application runtime: Laravel on PHP-FPM.
- Queue processing: Horizon and `gaeld-worker` systemd service.
- Database: PostgreSQL 16.
- Cache, queue, sessions: Redis 7.
- Search: Meilisearch when enabled.
- Deployment path: Deployer release structure under `/var/www/gaeld` by default.

## Recovery Targets

- RPO target: 24 hours maximum data loss.
- RTO target: 4 hours to restore core accounting operations on a clean server.
- Lower targets require replica-based database and cache failover, which are not in place yet.

## Incident Triage

1. Confirm scope: API only, queues only, or full stack.
2. Check current release and recent deploy activity.
3. Check PostgreSQL reachability.
4. Check Redis reachability.
5. Check Horizon status and failed jobs.
6. Decide whether to roll forward, restart services, or restore from backup.

## Deployment Smoke Checks

Run after every deployment:

1. Open `/up` and `/login`.
2. Verify authenticated dashboard access with an owner user.
3. Run `php artisan horizon:status`.
4. Run `php artisan schedule:list`.
5. Verify backup jobs and queue workers are visible in logs.
6. If API access is enabled, verify `/api/v1` and one authenticated token request.

## Backup Verification

### Daily Expectations

- One successful backup per day.
- Cleanup job removes expired backups.
- Failure notifications go to `BACKUP_NOTIFICATION_EMAIL`.

### Manual Checks

Run inside the deployed release:

```bash
php artisan backup:list
php artisan backup:monitor
```

Investigate immediately if:

- the latest backup is missing,
- backup size drops unexpectedly,
- or monitor output reports unhealthy backups.

## Database Restore Procedure

Use this when production data must be restored from a known-good backup.

1. Enable maintenance mode or remove the instance from the load balancer.
2. Stop queue processing:

```bash
sudo systemctl stop gaeld-worker
php artisan horizon:terminate
```

3. Confirm the backup artifact and timestamp.
4. Restore PostgreSQL to a fresh database or clean server.
5. Update environment settings if restoring to a different host.
6. Run migrations only if the restored snapshot is older than the deployed schema and the migration path is known-safe.
7. Clear and rebuild application caches:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

8. Restart services:

```bash
sudo systemctl restart php8.4-fpm
sudo systemctl restart gaeld-worker
php artisan horizon:terminate
```

9. Run smoke checks and inspect logs before reopening traffic.

## Redis Rebuild Procedure

Use this when Redis data is corrupted, unavailable, or intentionally flushed.

1. Stop queue workers to avoid partial job execution.
2. Restart Redis or rebuild the Redis container/service.
3. Clear stale Laravel cache state:

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

4. Rebuild optimized caches if the instance is healthy:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

5. Restart Horizon and worker services.
6. Verify queues resume and no jobs are stuck in failed state.

## Horizon Recovery Procedure

### Quick Recovery

```bash
php artisan horizon:status
php artisan horizon:terminate
sudo systemctl restart gaeld-worker
```

### Failed Jobs

```bash
php artisan queue:failed
php artisan queue:retry all
```

Retry only after the root cause is understood. Do not blindly replay webhook, billing, or import jobs against an unhealthy system.

## Full Clean-Server Recovery

1. Provision server with PHP, PostgreSQL client tools, Redis, supervisor/systemd, and required extensions.
2. Deploy the latest known-good release with Deployer.
3. Restore `.env` and shared storage.
4. Restore the PostgreSQL backup.
5. Recreate or reconnect Redis.
6. Run `php artisan gaeld:sync-permissions`.
7. Restart PHP-FPM and queue workers.
8. Run smoke checks.
9. Remove maintenance mode.

## Known Gaps

- No automated replica failover.
- No documented read replica for reporting.
- RTO and RPO targets are process goals, not yet SLA-backed guarantees.
- Production alerting should be expanded beyond Sentry before claiming full DR readiness.