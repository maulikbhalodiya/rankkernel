# Instant Indexing (IndexNow) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the `instant-indexing` module so RankKernel can notify participating search engines that a URL changed, with the API key never leaving PHP.

**Architecture:** Five new classes behind the existing `ModuleInterface` hard gate. `IndexNowSettings` owns the key and log. `IndexNowClient` builds and sends the spec-exact payload with an injectable transport. `KeyFileServer` serves the key file virtually. `InstantIndexingModule` wires them and owns the guarded auto-submit path. `InstantIndexingPage` is the admin surface.

**Tech Stack:** WordPress 8.1 typed PHP, PSR-12, WordPress Coding Standards, PHPStan level 6, PHPUnit with Brain Monkey and Mockery, no build step for JS.

**Spec:** `docs/competitor-analysis/indexnow-behavioral-spec.md` (read this first; it carries the protocol facts, the behavioral observations, and the locked design decisions). GitHub issue #80.

## Global Constraints

- Every class in `src/`: `declare(strict_types=1)`, namespace `RankKernel\`, file-level docblock with `@package RankKernel` and `@license GPL-2.0-or-later`, and `defined( 'ABSPATH' ) || exit;`.
- Zero standalone dashes in code, comments, commit messages, and strings. No em dash, no en dash, no hyphen used as a pause. Hyphens only inside compound words and identifiers.
- Never suppress a type error. No `as any`, no `@ts-ignore`, no equivalent PHP suppression. Fix the type.
- Every output escaped. Every input sanitized. Every write guarded by `current_user_can()` and `check_admin_referer()`.
- PHP 8.1 compatible. No new Composer or npm dependency.
- Keep every class at or under 250 lines of pure logic. Split rather than grow.
- Storage names are namespaced `rankkernel_`. Per-post meta is `_rankkernel_indexnow`.
- The module must register zero hooks and construct zero clients when it is disabled.
- No telemetry headers. The only outbound request is `POST https://api.indexnow.org/indexnow`.
- Four gates must pass before any PR: `composer lint`, `composer stan`, `composer test`, `composer test:js`.

## File Map

| Path | Responsibility |
|---|---|
| `src/Modules/InstantIndexing/IndexNowSettings.php` | Key storage, key validation, key location, capped log, debounce constant |
| `src/Modules/InstantIndexing/IndexNowClient.php` | Payload build, host filter, chunking, status mapping, transport |
| `src/Modules/InstantIndexing/KeyFileServer.php` | Virtual key file, pure match decision, response emission |
| `src/Modules/InstantIndexing/InstantIndexingModule.php` | Module contract, hook registration, guarded auto-submit |
| `src/Modules/InstantIndexing/UrlCollector.php` | Resolve a post or term id into the submittable URL list, including the pre-trash snapshot |
| `src/Admin/InstantIndexingPage.php` | Admin page, three authorized actions, log rendering |
| `src/Admin/Views/instant-indexing.php` | Presentation only |
| `src/Plugin.php` | Register the module and the admin page hook |
| `src/Admin/AdminMenu.php` | Construct the page, add the submenu, expose a getter |
| `src/Admin/DashboardPage.php` | Point the module card at its settings page, drop "Planned" |

`UrlCollector` exists so `InstantIndexingModule` stays under the line ceiling and so the permalink snapshot logic is independently testable.

---

### Task 1: `IndexNowSettings`

**Files:**
- Create: `src/Modules/InstantIndexing/IndexNowSettings.php`
- Test: `tests/Unit/IndexNowSettingsTest.php`

**Interfaces:**
- Produces: every public method listed below. Tasks 2, 3, 4 and 5 consume this class and nothing else from this task.

```php
namespace RankKernel\Modules\InstantIndexing;

final class IndexNowSettings {
    public const OPTION = 'rankkernel_instant_indexing_settings';
    public const LOG_OPTION = 'rankkernel_instant_indexing_log';
    public const LOG_LIMIT = 50;
    public const DEBOUNCE_SECONDS = 600;
    public const KEY_QUERY_ARG = 'rankkernel_indexnow_key';

    public static function defaults(): array;
    public static function isValidKey( string $key ): bool;
    public function all(): array;
    public function get( string $key, mixed $fallback = null ): mixed;
    public function set( array $partial ): bool;
    public function getKey(): string;
    public function ensureKey(): string;
    public function resetKey(): string;
    public function getAutoSubmit(): bool;
    public function setAutoSubmit( bool $enabled ): void;
    public function keyFileName(): string;
    public function keyLocation(): string;
    public function siteHost(): string;
    public function logEntry( string $url, int $code, string $source, string $message ): void;
    public function logEntries(): array;
    public function clearLog(): void;
}
```

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\InstantIndexing\IndexNowSettings;

final class IndexNowSettingsTest extends TestCase {
    /** @var array<string, mixed> */
    private array $stored = [];

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        Functions\when( 'get_option' )->alias(
            function ( string $key, mixed $fallback = false ): mixed {
                return $this->stored[ $key ] ?? $fallback;
            }
        );
        Functions\when( 'update_option' )->alias(
            function ( string $key, mixed $value, mixed $autoload = null ): bool {
                $this->stored[ $key ] = $value;
                return true;
            }
        );
        Functions\when( 'home_url' )->alias( static fn( string $p = '' ): string => 'https://example.com' . $p );
        Functions\when( 'get_option' )->alias(
            function ( string $key, mixed $fallback = false ): mixed {
                return $this->stored[ $key ] ?? $fallback;
            }
        );
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_defaults_are_off_and_keyless(): void {
        $this->assertSame( [ 'api_key' => '', 'auto_submit' => false ], IndexNowSettings::defaults() );
    }

