import http from 'k6/http';
import { fail } from 'k6';
import dashboard, { options as benchmark } from './dashboard.js';
import { apiHeaders, apiUrl } from './helpers.js';

export const options = {
  scenarios: {
    dashboard: { executor: 'shared-iterations', vus: 1, iterations: 3, maxDuration: '30s' },
  },
  thresholds: {
    ...benchmark.thresholds,
    checks: ['rate==1'],
    http_req_failed: ['rate==0'],
  },
};

export function setup() {
  const response = http.get(apiUrl('/accounts?per_page=1'), {
    ...apiHeaders(),
    tags: { name: 'dashboard_warmup' },
  });
  if (response.status !== 200 || !Array.isArray(response.json('data'))) {
    fail('Could not warm the authenticated dashboard API');
  }
}

export default dashboard;
