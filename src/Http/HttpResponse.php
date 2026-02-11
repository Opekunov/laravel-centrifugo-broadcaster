<?php

declare(strict_types=1);

namespace Opekunov\Centrifugo\Http;

class HttpResponse
{
    private string $body;

    private array $headers;

    private int $statusCode;

    public function __construct(array $response)
    {
        $this->body = $response['body'];
        $this->headers = $response['headers'] ?? [];
        $this->statusCode = $this->parseStatusCode($this->headers);
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }

    private function parseStatusCode(array $headers): int
    {
        if (empty($headers)) {
            return 0;
        }

        $statusLine = $headers[0] ?? '';

        // Parse status line: "HTTP/1.1 200 OK"
        if (preg_match('/HTTP\/[\d.]+\s+(\d+)/', $statusLine, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
