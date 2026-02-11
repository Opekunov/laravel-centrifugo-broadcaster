<?php

declare(strict_types=1);

namespace Opekunov\Centrifugo\Http;

use Opekunov\Centrifugo\Exceptions\CentrifugoConnectionException;
use Opekunov\Centrifugo\Exceptions\CentrifugoException;

class HttpClient
{
    private array $defaultOptions = [
        'timeout' => 30,
        'verify_ssl' => true,
        'ssl_cert' => null,
    ];

    public function __construct(array $options = [])
    {
        $this->defaultOptions = array_merge($this->defaultOptions, $options);
    }

    public function post(string $url, array $options = []): HttpResponse
    {
        $options = array_merge($this->defaultOptions, $options);

        $headers = $this->buildHeaders($options['headers'] ?? []);
        $body = $options['body'] ?? '';

        $context = $this->createContext($headers, $body, $options);

        $tries = $options['tries'] ?? 1;
        $lastException = null;

        for ($attempt = 1; $attempt <= $tries; $attempt++) {
            try {
                $response = $this->makeRequest($url, $context);

                return new HttpResponse($response);
            } catch (\RuntimeException $e) {
                $lastException = $e;
                if ($attempt < $tries && $this->isConnectionError($e->getMessage())) {
                    usleep(100000 * $attempt); // 0.1s, 0.2s, 0.3s...

                    continue;
                }
                break;
            }
        }

        if ($lastException) {
            $message = $lastException->getMessage();
            if ($this->isConnectionError($message)) {
                throw new CentrifugoConnectionException($message, 0, $lastException);
            }
            throw new CentrifugoException($message, 0, $lastException);
        }

        throw new CentrifugoException('HTTP request failed after '.$tries.' attempts');
    }

    private function buildHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            $result[] = $name.': '.$value;
        }

        return $result;
    }

    private function createContext(array $headers, string $body, array $options)
    {
        $contextOptions = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => $options['timeout'] ?? 30,
                'ignore_errors' => true,
                'follow_location' => false,
            ],
        ];

        if (str_starts_with($options['url'] ?? '', 'https://')) {
            $contextOptions['ssl'] = [
                'verify_peer' => $options['verify_ssl'] ?? true,
                'verify_peer_name' => $options['verify_ssl'] ?? true,
            ];

            if (! empty($options['ssl_cert'])) {
                $contextOptions['ssl']['local_cert'] = $options['ssl_cert'];
            }
        }

        return stream_context_create($contextOptions);
    }

    private function makeRequest(string $url, $context): array
    {
        set_error_handler(function ($errno, $errstr) {
            throw new \RuntimeException($errstr, $errno);
        }, E_WARNING);

        try {
            $http_response_header = [];
            $response = file_get_contents($url, false, $context);

            return [
                'body' => $response,
                'headers' => $http_response_header,
            ];
        } finally {
            restore_error_handler();
        }
    }

    private function isConnectionError(string $message): bool
    {
        $connectionErrors = [
            'failed to open stream',
            'Connection refused',
            'Could not resolve host',
            'Connection timed out',
            'Network is unreachable',
            'HTTP request failed',
            'getaddrinfo',
            'nodename nor servname provided',
            'SSL: Connection reset by peer',
            'SSL: Handshake timed out',
            'SSL operation failed',
            'SSL: Connection timeout',
        ];

        foreach ($connectionErrors as $error) {
            if (str_contains($message, $error)) {
                return true;
            }
        }

        return false;
    }
}
