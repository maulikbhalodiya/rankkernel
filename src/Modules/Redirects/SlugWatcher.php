<?php
/**
 * Automatic redirect on post slug change.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Watches post updates and keeps old addresses working.
 *
 * Hooks post_updated for posts and pages. When the slug actually changed, it
 * creates one exact 301 from the old permalink path to the new permalink
 * path through the normal pipeline: duplicate detection, loop detection, and
 * chain shortening. Revisions, autosaves, unchanged slugs, and other post
 * types are ignored. The RedirectsSettings auto slug redirect flag disables
 * the watcher entirely.
 */
final class SlugWatcher {
	/**
	 * Post types covered in v1.
	 *
	 * @var string[]
	 */
	private const TYPES = [ 'post', 'page' ];

	/**
	 * Hidden post metadata mapping owned redirect ids to persisted fingerprints.
	 */
	public const AUTO_REDIRECT_META = '_rankkernel_auto_redirects';

	/**
	 * Rule repository.
	 *
	 * @var RedirectRepository
	 */
	private RedirectRepository $repository;

	/**
	 * Module settings store.
	 *
	 * @var RedirectsSettings
	 */
	private RedirectsSettings $settings;

	/**
	 * Loop and chain analyzer.
	 *
	 * @var Validator
	 */
	private Validator $validator;

	/**
	 * Destination policy checker.
	 *
	 * @var DestinationValidator
	 */
	private DestinationValidator $destinationValidator;

	/**
	 * Constructor, dependencies are injectable for tests.
	 *
	 * @param RedirectRepository|null   $repository           Rule repository, fresh one when null.
	 * @param RedirectsSettings|null    $settings             Settings store, fresh one when null.
	 * @param Validator|null            $validator            Safety analyzer, fresh one when null.
	 * @param DestinationValidator|null $destinationValidator Destination checker, fresh one when null.
	 */
	public function __construct(
		?RedirectRepository $repository = null,
		?RedirectsSettings $settings = null,
		?Validator $validator = null,
		?DestinationValidator $destinationValidator = null
	) {
		$this->repository           = $repository ?? new RedirectRepository();
		$this->settings             = $settings ?? new RedirectsSettings();
		$this->validator            = $validator ?? new Validator();
		$this->destinationValidator = $destinationValidator ?? new DestinationValidator();
	}

	/**
	 * Register the watcher hook with the full post_updated signature.
	 */
	public function register(): void {
		add_action( 'post_updated', [ $this, 'on_post_updated' ], 10, 3 );
		add_action( 'transition_post_status', [ $this, 'on_transition_post_status' ], 10, 3 );
		add_action( 'before_delete_post', [ $this, 'on_before_delete_post' ], 10, 1 );
	}

	/**
	 * Hook entry for post_updated, delegates to the creation pipeline.
	 *
	 * Kept void because action callbacks must not return anything.
	 *
	 * @param int   $postId     Updated post id.
	 * @param mixed $postAfter  Post object after the update.
	 * @param mixed $postBefore Post object before the update.
	 */
	public function on_post_updated( int $postId, mixed $postAfter, mixed $postBefore ): void {
		$this->handle_post_updated( $postId, $postAfter, $postBefore );
	}

	/**
	 * Remove owned automatic redirects when published content leaves publish.
	 *
	 * Cleanup is ownership-gated rather than capability-gated. A writer who
	 * may legitimately unpublish the post may trigger removal of only the
	 * unchanged automatic rules already registered to that exact post.
	 *
	 * @param string $newStatus New post status.
	 * @param string $oldStatus Previous post status.
	 * @param mixed  $post      Post object after the transition.
	 */
	public function on_transition_post_status( string $newStatus, string $oldStatus, mixed $post ): void {
		if ( 'publish' !== $oldStatus || 'publish' === $newStatus || ! is_object( $post ) ) {
			return;
		}

		$type = (string) ( $post->post_type ?? '' );

		if ( ! in_array( $type, self::TYPES, true ) ) {
			return;
		}

		$postId = (int) ( $post->ID ?? 0 );

		if ( $postId > 0 ) {
			$this->cleanupOwnedRedirects( $postId );
		}
	}

	/**
	 * Remove owned automatic redirects before permanent post deletion.
	 *
	 * @param int $postId Post id being deleted.
	 */
	public function on_before_delete_post( int $postId ): void {
		if ( $postId <= 0 ) {
			return;
		}

		if ( function_exists( 'get_post_type' ) ) {
			$type = (string) get_post_type( $postId );

			if ( ! in_array( $type, self::TYPES, true ) ) {
				return;
			}
		}

		$this->cleanupOwnedRedirects( $postId );
	}

