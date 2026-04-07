import { sleep } from 'k6';
import invoiceCrud from './invoice-crud.js';
import dashboardQueries from './dashboard.js';

/**
 * Combined load test — runs invoice CRUD and dashboard queries
 * under concurrent load to test multi-tenant isolation.
 *
 * Ledger posting and bank import are excluded from the combined
 * suite as they require dedicated account setup per org.
 * Run them individually with the appropriate env vars.
 */
export const options = {
  scenarios: {
    invoices: {
      executor: 'ramping-vus',
      exec: 'invoiceScenario',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 15 },
        { duration: '2m', target: 30 },
        { duration: '30s', target: 0 },
      ],
    },
    dashboard: {
      executor: 'ramping-vus',
      exec: 'dashboardScenario',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 20 },
        { duration: '2m', target: 50 },
        { duration: '30s', target: 0 },
      ],
    },
  },
  thresholds: {
    'http_req_duration{name:create_invoice}': ['p(95)<500'],
    'http_req_duration{name:list_invoices}': ['p(95)<300'],
    'http_req_duration{name:accounts_list}': ['p(95)<300'],
    'http_req_duration{name:customers_list}': ['p(95)<300'],
    http_req_failed: ['rate<0.05'],
  },
};

export function invoiceScenario() {
  invoiceCrud();
  sleep(1);
}

export function dashboardScenario() {
  dashboardQueries();
  sleep(0.5);
}
