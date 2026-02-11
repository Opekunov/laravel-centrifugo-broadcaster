<?php

namespace Opekunov\Centrifugo\Tests\Unit;

use Opekunov\Centrifugo\Exceptions\CentrifugoConnectionException;
use Opekunov\Centrifugo\Http\HttpClient;
use Opekunov\Centrifugo\Http\HttpResponse;
use PHPUnit\Framework\TestCase;

class HttpClientTest extends TestCase
{
    // ---- Construction ----

    public function test_construction(): void
    {
        $client = new HttpClient;
        $this->assertInstanceOf(HttpClient::class, $client);
    }

    public function test_construction_with_options(): void
    {
        $client = new HttpClient(['timeout' => 60, 'verify_ssl' => false]);
        $this->assertInstanceOf(HttpClient::class, $client);
    }

    // ---- HttpResponse ----

    public function test_response_success(): void
    {
        $response = new HttpResponse([
            'body' => '{"result": []}',
            'headers' => ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
        ]);

        $this->assertEquals('{"result": []}', $response->getBody());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isClientError());
        $this->assertFalse($response->isServerError());
    }

    public function test_response_client_error(): void
    {
        $response = new HttpResponse([
            'body' => '{"error": "Not Found"}',
            'headers' => ['HTTP/1.1 404 Not Found'],
        ]);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isClientError());
        $this->assertFalse($response->isServerError());
    }

    public function test_response_server_error(): void
    {
        $response = new HttpResponse([
            'body' => '{"error": "Internal Server Error"}',
            'headers' => ['HTTP/1.1 500 Internal Server Error'],
        ]);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isClientError());
        $this->assertTrue($response->isServerError());
    }

    public function test_response_empty_headers(): void
    {
        $response = new HttpResponse([
            'body' => '{"result": []}',
            'headers' => [],
        ]);

        $this->assertEquals(0, $response->getStatusCode());
        $this->assertFalse($response->isSuccessful());
    }

    public function test_response_unparsable_status_line(): void
    {
        $response = new HttpResponse([
            'body' => '{}',
            'headers' => ['Some-Invalid-Header'],
        ]);

        $this->assertEquals(0, $response->getStatusCode());
        $this->assertFalse($response->isSuccessful());
    }

    public function test_response_get_headers(): void
    {
        $headers = ['HTTP/1.1 200 OK', 'Content-Type: application/json'];
        $response = new HttpResponse([
            'body' => '{}',
            'headers' => $headers,
        ]);

        $this->assertEquals($headers, $response->getHeaders());
    }

    // ---- Error handling ----

    public function test_invalid_url_throws_connection_exception(): void
    {
        $client = new HttpClient(['timeout' => 1]);

        $this->expectException(CentrifugoConnectionException::class);
        $client->post('http://invalid-host-that-does-not-exist.local:8001/api/publish', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '{"channel": "test", "data": {}}',
        ]);
    }
}