	/**
	 * Create one 301 when a post or page slug actually changed.
	 *
	 * Skips when the auto slug setting is off, when the writer lacks the
	 * capability, when the post type is outside posts and pages, when the
	 * result is not published, for revisions and autosaves, when the slug is
	 * unchanged, when the source is already redirected, when the new rule
	 * would resolve to itself, when the exact rule budget is full, and when
	 * the new rule would create a cycle.
	 * An inconclusive loop or chain analysis also refuses the automatic
	 * creation: with no administrator in the loop to show the warning to,
	 * fail closed instead of storing a possibly looping rule. Points
	 * directly at the final destination when the new address already
	 * redirects onward, so no avoidable chain is stored.
	 *
	 * @param int   $postId     Updated post id.
	 * @param mixed $postAfter  Post object after the update.
	 * @param mixed $postBefore Post object before the update.
	 * @return bool True when exactly one redirect was created.
	 */
	public function handle_post_updated( int $postId, mixed $postAfter, mixed $postBefore ): bool {
		if ( empty( $this->settings->get( 'auto_slug_redirect', true ) ) ) {
			return false;
		}

		// Every other write path into the redirect table requires
		// manage_options. An unattended hook must not mint public 301s for
		// writers who could never create one by hand, so contexts without an
		// authorized user fail closed here.
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( ! is_object( $postAfter ) || ! is_object( $postBefore ) ) {
			return false;
		}

		$after  = (array) $postAfter;
		$before = (array) $postBefore;

		$type = (string) ( $after['post_type'] ?? '' );

		if ( ! in_array( $type, self::TYPES, true ) ) {
			return false;
		}

		// Only published results have public URLs worth preserving. Drafts
		// were never served, and un-publishing removes the destination, so
		// neither transition creates a redirect.
		if ( 'publish' !== (string) ( $after['post_status'] ?? '' ) ) {
			return false;
		}

		if ( $this->is_revision_or_autosave( $postId ) ) {
			return false;
		}

		$oldSlug = (string) ( $before['post_name'] ?? '' );
		$newSlug = (string) ( $after['post_name'] ?? '' );

		if ( '' === $oldSlug || '' === $newSlug || $oldSlug === $newSlug ) {
			return false;
		}

		$oldPath = $this->permalink_path( $postBefore, $oldSlug );
		$newPath = $this->permalink_path( $postAfter, $newSlug );

		if ( null === $oldPath || null === $newPath ) {
			return false;
		}

		if ( Normalizer::isBlockedSource( $oldPath ) ) {
			return false;
		}

		if ( $oldPath === $newPath ) {
			return false;
		}

		if ( null !== $this->repository->lookup( $oldPath, 'exact' ) ) {
			return false;
		}

		$proposed = [
			'source'     => $oldPath,
			'target'     => $newPath,
			'code'       => '301',
			'match_type' => 'exact',
		];

		$candidates = $this->candidates( $proposed );
		$safety     = $this->validator->assess_safety( $proposed, $candidates );

		if ( 'equivalent' === $safety['verdict'] || 'cycle' === $safety['verdict'] ) {
			return false;
		}

		if ( $safety['loop']['inconclusive'] || $safety['chain']['inconclusive'] ) {
			return false;
		}

		$target = $this->final_target( $proposed, $candidates, $newPath );

		// Exact rules bypass the pattern cap by design, so the automatic path
		// carries its own bound against the same budget. Without it one
		// misbehaving writer could grow the table without limit.
		if ( $this->repository->count_exact_rules() >= RedirectRepository::MAX_PATTERNS ) {
			return false;
		}

		$newId = $this->repository->insert(
			[
				'source'     => $oldPath,
				'target'     => $target,
				'code'       => '301',
				'match_type' => 'exact',
				'is_active'  => true,
			]
		);

		if ( $newId <= 0 ) {
			return false;
		}

		$stored = $this->repository->get( $newId );

		if ( null === $stored || ! $this->recordOwnership( $postId, $newId, $stored, $oldPath, $target ) ) {
			$this->rollbackOwnedRedirect( $newId, $stored, $oldPath, $target );

			return false;
		}

		RedirectCache::invalidateAll();

		return true;
	}

	/**
	 * Record the persisted row as owned by this post.
	 *
	 * @param int                  $postId     Owning post id.
	 * @param int                  $redirectId New redirect id.
	 * @param array<string, mixed> $row        Persisted redirect row.
	 * @param string               $source     Intended stored source.
	 * @param string               $target     Intended stored target after shortening.
	 * @return bool True when ownership was stored.
	 */
	private function recordOwnership( int $postId, int $redirectId, array $row, string $source, string $target ): bool {
		$fingerprint = $this->fingerprint( $row );

		if (
			null === $fingerprint
			|| $source !== $row['source']
			|| $target !== $row['target']
			|| '301' !== $row['code']
			|| 'exact' !== $row['match_type']
			|| 1 !== (int) $row['is_active']
		) {
			return false;
		}

		$registry = $this->ownershipRegistry( $postId );

		if ( null === $registry ) {
			return false;
		}

		$registry[ $redirectId ] = $fingerprint + [ 'post_id' => $postId ];

		return $this->writeRegistry( $postId, $registry );
	}

