import http from 'k6/http';
import { check, sleep } from 'k6';

/**
 * Smoke Test — All Endpoints
 *
 * Sanity check: hits all key endpoints with 1 VU to verify they respond.
 * No load, just availability and basic response time verification.
 */

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

export const options = {
    vus: 1,
    iterations: 1,
    thresholds: {
        http_req_failed: ['rate<0.5'], // Allow some failures (auth-required endpoints)
    },
};

export default function () {
    // 1. Laravel health check
    const up = http.get(`${BASE_URL}/up`);
    check(up, {
        '/up returns 200': (r) => r.status === 200,
    });
    sleep(0.5);

    // 2. API health check
    const health = http.get(`${BASE_URL}/api/health`);
    check(health, {
        '/api/health returns 200': (r) => r.status === 200,
        '/api/health is healthy': (r) => {
            try {
                return JSON.parse(r.body).status === 'healthy';
            } catch {
                return false;
            }
        },
    });
    sleep(0.5);

    // 3. Admin login page loads
    const login = http.get(`${BASE_URL}/admin/login`);
    check(login, {
        '/admin/login loads': (r) => r.status === 200,
    });
    sleep(0.5);

    // 4. Root redirects to admin
    const root = http.get(`${BASE_URL}/`, { redirects: 0 });
    check(root, {
        '/ redirects to /admin': (r) => r.status === 302,
    });
    sleep(0.5);

    // 5. API docs (if accessible)
    const docs = http.get(`${BASE_URL}/docs/api`, { redirects: 0 });
    check(docs, {
        '/docs/api responds': (r) => r.status === 200 || r.status === 302 || r.status === 403,
    });
    sleep(0.5);

    // 6. Stripe webhook (expects 200 with "secret not configured" or 503 if disabled)
    const stripe = http.post(
        `${BASE_URL}/api/webhooks/stripe`,
        JSON.stringify({ id: 'evt_smoke_test', type: 'test' }),
        { headers: { 'Content-Type': 'application/json' } }
    );
    check(stripe, {
        '/api/webhooks/stripe responds (not 500)': (r) => r.status !== 500,
    });

    console.log('Smoke test complete — all endpoints checked.');
}