    public function test_generated_key_satisfies_the_protocol_alphabet(): void {
        $settings = new IndexNowSettings();
        $key      = $settings->ensureKey();

        $this->assertTrue( IndexNowSettings::isValidKey( $key ) );
        $this->assertMatchesRegularExpression( '/^[A-Za-z0-9-]{8,128}$/', $key );
    }

    public function test_is_valid_key_rejects_bad_length_and_alphabet(): void {
        $this->assertFalse( IndexNowSettings::isValidKey( 'short' ) );
        $this->assertFalse( IndexNowSettings::isValidKey( str_repeat( 'a', 129 ) ) );
        $this->assertFalse( IndexNowSettings::isValidKey( 'has_underscore' ) );
        $this->assertFalse( IndexNowSettings::isValidKey( 'has space here' ) );
        $this->assertTrue( IndexNowSettings::isValidKey( str_repeat( 'a', 128 ) ) );
    }

    public function test_reset_key_replaces_the_stored_key(): void {
        $settings = new IndexNowSettings();
        $first    = $settings->ensureKey();
        $second   = $settings->resetKey();

        $this->assertNotSame( $first, $second );
        $this->assertSame( $second, $settings->getKey() );
    }

    public function test_auto_submit_defaults_to_false(): void {
        $this->assertFalse( ( new IndexNowSettings() )->getAutoSubmit() );
    }

    public function test_site_host_has_no_scheme_and_no_www_collapsing(): void {
        $this->assertSame( 'example.com', ( new IndexNowSettings() )->siteHost() );
    }

    public function test_key_location_is_root_txt_when_permalinks_are_on(): void {
        $settings = new IndexNowSettings();
        $key      = $settings->ensureKey();

        $this->assertSame( 'https://example.com/' . $key . '.txt', $settings->keyLocation() );
    }

    public function test_key_location_falls_back_to_a_query_argument_on_plain_permalinks(): void {
        $settings = new IndexNowSettings();
        $key      = $settings->ensureKey();

        $settings->setPermalinkStructure( '' );

        $this->assertStringContainsString( IndexNowSettings::KEY_QUERY_ARG . '=' . $key, $settings->keyLocation() );
    }

    public function test_log_is_capped_newest_first_and_clears(): void {
        $settings = new IndexNowSettings();

        for ( $i = 0; $i < 60; $i++ ) {
            $settings->logEntry( 'https://example.com/p' . $i, 200, 'auto', 'ok' );
        }

        $entries = $settings->logEntries();

        $this->assertCount( IndexNowSettings::LOG_LIMIT, $entries );
        $this->assertSame( 'https://example.com/p59', $entries[0]['url'] );

        $settings->clearLog();
        $this->assertSame( [], $settings->logEntries() );
    }
}
```

The plain permalink test uses `setPermalinkStructure()`. Add that method to the class as a test seam, and have `keyLocation()` read the structure through it:

```php
/**
 * Permalink structure, isolated so tests can simulate plain permalinks.
 */
public function setPermalinkStructure( string $structure ): void {
    $this->permalinkStructure = $structure;
}

private function permalinkStructure(): string {
    if ( null !== $this->permalinkStructure ) {
        return $this->permalinkStructure;
    }

    return function_exists( 'get_option' ) ? (string) get_option( 'permalink_structure', '' ) : '';
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter IndexNowSettingsTest`
Expected: FAIL, class `RankKernel\Modules\InstantIndexing\IndexNowSettings` not found.

- [ ] **Step 3: Write the minimal implementation**

Implement the class to the interface block above. Key rules:

* `isValidKey()` is `1 === preg_match( '/^[A-Za-z0-9-]{8,128}$/', $key )`.
* `ensureKey()` returns the stored key when `isValidKey()` passes, otherwise generates, validates, persists, and returns. Generation is `strtolower( wp_generate_password( 32, false, false ) )`, which stays inside the protocol alphabet. If `wp_generate_password` is unavailable, fall back to `bin2hex( random_bytes( 16 ) )`, also inside the alphabet.
* `siteHost()` is `wp_parse_url( home_url(), PHP_URL_HOST )` cast to string. Never collapse or strip `www`.
* `keyLocation()` returns `trailingslashit( home_url() ) . $keyFileName()` when a permalink structure exists, otherwise `add_query_arg( self::KEY_QUERY_ARG, $key, home_url( '/' ) )`. Guard both helpers with `function_exists()`.
* `set()` whitelists `api_key` and `auto_submit` only. An `api_key` that fails `isValidKey()` is dropped, never stored. `auto_submit` normalizes through `filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE )`.
* `logEntry()` prepends, caps at `LOG_LIMIT`, and writes with `update_option( self::LOG_OPTION, $entries, false )` so the log is never autoloaded.
* The settings option is written with autoload `true`.

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter IndexNowSettingsTest`
Expected: PASS.

- [ ] **Step 5: Run the gates for this task**

Run: `composer lint && composer stan && vendor/bin/phpunit --filter IndexNowSettingsTest`
Expected: phpcs clean, PHPStan no errors, tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/Modules/InstantIndexing/IndexNowSettings.php tests/Unit/IndexNowSettingsTest.php
git commit -m "feat(instant-indexing): add server side key storage and capped log (#80)"
```

---

### Task 2: `IndexNowClient`

**Files:**
- Create: `src/Modules/InstantIndexing/IndexNowClient.php`
- Test: `tests/Unit/IndexNowClientTest.php`

**Interfaces:**
- Consumes: `IndexNowSettings` from Task 1. Use `getKey()`, `keyLocation()`, `siteHost()`, `logEntry()`.
- Produces: `IndexNowClient::submit()`, `buildPayload()`, `filterUrls()` for Task 4 and Task 5.

```php
namespace RankKernel\Modules\InstantIndexing;

