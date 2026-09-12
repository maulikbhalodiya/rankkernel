<?php
/**
 * Automatic redirect on post slug change.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

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
	 * Rule repository.
	 */
	private RedirectRepository $repository;

	/**
	 * Module settings store.
	 */
	private RedirectsSettings $settings;

	/**
	 * Loop and chain analyzer.
	 */
	private Validator $validator;

	/**
	 * Destination policy checker.
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
	 * Create one 301 when a post or page slug actually changed.
	 *
	 * Skips when the auto slug setting is off, when the post type is outside
	 * posts and pages, for revisions and autosaves, when the slug is
	 * unchanged, when the source is already redirected, and when the new rule
	 * would create a cycle. Points directly at the final destination when the
	 * new address already redirects onward, so no avoidable chain is stored.
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

		if ( ! is_object( $postAfter ) || ! is_object( $postBefore ) ) {
			return false;
		}

		$after  = (array) $postAfter;
		$before = (array) $postBefore;

		$type = (string) ( $after['post_type'] ?? '' );

		if ( ! in_array( $type, self::TYPES, true ) ) {
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
		$loop       = $this->validator->detect_loop( $proposed, $candidates );

		if ( $loop['has_cycle'] ) {
			return false;
		}

		$target = $this->final_target( $proposed, $candidates, $newPath );

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

		RedirectCache::invalidateAll();

		return true;
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
	 * @param array<string, mixed>              $proposed   Proposed source, target, code, match type.
	 * @param array<int, array<string, mixed>>  $candidates Active candidate rules.
	 * @param string                            $newPath    Normalized new permalink path.
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
