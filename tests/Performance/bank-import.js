import http from 'k6/http';
import { check, sleep } from 'k6';
import { apiHeaders, apiUrl } from './helpers.js';

export const options = {
  // Single VU — bank import is a batch operation, not concurrent
  vus: 1,
  iterations: 5,
  thresholds: {
    'http_req_duration{name:bank_import_500}': ['p(95)<5000'],
  },
};

/**
 * Generate a batch of bank transactions for import.
 * Mimics a CSV/CAMT import payload with 500 transactions.
 */
function buildTransactions(count) {
  const transactions = [];
  for (let i = 0; i < count; i++) {
    transactions.push({
      date: '2026-04-07',
      description: `k6 bank txn ${i}`,
      amount: (Math.random() > 0.5 ? 1 : -1) * (10 + Math.random() * 500).toFixed(2),
      reference: `K6-BANK-${Date.now()}-${i}`,
    });
  }
  return { transactions };
}

export default function () {
  const params = apiHeaders();

  const res = http.post(
    apiUrl('/bank-accounts/import'),
    JSON.stringify(buildTransactions(500)),
    { ...params, tags: { name: 'bank_import_500' } },
  );
  check(res, {
    'bank import accepted': (r) => r.status === 200 || r.status === 201 || r.status === 202,
  });

  sleep(2);
}
