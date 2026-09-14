<?php
/**
 * 404 settings tests, defaults plus growth bound clamping.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Monitor\MonitorSettings;

final class MonitorSettingsTest extends TestCase {
	/**
	 * Option store.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( '__' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value ): bool {
				$this->options[ $key ] = $value;

				return true;
			}
		);
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	public function test_defaults_match_binding_contract(): void {
		$settings = new MonitorSettings();

		$this->assertSame( MonitorSettings::OPTION, 'rankkernel_404_settings' );
		$this->assertFalse( $settings->isAdvancedFields() );
		$this->assertSame( 30, $settings->getRetentionDays() );
		$this->assertSame( 1000, $settings->getMaxRows() );
		$this->assertSame( 50, $settings->getFloodBudget() );
		$this->assertSame( 300, $settings->getFloodWindow() );
		$this->assertTrue( $settings->isIgnoreQuery() );
		$this->assertSame( [], $settings->getExclusions() );
	}

	public function test_ensure_schema_seeds_defaults_once(): void {
		$settings = new MonitorSettings();
		$settings->ensureSchema();

		$this->assertSame( MonitorSettings::defaults(), $this->options[ MonitorSettings::OPTION ] );

		$this->options[ MonitorSettings::OPTION ]['retention_days'] = 60;

		$settings->ensureSchema();

		$this->assertSame( 60, $settings->getRetentionDays() );
	}

	public function test_retention_days_cannot_be_zero_or_unbounded(): void {
		$settings = new MonitorSettings();

		$this->assertTrue( $settings->set( [ 'retention_days' => 0 ] ) );
		$this->assertSame( 1, $settings->getRetentionDays() );

		$this->assertTrue( $settings->set( [ 'retention_days' => '' ] ) );
		$this->assertSame( 1, $settings->getRetentionDays() );

		$this->assertTrue( $settings->set( [ 'retention_days' => 999 ] ) );
		$this->assertSame( 365, $settings->getRetentionDays() );

		$this->assertTrue( $settings->set( [ 'retention_days' => 90 ] ) );
		$this->assertSame( 90, $settings->getRetentionDays() );
	}

	public function test_max_rows_cannot_be_zero_or_unbounded(): void {
		$settings = new MonitorSettings();

		$this->assertTrue( $settings->set( [ 'max_rows' => 0 ] ) );
		$this->assertSame( 100, $settings->getMaxRows() );

		$this->assertTrue( $settings->set( [ 'max_rows' => 50 ] ) );
		$this->assertSame( 100, $settings->getMaxRows() );

		$this->assertTrue( $settings->set( [ 'max_rows' => 99999 ] ) );
		$this->assertSame( 10000, $settings->getMaxRows() );

		$this->assertTrue( $settings->set( [ 'max_rows' => 2500 ] ) );
		$this->assertSame( 2500, $settings->getMaxRows() );
	}

	public function test_flood_budget_and_window_clamp(): void {
		$settings = new MonitorSettings();

		$this->assertTrue( $settings->set( [ 'flood_budget' => 0 ] ) );
		$this->assertSame( 1, $settings->getFloodBudget() );

		$this->assertTrue( $settings->set( [ 'flood_budget' => 5000 ] ) );
		$this->assertSame( 1000, $settings->getFloodBudget() );

		$this->assertTrue( $settings->set( [ 'flood_window' => 0 ] ) );
		$this->assertSame( 60, $settings->getFloodWindow() );

		$this->assertTrue( $settings->set( [ 'flood_window' => 99999 ] ) );
		$this->assertSame( 3600, $settings->getFloodWindow() );
	}

	public function test_boolean_keys_sanitize(): void {
		$settings = new MonitorSettings();

		$this->assertTrue( $settings->set( [ 'advanced_fields' => 'yes' ] ) );
		$this->assertTrue( $settings->isAdvancedFields() );

		$this->assertTrue( $settings->set( [ 'ignore_query' => 0 ] ) );
		$this->assertFalse( $settings->isIgnoreQuery() );
	}

	public function test_unknown_keys_are_dropped(): void {
		$settings = new MonitorSettings();

		$this->assertFalse( $settings->set( [ 'unknown_key' => 'value' ] ) );
		$this->assertFalse( $settings->set( [] ) );
	}

	public function test_exclusions_keep_only_well_formed_rules(): void {
		$settings = new MonitorSettings();

		$ok = $settings->set(
			[
				'exclusions' => [
					[
						'comparator' => 'prefix',
						'value'      => '/private',
					],
					[
						'comparator' => 'regex',
						'value'      => '/nope',
					],
					[
						'comparator' => 'exact',
						'value'      => '',
					],
					'not-an-array',
					[
						'comparator' => 'contains',
						'value'      => '  temp  ',
					],
				],
			]
		);

		$this->assertTrue( $ok );

		$this->assertSame(
			[
				[
					'comparator' => 'prefix',
					'value'      => '/private',
				],
				[
					'comparator' => 'contains',
					'value'      => 'temp',
				],
			],
			$settings->getExclusions()
		);
	}

	public function test_exclusions_reject_non_array(): void {
		$settings = new MonitorSettings();

		$this->assertTrue( $settings->set( [ 'exclusions' => 'not-an-array' ] ) );
		$this->assertSame( [], $settings->getExclusions() );
	}
}
