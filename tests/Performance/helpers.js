/**
 * k6 helpers — shared auth, base URL, and request defaults.
 */
export const BASE_URL = __ENV.K6_BASE_URL || 'http://localhost';
export const API_TOKEN = __ENV.K6_API_TOKEN || '';
export const ORG_ID = __ENV.K6_ORG_ID || '';

export function apiHeaders() {
  return {
    headers: {
      Authorization: `Bearer ${API_TOKEN}`,
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
  };
}

export function apiUrl(path) {
  return `${BASE_URL}/api/v1${path}`;
}
