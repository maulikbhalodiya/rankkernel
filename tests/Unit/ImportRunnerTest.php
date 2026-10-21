<?php
/**
 * ImportRunner tests over fake readers and stubbed storage.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Importer\ImportLock;
use RankKernel\Modules\Importer\ImportRunException;
use RankKernel\Modules\Importer\ImportRunner;
use RankKernel\Modules\Importer\SourceReader;
use RankKernel\Modules\Redirects\RedirectRepository;
use RankKernel\Settings\SettingsStore;

/**
 * Import runner test.
 */
final class ImportRunnerTest extends TestCase {
	/**
	 * Fake source reader with canned chunks.
	 *
	 * @return SourceReader The reader.
	 */
	private function reader(): SourceReader {
		return new class() implements SourceReader {
			/** Source id. */
			public function id(): string {
				return 'yoast';
			}

			/** Source label. */
			public function label(): string {
				return 'Yoast';
			}

			/** Detected counts. */
			public function detect(): array {
				return [
					'posts'     => 2,
					'terms'     => 1,
					'settings'  => true,
					'redirects' => 1,
				];
			}

			/**
			 * Canned post chunk.
			 *
			 * @param int    $offset Chunk offset.
			 * @param int    $limit  Chunk size.
			 * @param string $search Search filter.
			 */
			public function fetchPosts( int $offset, int $limit, string $search = '' ): array {
				if ( 0 !== $offset ) {
					return [];
				}

				return [
					[
						'id'   => 11,
						'type' => 'post',
						'data' => [ '_yoast_wpseo_title' => 'Alpha' ],
					],
					[
						'id'   => 12,
						'type' => 'post',
						'data' => [ '_yoast_wpseo_title' => 'Beta' ],
					],
				];
			}

			/**
			 * Canned term chunk.
			 *
			 * @param int    $offset Chunk offset.
			 * @param int    $limit  Chunk size.
			 * @param string $search Search filter.
			 */
			public function fetchTerms( int $offset, int $limit, string $search = '' ): array {
				if ( 0 !== $offset ) {
					return [];
				}

				return [
					[
						'id'       => 21,
						'taxonomy' => 'category',
						'data'     => [ 'wpseo_title' => 'Cats' ],
					],
				];
			}

			/** Canned sitewide blob. */
			public function fetchSitewideRaw(): array {
				return [
					'title-post'    => '%%title%% %%sep%% %%sitename%%',
					'title-page'    => '%%title%%',
					'metadesc-post' => '%%excerpt%%',
					'noindex-post'  => true,
				];
			}

			/** Canned redirect rows. */
			public function fetchRedirectsRaw(): array {
				return [
					[
						'origin' => '/old',
						'url'    => '/new',
						'type'   => '301',
						'format' => 'plain',
					],
				];
			}
		};
	}

	/**
	 * Build the runner with an in memory redirect store.
	 *
	 * @return array{ImportRunner, RedirectsFakeDb} The runner and fake db.
	 */
	private function runner(): array {
		$db     = new RedirectsFakeDb();
		$runner = new ImportRunner(
			[ 'yoast' => $this->reader() ],
			new RedirectRepository( $db ),
			new SettingsStore()
		);

		return [ $runner, $db ];
	}

	/**
	 * Set up Brain Monkey and the wpdb double.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ) {
				$parts = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double for wp_parse_url, WordPress is not loaded in unit tests.

				if ( false === $parts ) {
					return false;
				}

				if ( -1 === $component ) {
					return $parts;
				}

				return $parts[ $component ] ?? null;
			}
		);
		Functions\when( 'sanitize_text_field' )->alias(
			static function ( $value ) {
				return trim( strip_tags( (string) $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
			}
		);
		Functions\when( 'home_url' )->justReturn( 'https://example.test' );
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( '__' )->alias(
			static function ( string $text, string $domain = '' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
				return $text;
			}
		);
	}

	/**
	 * Tear down Brain Monkey.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Posts chunk applies clean rows and reports done with detect total.
	 */
	public function test_posts_chunk_applies_clean_rows(): void {
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'update_post_meta' )->justReturn( true );

