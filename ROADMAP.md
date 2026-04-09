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

- [x] **D3: Pentest execution** — Executed against the Docker test stack, report saved to `pentest/reports/2026-04-09_093136.txt`; follow-up remains for docs dependency audit and production header hardening
- [ ] **D4: Response time assertions** — Executed against the Docker test stack; thresholds failed on 2026-04-09 (`p95` 1.33s–1.41s, `http_req_failed` 11.81%), so remediation is still required

## Priority 4 — Production Infrastructure (Phase E)

- [x] **E4: Octane evaluation** — Decision documented in `docs/OCTANE_EVALUATION.md`; keep PHP-FPM as default until staging benchmarks justify Octane

## Priority 5 — Documentation (Phase B — GitLab Wiki)

- [x] **B3: API versioning strategy** — Documented in `docs/API_VERSIONING_STRATEGY.md`
- [x] **B4: DR runbook** — Documented in `docs/OPERATIONS.md`
- [x] **B5: Git workflow & deployment** — Documented in `docs/GIT_WORKFLOW_AND_DEPLOYMENT.md`

## Priority 6 — Swiss Compliance (Phase F)

- [ ] **F1: External fiduciary engagement** — VAT calculation, QR-Bill format, KMU Kontenrahmen review
- [ ] **F2: Compliance sign-off document** — Template for fiduciary

## Low Priority — Future Evaluations (Phase G)

- [x] JSON:API Resources evaluation (RFC) — captured in `docs/FUTURE_RFC_EVALUATIONS.md`
- [x] PHP Attributes for middleware evaluation (RFC) — captured in `docs/FUTURE_RFC_EVALUATIONS.md`
- [x] pgvector semantic search evaluation (RFC) — captured in `docs/FUTURE_RFC_EVALUATIONS.md`
- [x] Laravel AI SDK evaluation (RFC) — captured in `docs/FUTURE_RFC_EVALUATIONS.md`