final class IndexNowClient {
    public const ENDPOINT = 'https://api.indexnow.org/indexnow';
    public const MAX_URLS = 10000;
    public const TIMEOUT = 10;

    /**
     * @param callable(string, array<string, mixed>): (array<string, mixed>|\WP_Error)|null $transport
     */
    public function __construct( IndexNowSettings $settings, ?callable $transport = null );

    /** @return array{accepted:int, permanent:int, transient:int, results:array<int, array{url:string, code:int, accepted:bool, retryable:bool, message:string}>} */
    public function submit( array $urls, string $source = 'auto' ): array;

    /** @return array{host:string, key:string, keyLocation:string, urlList:string[]} */
    public function buildPayload( array $urls ): array;

    /** @return string[] */
    public function filterUrls( array $urls ): array;
}
```

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\InstantIndexing\IndexNowClient;
use RankKernel\Modules\InstantIndexing\IndexNowSettings;
use WP_Error;

final class IndexNowClientTest extends TestCase {
    private IndexNowSettings $settings;

    /** @var array<int, array{url:string, args:array<string, mixed>}> */
    private array $calls = [];

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        $stored = [];
        Functions\when( 'get_option' )->alias( static function ( string $k, mixed $f = false ) use ( &$stored ): mixed {
            return $stored[ $k ] ?? $f;
        } );
        Functions\when( 'update_option' )->alias( static function ( string $k, mixed $v, mixed $a = null ) use ( &$stored ): bool {
            $stored[ $k ] = $v;
            return true;
        } );
        Functions\when( 'home_url' )->alias( static fn( string $p = '' ): string => 'https://example.com' . $p );
        Functions\when( 'wp_json_encode' )->alias( static fn( mixed $d ): string => (string) json_encode( $d ) );
        Functions\when( 'esc_url_raw' )->alias( static fn( string $u ): string => $u );

        $this->settings = new IndexNowSettings();
        $this->settings->ensureKey();
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    private function client( int $code = 200, bool $isError = false ): IndexNowClient {
        $transport = function ( string $url, array $args ) use ( $code, $isError ) {
            $this->calls[] = [ 'url' => $url, 'args' => $args ];

            if ( $isError ) {
                return new WP_Error( 'http_request_failed', 'boom' );
            }

            return [
                'response' => [ 'code' => $code ],
                'body'     => '',
            ];
        };

        return new IndexNowClient( $this->settings, $transport );
    }

    public function test_payload_uses_exact_spec_field_casing(): void {
        $payload = $this->client()->buildPayload( [ 'https://example.com/a' ] );

        $this->assertSame( [ 'host', 'key', 'keyLocation', 'urlList' ], array_keys( $payload ) );
        $this->assertSame( 'example.com', $payload['host'] );
        $this->assertStringEndsWith( '.txt', $payload['keyLocation'] );
        $this->assertSame( [ 'https://example.com/a' ], $payload['urlList'] );
    }

    public function test_request_posts_json_to_the_global_endpoint(): void {
        $this->client()->submit( [ 'https://example.com/a' ] );

        $this->assertCount( 1, $this->calls );
        $this->assertSame( IndexNowClient::ENDPOINT, $this->calls[0]['url'] );
        $this->assertSame( 'application/json; charset=utf-8', $this->calls[0]['args']['headers']['Content-Type'] );
        $this->assertArrayNotHasKey( 'X-Source-Info', $this->calls[0]['args']['headers'] );
        $this->assertArrayNotHasKey( 'User-Agent', $this->calls[0]['args']['headers'] );
        $this->assertTrue( $this->calls[0]['args']['blocking'] );
    }

    public function test_202_is_treated_as_accepted_not_failed(): void {
        $result = $this->client( 202 )->submit( [ 'https://example.com/a' ] );

        $this->assertSame( 1, $result['accepted'] );
        $this->assertSame( 0, $result['permanent'] );
        $this->assertTrue( $result['results'][0]['accepted'] );
    }

    public function test_403_is_permanent_and_429_is_retryable(): void {
        $this->assertSame( 1, $this->client( 403 )->submit( [ 'https://example.com/a' ] )['permanent'] );
        $this->assertSame( 1, $this->client( 429 )->submit( [ 'https://example.com/a' ] )['transient'] );
    }

    public function test_wp_error_is_retryable(): void {
        $result = $this->client( 0, true )->submit( [ 'https://example.com/a' ] );

        $this->assertSame( 1, $result['transient'] );
    }

    public function test_urls_on_another_host_are_dropped_without_any_request(): void {
        $result = $this->client()->submit( [ 'https://evil.test/a', 'https://example.com/b' ] );

        $this->assertCount( 1, $this->calls );
        $this->assertSame( [ 'https://example.com/b' ], json_decode( (string) $this->calls[0]['args']['body'], true )['urlList'] );
        $this->assertSame( 1, $result['accepted'] );
    }

    public function test_batch_is_chunked_at_ten_thousand(): void {
        $urls = [];
        for ( $i = 0; $i < 10001; $i++ ) {
            $urls[] = 'https://example.com/p' . $i;
        }

        $this->client()->submit( $urls );

        $this->assertCount( 2, $this->calls );
        $this->assertCount( 10000, json_decode( (string) $this->calls[0]['args']['body'], true )['urlList'] );
        $this->assertCount( 1, json_decode( (string) $this->calls[1]['args']['body'], true )['urlList'] );
    }

    public function test_no_key_means_no_request_at_all(): void {
        $empty = new IndexNowSettings();

        $result = ( new IndexNowClient( $empty, $this->client() ) )->submit( [ 'https://example.com/a' ] );

        $this->assertCount( 0, $this->calls );
        $this->assertSame( 0, $result['accepted'] );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter IndexNowClientTest`
