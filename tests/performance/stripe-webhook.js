import http from 'k6/http';
import { check, sleep } from 'k6';

/**
 * Stripe Webhook Performance Test
 *
 * Target: POST /api/webhooks/stripe
 * Threshold: p95 < 300ms
 *
 * Note: Without valid Stripe signature, the controller returns 400.
 * We test throughput and response time, not business logic.
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
    },
    thresholds: {
        http_req_duration: ['p(95)<300'],
    },
};

export default function () {
    const payload = JSON.stringify({
        id: `evt_test_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
        type: 'payment_intent.succeeded',
        data: {
            object: {
                id: `pi_test_${Date.now()}`,
                amount: 150000,
                currency: 'eur',
            },
        },
    });

    const res = http.post(`${BASE_URL}/api/webhooks/stripe`, payload, {
        headers: {
            'Content-Type': 'application/json',
            'Stripe-Signature': 'invalid-for-perf-test',
        },
    });

    // Expect 200 (webhook secret not configured) or 400 (invalid signature)
    // 503 if feature flag disabled — all acceptable for perf testing
    check(res, {
        'returns response (not 500)': (r) => r.status !== 500,
        'response time < 300ms': (r) => r.timings.duration < 300,
    });

    sleep(0.5);
}
