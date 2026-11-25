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
use RankKernel\Modules\Redirects\RedirectCache;
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
	 * Per-post metadata store.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $postMeta = [];

	/**
	 * Whether post metadata writes fail.
	 *
	 * @var bool
	 */
	private bool $failMetaWrites = false;

	/**
	 * Number of metadata writes.
	 *
	 * @var int
	 */
	private int $metaWrites = 0;

	/**
	 * Number of metadata deletes.
	 *
	 * @var int
	 */
	private int $metaDeletes = 0;

	/**
	 * Set up doubles.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
		RedirectsSettings::resetCache();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		$this->db             = new RedirectsFakeDb();
		$this->options        = [];
		$this->settingsOption = null;
		$this->isRevision     = false;
		$this->isAutosave     = false;
		$this->hooks          = [];
		$this->postMeta       = [];
		$this->failMetaWrites = false;
		$this->metaWrites     = 0;
		$this->metaDeletes    = 0;

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
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_post_meta' )->alias(
			function ( int $postId, string $key, bool $single = false ): mixed {
				unset( $single );

				return $this->postMeta[ $postId ][ $key ] ?? '';
			}
		);
		Functions\when( 'update_post_meta' )->alias(
			function ( int $postId, string $key, mixed $value ): bool {
				if ( $this->failMetaWrites ) {
					return false;
				}

				$this->postMeta[ $postId ][ $key ] = $value;
				++$this->metaWrites;

				return true;
			}
		);
		Functions\when( 'delete_post_meta' )->alias(
			function ( int $postId, string $key ): bool {
				unset( $this->postMeta[ $postId ][ $key ] );
				++$this->metaDeletes;

				return true;
			}
		);
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
	 * @param string $slug   Slug.
	 * @param string $type   Type.
	 * @param string $status Status, publish like a real rename.
	 * @param int    $id     Post id.
	 * @return object Post shaped object.
	 */
	private function makePost( string $slug, string $type = 'post', string $status = 'publish', int $id = 5 ): object {
		return (object) [
			'ID'          => $id,
			'post_type'   => $type,
			'post_name'   => $slug,
			'post_status' => $status,
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
	 * Create one automatic rule through the real watcher.
	 *
	 * @param string $newSlug New slug.
	 * @param string $oldSlug Old slug.
	 * @return int Redirect id.
	 */
	private function createAuto( string $newSlug = 'new-slug', string $oldSlug = 'old-slug' ): int {
		$created = $this->makeWatcher()->handle_post_updated(
			5,
			$this->makePost( $newSlug ),
			$this->makePost( $oldSlug )
		);

		$this->assertTrue( $created );

		return array_key_last( $this->db->rows );
	}

	/**
	 * Read the ownership registry for a post.
	 *
	 * @param int $postId Post id.
	 * @return array<string, mixed>
	 */
	private function registry( int $postId = 5 ): array {
		$value = $this->postMeta[ $postId ][ SlugWatcher::AUTO_REDIRECT_META ] ?? [];

		return is_array( $value ) ? $value : [];
	}

	/**
	 * Run a status transition through the watcher lifecycle callback.
	 *
	 * @param string $newStatus New status.
	 * @param string $oldStatus Old status.
	 * @param object $post      Post object.
	 */
	private function transition( string $newStatus, string $oldStatus, object $post ): void {
		$this->makeWatcher()->on_transition_post_status( $newStatus, $oldStatus, $post );
	}

	/**
	 * Run permanent deletion cleanup through the watcher callback.
	 *
	 * @param int $postId Post id.
	 */
	private function deletePost( int $postId = 5 ): void {
		$this->makeWatcher()->on_before_delete_post( $postId );
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
	 * Test a writer without the capability creates nothing.
	 */
	public function test_refuses_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$created = $this->makeWatcher()->handle_post_updated(
			5,
			$this->makePost( 'new-slug' ),
			$this->makePost( 'old-slug' )
		);

		$this->assertFalse( $created );
		$this->assertCount( 0, $this->db->rows );
	}

	/**
	 * Test renaming a draft creates nothing, its URL was never public.
	 */
	public function test_refuses_draft_rename(): void {
		$created = $this->makeWatcher()->handle_post_updated(
			5,
			$this->makePost( 'new-slug', 'post', 'draft' ),
			$this->makePost( 'old-slug', 'post', 'draft' )
		);

		$this->assertFalse( $created );
		$this->assertCount( 0, $this->db->rows );
	}

	/**
	 * Test un-publishing creates nothing.
	 */
	public function test_refuses_unpublish_transition(): void {
		$created = $this->makeWatcher()->handle_post_updated(
			5,
			$this->makePost( 'new-slug', 'post', 'draft' ),
			$this->makePost( 'old-slug', 'post', 'publish' )
		);

		$this->assertFalse( $created );
		$this->assertCount( 0, $this->db->rows );
	}

	/**
	 * Test creation stops when the exact rule budget is full.
	 */
	public function test_refuses_at_exact_cap(): void {
		for ( $i = 0; $i < RedirectRepository::MAX_PATTERNS; $i++ ) {
			$this->seedRule( '/old-' . $i, '/new-' . $i );
		}

		$created = $this->makeWatcher()->handle_post_updated(
			5,
			$this->makePost( 'new-slug' ),
			$this->makePost( 'old-slug' )
		);

		$this->assertFalse( $created );
		$this->assertCount( RedirectRepository::MAX_PATTERNS, $this->db->rows );
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
	 * Test successful creation records actual persisted ownership.
	 */
	public function test_creation_records_actual_persisted_ownership(): void {
		$id   = $this->createAuto();
		$row  = $this->db->rows[ $id ];
		$meta = $this->registry();

		$this->assertArrayHasKey( $id, $meta );
		$this->assertSame( $row['source'], $meta[ $id ]['source'] );
		$this->assertSame( $row['source_hash'], $meta[ $id ]['source_hash'] );
		$this->assertSame( $row['target'], $meta[ $id ]['target'] );
		$this->assertSame( $row['code'], $meta[ $id ]['code'] );
		$this->assertSame( $row['match_type'], $meta[ $id ]['match_type'] );
		$this->assertSame( (int) $row['is_active'], $meta[ $id ]['is_active'] );
		$this->assertSame( $row['created'], $meta[ $id ]['created'] );
		$this->assertSame( 5, $meta[ $id ]['post_id'] );
	}

	/**
	 * Test the chain-shortened target is the recorded target.
	 */
	public function test_chain_shortened_target_is_recorded(): void {
		$this->seedRule( '/new-slug', '/final' );
		$id = $this->createAuto();

		$this->assertSame( '/final', $this->db->rows[ $id ]['target'] );
		$this->assertSame( '/final', $this->registry()[ $id ]['target'] );
	}

	/**
	 * Test a manual source collision creates no ownership.
	 */
	public function test_manual_collision_creates_no_ownership(): void {
		$this->seedRule( '/old-slug', '/manual' );

		$this->assertFalse(
			$this->makeWatcher()->handle_post_updated(
				5,
				$this->makePost( 'new-slug' ),
				$this->makePost( 'old-slug' )
			)
		);
		$this->assertSame( [], $this->registry() );
	}

	/**
	 * Test failed insertion creates no ownership.
	 */
	public function test_failed_insert_creates_no_ownership(): void {
		$this->db->failInserts = true;

		$this->assertFalse(
			$this->makeWatcher()->handle_post_updated(
				5,
				$this->makePost( 'new-slug' ),
				$this->makePost( 'old-slug' )
			)
		);
		$this->assertSame( [], $this->db->rows );
		$this->assertSame( [], $this->registry() );
	}

	/**
	 * Test metadata failure rolls back only the just-created row.
	 */
	public function test_metadata_failure_rolls_back_created_rule(): void {
		$this->failMetaWrites = true;

		$this->assertFalse(
			$this->makeWatcher()->handle_post_updated(
				5,
				$this->makePost( 'new-slug' ),
				$this->makePost( 'old-slug' )
			)
		);
		$this->assertSame( [], $this->db->rows );
		$this->assertSame( [], $this->registry() );
	}

	/**
	 * Test rollback failure is not reported as creation success.
	 */
	public function test_metadata_failure_with_failed_rollback_returns_false(): void {
		$this->failMetaWrites  = true;
		$this->db->failDeletes = true;

		$this->assertFalse(
			$this->makeWatcher()->handle_post_updated(
				5,
				$this->makePost( 'new-slug' ),
				$this->makePost( 'old-slug' )
			)
		);
		$this->assertCount( 1, $this->db->rows );
		$this->assertSame( [], $this->registry() );
	}

	/**
	 * Test a row edited before ownership recording is not deleted by rollback.
	 */
	public function test_metadata_failure_after_row_edit_does_not_delete_edited_rule(): void {
		Functions\when( 'update_post_meta' )->alias(
			function ( int $postId, string $key, mixed $value ): bool {
				unset( $postId, $key );

				if ( is_array( $value ) ) {
					$id                              = (int) array_key_first( $value );
					$this->db->rows[ $id ]['target'] = '/edited-during-write';
				}

				return false;
			}
		);

		$this->assertFalse(
			$this->makeWatcher()->handle_post_updated(
				5,
				$this->makePost( 'new-slug' ),
				$this->makePost( 'old-slug' )
			)
		);
		$this->assertCount( 1, $this->db->rows );
		$row = array_values( $this->db->rows )[0];
		$this->assertSame( '/edited-during-write', $row['target'] );
		$this->assertSame( [], $this->registry() );
	}

	/**
	 * Test every publish-to-nonpublish status removes the owned rule.
	 *
	 * @dataProvider nonpublish_status_provider
	 * @param string $status New status.
	 */
	public function test_publish_to_nonpublish_removes_owned_rule( string $status ): void {
		$id = $this->createAuto();

		$this->transition( $status, 'publish', $this->makePost( 'new-slug', 'post', $status ) );

		$this->assertArrayNotHasKey( $id, $this->db->rows );
		$this->assertSame( [], $this->registry() );
	}

	/**
	 * Non-publish statuses that must clean owned redirects.
	 *
	 * @return array<string, array{string}>
	 */
	public static function nonpublish_status_provider(): array {
		return [
			'draft'   => [ 'draft' ],
			'pending' => [ 'pending' ],
			'private' => [ 'private' ],
			'trash'   => [ 'trash' ],
		];
	}

	/**
	 * Test repeated cleanup is idempotent.
	 */
	public function test_repeated_cleanup_is_idempotent(): void {
		$id = $this->createAuto();
		$this->transition( 'draft', 'publish', $this->makePost( 'new-slug', 'post', 'draft' ) );
		$writes  = $this->db->writes;
		$deletes = $this->metaDeletes;

		$this->transition( 'draft', 'publish', $this->makePost( 'new-slug', 'post', 'draft' ) );

		$this->assertArrayNotHasKey( $id, $this->db->rows );
		$this->assertSame( $writes, $this->db->writes );
		$this->assertSame( $deletes, $this->metaDeletes );
	}

	/**
	 * Test unrelated transitions do not delete rules.
	 */
	public function test_unrelated_transitions_do_not_delete_rules(): void {
		$id = $this->createAuto();

		$this->transition( 'pending', 'draft', $this->makePost( 'new-slug', 'post', 'pending' ) );
		$this->transition( 'publish', 'publish', $this->makePost( 'new-slug' ) );

		$this->assertArrayHasKey( $id, $this->db->rows );
		$this->assertArrayHasKey( $id, $this->registry() );
	}

	/**
	 * Test restoring content does not resurrect a deleted automatic rule.
	 */
	public function test_restore_does_not_resurrect_deleted_rule(): void {
		$id = $this->createAuto();
		$this->transition( 'trash', 'publish', $this->makePost( 'new-slug', 'post', 'trash' ) );

		$this->transition( 'publish', 'trash', $this->makePost( 'new-slug' ) );

		$this->assertArrayNotHasKey( $id, $this->db->rows );
		$this->assertSame( [], $this->registry() );
	}

	/**
	 * Test direct permanent deletion removes owned rules.
	 */
	public function test_direct_permanent_deletion_removes_owned_rule(): void {
		$id = $this->createAuto();

		$this->deletePost();

		$this->assertArrayNotHasKey( $id, $this->db->rows );
		$this->assertSame( [], $this->registry() );
	}

	/**
	 * Test deleting an already-cleaned trashed post is harmless.
	 */
	public function test_delete_after_trash_cleanup_is_harmless(): void {
		$id = $this->createAuto();
		$this->transition( 'trash', 'publish', $this->makePost( 'new-slug', 'post', 'trash' ) );
		$writes = $this->db->writes;

		$this->deletePost();

		$this->assertArrayNotHasKey( $id, $this->db->rows );
		$this->assertSame( $writes, $this->db->writes );
	}

	/**
	 * Test manual and legacy unknown rules survive cleanup.
	 */
	public function test_manual_and_legacy_rules_survive_cleanup(): void {
		$manual = $this->seedRule( '/manual', '/target' );
		$legacy = $this->seedRule( '/legacy', '/target' );

		$this->transition( 'draft', 'publish', $this->makePost( 'new-slug', 'post', 'draft' ) );

		$this->assertArrayHasKey( $manual, $this->db->rows );
		$this->assertArrayHasKey( $legacy, $this->db->rows );
	}

	/**
	 * Test an edited automatic rule survives and loses ownership.
	 */
	public function test_edited_automatic_rule_survives_and_loses_ownership(): void {
		$id   = $this->createAuto();
		$repo = new RedirectRepository( $this->db );
		$this->assertTrue( $repo->update( $id, [ 'target' => '/user-edited' ] ) );

		$this->transition( 'draft', 'publish', $this->makePost( 'new-slug', 'post', 'draft' ) );

		$this->assertArrayHasKey( $id, $this->db->rows );
		$this->assertSame( '/user-edited', $this->db->rows[ $id ]['target'] );
		$this->assertSame( [], $this->registry() );
	}

	/**
	 * Test malformed ownership metadata causes no deletion.
	 */
	public function test_malformed_metadata_causes_no_deletion(): void {
		$id = $this->createAuto();
		$this->postMeta[5][ SlugWatcher::AUTO_REDIRECT_META ] = [ $id => [ 'target' => '/new-slug' ] ];

		$this->transition( 'draft', 'publish', $this->makePost( 'new-slug', 'post', 'draft' ) );

		$this->assertArrayHasKey( $id, $this->db->rows );
	}

	/**
	 * Test a fractional owning post id cannot authorize deletion.
	 */
	public function test_fractional_post_id_metadata_causes_no_deletion(): void {
		$id               = $this->createAuto();
		$entry            = $this->registry()[ $id ];
		$entry['post_id'] = 5.5;
		$this->postMeta[5][ SlugWatcher::AUTO_REDIRECT_META ] = [ $id => $entry ];

		$this->transition( 'draft', 'publish', $this->makePost( 'new-slug', 'post', 'draft' ) );

		$this->assertArrayHasKey( $id, $this->db->rows );
	}

	/**
	 * Test fractional active-state metadata cannot authorize deletion.
	 */
	public function test_fractional_active_state_metadata_causes_no_deletion(): void {
		$id                 = $this->createAuto();
		$entry              = $this->registry()[ $id ];
		$entry['is_active'] = 1.5;
		$this->postMeta[5][ SlugWatcher::AUTO_REDIRECT_META ] = [ $id => $entry ];

		$this->transition( 'draft', 'publish', $this->makePost( 'new-slug', 'post', 'draft' ) );

		$this->assertArrayHasKey( $id, $this->db->rows );
	}

	/**
	 * Test another post's registry cannot authorize deletion.
	 */
	public function test_other_post_registry_cannot_authorize_deletion(): void {
		$id = $this->createAuto();
		$this->postMeta[6][ SlugWatcher::AUTO_REDIRECT_META ] = $this->registry();

		$this->transition( 'draft', 'publish', $this->makePost( 'new-slug', 'post', 'draft', 6 ) );

		$this->assertArrayHasKey( $id, $this->db->rows );
		$this->assertArrayHasKey( $id, $this->registry() );
	}

	/**
	 * Test a reused id with a different fingerprint is protected.
	 */
	public function test_reused_id_with_different_fingerprint_is_protected(): void {
		$id   = $this->createAuto();
		$repo = new RedirectRepository( $this->db );
		$this->assertTrue( $repo->delete( $id ) );
		$this->db->nextId = $id;
		$replacement      = $repo->insert(
			[
				'source'     => '/old-slug',
				'target'     => '/replacement',
				'code'       => '301',
				'match_type' => 'exact',
				'is_active'  => true,
			]
		);

		$this->transition( 'draft', 'publish', $this->makePost( 'new-slug', 'post', 'draft' ) );

		$this->assertSame( $id, $replacement );
		$this->assertArrayHasKey( $id, $this->db->rows );
		$this->assertSame( '/replacement', $this->db->rows[ $id ]['target'] );
		$this->assertSame( [], $this->registry() );
	}

	/**
	 * Test regex and wildcard fingerprints are rejected.
	 *
	 * @dataProvider non_exact_match_provider
	 * @param string $matchType Match type.
	 */
	public function test_non_exact_ownership_is_rejected( string $matchType ): void {
		$id                  = $this->createAuto();
		$entry               = $this->registry()[ $id ];
		$entry['match_type'] = $matchType;
		$this->postMeta[5][ SlugWatcher::AUTO_REDIRECT_META ] = [ $id => $entry ];

		$this->transition( 'draft', 'publish', $this->makePost( 'new-slug', 'post', 'draft' ) );

		$this->assertArrayHasKey( $id, $this->db->rows );
	}

	/**
	 * Match types that can never be SlugWatcher-owned.
	 *
	 * @return array<string, array{string}>
	 */
	public static function non_exact_match_provider(): array {
		return [
			'regex'    => [ 'regex' ],
			'wildcard' => [ 'wildcard' ],
		];
	}

	/**
	 * Test multiple automatic redirects are all removed.
	 */
	public function test_multiple_owned_redirects_are_removed(): void {
		$first  = $this->createAuto();
		$second = $this->makeWatcher()->handle_post_updated(
			5,
			$this->makePost( 'newer-slug' ),
			$this->makePost( 'new-slug' )
		);
		$this->assertTrue( $second );
		$secondId = array_key_last( $this->db->rows );

		$this->transition( 'draft', 'publish', $this->makePost( 'newer-slug', 'post', 'draft' ) );

		$this->assertArrayNotHasKey( $first, $this->db->rows );
		$this->assertArrayNotHasKey( $secondId, $this->db->rows );
		$this->assertSame( [], $this->registry() );
	}

	/**
	 * Test cleanup deletes unchanged owned rules but preserves edited ones.
	 */
	public function test_cleanup_preserves_edited_rule_and_deletes_unchanged_rule(): void {
		$first = $this->createAuto();
		$this->assertTrue(
			$this->makeWatcher()->handle_post_updated(
				5,
				$this->makePost( 'newer-slug' ),
				$this->makePost( 'new-slug' )
			)
		);
		$secondId = array_key_last( $this->db->rows );
		$this->assertTrue( ( new RedirectRepository( $this->db ) )->update( $secondId, [ 'code' => '302' ] ) );

		$this->transition( 'draft', 'publish', $this->makePost( 'newer-slug', 'post', 'draft' ) );

		$this->assertArrayNotHasKey( $first, $this->db->rows );
		$this->assertArrayHasKey( $secondId, $this->db->rows );
		$this->assertSame( '302', $this->db->rows[ $secondId ]['code'] );
		$this->assertSame( [], $this->registry() );
	}

	/**
	 * Test failed deletion retains retryable provenance.
	 */
	public function test_failed_deletion_retains_provenance(): void {
		$id                    = $this->createAuto();
		$this->db->failDeletes = true;

		$this->transition( 'draft', 'publish', $this->makePost( 'new-slug', 'post', 'draft' ) );

		$this->assertArrayHasKey( $id, $this->db->rows );
		$this->assertArrayHasKey( $id, $this->registry() );
	}

	/**
	 * Test successful cleanup invalidates the redirect validator.
	 */
	public function test_successful_cleanup_invalidates_cache(): void {
		$this->createAuto();
		$before = $this->options[ RedirectCache::VALIDATOR_OPTION ] ?? '';

		$this->transition( 'draft', 'publish', $this->makePost( 'new-slug', 'post', 'draft' ) );

		$this->assertNotSame( $before, $this->options[ RedirectCache::VALIDATOR_OPTION ] ?? '' );
	}

	/**
	 * Test ownership cleanup is not manage_options gated.
	 */
	public function test_cleanup_does_not_require_manage_options(): void {
		$id = $this->createAuto();
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->transition( 'draft', 'publish', $this->makePost( 'new-slug', 'post', 'draft' ) );

		$this->assertArrayNotHasKey( $id, $this->db->rows );
	}

	/**
	 * Test disabling future creation does not skip tracked cleanup.
	 */
	public function test_disabled_auto_setting_does_not_skip_tracked_cleanup(): void {
		$id                   = $this->createAuto();
		$this->settingsOption = [ 'auto_slug_redirect' => false ];

		$this->transition( 'draft', 'publish', $this->makePost( 'new-slug', 'post', 'draft' ) );

		$this->assertArrayNotHasKey( $id, $this->db->rows );
	}

	/**
	 * Test revisions and autosaves create no ownership records.
	 */
	public function test_revisions_and_autosaves_create_no_ownership(): void {
		$this->isRevision = true;
		$this->assertFalse(
			$this->makeWatcher()->handle_post_updated(
				5,
				$this->makePost( 'new-slug' ),
				$this->makePost( 'old-slug' )
			)
		);
		$this->assertSame( [], $this->registry() );

		$this->isRevision = false;
		$this->isAutosave = true;
		$this->assertFalse(
			$this->makeWatcher()->handle_post_updated(
				5,
				$this->makePost( 'new-slug' ),
				$this->makePost( 'old-slug' )
			)
		);
		$this->assertSame( [], $this->registry() );
	}

	/**
	 * Test unsupported post types are excluded from cleanup.
	 */
	public function test_unsupported_post_type_is_excluded_from_cleanup(): void {
		$id = $this->createAuto();

		$this->transition( 'draft', 'publish', $this->makePost( 'new-slug', 'product', 'draft' ) );

		$this->assertArrayHasKey( $id, $this->db->rows );
	}

	/**
	 * Test permanent deletion excludes unsupported post types.
	 */
	public function test_permanent_deletion_excludes_unsupported_post_type(): void {
		$id = $this->createAuto();
		Functions\when( 'get_post_type' )->justReturn( 'product' );

		$this->deletePost();

		$this->assertArrayHasKey( $id, $this->db->rows );
		$this->assertArrayHasKey( $id, $this->registry() );
	}

	/**
	 * Test lifecycle hooks are registered once with the expected signatures.
	 */
	public function test_registers_lifecycle_cleanup_hooks(): void {
		$this->makeWatcher()->register();

		$found = array_values(
			array_filter(
				$this->hooks,
				static fn ( array $hook ): bool => in_array( $hook['hook'], [ 'post_updated', 'transition_post_status', 'before_delete_post' ], true )
			)
		);

		$this->assertCount( 3, $found );
		$this->assertSame( 'post_updated', $found[0]['hook'] );
		$this->assertSame( 3, $found[0]['args'] );
		$this->assertSame( 'transition_post_status', $found[1]['hook'] );
		$this->assertSame( 3, $found[1]['args'] );
		$this->assertSame( 'before_delete_post', $found[2]['hook'] );
		$this->assertSame( 1, $found[2]['args'] );
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
