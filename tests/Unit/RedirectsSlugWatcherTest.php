<?php
/**
 * Slug watcher tests over the in memory wpdb double.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\RedirectRepository;
use RankKernel\Modules\Redirects\RedirectsSettings;
use RankKernel\Modules\Redirects\SlugWatcher;

/**
 * Covers automatic 301 creation on real slug changes.
 *
 * Uses post object doubles plus Brain Monkey for the permalink and revision
 * helpers. Every test asserts the stored rule or the deliberate absence of
 * one, so duplicates and loops can never slip through silently.
 */
final class RedirectsSlugWatcherTest extends TestCase {
	/**
	 * In memory redirect table.
	 *
	 * @var RedirectsFakeDb
	 */
	private RedirectsFakeDb $db;

	/**
	 * Option storage backing get option and update option.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Stored redirects settings for the settings store.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $settingsOption = null;

	/**
	 * Whether the revision helper reports a revision.
	 *
	 * @var bool
	 */
	private bool $isRevision = false;

	/**
	 * Whether the autosave helper reports an autosave.
	 *
	 * @var bool
	 */
	private bool $isAutosave = false;

	/**
	 * Registered hooks captured from add action.
	 *
	 * @var array<int, array{hook: string, priority: int, args: int}>
	 */
	private array $hooks = [];

	/**
	 * Set up doubles.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		$this->db             = new RedirectsFakeDb();
		$this->options        = [];
		$this->settingsOption = null;
		$this->isRevision     = false;
		$this->isAutosave     = false;
		$this->hooks          = [];

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
				if ( RedirectsSettings::OPTION === $key && null !== $this->settingsOption ) {
					return $this->settingsOption;
				}

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
		Functions\when( 'wp_is_post_revision' )->alias( fn (): bool => $this->isRevision );
		Functions\when( 'wp_is_post_autosave' )->alias( fn (): bool => $this->isAutosave );
		Functions\when( 'add_action' )->alias(
			function ( string $hook, mixed $callback, int $priority = 10, int $args = 1 ): bool {
				$this->hooks[] = [
					'hook'     => $hook,
					'priority' => $priority,
					'args'     => $args,
				];

				return true;
			}
		);
	}

	/**
	 * Reset globals.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build the watcher over the fake database.
	 *
	 * @return SlugWatcher The result.
	 */
	private function makeWatcher(): SlugWatcher {
		return new SlugWatcher( new RedirectRepository( $this->db ), new RedirectsSettings() );
	}

	/**
	 * Build a post object double.
	 *
	 * @param string $slug Slug.
	 * @param string $type Type.
	 * @return object Post shaped object.
	 */
	private function makePost( string $slug, string $type = 'post' ): object {
		return (object) [
			'ID'        => 5,
			'post_type' => $type,
			'post_name' => $slug,
		];
	}

	/**
	 * Seed one rule in the fake table.
	 *
	 * @param string $source Source.
	 * @param string $target Target.
	 * @param string $code   Code.
	 * @return int New row id.
	 */
	private function seedRule( string $source, string $target, string $code = '301' ): int {
		$repo = new RedirectRepository( $this->db );

		return $repo->insert(
			[
				'source'     => $source,
				'target'     => $target,
				'code'       => $code,
				'match_type' => 'exact',
				'is_active'  => true,
			]
		);
	}

	/**
	 * Test creates one 301 on real change.
	 */
	public function test_creates_one_301_on_real_change(): void {
		$created = $this->makeWatcher()->handle_post_updated(
			5,
			$this->makePost( 'new-slug' ),
			$this->makePost( 'old-slug' )
		);

		$this->assertTrue( $created );
		$this->assertCount( 1, $this->db->rows );

		$row = $this->db->rows[1];

		$this->assertSame( '/old-slug', $row['source'] );
		$this->assertSame( '/new-slug', $row['target'] );
		$this->assertSame( '301', $row['code'] );
		$this->assertSame( 'exact', $row['match_type'] );
		$this->assertSame( 1, (int) $row['is_active'] );
	}