Expected: FAIL, class not found.

- [ ] **Step 3: Write the minimal implementation**

* `filterUrls()` keeps only `http` and `https` URLs whose `wp_parse_url( $url, PHP_URL_HOST )` equals `siteHost()` exactly. Deduplicate with `array_unique`, then `array_values`.
* `buildPayload()` returns exactly the four keys in the interface block, in that order. Throw nothing when the key is empty; `submit()` short circuits first.
* `submit()` returns early with a zeroed result when the key is empty or `filterUrls()` is empty, so no transport call happens.
* Chunk with `array_chunk( $urls, self::MAX_URLS )`.
* Per request: `'blocking' => true`, `'timeout' => self::TIMEOUT`, `'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ]`, `'body' => wp_json_encode( $payload )`. Add no other header. Use `wp_safe_remote_post` only in the default transport, guarded by `function_exists()`, with a `wp_remote_post` fallback and then a hard failure recorded if neither exists.
* Status mapping: `200` and `202` accepted; `400`, `403`, `405`, `422` permanent; `429` and any `5xx` transient; a `WP_Error` transient. A `202` message records key pending verification.
* Log one entry per submitted URL through `IndexNowSettings::logEntry()`.

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter IndexNowClientTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Modules/InstantIndexing/IndexNowClient.php tests/Unit/IndexNowClientTest.php
git commit -m "feat(instant-indexing): add the IndexNow protocol client (#80)"
```

---

### Task 3: `KeyFileServer`

**Files:**
- Create: `src/Modules/InstantIndexing/KeyFileServer.php`
- Test: `tests/Unit/KeyFileServerTest.php`

**Interfaces:**
- Consumes: `IndexNowSettings::getKey()`, `keyFileName()`, `keyLocation()`, `KEY_QUERY_ARG`, and the permalink structure seam from Task 1.
- Produces: `register()`, `maybeServe()`, `matches()`, `respond()` for Task 4.

```php
namespace RankKernel\Modules\InstantIndexing;

final class KeyFileServer {
    public function __construct( IndexNowSettings $settings );
    public function register(): void;
    public function maybeServe( mixed $wp = null ): void;
    public function matches( string $requestPath, array $query ): bool;
    public function respond( string $key ): void;
}
```

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\InstantIndexing\IndexNowSettings;
use RankKernel\Modules\InstantIndexing\KeyFileServer;

final class KeyFileServerTest extends TestCase {
    private IndexNowSettings $settings;
    private string $key;

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        $stored = [];
        Functions\when( 'get_option' )->alias( static function ( string $k, mixed $f = false ) use ( &$stored ): mixed {
            return $stored[ $k ] ?? $f;
        } );
        Functions\when( 'update_option' )->alias( static function ( string $k, mixed $v, mixed $a = null ) use ( &$stored ): bool {
            $stored[ $k ] = $v;
            return true;
        } );
        Functions\when( 'home_url' )->alias( static fn( string $p = '' ): string => 'https://example.com' . $p );
        Functions\when( 'add_action' )->justReturn( true );
        Functions\when( 'trailingslashit' )->alias( static fn( string $u ): string => rtrim( $u, '/' ) . '/' );
        Functions\when( 'status_header' )->justReturn( null );
        Functions\when( 'nocache_headers' )->justReturn( null );

        $this->settings = new IndexNowSettings();
        $this->key      = $this->settings->ensureKey();
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_hooks_parse_request(): void {
        $hooked = [];
        Functions\when( 'add_action' )->alias(
            static function ( string $hook, mixed $cb, int $p = 10, int $a = 1 ) use ( &$hooked ): bool {
                $hooked[] = $hook;
                return true;
            }
        );

        ( new KeyFileServer( $this->settings ) )->register();

        $this->assertContains( 'parse_request', $hooked );
    }

    public function test_matches_the_root_key_file_when_permalinks_are_on(): void {
        $this->settings->setPermalinkStructure( '/%postname%/' );
        $server = new KeyFileServer( $this->settings );

        $this->assertTrue( $server->matches( $this->key . '.txt', [] ) );
        $this->assertTrue( $server->matches( '/' . $this->key . '.txt', [] ) );
        $this->assertFalse( $server->matches( 'some-other-page', [] ) );
        $this->assertFalse( $server->matches( $this->key . '.txt.bak', [] ) );
    }

    public function test_matches_the_query_argument_when_permalinks_are_off(): void {
        $this->settings->setPermalinkStructure( '' );
        $server = new KeyFileServer( $this->settings );

        $this->assertTrue( $server->matches( '', [ IndexNowSettings::KEY_QUERY_ARG => $this->key ] ) );
        $this->assertFalse( $server->matches( '', [ IndexNowSettings::KEY_QUERY_ARG => 'wrong' ] ) );
        $this->assertFalse( $server->matches( '', [] ) );
    }

    public function test_never_intercepts_a_normal_request(): void {
        $this->settings->setPermalinkStructure( '/%postname%/' );

        $this->assertFalse( ( new KeyFileServer( $this->settings ) )->matches( 'hello-world', [] ) );
    }
}
```

`respond()` calls `exit`, so it is not unit tested directly. Instead add a test that asserts the response body is exactly the key by extracting the emission into a testable seam:

```php
/**
 * Response body for the key file, separated so tests never hit exit.
 */
public function responseBody( string $key ): string {
    return esc_html( $key );
}
```

