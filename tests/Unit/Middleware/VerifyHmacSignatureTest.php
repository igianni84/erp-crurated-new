<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\VerifyHmacSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class VerifyHmacSignatureTest extends TestCase
{
    private string $testSecret = 'test-hmac-secret-key-for-unit-tests';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.trading_platform.hmac_secret' => $this->testSecret]);
    }

    public function test_passes_with_valid_signature(): void
    {
        $body = '{"new_customer_id":1,"trading_reference":"REF-001"}';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $this->testSecret);

        $request = Request::create('/api/test', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Signature', $signature);
        $request->headers->set('X-Timestamp', $timestamp);

        $middleware = new VerifyHmacSignature;
        $response = $middleware->handle($request, fn () => new Response('OK', 200));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_rejects_missing_signature_header(): void
    {
        $request = Request::create('/api/test', 'POST', [], [], [], [], '{}');
        $request->headers->set('X-Timestamp', (string) time());

        $middleware = new VerifyHmacSignature;
        $response = $middleware->handle($request, fn () => new Response('OK', 200));

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_rejects_missing_timestamp_header(): void
    {
        $request = Request::create('/api/test', 'POST', [], [], [], [], '{}');
        $request->headers->set('X-Signature', 'some-signature');

        $middleware = new VerifyHmacSignature;
        $response = $middleware->handle($request, fn () => new Response('OK', 200));

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_rejects_expired_timestamp(): void
    {
        $body = '{}';
        $timestamp = (string) (time() - 600);
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $this->testSecret);

        $request = Request::create('/api/test', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Signature', $signature);
        $request->headers->set('X-Timestamp', $timestamp);

        $middleware = new VerifyHmacSignature;
        $response = $middleware->handle($request, fn () => new Response('OK', 200));

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('expired', $response->getContent());
    }

    public function test_rejects_tampered_payload(): void
    {
        $originalBody = '{"new_customer_id":1}';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$originalBody, $this->testSecret);

        $tamperedBody = '{"new_customer_id":999}';
        $request = Request::create('/api/test', 'POST', [], [], [], [], $tamperedBody);
        $request->headers->set('X-Signature', $signature);
        $request->headers->set('X-Timestamp', $timestamp);

        $middleware = new VerifyHmacSignature;
        $response = $middleware->handle($request, fn () => new Response('OK', 200));

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_rejects_when_secret_not_configured(): void
    {
        config(['services.trading_platform.hmac_secret' => null]);

        $request = Request::create('/api/test', 'POST', [], [], [], [], '{}');
        $request->headers->set('X-Signature', 'any');
        $request->headers->set('X-Timestamp', (string) time());

        $middleware = new VerifyHmacSignature;
        $response = $middleware->handle($request, fn () => new Response('OK', 200));

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function test_rejects_invalid_timestamp_format(): void
    {
        $request = Request::create('/api/test', 'POST', [], [], [], [], '{}');
        $request->headers->set('X-Signature', 'any');
        $request->headers->set('X-Timestamp', 'not-a-number');

        $middleware = new VerifyHmacSignature;
        $response = $middleware->handle($request, fn () => new Response('OK', 200));

        $this->assertEquals(401, $response->getStatusCode());
    }
}