	/**
	 * Test creates redirect for pages.
	 */
	public function test_creates_redirect_for_pages(): void {
		$created = $this->makeWatcher()->handle_post_updated(
			7,
			$this->makePost( 'new-page', 'page' ),
			$this->makePost( 'old-page', 'page' )
		);

		$this->assertTrue( $created );
		$this->assertCount( 1, $this->db->rows );
		$this->assertSame( '/old-page', $this->db->rows[1]['source'] );
	}

	/**
	 * Test no action when slug unchanged.
	 */
	public function test_no_action_when_slug_unchanged(): void {
		$created = $this->makeWatcher()->handle_post_updated(
			5,
			$this->makePost( 'same-slug' ),
			$this->makePost( 'same-slug' )
		);

		$this->assertFalse( $created );
		$this->assertSame( [], $this->db->rows );
	}

	/**
	 * Test skipped when disabled.
	 */
	public function test_skipped_when_disabled(): void {
		$this->settingsOption = [ 'auto_slug_redirect' => false ];

		$created = $this->makeWatcher()->handle_post_updated(
			5,
			$this->makePost( 'new-slug' ),
			$this->makePost( 'old-slug' )
		);

		$this->assertFalse( $created );
		$this->assertSame( [], $this->db->rows );
	}

	/**
	 * Test revisions ignored.
	 */
	public function test_revisions_ignored(): void {
		$this->isRevision = true;

		$created = $this->makeWatcher()->handle_post_updated(
			5,
			$this->makePost( 'new-slug' ),
			$this->makePost( 'old-slug' )
		);

		$this->assertFalse( $created );
		$this->assertSame( [], $this->db->rows );
	}

	/**
	 * Test autosaves ignored.
	 */
	public function test_autosaves_ignored(): void {
		$this->isAutosave = true;

		$created = $this->makeWatcher()->handle_post_updated(
			5,
			$this->makePost( 'new-slug' ),
			$this->makePost( 'old-slug' )
		);

		$this->assertFalse( $created );
		$this->assertSame( [], $this->db->rows );
	}

	/**
	 * Test duplicate source skipped.
	 */
	public function test_duplicate_source_skipped(): void {
		$this->seedRule( '/old-slug', '/something-else' );

		$created = $this->makeWatcher()->handle_post_updated(
			5,
			$this->makePost( 'new-slug' ),
			$this->makePost( 'old-slug' )
		);

		$this->assertFalse( $created );
		$this->assertCount( 1, $this->db->rows );
		$this->assertSame( '/something-else', $this->db->rows[1]['target'] );
	}

	/**
	 * Test loop skipped.
	 */
	public function test_loop_skipped(): void {
		$this->seedRule( '/new-slug', '/old-slug' );

		$created = $this->makeWatcher()->handle_post_updated(
			5,
			$this->makePost( 'new-slug' ),
			$this->makePost( 'old-slug' )
		);

		$this->assertFalse( $created );
		$this->assertCount( 1, $this->db->rows );
	}

	/**
	 * Test chain shortened to final destination.
	 */
	public function test_chain_shortened_to_final_destination(): void {
		$this->seedRule( '/new-slug', '/final' );

		$created = $this->makeWatcher()->handle_post_updated(
			5,
			$this->makePost( 'new-slug' ),
			$this->makePost( 'old-slug' )
		);

		$this->assertTrue( $created );
		$this->assertCount( 2, $this->db->rows );
		$this->assertSame( '/final', $this->db->rows[2]['target'] );
	}

	/**
	 * Test non post page types ignored.
	 */
	public function test_non_post_page_types_ignored(): void {
		foreach ( [ 'attachment', 'product', 'revision' ] as $type ) {
			$created = $this->makeWatcher()->handle_post_updated(
				5,
				$this->makePost( 'new-slug', $type ),
				$this->makePost( 'old-slug', $type )
			);

			$this->assertFalse( $created );
		}

		$this->assertSame( [], $this->db->rows );
	}

	/**
	 * Test register hooks post updated with full signature.
	 */
	public function test_register_hooks_post_updated_with_full_signature(): void {
		$this->makeWatcher()->register();

		$found = array_values(
			array_filter(
				$this->hooks,
				static fn ( array $hook ): bool => 'post_updated' === $hook['hook']
			)
		);

		$this->assertCount( 1, $found );
		$this->assertSame( 10, $found[0]['priority'] );
		$this->assertSame( 3, $found[0]['args'] );
	}
}