Then assert `$server->responseBody( $this->key ) === $this->key` and that a key containing markup is escaped.

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter KeyFileServerTest`
Expected: FAIL, class not found.

- [ ] **Step 3: Write the minimal implementation**

* `matches()` reads the permalink structure through the Task 1 seam. With a structure, compare `trim( $requestPath, '/' )` to `keyFileName()` using `hash_equals()` for constant time. Without a structure, compare `$query[ self::KEY_QUERY_ARG ]` to the key with `hash_equals()`. An empty key never matches.
* `maybeServe()` resolves the request path and query, returns silently when `matches()` is false, otherwise calls `respond()`.
* `respond()` sends `Content-Type: text/plain; charset=utf-8`, `X-Robots-Tag: noindex, nofollow`, calls `nocache_headers()` and `status_header( 200 )`, prints `responseBody( $key )`, then `exit`. Guard `defined( 'RANKKERNEL_TESTING' )` so a test can call it without dying, matching the pattern already used in `DashboardPage::handleToggle()`.

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter KeyFileServerTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Modules/InstantIndexing/KeyFileServer.php tests/Unit/KeyFileServerTest.php
git commit -m "feat(instant-indexing): serve the verification key file virtually (#80)"
```

---

### Task 4: `UrlCollector` and `InstantIndexingModule`

**Files:**
- Create: `src/Modules/InstantIndexing/UrlCollector.php`
- Create: `src/Modules/InstantIndexing/InstantIndexingModule.php`
- Modify: `src/Plugin.php`, register the module next to the other optional modules around the sitemaps and robots registrations
- Test: `tests/Unit/InstantIndexingModuleTest.php`

**Interfaces:**
- Consumes: Tasks 1, 2 and 3 in full.
- Produces: `InstantIndexingModule` implementing `RankKernel\Modules\ModuleInterface`, and a `submitUrls()` entry point for Task 5.

```php
namespace RankKernel\Modules\InstantIndexing;

final class UrlCollector {
    public function __construct( IndexNowSettings $settings );
    public function snapshotPermalink( int $postId, string $permalink ): void;
    public function postUrls( int $postId, string $newStatus, string $oldStatus ): array;
    public function termUrl( int $termId, string $taxonomy ): string;
    public function wasRecentlySubmitted( int $postId, string $url ): bool;
    public function markSubmitted( int $postId, string $url ): void;
}

final class InstantIndexingModule implements ModuleInterface {
    public function __construct(
        ?ModuleEnableMap $enableMap = null,
        ?IndexNowSettings $settings = null,
        ?IndexNowClient $client = null
    );
    public function getId(): string;
    public function getName(): string;
    public function getPriority(): int;
    public function dependsOn(): array;
    public function isEnabled(): bool;
    public function register(): void;
    public function boot(): void;
    public function submitUrls( array $urls, string $source ): array;
    public function onTransitionPostStatus( string $newStatus, string $oldStatus, mixed $post ): void;
    public function onTermChange( int $termId, int $ttId, string $taxonomy, string $action ): void;
}
```

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\InstantIndexing\IndexNowClient;
use RankKernel\Modules\InstantIndexing\IndexNowSettings;
use RankKernel\Modules\InstantIndexing\InstantIndexingModule;
use RankKernel\Modules\ModuleEnableMap;
use WP_Post;

