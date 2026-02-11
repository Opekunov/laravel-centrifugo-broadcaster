# Testing Guide

This document explains how to run tests for the Laravel Centrifugo Broadcaster package.

## Prerequisites

- Docker and Docker Compose installed
- PHP 8.0+ with ext-json
- Composer dependencies installed (`composer install`)

## Available Test Commands

### Quick Tests (No Docker Required)

```bash
# Run all unit tests (no server required)
composer test:unit

# Run security tests
composer test:security

# Run HTTP client tests
composer test:http
```

### Integration Tests (Docker Required)

```bash
# Full cycle: start both v5 & v6, run tests on both, cleanup
composer test:docker

# Test only against Centrifugo v5
composer test:docker:v5

# Test only against Centrifugo v6
composer test:docker:v6

# Run integration tests against a running server (default: localhost:8001)
composer test:integration

# Run against a specific server URL
CENTRIFUGO_URL=http://localhost:8002 composer test:integration
```

### Manual Docker Setup

If you need more control over the testing environment:

```bash
# Start both Centrifugo v5 (port 8001) and v6 (port 8002)
composer test:docker:setup

# Start only v5
composer test:docker:setup:v5

# Start only v6
composer test:docker:setup:v6

# Run integration tests against v5
composer test:integration:v5

# Run integration tests against v6
composer test:integration:v6

# Stop all containers
composer test:docker:cleanup
```

## Test Structure

### Unit Tests (`tests/Unit/`)

- **CentrifugoTest.php** - JWT token generation, timeout/retry, configuration
- **CentrifugoUnitTest.php** - Core functionality without network calls
- **HttpClientTest.php** - Custom HTTP client functionality
- **SecurityTest.php** - Security and JWT token validation
- **CentrifugoBroadcasterTest.php** - Laravel broadcaster integration (mocked)

### Integration Tests (`tests/Integration/`)

- **CentrifugoIntegrationTest.php** - Full API integration with real Centrifugo server
- **CentrifugoBroadcasterIntegrationTest.php** - Laravel broadcaster with real server

## Docker Configuration

The test environment uses:

- **Centrifugo v5** on port 8001
- **Centrifugo v6** on port 8002

Both servers start simultaneously by default. Each has a health check configured.

### Configuration Files

- `tests/Docker/config.json` - Centrifugo server configuration
- `tests/Docker/docker-compose.yml` - Docker containers setup
- `tests/Docker/wait-for-server.php` - Server readiness check script

### Environment Variables

| Variable | Default | Description |
|---|---|---|
| `CENTRIFUGO_URL` | `http://localhost:8001` | Centrifugo server URL for integration tests |

## Running Tests in CI/CD

For automated testing environments:

```bash
# Full test suite on both v5 and v6
composer test:docker

# Or step by step
composer test:docker:setup        # Start both servers + wait
composer test:integration:v5      # Run against v5
composer test:integration:v6      # Run against v6
composer test:docker:cleanup      # Stop containers
```

## Troubleshooting

### Docker Issues

```bash
# Check if containers are running
cd tests/Docker && docker compose ps

# View Centrifugo logs
cd tests/Docker && docker compose logs centrifugo-v5
cd tests/Docker && docker compose logs centrifugo-v6

# Force cleanup
cd tests/Docker && docker compose down --volumes --remove-orphans
```

### Connection Issues

If tests fail with connection errors:

1. Ensure Docker is running
2. Check if ports 8001/8002 are available
3. Wait for servers to be ready: `php tests/Docker/wait-for-server.php http://localhost:8001 http://localhost:8002`
4. Verify Centrifugo is responding: `curl http://localhost:8001/health`

### Test Coverage

To see test coverage:

```bash
./vendor/bin/phpunit --coverage-html coverage/
```

Then open `coverage/index.html` in your browser.

## Writing New Tests

### Unit Tests

Add to appropriate `tests/Unit/` file or create new ones following the naming convention `*Test.php`. Unit tests should not require a running Centrifugo server.

### Integration Tests

Add to `tests/Integration/` - these tests use real Centrifugo server connections. Always use `$this->centrifugoUrl` for the server URL and check `isCentrifugoRunning()` before making API calls.

### Best Practices

1. **Skip tests gracefully** when Centrifugo is not available
2. **Clean up resources** after tests
3. **Use unique channel names** to avoid conflicts
4. **Test both success and error scenarios**
5. **Verify API format compatibility** (v5+ endpoints)
6. **Use env variables** for server URLs — never hardcode `localhost:8001`
