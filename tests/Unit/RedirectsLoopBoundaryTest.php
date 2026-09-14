<?php
/**
 * Loop boundary tests, caps report inconclusive while real cycles block.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\CsvHandler;
use RankKernel\Modules\Redirects\RedirectRepository;
use RankKernel\Modules\Redirects\SlugWatcher;
use RankKernel\Modules\Redirects\Validator;

/**
 * Proves the MAX_DEPTH and MAX_NODES caps never read as proof of safety.
 *
 * A limit overrun reports inconclusive with no cycle, a real cycle still
 * blocks inside a large unrelated set, the slug watcher fails closed when
 * no administrator is present to warn, and CSV import carries the
 * could not fully verify warning.
 */
final class RedirectsLoopBoundaryTest extends TestCase {
	/**
	 * In memory redirect table.
	 *
	 * @var RedirectsFakeDb
	 */
	private RedirectsFakeDb $db;

	/**
	 * Option store.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Temp files to remove in tearDown.
	 *
	 * @var string[]
	 */
	private array $tempFiles = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		$this->db        = new RedirectsFakeDb();
		$this->options   = [];
		$this->tempFiles = [];

		// Test installs the in memory wpdb double, restored in tearDown.
		$GLOBALS['wpdb'] = $this->db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				// Test double backing the stubbed wp_parse_url with the native parser.
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $path = '/' ): string => 'https://example.com' . $path );
		Functions\when( 'wp_allowed_protocols' )->alias( static fn (): array => [ 'http', 'https' ] );
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-01-01 00:00:00' );
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
		Functions\when( 'get_permalink' )->alias(
			static function ( mixed $post ): string {
				$arr  = is_object( $post ) ? (array) $post : [];
				$slug = (string) ( $arr['post_name'] ?? '' );

				return 'https://example.com/' . $slug . '/';
			}
		);
		Functions\when( 'wp_is_post_revision' )->justReturn( false );
		Functions\when( 'wp_is_post_autosave' )->justReturn( false );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		foreach ( $this->tempFiles as $path ) {
			if ( is_file( $path ) ) {
				// Temp CSV files created by this test, removed during tearDown.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $path );
			}
		}

		unset( $GLOBALS['wpdb'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build an active rule row.
	 *
	 * @param int    $id Rule id.
	 * @param string $source Source path.
	 * @param string $target Target path.
	 * @param string $type Matcher.
	 * @return array<string, mixed>
	 */
	private function rule( int $id, string $source, string $target, string $type = 'exact' ): array {
		return [
			'id'         => $id,
			'match_type' => $type,
			'source'     => $source,
			'target'     => $target,
			'code'       => '301',
			'is_active'  => 1,
		];
	}

	/**
	 * Test depth overrun with cycle reports inconclusive not clean.
	 */
	public function test_depth_overrun_with_cycle_reports_inconclusive_not_clean(): void {
		$rules = [];

		for ( $i = 1; $i <= 11; $i++ ) {
			$rules[] = $this->rule( $i, '/n' . (string) $i, '/n' . (string) ( $i + 1 ) );
		}

		$rules[] = $this->rule( 12, '/n12', '/n1' );

		$result = ( new Validator() )->detect_loop(
			[
				'source' => '/start',
				'target' => '/n1',
				'code'   => '301',
			],
			$rules
		);

		$this->assertFalse( $result['has_cycle'], 'A cycle past the depth cap must not read as found' );
		$this->assertTrue( $result['inconclusive'], 'A depth overrun must report inconclusive, never a clean pass' );
	}

	/**
	 * Test node overrun reports inconclusive not clean.
	 */
	public function test_node_overrun_reports_inconclusive_not_clean(): void {
		$rules = [];

		for ( $i = 1; $i <= 60; $i++ ) {
			$rules[] = $this->rule( $i, 'fan', '/done-' . (string) $i, 'contains' );
		}

		$result = ( new Validator() )->detect_loop(
			[
				'source' => '/new',
				'target' => '/fan-out',
				'code'   => '301',
			],
			$rules
		);

		$this->assertFalse( $result['has_cycle'] );
		$this->assertTrue( $result['inconclusive'], 'A node overrun must report inconclusive, never a clean pass' );
	}

	/**
	 * Test real cycle still blocked among many unrelated rules.
	 */
	public function test_real_cycle_still_blocked_among_many_unrelated_rules(): void {
		$rules = [
			$this->rule( 1, '/a', '/b' ),
			$this->rule( 2, '/b', '/c' ),
		];

		for ( $i = 3; $i <= 62; $i++ ) {
			$rules[] = $this->rule( $i, '/unrelated-' . (string) $i, '/elsewhere-' . (string) $i );
		}

		$rules[] = $this->rule( 63, '/c', '/a' );

		$result = ( new Validator() )->detect_loop(
			[
				'source' => '/c',
				'target' => '/a',
				'code'   => '301',
			],
			$rules
		);

		$this->assertTrue( $result['has_cycle'], 'A real cycle must block even inside a large unrelated set' );
		$this->assertFalse( $result['inconclusive'] );
	}

	/**
	 * Test slug watcher fails closed on inconclusive analysis.
	 */
	public function test_slug_watcher_fails_closed_on_inconclusive_analysis(): void {
		$repo = new RedirectRepository( $this->db );
		$id   = $repo->insert(
			[
				'source'     => '/new-slug',
				'target'     => '/done',
				'code'       => '301',
				'match_type' => 'regex',
				'is_active'  => true,
			]
		);

		$this->assertGreaterThan( 0, $id, 'Seed rule must insert' );

		$watcher = new SlugWatcher( $repo );
		$before  = (object) [
			'ID'        => 5,
			'post_type' => 'post',
			'post_name' => 'old-slug',
		];
		$after   = (object) [
			'ID'        => 5,
			'post_type' => 'post',
			'post_name' => 'new-slug',
		];

		$this->assertFalse(
			$watcher->handle_post_updated( 5, $after, $before ),
			'Automatic creation must fail closed when the analysis is inconclusive'
		);
		$this->assertCount( 1, $this->db->rows, 'No automatic rule may be stored on inconclusive analysis' );
	}

	/**
	 * Test csv import carries could not fully verify warning.
	 */
	public function test_csv_import_carries_could_not_fully_verify_warning(): void {
		$repo = new RedirectRepository( $this->db );
		$id   = $repo->insert(
			[
				'source'     => '^/old-[0-9]+$',
				'target'     => '/new',
				'code'       => '301',
				'match_type' => 'regex',
				'is_active'  => true,
			]
		);

		$this->assertGreaterThan( 0, $id );

		$path = tempnam( sys_get_temp_dir(), 'rkloop' );

		$this->assertIsString( $path );

		$this->tempFiles[] = $path;

		// CSV fixture written with plain file operations, part of the test setup.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $path, "source,target,code,match_type,active,hits,last_accessed\n/a,/old-123,301,exact,yes,,\n" );

		$handler = new CsvHandler( $repo );
		$result  = $handler->import_csv( $path );

		$this->assertSame( 1, $result['created'] );
		$this->assertCount( 1, $result['warnings'] );
		$this->assertStringContainsString( 'could not fully verify', $result['warnings'][0]['message'] );
	}
}