		[ $runner ] = $this->runner();

		$result = $runner->run( 'yoast', 'posts', 0, 50, 'skip' );

		$this->assertSame( 2, $result['applied'] );
		$this->assertSame( 0, $result['failed'] );
		$this->assertTrue( $result['done'] );
		$this->assertSame( 2, $result['total'] );
	}

	/**
	 * Terms chunk writes through the term meta path.
	 */
	public function test_terms_chunk_applies_term_rows(): void {
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'update_term_meta' )->justReturn( true );

		[ $runner ] = $this->runner();

		$result = $runner->run( 'yoast', 'terms', 0, 50, 'skip' );

		$this->assertSame( 1, $result['applied'] );
		$this->assertTrue( $result['done'] );
		$this->assertSame( 1, $result['total'] );
	}

	/**
	 * Skip mode leaves a conflicting field alone.
	 */
	public function test_skip_mode_skips_conflicting_row(): void {
		Functions\when( 'get_post_meta' )->justReturn(
			[
				'title'       => 'Mine',
				'description' => '',
			]
		);
		Functions\when( 'update_post_meta' )->justReturn( true );

		[ $runner ] = $this->runner();

		$result = $runner->run( 'yoast', 'posts', 0, 50, 'skip' );

		$this->assertSame( 0, $result['applied'] );
		$this->assertSame( 2, $result['skipped'] );
	}

	/**
	 * Overwrite mode takes the conflicting field.
	 */
	public function test_overwrite_mode_takes_conflicting_row(): void {
		Functions\when( 'get_post_meta' )->justReturn(
			[
				'title'       => 'Mine',
				'description' => '',
			]
		);
		Functions\when( 'update_post_meta' )->justReturn( true );

		[ $runner ] = $this->runner();

		$result = $runner->run( 'yoast', 'posts', 0, 50, 'overwrite' );

		$this->assertSame( 2, $result['applied'] );
	}

	/**
	 * Excluded identities are skipped without touching storage.
	 */
	public function test_excludes_skip_rows(): void {
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'update_post_meta' )->justReturn( true );

		[ $runner ] = $this->runner();

		$result = $runner->run( 'yoast', 'posts', 0, 50, 'skip', [ 'post:11' ] );

		$this->assertSame( 1, $result['applied'] );
		$this->assertSame( 1, $result['skipped'] );
	}

	/**
	 * Term exclusions carry taxonomy and id, malformed values throw.
	 */
	public function test_term_excludes_use_taxonomy_identities(): void {
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'update_term_meta' )->justReturn( true );

		[ $runner ] = $this->runner();

		$result = $runner->run( 'yoast', 'terms', 0, 50, 'skip', [ 'term:category:21' ] );

		$this->assertSame( 0, $result['applied'] );
		$this->assertSame( 1, $result['skipped'] );

		$this->expectException( \InvalidArgumentException::class );

		$runner->run( 'yoast', 'terms', 0, 50, 'skip', [ 'bogus' ] );
	}

	/**
	 * Sitewide run applies the chosen candidate per slot.
	 */
	public function test_sitewide_applies_chosen_candidate(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$saved = [];

		Functions\when( 'update_option' )->alias(
			static function ( string $option, $value ) use ( &$saved ) {
				$saved[ $option ] = $value;

				return true;
			}
		);

		[ $runner ] = $this->runner();

		$result = $runner->run( 'yoast', 'sitewide', 0, 50, 'skip', [], [ 'choices' => [ 'title_template' => 'sitewide:title_template:page' ] ] );

		$this->assertSame( 1, $result['applied'] );
		$this->assertTrue( $result['done'] );
		$this->assertSame( '%%title%%', $saved['rankkernel_settings']['title_template'] ?? null );
	}

	/**
	 * Sitewide run rejects an unknown choice instead of guessing.
	 */
	public function test_sitewide_invalid_choice_throws(): void {
		[ $runner ] = $this->runner();

		$this->expectException( \InvalidArgumentException::class );

		$runner->run( 'yoast', 'sitewide', 0, 50, 'skip', [], [ 'choices' => [ 'title_template' => 'sitewide:title_template:nope' ] ] );
	}

	/**
	 * Redirect rows insert through the repository.
	 */
	public function test_redirects_insert_through_repository(): void {
		[ $runner, $db ] = $this->runner();

		$GLOBALS['wpdb'] = $db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test installs the in memory wpdb double.

		Functions\when( 'update_option' )->justReturn( true );

		$result = $runner->run( 'yoast', 'redirects', 0, 50, 'skip' );

		$this->assertSame( 1, $result['applied'] );
		$this->assertTrue( $result['done'] );
	}

	/**
	 * Redirect exclusions match the redirect identity, unknown ones throw.
	 */
	public function test_redirects_exclude_by_identity(): void {
		[ $runner, $db ] = $this->runner();

		$GLOBALS['wpdb'] = $db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test installs the in memory wpdb double.

		Functions\when( 'update_option' )->justReturn( true );

		$result = $runner->run( 'yoast', 'redirects', 0, 50, 'skip', [ 'redirect:exact:/old' ] );

		$this->assertSame( 0, $result['applied'] );
		$this->assertSame( 1, $result['skipped'] );

		$this->expectException( \InvalidArgumentException::class );

		$runner->run( 'yoast', 'redirects', 0, 50, 'skip', [ 'redirect:exact:/missing' ] );
	}

	/**
	 * Repeating a redirect chunk attempts no second write.
	 *
	 * The attempt counter proves the runner consults find() before
	 * writing: a broken lookup would attempt the insert again even
	 * though the unique constraint would still refuse the duplicate.
	 */
	public function test_redirects_retry_is_idempotent(): void {
		[ $runner, $db ] = $this->runner();

		$GLOBALS['wpdb'] = $db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test installs the in memory wpdb double.

		Functions\when( 'update_option' )->justReturn( true );

		$first = $runner->run( 'yoast', 'redirects', 0, 50, 'skip' );

		$this->assertSame( 1, $first['applied'] );
		$this->assertSame( 1, $db->insertAttempts );

		$second = $runner->run( 'yoast', 'redirects', 0, 50, 'skip' );

		$this->assertSame( 0, $second['applied'] );
		$this->assertSame( 1, $second['skipped'] );
		$this->assertSame( 1, $db->insertAttempts );
	}

	/**
	 * Build a runner with a canned bulk id list.
	 *
	 * @return ImportRunner The runner.
	 */
	private function bulkRunner(): ImportRunner {
		return new ImportRunner(
			[ 'yoast' => $this->reader() ],
			new RedirectRepository( new RedirectsFakeDb() ),
			new SettingsStore(),
			static function ( string $kind, string $type ): array {
				return 'post' === $kind && 'post' === $type ? [ 41, 42 ] : [];
			}
		);
	}

	/**
	 * A selected bulk rule stamps its targets and reports totals.
	 */
	public function test_bulk_applies_selected_rule(): void {
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'update_post_meta' )->justReturn( true );

		$runner = $this->bulkRunner();

		$result = $runner->run( 'yoast', 'bulk', 0, 50, 'skip', [], [ 'rule' => 'bulk:post:post' ] );

		$this->assertSame( 2, $result['applied'] );
		$this->assertSame( 2, $result['total'] );
		$this->assertTrue( $result['done'] );
	}

	/**
	 * Bulk run rejects an unknown or missing rule instead of guessing.
	 */
	public function test_bulk_rejects_unknown_rule(): void {
		$runner = $this->bulkRunner();

		try {
			$runner->run( 'yoast', 'bulk', 0, 50, 'skip', [], [ 'rule' => 'bulk:post:nope' ] );

			$this->fail( 'Expected an InvalidArgumentException for an unknown bulk rule.' );
		} catch ( \InvalidArgumentException $e ) {
			$this->assertNotSame( '', $e->getMessage() );
		}

		$this->expectException( \InvalidArgumentException::class );

		$runner->run( 'yoast', 'bulk', 0, 50, 'skip' );
	}

	/**
	 * Empty chunk reports done with zero counts.
	 */
	public function test_empty_chunk_reports_done(): void {
		[ $runner ] = $this->runner();

		$result = $runner->run( 'yoast', 'posts', 50, 50, 'skip' );

		$this->assertSame( 0, $result['applied'] );
		$this->assertTrue( $result['done'] );
	}

	/**
	 * Unknown source, kind, or mode throws instead of running.
	 */
	public function test_invalid_arguments_throw(): void {
		[ $runner ] = $this->runner();

		$this->expectException( \InvalidArgumentException::class );

		$runner->run( 'yoast', 'nope', 0, 50, 'skip' );
	}

	/**
	 * A run that owns the lock writes its rows.
	 */
	public function test_run_writes_when_the_lock_is_owned(): void {
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'update_post_meta' )->justReturn( true );

		$lock = new ImportLock( new ImportLockFakeDb() );
		$lock->claim( 'run-aaaa-1111' );

		$runner = new ImportRunner(
			[ 'yoast' => $this->reader() ],
			new RedirectRepository( new RedirectsFakeDb() ),
			new SettingsStore(),
			null,
			$lock
		);

		$result = $runner->run( 'yoast', 'posts', 0, 50, 'skip', [], [], 'run-aaaa-1111' );

		$this->assertSame( 2, $result['applied'] );
	}

	/**
	 * A run that lost the lock stops before it writes anything.
	 */
	public function test_run_stops_when_the_lock_is_not_owned(): void {
		$writes = 0;

		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'update_post_meta' )->alias(
			static function () use ( &$writes ): bool {
				++$writes;
				return true;
			}
		);

		$lock = new ImportLock( new ImportLockFakeDb() );
		$lock->claim( 'run-other-9999' );

		$runner = new ImportRunner(
			[ 'yoast' => $this->reader() ],
			new RedirectRepository( new RedirectsFakeDb() ),
			new SettingsStore(),
			null,
			$lock
		);

		try {
			$runner->run( 'yoast', 'posts', 0, 50, 'skip', [ 'post:12' ], [], 'run-aaaa-1111' );
			$this->fail( 'the run must stop when the lock belongs to another run' );
		} catch ( ImportRunException $exception ) {
			$this->assertSame( 0, $exception->counts()['applied'] );
			$this->assertSame( 0, $exception->counts()['failed'] );
			// The excluded row was skipped before any write, so the
			// report has to say so.
			$this->assertSame( 1, $exception->counts()['skipped'] );
		}

		$this->assertSame( 0, $writes, 'a run that lost the lock must not write' );
	}

	/**
	 * A runner that has a lock but no run id fails closed.
	 */
	public function test_run_without_a_run_id_does_not_write(): void {
		$writes = 0;

		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'update_post_meta' )->alias(
			static function () use ( &$writes ): bool {
				++$writes;
				return true;
			}
		);

		$runner = new ImportRunner(
			[ 'yoast' => $this->reader() ],
			new RedirectRepository( new RedirectsFakeDb() ),
			new SettingsStore(),
			null,
			new ImportLock( new ImportLockFakeDb() )
		);

		try {
			$runner->run( 'yoast', 'posts', 0, 50, 'skip' );
			$this->fail( 'a run without a run id must not write' );
		} catch ( ImportRunException $exception ) {
			$this->assertSame( 0, $exception->counts()['applied'] );
		}

		$this->assertSame( 0, $writes, 'a run without a run id must not write' );
	}

	/**
	 * A run without a lock is not fenced, so the unit paths still work.
	 */
	public function test_run_without_a_lock_is_not_fenced(): void {
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'update_post_meta' )->justReturn( true );

		[ $runner ] = $this->runner();

		$result = $runner->run( 'yoast', 'posts', 0, 50, 'skip', [], [], 'run-aaaa-1111' );

		$this->assertSame( 2, $result['applied'] );
	}
}
