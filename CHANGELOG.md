# Changelog

All notable changes to this project will be documented in this file.

## [3.0.1] - 2026-02-11

### Added
- GitHub Actions CI: unit tests on PHP 8.0-8.4 + Laravel 8-12 matrix (14 combinations)
- GitHub Actions code quality: Laravel Pint (code style) + PHPStan/Larastan level 5 (static analysis)
- Code coverage upload to Codecov
- Dependabot for automated composer and GitHub Actions dependency updates

### Fixed
- JWT tokens with `exp=0` now omit the `exp` claim instead of setting it to `0` (Unix epoch 1970), which caused Centrifugo v5+/v6 to reject tokens as expired
- `getDefaultTokenExpiration()` now returns `int` (with `(int)` cast) — fixes `TypeError` when config value comes from `env()` as string and is passed to `Carbon::addSeconds()`
- Minor PHPStan issues in `Centrifugo.php` and `HttpClient.php`
- Code style normalized across all source and test files via Laravel Pint

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
