<?php
/**
 * SettingsStore tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Settings\SettingsStore;

/**
 * Settings Store Test.
 */
final class SettingsStoreTest extends TestCase {
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
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test org_sameas is capped.
	 *
	 * The option is autoloaded and read on every frontend request, so an
	 * unbounded list here grows a hot option on every save.
	 */
	public function test_org_sameas_is_capped(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $url ): string => $url );

		$saved = null;

		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$saved ): bool {
				$saved = $value;

				return true;
			}
		);

		$urls = [];

		for ( $i = 0; $i < SettingsStore::ORG_SAMEAS_MAX + 15; $i++ ) {
			$urls[] = 'https://example.com/profile/' . $i;
		}

		$store = new SettingsStore();

		$this->assertTrue( $store->set( [ 'org_sameas' => $urls ] ) );

		$this->assertIsArray( $saved );
		$this->assertCount( SettingsStore::ORG_SAMEAS_MAX, $saved['org_sameas'] );
		$this->assertSame( 'https://example.com/profile/0', $saved['org_sameas'][0] );
	}

	/**
	 * Test a schema_default_ key naming an unknown post type is rejected.
	 */
	public function test_schema_default_key_requires_a_registered_post_type(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'post_type_exists' )->alias(
			static fn ( string $postType ): bool => 'post' === $postType || 'page' === $postType
		);

		$saved = null;

		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$saved ): bool {
				$saved = $value;

				return true;
			}
		);

		$store = new SettingsStore();

		$this->assertTrue( $store->set( [ 'schema_default_post' => 'Article' ] ) );
		$this->assertIsArray( $saved );
		$this->assertSame( 'Article', $saved['schema_default_post'] );

		$this->assertFalse(
			$store->set( [ 'schema_default_not_a_real_type' => 'Article' ] ),
			'A key naming an unregistered post type must not be stored'
		);
	}

	/**
	 * Test the number of schema_default_ keys is capped.
	 */
	public function test_schema_default_keys_are_capped(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'post_type_exists' )->justReturn( true );

		$saved = null;

		Functions\when( 'update_option' )->alias(
			static function ( string $key, mixed $value ) use ( &$saved ): bool {
				$saved = $value;

				return true;
			}
		);

		$partial = [];

		for ( $i = 0; $i < SettingsStore::SCHEMA_DEFAULT_MAX + 20; $i++ ) {
			$partial[ 'schema_default_type' . $i ] = 'Article';
		}

		$store = new SettingsStore();

		$this->assertTrue( $store->set( $partial ) );

		$this->assertIsArray( $saved );

		$dynamic = array_filter(
			array_keys( $saved ),
			static fn ( string $key ): bool => str_starts_with( $key, 'schema_default_' )
		);

		$this->assertCount( SettingsStore::SCHEMA_DEFAULT_MAX, $dynamic );
	}

	/**
	 * Test defaults merge.
	 */
	public function test_defaults_merge(): void {
		Functions\when( 'get_option' )->justReturn( [ 'title_template' => 'Custom %%title%%' ] );

		$store = new SettingsStore();
		$all   = $store->all();

		$this->assertSame( 'Custom %%title%%', $all['title_template'] );
		// Separator default still present.
		$this->assertSame( '–', $all['separator'] );
	}

	/**
	 * Test set whitelists unknown keys.
	 */
	public function test_set_whitelists_unknown_keys(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );

		$store = new SettingsStore();

		// update_option should be called with only whitelisted keys.
		Functions\expect( 'update_option' )
			->once()
			->with(
				SettingsStore::OPTION,
				\Mockery::on(
					static function ( array $data ): bool {
						// Unknown key should NOT be present.
						if ( array_key_exists( 'evil_key', $data ) ) {
							return false;
						}
						// Whitelisted key should be present.
						return array_key_exists( 'title_template', $data );
					}
				)
			)
			->andReturn( true );

		$result = $store->set(
			[
				'title_template' => 'New title',
				'evil_key'       => 'should be ignored',
			]
		);

		$this->assertTrue( $result );
	}

	/**
	 * Test set returns false when only unknown keys.
	 */
	public function test_set_returns_false_when_only_unknown_keys(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$store = new SettingsStore();

		$result = $store->set( [ 'evil_key' => 'x' ] );

		$this->assertFalse( $result );
	}

	/**
	 * Test get returns fallback for missing key.
	 */
	public function test_get_returns_fallback_for_missing_key(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$store = new SettingsStore();

		$this->assertSame( 'fallback', $store->get( 'nonexistent', 'fallback' ) );
	}

	/**
	 * Test autoload yes option name.
	 */
	public function test_autoload_yes_option_name(): void {
		$this->assertSame( 'rankkernel_settings', SettingsStore::OPTION );
	}

	/**
	 * Test set noop same values returns true.
	 */
	public function test_set_noop_same_values_returns_true(): void {
		Functions\when( 'get_option' )->justReturn( [ 'title_template' => 'Same' ] );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		// An unchanged value is never written; update_option() would return
		// false for it, which must not be confused with a failed write.
		Functions\expect( 'update_option' )->never();

		$store  = new SettingsStore();
		$result = $store->set( [ 'title_template' => 'Same' ] );

		$this->assertTrue( $result, 'no-op save with valid key must return true' );
	}

	/**
	 * Test a failed write reports false and keeps the in-memory cache.
	 *
	 * The update_option() function returns false both for an unchanged
	 * value and for a failed write, so the changed-value guard above must
	 * run first. This covers the other half: a value that did change and
	 * whose write failed.
	 */
	public function test_set_reports_failed_write_and_keeps_cache(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\expect( 'update_option' )->once()->andReturn( false );

		$store  = new SettingsStore();
		$result = $store->set( [ 'title_template' => 'New title' ] );

		$this->assertFalse( $result, 'a changed write that fails must report false' );
		$this->assertSame(
			SettingsStore::defaults()['title_template'],
			$store->get( 'title_template' ),
			'a failed write must not update the in-memory cache'
		);
	}

	/**
	 * Test set all unknown keys returns false no db write.
	 */
	public function test_set_all_unknown_keys_returns_false_no_db_write(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\expect( 'update_option' )->never();

		$store  = new SettingsStore();
		$result = $store->set(
			[
				'unknown_one' => 'x',
				'unknown_two' => 'y',
			]
		);

		$this->assertFalse( $result );
	}
}
