<?php
/**
 * Redirects plus 404 Monitor uninstall tests, naming contract plus live purge.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Monitor\FloodGuard;
use RankKernel\Modules\Monitor\LogTable;
use RankKernel\Modules\Monitor\MonitorSettings;
use RankKernel\Modules\Redirects\RedirectCache;
use RankKernel\Modules\Redirects\RedirectsSettings;
use RankKernel\Modules\Redirects\RedirectTable;

/**
 * Redirects Monitor Uninstall Test.
 */
final class RedirectsMonitorUninstallTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
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
	 * Every owned table and option name matches the uninstall purge prefixes.
	 */
	public function test_owned_names_match_uninstall_prefixes(): void {
		$db = new RedirectsMonitorUninstallStubDb();

		// Test installs the stub wpdb double here and restores it in tearDown.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $db;

		$this->assertSame( 'wp_rankkernel_redirects', RedirectTable::name() );
		$this->assertSame( 'wp_rankkernel_404_log', LogTable::name() );
		$this->assertStringStartsWith( 'wp_rankkernel_', RedirectTable::name() );
		$this->assertStringStartsWith( 'wp_rankkernel_', LogTable::name() );

		$ownedOptions = [
			RedirectsSettings::OPTION,
			MonitorSettings::OPTION,
			FloodGuard::SUPPRESSED_OPTION,
			RedirectCache::VALIDATOR_OPTION,
		];

		foreach ( $ownedOptions as $option ) {
			$this->assertStringStartsWith( 'rankkernel_', $option, 'Owned option must match the uninstall purge prefix' );
		}
	}

	/**
	 * The uninstall file declares the option and table prefix purges.
	 */
	public function test_uninstall_file_declares_prefix_purges(): void {
		$path = dirname( __DIR__, 2 ) . '/uninstall.php';

		// The purge pattern assertion reads the plugin uninstall file directly.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$code = file_get_contents( $path );

		$this->assertIsString( $code );
		$this->assertStringContainsString( 'option_name LIKE', $code );
		$this->assertStringContainsString( "esc_like( 'rankkernel_' )", $code );
		$this->assertStringContainsString( 'SHOW TABLES LIKE', $code );
		$this->assertStringContainsString( "\$wpdb->prefix . 'rankkernel_'", $code );
		$this->assertStringContainsString( "array( 'rankkernel_', 'rkredir_', 'rk404_flood_' )", $code );
	}

	/**
	 * A live uninstall run drops owned tables and options and keeps the rest.
	 */
	public function test_uninstall_run_purges_owned_data_only(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		$db             = new RedirectsMonitorUninstallStubDb();
		$db->optionRows = [
			'rankkernel_modules'                    => [ 'redirects' ],
			'rankkernel_redirects_settings'         => [ 'preserve_query' => true ],
			'rankkernel_404_settings'               => [ 'max_rows' => 1000 ],
			'rankkernel_redirects_validator'        => 'stale',
			'rankkernel_404_suppressed'             => 123,
			'_transient_rankkernel_sitemap'         => 'xml',
			'_transient_timeout_rankkernel_sitemap' => 123456,
			'_transient_rkredir_match'              => 'data',
			'_transient_timeout_rkredir_match'      => 123456,
			'_transient_rk404_flood_site'           => 'data',
			'_transient_timeout_rk404_flood_site'   => 123456,
			'other_plugin_option'                   => 'keep',
			'_transient_other_plugin'               => 'keep',
		];
		$db->tables     = [
			'wp_rankkernel_redirects',
			'wp_rankkernel_404_log',
			'wp_posts',
		];

		// Test installs the stub wpdb double here and restores it in tearDown.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $db;

		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ): mixed {
				if ( 'rankkernel_settings' === $key ) {
					return [ 'purge_on_uninstall' => true ];
				}

				return $fallback;
			}
		);

		require dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertSame(
			[
				'other_plugin_option'     => 'keep',
				'_transient_other_plugin' => 'keep',
			],
			$db->optionRows
		);
		$this->assertSame( [ 'wp_posts' ], array_values( $db->tables ) );

		$drops = array_values(
			array_filter(
				$db->queries,
				static fn ( string $q ): bool => str_starts_with( $q, 'DROP TABLE IF EXISTS' )
			)
		);

		$this->assertCount( 2, $drops );
	}

	/**
	 * Test the uninstall retires the cached existence flag for every dropped table.
	 *
	 * Without this a stale true would survive in a persistent object cache and
	 * ensureTables would skip recreating the table the uninstall just dropped.
	 */
	public function test_uninstall_retires_the_table_existence_cache(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		$db         = new RedirectsMonitorUninstallStubDb();
		$db->tables = [
			'wp_rankkernel_redirects',
			'wp_rankkernel_404_log',
			'wp_posts',
		];

		// Test installs the stub wpdb double here and restores it in tearDown.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $db;

		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ): mixed {
				if ( 'rankkernel_settings' === $key ) {
					return [ 'purge_on_uninstall' => true ];
				}

				return $fallback;
			}
		);

		$deleted = array();

		Functions\when( 'wp_cache_delete' )->alias(
			function ( string $key, string $group ) use ( &$deleted ): bool {
				$deleted[] = [ $key, $group ];

				return true;
			}
		);

		require dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertContains( [ 'table_exists_wp_rankkernel_redirects', 'rankkernel_tables' ], $deleted );
		$this->assertContains( [ 'table_exists_wp_rankkernel_404_log', 'rankkernel_tables' ], $deleted );
	}
}
