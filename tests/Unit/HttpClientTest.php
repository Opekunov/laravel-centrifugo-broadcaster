<?php

namespace Opekunov\Centrifugo\Tests\Unit;

use Opekunov\Centrifugo\Exceptions\CentrifugoConnectionException;
use Opekunov\Centrifugo\Exceptions\CentrifugoException;
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

    public function test_non_connection_error_throws_centrifugo_exception(): void
    {
        $client = new HttpClient(['timeout' => 1]);

        $this->expectException(CentrifugoException::class);

        // Unknown scheme triggers "Unable to find the wrapper" warning — not a connection error
        $client->post('fakescheme://example.com', [
            'url' => 'fakescheme://example.com',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '{}',
        ]);
    }

    public function test_zero_tries_throws_exception(): void
    {
        $client = new HttpClient;

        $this->expectException(CentrifugoException::class);
        $this->expectExceptionMessage('HTTP request failed after 0 attempts');

        $client->post('http://localhost:8001/api/info', [
            'url' => 'http://localhost:8001/api/info',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '{}',
            'tries' => 0,
        ]);
    }

    // ---- Successful request ----

    public function test_successful_request_returns_response(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'centrifugo_test_');
        file_put_contents($tmpFile, '{"result":[]}');

        try {
            $client = new HttpClient(['timeout' => 5]);
            // file:// URL bypasses HTTP layer but exercises makeRequest + post return path
            $response = $client->post('file://'.$tmpFile, [
                'url' => 'http://localhost',
                'headers' => ['Content-Type' => 'application/json'],
                'body' => '{}',
            ]);

            $this->assertInstanceOf(HttpResponse::class, $response);
            $this->assertEquals('{"result":[]}', $response->getBody());
        } finally {
            @unlink($tmpFile);
        }
    }

    // ---- Private methods via reflection ----

    public function test_is_connection_error_returns_false_for_unknown_errors(): void
    {
        $client = new HttpClient;
        $method = new \ReflectionMethod($client, 'isConnectionError');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($client, 'Some unknown error'));
        $this->assertFalse($method->invoke($client, 'Parse error: syntax error'));
        $this->assertFalse($method->invoke($client, ''));
    }

    public function test_is_connection_error_returns_true_for_known_errors(): void
    {
        $client = new HttpClient;
        $method = new \ReflectionMethod($client, 'isConnectionError');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($client, 'failed to open stream: Connection refused'));
        $this->assertTrue($method->invoke($client, 'Connection timed out'));
        $this->assertTrue($method->invoke($client, 'Could not resolve host: example.com'));
        $this->assertTrue($method->invoke($client, 'Network is unreachable'));
        $this->assertTrue($method->invoke($client, 'SSL operation failed with code 1'));
    }

    public function test_create_context_includes_ssl_cert_for_https(): void
    {
        $client = new HttpClient;
        $method = new \ReflectionMethod($client, 'createContext');
        $method->setAccessible(true);

        $context = $method->invoke($client, ['Content-Type: application/json'], '{}', [
            'url' => 'https://example.com',
            'verify_ssl' => true,
            'ssl_cert' => '/path/to/cert.pem',
            'timeout' => 5,
        ]);

        $options = stream_context_get_options($context);
        $this->assertArrayHasKey('ssl', $options);
        $this->assertTrue($options['ssl']['verify_peer']);
        $this->assertTrue($options['ssl']['verify_peer_name']);
        $this->assertEquals('/path/to/cert.pem', $options['ssl']['local_cert']);
    }

    public function test_create_context_without_ssl_for_http(): void
    {
        $client = new HttpClient;
        $method = new \ReflectionMethod($client, 'createContext');
        $method->setAccessible(true);

        $context = $method->invoke($client, [], '{}', [
            'url' => 'http://localhost:8001',
            'timeout' => 5,
        ]);

        $options = stream_context_get_options($context);
        $this->assertArrayNotHasKey('ssl', $options);
        $this->assertArrayHasKey('http', $options);
        $this->assertEquals('POST', $options['http']['method']);
    }

    public function test_build_headers_formats_correctly(): void
    {
        $client = new HttpClient;
        $method = new \ReflectionMethod($client, 'buildHeaders');
        $method->setAccessible(true);

        $result = $method->invoke($client, [
            'Content-Type' => 'application/json',
            'X-API-Key' => 'test-key',
        ]);

        $this->assertEquals([
            'Content-Type: application/json',
            'X-API-Key: test-key',
        ], $result);
    }
}
