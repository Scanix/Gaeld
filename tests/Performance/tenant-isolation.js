import http from 'k6/http';
import { check, fail } from 'k6';
import { SharedArray } from 'k6/data';
import { apiUrl, CUSTOMER_ID, VAT_RATE_ID } from './helpers.js';

/**
 * Multi-tenant isolation stress test.
 *
 * Supply two (or more) org tokens via the K6_TOKENS env var
 * as a JSON array of objects: [{"token":"…","orgId":"…","customerId":"…","vatRateId":"…"}, …]
 *
 * Each VU picks a token (round-robin). On every iteration the VU:
 *   1. Creates an invoice under its org.
 *   2. Tries to read it with every *other* org's token — expects 404.
 *   3. Lists invoices and checks no foreign org ID appears.
 *   4. Cleans up the invoice.
 *
 * Usage:
 *   K6_BASE_URL=http://localhost K6_TOKENS='[{"token":"tok_a","orgId":"uuid_a","customerId":"cust_a"},{"token":"tok_b","orgId":"uuid_b","customerId":"cust_b"}]' \
 *     k6 run tests/Performance/tenant-isolation.js
 */

const tokens = new SharedArray('tokens', function () {
  const raw = __ENV.K6_TOKENS;
  if (!raw) fail('K6_TOKENS env var is required (JSON array of {token, orgId})');
  return JSON.parse(raw);
});

export const options = {
  scenarios: {
    isolation: {
      executor: 'per-vu-iterations',
      vus: tokens.length,
      iterations: 10,
      maxDuration: '2m',
    },
  },
  thresholds: {
    checks: ['rate==1.0'],
    http_req_failed: ['rate<0.01'],
  },
};

function headersFor(token) {
  return {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
  };
}

export default function () {
  const mine = tokens[__VU % tokens.length];
  const others = tokens.filter((t) => t.orgId !== mine.orgId);
  const params = headersFor(mine.token);

  // 1. Create an invoice in my org
  const createRes = http.post(
    apiUrl('/invoices'),
    JSON.stringify({
      customer_id: mine.customerId || CUSTOMER_ID,
      issue_date: '2026-06-01',
      due_date: '2026-07-01',
      lines: [{ description: 'Isolation test', quantity: 1, unit_price: '50.00', vat_rate_id: mine.vatRateId || VAT_RATE_ID || null }],
    }),
    { ...params, tags: { name: 'create_own' } },
  );
  check(createRes, { 'own invoice created': (r) => [200, 201].includes(r.status) });

  const invoiceId = createRes.json('data.id') || createRes.json('id');
  if (!invoiceId) return;

  // 2. Other orgs must NOT be able to read this invoice
  for (const other of others) {
    const crossRes = http.get(apiUrl(`/invoices/${invoiceId}`), {
      ...headersFor(other.token),
      tags: { name: 'cross_tenant_read' },
    });
    check(crossRes, {
      [`org ${other.orgId} cannot read foreign invoice`]: (r) =>
        r.status === 404 || r.status === 403,
    });
  }

  // 3. List my invoices — none should belong to a foreign org
  const listRes = http.get(apiUrl('/invoices?per_page=100'), {
    ...params,
    tags: { name: 'list_own' },
  });
  check(listRes, { 'list succeeds': (r) => r.status === 200 });

  const items = listRes.json('data') || [];
  const orgField = items.length > 0 && items[0].organization_id !== undefined;
  if (orgField) {
    const leak = items.some((i) => i.organization_id !== mine.orgId);
    check(null, { 'no foreign org data in list': () => !leak });
  }

  // 4. Cleanup
  http.del(apiUrl(`/invoices/${invoiceId}`), null, {
    ...params,
    tags: { name: 'cleanup' },
  });
}
