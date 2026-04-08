# Gäld — Engineering Roadmap

Remaining items from the ecosystem backlog. Completed items omitted.

## Priority 1 — Push & Validate ✅

- [x] Push all 5 repos to remotes (41 commits total across api, docs, web, dl-stockaj, orchestrator)
- [x] Validate GitLab CI pipeline on self-hosted Nectoria runners (1012 tests, 65.66% coverage)
- [x] Validate GitHub Actions CI passes on main

## Priority 2 — Web Frontend (Phase C) ✅

- [x] **C4: Accessibility audit** — axe-core WCAG 2.1 AA, all 5 pages pass (16 violations fixed)
- [x] **C5: Bundle analysis** — `@next/bundle-analyzer` added, Three.js ~350K gz (lazy-loaded), total 671K gz

## Priority 3 — Security & Performance (Phase D)

- [ ] **D3: Pentest execution** — Run `pentest/scan.sh` against docker-compose.test.yml stack, save report to `pentest/reports/`
- [ ] **D4: Response time assertions** — Run k6 full suite against test stack, verify all p95 thresholds pass

## Priority 4 — Production Infrastructure (Phase E)

- [ ] **E4: Octane evaluation** — Benchmark FrankenPHP vs PHP-FPM with k6/wrk, write decision document

## Priority 5 — Documentation (Phase B — GitLab Wiki)

- [ ] **B3: API versioning strategy** — Route prefix strategy, deprecation policy
- [ ] **B4: DR runbook** — Database restore, Redis rebuild, Horizon restart sequence
- [ ] **B5: Git workflow & deployment** — GitHub ↔ GitLab dual-platform, CE/EE split, Deployer config

## Priority 6 — Swiss Compliance (Phase F)

- [ ] **F1: External fiduciary engagement** — VAT calculation, QR-Bill format, KMU Kontenrahmen review
- [ ] **F2: Compliance sign-off document** — Template for fiduciary

## Low Priority — Future Evaluations (Phase G)

- [ ] JSON:API Resources evaluation (RFC)
- [ ] PHP Attributes for middleware evaluation (RFC)
- [ ] pgvector semantic search evaluation (RFC)
- [ ] Laravel AI SDK evaluation (RFC)