final class InstantIndexingModuleTest extends TestCase {
    private IndexNowSettings $settings;
    private int $clientCalls = 0;

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
            define( 'RANKKERNEL_TESTING', true );
        }

        $stored = [];
        Functions\when( 'get_option' )->alias( static function ( string $k, mixed $f = false ) use ( &$stored ): mixed {
            return $stored[ $k ] ?? $f;
        } );
        Functions\when( 'update_option' )->alias( static function ( string $k, mixed $v, mixed $a = null ) use ( &$stored ): bool {
            $stored[ $k ] = $v;
            return true;
        } );
        Functions\when( 'home_url' )->alias( static fn( string $p = '' ): string => 'https://example.com' . $p );
        Functions\when( 'trailingslashit' )->alias( static fn( string $u ): string => rtrim( $u, '/' ) . '/' );
        Functions\when( 'get_permalink' )->alias( static fn( int $id ): string => 'https://example.com/p' . $id );
        Functions\when( 'get_term_link' )->alias( static fn( int $id ): string => 'https://example.com/t' . $id );
        Functions\when( 'get_post_meta' )->justReturn( '' );
        Functions\when( 'update_post_meta' )->justReturn( true );
        Functions\when( 'wp_is_post_revision' )->justReturn( false );
        Functions\when( 'wp_is_post_autosave' )->justReturn( false );
        Functions\when( 'is_post_type_viewable' )->justReturn( true );
        Functions\when( 'is_post_status_viewable' )->justReturn( true );
        Functions\when( 'is_taxonomy_viewable' )->justReturn( true );
        Functions\when( 'get_post_type_object' )->justReturn( (object) [ 'name' => 'post' ] );
        Functions\when( 'taxonomy_exists' )->justReturn( true );
        Functions\when( 'add_action' )->justReturn( true );
        Functions\when( 'add_filter' )->justReturn( true );

        $this->settings = new IndexNowSettings();
        $this->settings->ensureKey();
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    private function client(): IndexNowClient {
        $transport = function (): array {
            $this->clientCalls++;
            return [ 'response' => [ 'code' => 200 ], 'body' => '' ];
        };

        return new IndexNowClient( $this->settings, $transport );
    }

    private function module( bool $enabled, bool $autoSubmit ): InstantIndexingModule {
        $map = new ModuleEnableMap();
        Functions\when( 'get_option' )->alias( static function ( string $k, mixed $f = false ) use ( $enabled ): mixed {
            if ( 'rankkernel_modules' === $k ) {
                return $enabled ? [ 'instant-indexing' ] : [];
            }
            return $f;
        } );

        $this->settings->set( [ 'auto_submit' => $autoSubmit ] );

        return new InstantIndexingModule( $map, $this->settings, $this->client() );
    }

    public function test_module_identity(): void {
        $module = $this->module( true, true );

        $this->assertSame( 'instant-indexing', $module->getId() );
        $this->assertSame( 'Instant Indexing (IndexNow)', $module->getName() );
        $this->assertSame( 45, $module->getPriority() );
        $this->assertSame( [], $module->dependsOn() );
    }

    public function test_disabled_module_registers_zero_hooks(): void {
        $hooks = [];
        Functions\when( 'add_action' )->alias( static function ( string $h ) use ( &$hooks ): bool {
            $hooks[] = $h;
            return true;
        } );
        Functions\when( 'add_filter' )->alias( static function ( string $h ) use ( &$hooks ): bool {
            $hooks[] = $h;
            return true;
        } );

        $module = $this->module( false, true );
        $module->register();
        $module->boot();

        $this->assertSame( [], $hooks );
    }

    public function test_enabling_the_module_alone_sends_nothing(): void {
        $module = $this->module( true, false );
        $module->boot();

        $post     = Mockery::mock( WP_Post::class );
        $post->ID = 42;

        $module->onTransitionPostStatus( 'publish', 'draft', $post );

        $this->assertSame( 0, $this->clientCalls, 'auto_submit off must produce zero outbound calls' );
    }

    public function test_auto_submit_on_publish_submits_the_permalink(): void {
        $module = $this->module( true, true );
        $module->boot();

        $post     = Mockery::mock( WP_Post::class );
        $post->ID = 42;

        $module->onTransitionPostStatus( 'publish', 'draft', $post );

        $this->assertSame( 1, $this->clientCalls );
    }

    public function test_revision_is_never_submitted(): void {
        Functions\when( 'wp_is_post_revision' )->justReturn( 7 );

        $module = $this->module( true, true );
        $module->boot();

        $post     = Mockery::mock( WP_Post::class );
        $post->ID = 7;

        $module->onTransitionPostStatus( 'publish', 'draft', $post );

        $this->assertSame( 0, $this->clientCalls );
    }

    public function test_autosave_is_never_submitted(): void {
        Functions\when( 'wp_is_post_autosave' )->justReturn( 9 );

        $module = $this->module( true, true );
        $module->boot();

        $post     = Mockery::mock( WP_Post::class );
        $post->ID = 9;

        $module->onTransitionPostStatus( 'publish', 'draft', $post );

        $this->assertSame( 0, $this->clientCalls );
    }

    public function test_non_public_post_type_is_never_submitted(): void {
        Functions\when( 'is_post_type_viewable' )->justReturn( false );

        $module = $this->module( true, true );
        $module->boot();

        $post     = Mockery::mock( WP_Post::class );
        $post->ID = 5;

        $module->onTransitionPostStatus( 'publish', 'draft', $post );

        $this->assertSame( 0, $this->clientCalls );
    }

    public function test_unpublishing_a_draft_submits_nothing(): void {
        $module = $this->module( true, true );
        $module->boot();

        $post     = Mockery::mock( WP_Post::class );
        $post->ID = 3;

        $module->onTransitionPostStatus( 'draft', 'publish', $post );

        $this->assertSame( 0, $this->clientCalls, 'going from public to non public is not a publish signal' );
    }
}
```

Also cover the debounce, the pre-trash permalink and the term path:

```php
public function test_same_url_inside_the_debounce_window_submits_once(): void {
    $meta = [];
    Functions\when( 'get_post_meta' )->alias( static function ( int $id, string $k, bool $single = false ) use ( &$meta ): mixed {
        return $meta[ $k ] ?? '';
    } );
    Functions\when( 'update_post_meta' )->alias( static function ( int $id, string $k, mixed $v ) use ( &$meta ): bool {
        $meta[ $k ] = $v;
        return true;
    } );
    Functions\when( 'time' )->justReturn( 1000 );

    $module = $this->module( true, true );
    $module->boot();

    $post     = Mockery::mock( WP_Post::class );
    $post->ID = 11;

    $module->onTransitionPostStatus( 'publish', 'draft', $post );
    $module->onTransitionPostStatus( 'publish', 'publish', $post );

    $this->assertSame( 1, $this->clientCalls );
}

public function test_trashing_submits_the_permalink_captured_before_the_trash(): void {
    $module = $this->module( true, true );
    $module->boot();

    $post     = Mockery::mock( WP_Post::class );
    $post->ID = 21;

    $module->onTransitionPostStatus( 'publish', 'publish', $post );
    $callsAfterUpdate = $this->clientCalls;

    $module->onTransitionPostStatus( 'trash', 'publish', $post );

    $this->assertGreaterThan( $callsAfterUpdate, $this->clientCalls );
}

