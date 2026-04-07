import http from 'k6/http';
import { check, sleep } from 'k6';
import { apiHeaders, apiUrl } from './helpers.js';

export const options = {
  stages: [
    { duration: '30s', target: 20 },
    { duration: '1m', target: 50 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    'http_req_duration{name:accounts_list}': ['p(95)<300'],
    'http_req_duration{name:customers_list}': ['p(95)<300'],
    'http_req_duration{name:invoices_list}': ['p(95)<300'],
    'http_req_duration{name:expenses_list}': ['p(95)<300'],
  },
};

export default function () {
  const params = apiHeaders();

  const accounts = http.get(apiUrl('/accounts?per_page=100'), {
    ...params,
    tags: { name: 'accounts_list' },
  });
  check(accounts, { 'accounts loaded': (r) => r.status === 200 });

  const customers = http.get(apiUrl('/customers?per_page=50'), {
    ...params,
    tags: { name: 'customers_list' },
  });
  check(customers, { 'customers loaded': (r) => r.status === 200 });

  const invoices = http.get(apiUrl('/invoices?per_page=25'), {
    ...params,
    tags: { name: 'invoices_list' },
  });
  check(invoices, { 'invoices loaded': (r) => r.status === 200 });

  const expenses = http.get(apiUrl('/expenses?per_page=25'), {
    ...params,
    tags: { name: 'expenses_list' },
  });
  check(expenses, { 'expenses loaded': (r) => r.status === 200 });

  sleep(0.5);
}
