<?php
/**
 * Instant Indexing settings tests, key storage, key location and log.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\InstantIndexing\IndexNowSettings;
use RankKernel\Modules\InstantIndexing\LogTable;

/**
 * IndexNow Settings Test.
 */
final class IndexNowSettingsTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Option store backing the get_option and update_option stubs.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * Fake database backing the log table storage.
	 *
	 * @var InstantIndexingFakeDb
	 */
	private InstantIndexingFakeDb $db;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		$this->db = new InstantIndexingFakeDb();

		// Test installs the in memory wpdb double here and restores it in tearDown.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $this->db;

		// The suite models a site with pretty permalinks, which is the
		// state keyLocation() serves the root txt file from. The plain
		// permalink case opts out through the public seam.
		$this->stored['permalink_structure'] = '/%postname%/';

		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->stored[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value, mixed $autoload = null ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress update_option signature.
				$this->stored[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'home_url' )->alias( static fn( string $p = '' ): string => 'https://example.com' . $p );
		Functions\when( 'trailingslashit' )->alias( static fn( string $url ): string => rtrim( $url, '/' ) . '/' );
		Functions\when( 'add_query_arg' )->alias(
			static function ( string $key, string $value, string $url ): string {
				$separator = str_contains( $url, '?' ) ? '&' : '?';

				return $url . $separator . $key . '=' . rawurlencode( $value );
			}
		);
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): string|int|false|null {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double mirrors wp_parse_url with the native parser.
				return parse_url( $url, $component );
			}
		);

		$generated = 0;
		Functions\when( 'wp_generate_password' )->alias(
			static function ( int $length = 12 ) use ( &$generated ): string {
				++$generated;

				return str_pad( 'testkey' . $generated, $length, 'x' );
			}
		);
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test defaults are off and keyless.
	 */
	public function test_defaults_are_off_and_keyless(): void {
		$this->assertSame(
			[
				'api_key'     => '',
				'auto_submit' => false,
			],
			IndexNowSettings::defaults()
		);
	}

	/**
	 * Test generated key satisfies the protocol alphabet.
	 */
	public function test_generated_key_satisfies_the_protocol_alphabet(): void {
		$settings = new IndexNowSettings();
		$key      = $settings->ensureKey();

		$this->assertTrue( IndexNowSettings::isValidKey( $key ) );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9-]{8,128}$/', $key );
	}

	/**
	 * Test is valid key rejects bad length and alphabet.
	 */
	public function test_is_valid_key_rejects_bad_length_and_alphabet(): void {
		$this->assertFalse( IndexNowSettings::isValidKey( 'short' ) );
		$this->assertFalse( IndexNowSettings::isValidKey( str_repeat( 'a', 129 ) ) );
		$this->assertFalse( IndexNowSettings::isValidKey( 'has_underscore' ) );
		$this->assertFalse( IndexNowSettings::isValidKey( 'has space here' ) );
		$this->assertTrue( IndexNowSettings::isValidKey( str_repeat( 'a', 128 ) ) );
	}

	/**
	 * Test reset key replaces the stored key.
	 */
	public function test_reset_key_replaces_the_stored_key(): void {
		$settings = new IndexNowSettings();
		$first    = $settings->ensureKey();
		$second   = $settings->resetKey();

		$this->assertNotSame( $first, $second );
		$this->assertSame( $second, $settings->getKey() );
	}

	/**
	 * Test auto submit defaults to false.
	 */
	public function test_auto_submit_defaults_to_false(): void {
		$this->assertFalse( ( new IndexNowSettings() )->getAutoSubmit() );
	}

	/**
	 * Test site host has no scheme and no www collapsing.
	 */
	public function test_site_host_has_no_scheme_and_no_www_collapsing(): void {
		$this->assertSame( 'example.com', ( new IndexNowSettings() )->siteHost() );
	}

	/**
	 * Test key location is root txt when permalinks are on.
	 */
	public function test_key_location_is_root_txt_when_permalinks_are_on(): void {
		$settings = new IndexNowSettings();
		$key      = $settings->ensureKey();

		$this->assertSame( 'https://example.com/' . $key . '.txt', $settings->keyLocation() );
	}

	/**
	 * Test key location falls back to a query argument on plain permalinks.
	 */
	public function test_key_location_falls_back_to_a_query_argument_on_plain_permalinks(): void {
		$settings = new IndexNowSettings();
		$key      = $settings->ensureKey();

		$settings->setPermalinkStructure( '' );

		$this->assertStringContainsString( IndexNowSettings::KEY_QUERY_ARG . '=' . $key, $settings->keyLocation() );
	}

	/**
	 * Test the log keeps every entry, newest first, pages it and clears.
	 *
	 * The former fifty row ring buffer is gone by owner decision: rows are
	 * kept until an admin clears the log, and the table is the record.
	 */
	public function test_log_keeps_every_entry_newest_first_pages_and_clears(): void {
		$settings = new IndexNowSettings();

		for ( $i = 0; $i < 60; $i++ ) {
			$settings->logEntry( 'https://example.com/p' . $i, 200, 'auto', 'ok' );
		}

		$entries = $settings->logEntries();

		$this->assertCount( 60, $entries, 'the old fifty row cap must be gone, every row is kept' );
		$this->assertSame( 'https://example.com/p59', $entries[0]['url'] );
		$this->assertSame( 60, $settings->countLog() );

		$page = $settings->logPage( 10, 10 );

		$this->assertCount( 10, $page, 'the paged read must honour limit and offset' );
		$this->assertSame( 'https://example.com/p49', $page[0]['url'] );
		$this->assertSame( 'example.com', $page[0]['host'], 'the stored host must round trip' );

		$settings->clearLog();

		$this->assertSame( [], $settings->logEntries() );
		$this->assertSame( 0, $settings->countLog() );
	}

	/**
	 * Test log entry is a single insert with no read of the log first.
	 */
	public function test_log_entry_inserts_without_reading_the_log_first(): void {
		$settings = new IndexNowSettings();

		// Warm the table existence probe so a hidden read would show up.
		LogTable::exists();
		$readsBefore = $this->db->reads;

		$settings->logEntry( 'https://www.example.com/a?b=1', 429, 'auto', 'Temporary failure, retry later.' );

		$this->assertSame( $readsBefore, $this->db->reads, 'logEntry must not select from the log before it inserts' );
		$this->assertSame( 1, $this->db->writes );

		$row = reset( $this->db->rows );

		$this->assertSame( 'https://www.example.com/a?b=1', $row['url'] );
		$this->assertSame( 'www.example.com', $row['host'] );
		$this->assertSame( 429, $row['code'] );
		$this->assertSame( 'auto', $row['source'] );
		$this->assertSame( 'Temporary failure, retry later.', $row['message'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $row['created'], 'the stored timestamp must stay UTC gmdate format' );
	}

	/**
	 * Test reads are bounded and values are clamped to the column widths.
	 */
	public function test_reads_are_bounded_and_values_fit_the_columns(): void {
		$settings = new IndexNowSettings();

		for ( $i = 0; $i < 250; $i++ ) {
			$settings->logEntry( 'https://example.com/p' . $i, 200, 'auto', 'ok' );
		}

		$this->assertCount( IndexNowSettings::READ_LIMIT, $settings->logEntries(), 'logEntries must never read an unbounded table' );
		$this->assertCount( IndexNowSettings::READ_LIMIT, $settings->logPage( 1000, 0 ), 'logPage must cap an oversized limit' );

		$settings->clearLog();
		$settings->logEntry( 'https://example.com/long', 999999, str_repeat( 's', 30 ), str_repeat( 'm', 600 ) );

		$row = $settings->logPage( 1, 0 )[0];

		$this->assertSame( 65535, $row['code'], 'the code must clamp to the SMALLINT UNSIGNED range' );
		$this->assertSame( 20, strlen( $row['source'] ) );
		$this->assertSame( 500, strlen( $row['message'] ) );
	}

	/**
	 * Test the log fails open when no database is available.
	 */
	public function test_log_fails_open_without_a_database(): void {
		unset( $GLOBALS['wpdb'] );

		$settings = new IndexNowSettings();

		$settings->logEntry( 'https://example.com/a', 200, 'manual', 'Accepted.' );

		$this->assertSame( [], $settings->logEntries() );
		$this->assertSame( [], $settings->logPage( 20, 0 ) );
		$this->assertSame( 0, $settings->countLog() );
		$settings->clearLog();
	}
}
