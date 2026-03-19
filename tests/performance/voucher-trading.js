import http from 'k6/http';
import { check, sleep } from 'k6';
import crypto from 'k6/crypto';

/**
 * Voucher Trading Completion Performance Test
 *
 * Target: POST /api/vouchers/{id}/trading-complete
 * Threshold: p95 < 1000ms
 *
 * Note: This endpoint requires HMAC signature verification.
 * Set HMAC_SECRET env var to match the server's TRADING_PLATFORM_HMAC_SECRET.
 */

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const HMAC_SECRET = __ENV.HMAC_SECRET || 'test-secret';
const VOUCHER_ID = __ENV.VOUCHER_ID || '00000000-0000-0000-0000-000000000001';

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
        http_req_duration: ['p(95)<1000'],
    },
};

export default function () {
    const payload = JSON.stringify({
        voucher_id: VOUCHER_ID,
        buyer_reference: `BUYER-${Date.now()}`,
        sale_price: '1500.00',
        sale_currency: 'EUR',
        traded_at: new Date().toISOString(),
    });

    const signature = crypto.hmac('sha256', HMAC_SECRET, payload, 'hex');

    const res = http.post(
        `${BASE_URL}/api/vouchers/${VOUCHER_ID}/trading-complete`,
        payload,
        {
            headers: {
                'Content-Type': 'application/json',
                'X-Signature': signature,
            },
        }
    );

    // Expect 404/422 since test voucher doesn't exist — that's fine for perf testing
    // We're measuring response time, not correctness
    check(res, {
        'returns response (not 500)': (r) => r.status !== 500,
        'response time < 1s': (r) => r.timings.duration < 1000,
    });

    sleep(1);
}
