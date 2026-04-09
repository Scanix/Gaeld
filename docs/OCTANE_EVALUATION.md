# Octane Evaluation

## Question

Should the production API move from the current PHP-FPM deployment model to Laravel Octane, with FrankenPHP or Swoole, to improve response times?

## Current Baseline

### Production Model

- Current deployment automation is built around PHP-FPM reloads.
- Queue workers, cache warm-up, and release publishing already assume a classic stateless Laravel request model.

### Local Test Findings From 2026-04-09

- Pentest execution completed successfully against the local Docker test stack.
- k6 full-suite execution did not meet thresholds on the local stack.
- Observed k6 results:
  - `accounts_list` p95: 1.38s against a 300ms target
  - `customers_list` p95: 1.36s against a 300ms target
  - `list_invoices` p95: 1.33s against a 300ms target
  - `create_invoice` p95: 1.41s against a 500ms target
  - `http_req_failed`: 11.81% against a sub-5% target

These results are useful as a baseline, but they were gathered on the local `docker-compose.test.yml` stack, which currently runs `php artisan serve`, not a production-grade PHP-FPM or Octane runtime. They are therefore not sufficient on their own to justify an Octane rollout.

## Operational Trade-Offs

### Advantages Of Staying On PHP-FPM

- Existing deploy flow already supports it.
- Process isolation reduces long-lived state risks.
- Lower operational novelty for a small team.
- Most Laravel packages are already validated against this model.

### Advantages Of Octane

- Lower boot cost per request.
- Better headroom for high-concurrency API traffic.
- FrankenPHP offers a Laravel-friendly path without introducing Swoole-specific operational burden.

### Risks With Octane

- Long-lived worker state can surface hidden singleton, cache, or request lifecycle bugs.
- Deployment and restart semantics become more sensitive.
- Team bus factor is still low, so operational complexity matters.
- Current bottlenecks may also be database, cache, or query related rather than framework boot time.

## Decision

- Do not switch production to Octane yet.
- Keep PHP-FPM as the production default until a representative benchmark is run on staging.
- Treat Octane as a targeted optimization path, not a launch prerequisite.

## Required Benchmark Before Reconsidering

Benchmark the same workload on staging across:

1. PHP-FPM behind the intended production web server.
2. FrankenPHP with Octane.

Measure at minimum:

- p50, p90, and p95 response times for `/api/v1/accounts`, `/api/v1/customers`, and `/api/v1/invoices`
- error rate under the existing k6 full suite
- CPU and memory usage under sustained load
- deployment and restart complexity

## Adoption Gate

Adopt Octane only if all of the following are true:

- p95 improves materially on critical API endpoints,
- failure rate remains below the current threshold budget,
- deployment and restart automation stays simple enough for the current team,
- and no request-state leakage or tenant-isolation regressions appear under concurrency.

## Recommendation

- Keep the current production target on PHP-FPM.
- Use the 2026-04-09 k6 results as the pre-optimization baseline.
- Re-run the benchmark on staging with a production-like runtime before revisiting Octane.