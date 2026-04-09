import http from 'k6/http';
import { check, sleep } from 'k6';
import { apiHeaders, apiUrl, CUSTOMER_ID, VAT_RATE_ID } from './helpers.js';

export const options = {
  stages: [
    { duration: '30s', target: 10 },
    { duration: '1m', target: 25 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    'http_req_duration{name:create_invoice}': ['p(95)<500'],
    'http_req_duration{name:get_invoice}': ['p(95)<300'],
    'http_req_duration{name:list_invoices}': ['p(95)<300'],
    'http_req_duration{name:update_invoice}': ['p(95)<500'],
    'http_req_duration{name:delete_invoice}': ['p(95)<500'],
  },
};

export default function () {
  const params = apiHeaders();

  // Create
  const invoice = {
    customer_id: CUSTOMER_ID,
    issue_date: '2026-04-07',
    due_date: '2026-05-07',
    lines: [
      { description: 'k6 load test item', quantity: 1, unit_price: '100.00', vat_rate_id: VAT_RATE_ID || null },
    ],
  };

  const createRes = http.post(apiUrl('/invoices'), JSON.stringify(invoice), {
    ...params,
    tags: { name: 'create_invoice' },
  });
  check(createRes, { 'invoice created': (r) => r.status === 201 || r.status === 200 });

  const invoiceId = createRes.json('data.id') || createRes.json('id');
  if (!invoiceId) {
    sleep(1);
    return;
  }

  // Read
  const getRes = http.get(apiUrl(`/invoices/${invoiceId}`), {
    ...params,
    tags: { name: 'get_invoice' },
  });
  check(getRes, { 'invoice fetched': (r) => r.status === 200 });

  // List
  const listRes = http.get(apiUrl('/invoices?per_page=25'), {
    ...params,
    tags: { name: 'list_invoices' },
  });
  check(listRes, { 'invoices listed': (r) => r.status === 200 });

  // Update
  const updateRes = http.put(
    apiUrl(`/invoices/${invoiceId}`),
    JSON.stringify({ notes: 'Updated by k6' }),
    { ...params, tags: { name: 'update_invoice' } },
  );
  check(updateRes, { 'invoice updated': (r) => r.status === 200 });

  // Delete
  const deleteRes = http.del(apiUrl(`/invoices/${invoiceId}`), null, {
    ...params,
    tags: { name: 'delete_invoice' },
  });
  check(deleteRes, { 'invoice deleted': (r) => r.status === 200 || r.status === 204 });

  sleep(1);
}
