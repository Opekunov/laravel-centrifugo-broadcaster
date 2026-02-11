# Changelog

All notable changes to this project will be documented in this file.

## [3.0.0] - 2026-02-11

### Added
- Centrifugo 6.x support
- Laravel 12.x support
- Custom HTTP client (`HttpClient`) — no Guzzle dependency, uses PHP streams
- Custom exceptions: `CentrifugoException`, `CentrifugoConnectionException`
- `subscribe()` method for server-side channel subscriptions
- `rpc()` method for remote procedure calls
- `presenceStats()` method for short-form presence info
- `history()` now supports `limit`, `offset`, `epoch`, `reverse` parameters
- Retry mechanism with configurable `timeout` and `tries`
- Docker-based integration test setup for Centrifugo v5 and v6
- Comprehensive unit test suite (49 tests) with PHPUnit 9/10/11 support
- Integration test suite for real Centrifugo server testing

### Changed
- **Breaking:** Minimum Centrifugo version is now 5.x (dropped v4 support)
- API format updated to Centrifugo v5+: `/api/{method}` endpoints with `X-API-Key` header
- Batch operations use new format: `{"commands": [{"publish": {...}}, ...]}`
- Updated `orchestra/testbench` to support all Laravel 8.75-12.x versions
- Modernized README examples with current `centrifuge-js` API (async/await, `UnauthorizedError`, event listeners)
- `generateConnectionToken()` now accepts `$channels` parameter
- `generateSubscriptionToken()` now accepts `$override` parameter

### Removed
- Old Centrifugo v4 API format (`method`/`params` wrapper)
- `tests/CentrifugoClientTest` Vue.js test app (replaced with proper PHPUnit tests)
