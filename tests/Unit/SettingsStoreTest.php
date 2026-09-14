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
		// update_option returns false (no DB change), should still be true.
		Functions\expect( 'update_option' )->once()->andReturn( false );

		$store  = new SettingsStore();
		$result = $store->set( [ 'title_template' => 'Same' ] );

		$this->assertTrue( $result, 'no-op save with valid key must return true' );
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