public function test_term_create_submits_the_term_url(): void {
    $module = $this->module( true, true );
    $module->boot();

    $module->onTermChange( 8, 8, 'category', 'created' );

    $this->assertSame( 1, $this->clientCalls );
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter InstantIndexingModuleTest`
Expected: FAIL, class not found.

- [ ] **Step 3: Write the minimal implementation**

* `UrlCollector` holds an in-memory `array<int, string>` of permalinks snapshotted before an update, plus debounce reads and writes against `_rankkernel_indexnow`. `markSubmitted()` writes `last_url` and `last_submitted`. `wasRecentlySubmitted()` returns true when the stored `last_url` matches and `last_submitted` is within `IndexNowSettings::DEBOUNCE_SECONDS`. All WordPress helpers are `function_exists()` guarded.
* `InstantIndexingModule::register()` calls `ensureKey()` only when enabled. `boot()` returns immediately when disabled, otherwise registers `KeyFileServer::register()`, the `pre_post_update` permalink snapshot, `transition_post_status`, and the created, edited and deleted term hooks.
* `onTransitionPostStatus()` guard order: not a revision, not an autosave, post type viewable, then the signal test. A signal is a transition into `publish`, a transition from `publish` to `publish`, or a transition into `trash` from a public status. `publish` to `draft` is deliberately not a signal, matching the sitemaps precedent already in the codebase.
* For `trash`, submit the snapshotted permalink so the URL that existed is the one signalled.
* `submitUrls()` is the single entry point. It applies the debounce, calls `IndexNowClient::submit()`, and never throws.
* No `wp_schedule_event`, no cron, no Action Scheduler.

- [ ] **Step 4: Register the module in `src/Plugin.php`**

Add the import and, next to the other optional module registrations:

```php
$instantIndexingModule = new InstantIndexingModule( $enableMap );
$moduleManager->register( $instantIndexingModule );
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter InstantIndexingModuleTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Modules/InstantIndexing/UrlCollector.php src/Modules/InstantIndexing/InstantIndexingModule.php src/Plugin.php tests/Unit/InstantIndexingModuleTest.php
git commit -m "feat(instant-indexing): add the guarded auto submit module (#80)"
```

---

### Task 5: Admin surface

**Files:**
- Create: `src/Admin/InstantIndexingPage.php`
- Create: `src/Admin/Views/instant-indexing.php`
- Modify: `src/Admin/AdminMenu.php`, construct the page, add `addInstantIndexingPage()` and `getInstantIndexingPage()`
- Modify: `src/Admin/DashboardPage.php`, point the card at the new page and drop the word Planned
- Test: `tests/Unit/InstantIndexingPageTest.php`

**Interfaces:**
- Consumes: `InstantIndexingModule::submitUrls()` and `IndexNowSettings` from earlier tasks.
- Produces: nothing consumed by later tasks. This is the final task.

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\InstantIndexingPage;
use RankKernel\Modules\InstantIndexing\IndexNowSettings;

final class InstantIndexingPageTest extends TestCase {
    private IndexNowSettings $settings;
    private string $key;
    private string $output = '';

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();

        if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
            define( 'RANKKERNEL_TESTING', true );
        }

        $stored = [];
        Functions\when( 'get_option' )->alias( static function ( string $k, mixed $f = false ) use ( &$stored ): mixed {
            return $stored[ $k ] ?? $f;
        } );
        Functions\when( 'update_option' )->alias( static function ( string $k, mixed $v, mixed $a = null ) use ( &$stored ): bool {
            $stored[ $k ] = $v;
            return true;
        } );
        Functions\when( 'home_url' )->alias( static fn( string $p = '' ): string => 'https://example.com' . $p );
        Functions\when( 'admin_url' )->alias( static fn( string $p = '' ): string => 'https://example.com/wp-admin/' . $p );
        Functions\when( 'trailingslashit' )->alias( static fn( string $u ): string => rtrim( $u, '/' ) . '/' );
        Functions\when( 'esc_html' )->alias( static fn( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
        Functions\when( 'esc_attr' )->alias( static fn( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
        Functions\when( 'esc_url' )->alias( static fn( string $v ): string => $v );
        Functions\when( 'esc_html__' )->alias( static fn( string $v ): string => $v );
        Functions\when( '__' )->alias( static fn( string $v ): string => $v );
        Functions\when( 'wp_nonce_field' )->justReturn( '' );
        Functions\when( 'submit_button' )->justReturn( '' );
        Functions\when( 'checked' )->alias( static fn( bool $c, bool $e = true, bool $echo = true ): string => ( $c === $e ) ? ' checked' : '' );
        Functions\when( 'selected' )->alias( static fn( bool $c, bool $e = true, bool $echo = true ): string => ( $c === $e ) ? ' selected' : '' );
        Functions\when( 'wp_safe_redirect' )->justReturn( true );
        Functions\when( 'wp_die' )->alias( function ( string $m = '', string $t = '', array $a = [] ): void {
            throw new RuntimeException( $m );
        } );

        $this->settings = new IndexNowSettings();
        $this->key      = $this->settings->ensureKey();
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    private function page( ?callable $submit = null ): InstantIndexingPage {
        return new InstantIndexingPage( $this->settings, $submit );
    }

    public function test_render_never_contains_the_key(): void {
        $page = $this->page();
        ob_start();
        $page->render();
        $html = (string) ob_get_clean();

        $this->assertStringNotContainsString( $this->key, $html, 'the API key must never reach admin HTML' );
        $this->assertStringContainsString( 'Instant Indexing', $html );
    }

    public function test_render_shows_configured_state_without_the_key(): void {
        $page = $this->page();
        ob_start();
        $page->render();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString( 'configured', strtolower( $html ) );
    }

    public function test_manual_submit_is_rejected_without_capability(): void {
        Functions\when( 'current_user_can' )->justReturn( false );
        $_POST = [ 'rankkernel_indexnow_action' => 'submit', 'rankkernel_indexnow_url' => 'https://example.com/a' ];

        $this->expectException( RuntimeException::class );

        $this->page()->maybeHandleSave();
    }

    public function test_manual_submit_is_rejected_without_a_valid_nonce(): void {
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'check_admin_referer' )->justReturn( false );
        $_POST = [ 'rankkernel_indexnow_action' => 'submit', 'rankkernel_indexnow_url' => 'https://example.com/a' ];

        $this->expectException( RuntimeException::class );

        $this->page()->maybeHandleSave();
    }

    public function test_manual_submit_rejects_a_url_on_another_host_without_submitting(): void {
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'check_admin_referer' )->justReturn( 1 );

        $called = 0;
        $page   = $this->page( function () use ( &$called ): void {
            $called++;
        } );

        $_POST = [ 'rankkernel_indexnow_action' => 'submit', 'rankkernel_indexnow_url' => 'https://evil.test/a' ];
        $page->maybeHandleSave();

        $this->assertSame( 0, $called, 'a foreign host must never be submitted' );
    }

    public function test_manual_submit_sends_a_valid_same_host_url(): void {
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'check_admin_referer' )->justReturn( 1 );
        Functions\when( 'wp_http_validate_url' )->alias( static fn( string $u ): string => $u );

        $called = 0;
        $page   = $this->page( function () use ( &$called ): void {
            $called++;
        } );

        $_POST = [ 'rankkernel_indexnow_action' => 'submit', 'rankkernel_indexnow_url' => 'https://example.com/a' ];
        $page->maybeHandleSave();

        $this->assertSame( 1, $called );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter InstantIndexingPageTest`
Expected: FAIL, class not found.

- [ ] **Step 3: Write the page**

`InstantIndexingPage` takes `IndexNowSettings` and an optional `?callable $submit` used by tests, defaulting to a closure that calls the real client. Constants: `SLUG = 'rankkernel-instant-indexing'`, `CAPABILITY = 'manage_options'`, and one nonce action per write, all prefixed `rankkernel_indexnow_`.

`maybeHandleSave()` handles three actions, each in this order and each rejecting independently:

1. `save` toggles `auto_submit`. The checkbox semantics are absent from POST means false, matching the existing `purge_on_uninstall` pattern in `SettingsPage`.
2. `regenerate` calls `resetKey()`.
3. `submit` reads `rankkernel_indexnow_url`, runs it through `wp_http_validate_url()`, compares its host to `IndexNowSettings::siteHost()` with `hash_equals()`, and calls the submit callback only on a match. A foreign host records a rejected log entry and never calls the submit callback.

Every branch: `current_user_can( self::CAPABILITY )` then `check_admin_referer( <matching action> )`, each failing to `wp_die()` with a 403. Then `wp_safe_redirect()` back to the page with `settings-updated=1`, and `exit` only when `RANKKERNEL_TESTING` is not defined.

`render()` requires a view file and shows: whether a key is configured, never the key; the auto-submit checkbox; a regenerate button; the manual submit form; and the log table with URL, status code, source, time and message. Escape every field. Do not print the key in any attribute, comment, or `data-` attribute.

- [ ] **Step 4: Wire the page**

In `AdminMenu::__construct()` add:

```php
$this->instantIndexingPage = new InstantIndexingPage();
```

Add an `addInstantIndexingPage()` that mirrors `addRedirectsPage()` exactly, using slug `rankkernel-instant-indexing`, plus a `getInstantIndexingPage()` for tests.

In `src/Plugin.php`, next to the other page hooks:

```php
add_action( 'admin_menu', [ $adminMenu, 'addInstantIndexingPage' ] );
```

In `DashboardPage`, add `'instant-indexing' => 'rankkernel-instant-indexing',` to `SETTINGS_PAGES`, and change the `DESCRIPTIONS` entry to drop the trailing `Planned.`:

```php
'instant-indexing' => 'Notify participating search engines when a URL changes, using the IndexNow protocol.',
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter InstantIndexingPageTest`
Expected: PASS.

- [ ] **Step 6: Run the full gate suite**

```bash
composer lint && composer stan && composer test && composer test:js
find assets/js -name '*.js' -print0 | xargs -0 -n1 node --check
```

Expected: all clean.

- [ ] **Step 7: Commit**

```bash
git add src/Admin/InstantIndexingPage.php src/Admin/Views/instant-indexing.php src/Admin/AdminMenu.php src/Admin/DashboardPage.php src/Plugin.php tests/Unit/InstantIndexingPageTest.php
git commit -m "feat(instant-indexing): add the admin page with manual submit (#80)"
```

---

## Self-Review

**Spec coverage.** Section 2 protocol facts land in Tasks 1 and 2. Section 2.3 submittable set lands in Task 4. Section 3.1 trap 1 (key exposure) is asserted in Task 5 and Task 1. Trap 2 (autosave guard) is asserted in Task 4. Trap 3 (no telemetry headers) is asserted in Task 2. Trap 4 (no fetch of user URLs) is asserted in Task 5. Trap 5 (virtual key file) is Task 3. Trap 6 (blocking request) is asserted in Task 2. Section 5 storage names are defined in Task 1. Section 6 matrix is distributed across Tasks 1 through 5; the two opt-in rows are asserted in Task 4.

**Placeholder scan.** No TBD, no TODO, no "similar to Task N". Every referenced method is defined in the task that owns it.

**Type consistency.** `getKey()`, `keyLocation()`, `siteHost()`, `logEntry()` are called by Tasks 2, 3, 4 and 5 and are all declared in Task 1. `IndexNowClient::submit()` returns the `accepted`/`permanent`/`transient`/`results` shape in Task 2 and Task 4 consumes only `submit()`. `IndexNowSettings::KEY_QUERY_ARG` and the permalink structure seam are defined in Task 1 and used by Task 3.

**Known deviation, deliberate.** The spec's test matrix asks for an HTTP level check that the key file serves over the wire. That is not a unit test and belongs in the verification pass, not in this plan.
