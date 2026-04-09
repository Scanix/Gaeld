# Performance Testing with k6

## Prerequisites

```bash
# macOS
brew install k6

# Docker
docker run --rm -i grafana/k6 run -
```

## Running Tests

```bash
# Authenticate first — set environment variables
export K6_BASE_URL=http://localhost  # or staging URL
export K6_API_TOKEN=your-sanctum-token
export K6_ORG_ID=your-organization-uuid
export K6_CUSTOMER_ID=your-customer-uuid
export K6_VAT_RATE_ID=your-vat-rate-uuid  # optional

# Run individual scenarios
k6 run tests/Performance/invoice-crud.js
k6 run tests/Performance/ledger-posting.js
k6 run tests/Performance/dashboard.js
k6 run tests/Performance/bank-import.js

# Run all scenarios with thresholds
k6 run tests/Performance/full-suite.js

# Run with custom VUs and duration
k6 run --vus 50 --duration 2m tests/Performance/invoice-crud.js
```

## Test Scenarios

| Script | Description | p95 Target |
|--------|-------------|------------|
| `invoice-crud.js` | Invoice create/read/update/delete cycle | < 500ms |
| `ledger-posting.js` | Journal entry posting with 2–100 lines | < 1s |
| `dashboard.js` | Dashboard aggregation queries | < 300ms |
| `bank-import.js` | Bank transaction import (500 txns) | < 5s |
| `full-suite.js` | All scenarios combined under load | per-scenario |

## Interpreting Results

k6 outputs per-request metrics. The `thresholds` block in each script
defines pass/fail criteria matching the targets in
the internal post-acquisition roadmap (Phase 8.3).