	/**
	 * Roll back only the just-created row when ownership cannot be recorded.
	 *
	 * @param int                       $redirectId New redirect id.
	 * @param array<string, mixed>|null $row        Persisted row read after insert.
	 * @param string                    $source     Intended stored source.
	 * @param string                    $target     Intended stored target after shortening.
	 */
	private function rollbackOwnedRedirect( int $redirectId, ?array $row, string $source, string $target ): void {
		if ( null === $row || ! isset( $row['created'] ) || ! is_scalar( $row['created'] ) ) {
			return;
		}

		$expected = [
			'match_type'  => 'exact',
			'source_hash' => Normalizer::hash( 'exact', $source ),
			'source'      => $source,
			'target'      => $target,
			'code'        => '301',
			'is_active'   => 1,
			'created'     => (string) $row['created'],
		];

		if ( null === $this->fingerprint( $expected ) ) {
			return;
		}

		$this->repository->deleteIfUnchanged( $redirectId, $expected );
	}

	/**
	 * Delete unchanged automatic redirects registered to one post.
	 *
	 * @param int $postId Owning post id.
	 */
	private function cleanupOwnedRedirects( int $postId ): void {
		$registry = $this->ownershipRegistry( $postId );

		if ( null === $registry || [] === $registry ) {
			return;
		}

		$changed = false;

		foreach ( $registry as $redirectId => $entry ) {
			$expected = $this->fingerprint( $entry );

			if ( null === $expected ) {
				unset( $registry[ $redirectId ] );
				$changed = true;

				continue;
			}

			$row = $this->repository->get( $redirectId );

			if ( null === $row ) {
				unset( $registry[ $redirectId ] );
				$changed = true;

				continue;
			}

			$current = $this->fingerprint( $row );

			if ( null === $current || $current !== $expected ) {
				// An edited or replaced row is no longer safe to treat as owned.
				unset( $registry[ $redirectId ] );
				$changed = true;

				continue;
			}

			$result = $this->repository->deleteIfUnchanged( $redirectId, $expected );

			if ( in_array( $result, [ 'deleted', 'missing', 'changed' ], true ) ) {
				unset( $registry[ $redirectId ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			$this->writeRegistry( $postId, $registry );
		}
	}

	/**
	 * Load and validate the hidden per-post ownership registry.
	 *
	 * A malformed registry fails closed: no row is deleted and the metadata
	 * is left untouched for investigation.
	 *
	 * @param int $postId Owning post id.
	 * @return array<int, array<string, mixed>>|null Valid registry or null.
	 */
	private function ownershipRegistry( int $postId ): ?array {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return null;
		}

		$raw = get_post_meta( $postId, self::AUTO_REDIRECT_META, true );

		if ( '' === $raw || [] === $raw ) {
			return [];
		}

		if ( ! is_array( $raw ) ) {
			return null;
		}

		$registry = [];

		foreach ( $raw as $redirectId => $entry ) {
			if ( ( ! is_int( $redirectId ) && ! ctype_digit( (string) $redirectId ) ) || (int) $redirectId <= 0 ) {
				return null;
			}

			if ( ! is_array( $entry ) ) {
				return null;
			}

			$owner = $entry['post_id'] ?? null;

			if (
				( ! is_int( $owner ) && ! ( is_string( $owner ) && ctype_digit( $owner ) ) )
				|| (int) $owner !== $postId
			) {
				return null;
			}

			$fingerprint = $this->fingerprint( $entry );

			if ( null === $fingerprint ) {
				return null;
			}

			$registry[ (int) $redirectId ] = $fingerprint + [ 'post_id' => $postId ];
		}

		return $registry;
	}

	/**
	 * Persist or clear a validated ownership registry.
	 *
	 * @param int                              $postId   Owning post id.
	 * @param array<int, array<string, mixed>> $registry Registry.
	 * @return bool True when metadata was written or cleared.
	 */
	private function writeRegistry( int $postId, array $registry ): bool {
		if ( [] === $registry ) {
			return function_exists( 'delete_post_meta' ) && delete_post_meta( $postId, self::AUTO_REDIRECT_META );
		}

		return function_exists( 'update_post_meta' ) && update_post_meta( $postId, self::AUTO_REDIRECT_META, $registry );
	}

	/**
	 * Build the immutable persisted-row fingerprint used for ownership.
	 *
	 * Hits and last_accessed are intentionally excluded because ordinary
	 * redirect use changes them without changing rule ownership.
	 *
	 * @param array<string, mixed> $row Redirect row or metadata entry.
	 * @return array<string, mixed>|null Valid fingerprint or null.
	 */
	private function fingerprint( array $row ): ?array {
		$values = [];

		foreach ( [ 'match_type', 'source_hash', 'source', 'target', 'code', 'created', 'is_active' ] as $field ) {
			if ( ! array_key_exists( $field, $row ) || ! is_scalar( $row[ $field ] ) ) {
				return null;
			}

			$values[ $field ] = $row[ $field ];
		}

		$matchType  = (string) $values['match_type'];
		$sourceHash = (string) $values['source_hash'];
		$source     = (string) $values['source'];
		$target     = (string) $values['target'];
		$code       = (string) $values['code'];
		$created    = (string) $values['created'];

		if (
			'exact' !== $matchType
			|| 64 !== strlen( $sourceHash )
			|| ! ctype_xdigit( $sourceHash )
			|| '' === $source
			|| '' === $target
			|| '301' !== $code
			|| '' === $created
			|| ! in_array( $values['is_active'], [ 0, 1, '0', '1' ], true )
		) {
			return null;
		}

		return [
			'match_type'  => $matchType,
			'source_hash' => $sourceHash,
			'source'      => $source,
			'target'      => $target,
			'code'        => $code,
			'is_active'   => (int) $values['is_active'],
			'created'     => $created,
		];
	}

	/**
	 * Whether the updated post is a revision or an autosave.
	 *
	 * Falls back to the revision post type check when the core helpers are
	 * unavailable, so the guard holds in every context.
	 *
	 * @param int $postId Updated post id.
	 * @return bool True for revisions and autosaves.
	 */
	private function is_revision_or_autosave( int $postId ): bool {
		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $postId ) ) {
			return true;
		}

		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $postId ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Permalink path for a post object, with a home URL fallback.
	 *
	 * Prefers get_permalink on the given object, then constructs from the
	 * home URL plus the slug when the permalink is unavailable.
	 *
	 * @param mixed  $post Post object.
	 * @param string $slug Post slug for the fallback address.
	 * @return string|null Normalized path or null when unresolvable.
	 */
	private function permalink_path( mixed $post, string $slug ): ?string {
		$permalink = '';

		if ( function_exists( 'get_permalink' ) && is_object( $post ) ) {
			$candidate = get_permalink( $post );

			if ( is_string( $candidate ) && '' !== $candidate ) {
				$permalink = $candidate;
			}
		}

		if ( '' === $permalink && function_exists( 'home_url' ) && '' !== $slug ) {
			$permalink = home_url( '/' . trim( $slug, '/' ) . '/' );
		}

		if ( '' === $permalink ) {
			return null;
		}

		$path = function_exists( 'wp_parse_url' ) ? wp_parse_url( $permalink, PHP_URL_PATH ) : null;

		if ( ! is_string( $path ) || '' === $path ) {
			return null;
		}

		return Normalizer::normalize( $path );
	}

	/**
	 * Candidate rows for safety analysis, including the proposed rule itself.
	 *
	 * @param array<string, mixed> $proposed Proposed source, target, code, match type.
	 * @return array<int, array<string, mixed>>
	 */
	private function candidates( array $proposed ): array {
		$rows = $this->repository->find_cycle_candidates();
		$out  = [];

		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$out[] = $row;
			}
		}

		$out[] = array_merge( $proposed, [ 'is_active' => 1 ] );

		return $out;
	}

	/**
	 * Final target for the new rule, shortening avoidable chains.
	 *
	 * When the new address already redirects onward to a deterministic and
	 * policy clean destination, the watcher points the old address straight
	 * at it. Otherwise the new address itself is the target.
	 *
	 * @param array<string, mixed>             $proposed   Proposed source, target, code, match type.
	 * @param array<int, array<string, mixed>> $candidates Active candidate rules.
	 * @param string                           $newPath    Normalized new permalink path.
	 * @return string Target to store.
	 */
	private function final_target( array $proposed, array $candidates, string $newPath ): string {
		$chain = $this->validator->detect_chain( $proposed, $candidates );

		if ( ! $chain['has_chain'] || $chain['inconclusive'] ) {
			return $newPath;
		}

		$final = $chain['final'];

		if ( ! is_string( $final ) || '' === $final ) {
			return $newPath;
		}

		$checked = $this->destinationValidator->validate( $final, '301' );

		if ( ! $checked['valid'] ) {
			return $newPath;
		}

		return $checked['destination'];
	}
}
