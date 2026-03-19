import http from 'k6/http';
import { check, sleep } from 'k6';

/**
 * Health Check Performance Test
 *
 * Targets: GET /up, GET /api/health
 * Threshold: p95 < 200ms
 */

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

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
        stress: {
            executor: 'constant-vus',
            vus: 50,
            duration: '10m',
            startTime: '6m',
            tags: { profile: 'stress' },
        },
    },
    thresholds: {
        'http_req_duration{url:up}': ['p(95)<200'],
        'http_req_duration{url:health}': ['p(95)<200'],
        http_req_failed: ['rate<0.01'],
    },
};

export default function () {
    // Laravel built-in health check
    const upRes = http.get(`${BASE_URL}/up`, {
        tags: { url: 'up' },
    });
    check(upRes, {
        '/up returns 200': (r) => r.status === 200,
    });

    sleep(0.5);

    // Custom health endpoint with DB/cache/storage checks
    const healthRes = http.get(`${BASE_URL}/api/health`, {
        tags: { url: 'health' },
    });
    check(healthRes, {
        '/api/health returns 200': (r) => r.status === 200,
        '/api/health status is healthy': (r) => {
            const body = JSON.parse(r.body);
            return body.status === 'healthy';
        },
        '/api/health has all checks': (r) => {
            const body = JSON.parse(r.body);
            return body.checks && body.checks.database && body.checks.cache && body.checks.storage;
        },
    });

    sleep(0.5);
}
