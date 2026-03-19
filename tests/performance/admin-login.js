import http from 'k6/http';
import { check, sleep } from 'k6';

/**
 * Admin Login Performance Test
 *
 * Target: POST /admin/login
 * Threshold: p95 < 500ms
 *
 * Tests the Filament authentication flow under load.
 */

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const ADMIN_EMAIL = __ENV.ADMIN_EMAIL || 'admin@crurated.com';
const ADMIN_PASSWORD = __ENV.ADMIN_PASSWORD || 'password';

export const options = {
    scenarios: {
        smoke: {
            executor: 'constant-vus',
            vus: 1,
            duration: '30s',
            tags: { profile: 'smoke' },
        },
        load: {
            executor: 'constant-vus',
            vus: 10,
            duration: '5m',
            startTime: '35s',
            tags: { profile: 'load' },
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<500'],
    },
};

export default function () {
    // Get login page (fetches CSRF token)
    const loginPage = http.get(`${BASE_URL}/admin/login`);
    check(loginPage, {
        'login page loads': (r) => r.status === 200,
    });

    // Extract CSRF token from the page
    const csrfMatch = loginPage.body.match(/name="_token"[^>]*value="([^"]+)"/);
    const csrfToken = csrfMatch ? csrfMatch[1] : '';

    // Attempt login
    const res = http.post(
        `${BASE_URL}/admin/login`,
        {
            email: ADMIN_EMAIL,
            password: ADMIN_PASSWORD,
            _token: csrfToken,
        },
        {
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            redirects: 0, // Don't follow redirect to dashboard
        }
    );

    // Expect 302 redirect on success, 422 on validation failure
    check(res, {
        'login responds (not 500)': (r) => r.status !== 500,
        'response time < 500ms': (r) => r.timings.duration < 500,
    });

    sleep(2);
}
