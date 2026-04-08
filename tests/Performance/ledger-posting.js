import http from 'k6/http';
import { check, sleep } from 'k6';
import { apiHeaders, apiUrl } from './helpers.js';

export const options = {
  stages: [
    { duration: '30s', target: 10 },
    { duration: '1m', target: 20 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    'http_req_duration{name:post_2_lines}': ['p(95)<500'],
    'http_req_duration{name:post_20_lines}': ['p(95)<1000'],
    'http_req_duration{name:post_100_lines}': ['p(95)<1000'],
  },
};

/**
 * Build a balanced journal entry with N debit/credit line pairs.
 * Requires at least 2 valid account IDs in the target org.
 */
function buildEntry(linePairs) {
  const lines = [];
  for (let i = 0; i < linePairs; i++) {
    const amount = (10 + Math.random() * 90).toFixed(2);
    lines.push(
      { account_id: __ENV.K6_DEBIT_ACCOUNT || '', debit: amount, credit: '0.00', description: `Debit ${i}` },
      { account_id: __ENV.K6_CREDIT_ACCOUNT || '', debit: '0.00', credit: amount, description: `Credit ${i}` },
    );
  }
  return {
    date: '2026-04-07',
    reference: `K6-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    description: `k6 load test entry (${linePairs * 2} lines)`,
    lines,
  };
}

export default function () {
  const params = apiHeaders();

  // 2-line entry (simplest)
  const small = http.post(apiUrl('/journal-entries'), JSON.stringify(buildEntry(1)), {
    ...params,
    tags: { name: 'post_2_lines' },
  });
  check(small, { '2-line posted': (r) => r.status === 201 || r.status === 200 });

  // 20-line entry (typical manual entry)
  const medium = http.post(apiUrl('/journal-entries'), JSON.stringify(buildEntry(10)), {
    ...params,
    tags: { name: 'post_20_lines' },
  });
  check(medium, { '20-line posted': (r) => r.status === 201 || r.status === 200 });

  // 100-line entry (large import batch)
  const large = http.post(apiUrl('/journal-entries'), JSON.stringify(buildEntry(50)), {
    ...params,
    tags: { name: 'post_100_lines' },
  });
  check(large, { '100-line posted': (r) => r.status === 201 || r.status === 200 });

  sleep(1);
}
