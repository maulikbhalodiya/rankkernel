<?php
/**
 * Instant Indexing URL collection, snapshots and debounce bookkeeping.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\InstantIndexing;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves post and term signals into the URLs the engine should see.
 *
 * The in-memory permalink snapshot is the only place the pre-trash URL
 * survives: once the trash lands, get_permalink() reports the slug with
 * the __trashed suffix rather than the URL that was live. Debounce state
 * lives in the _rankkernel_indexnow post meta row, which stores last_url
 * and last_submitted for the most recent post signal.
 */
final class UrlCollector {
	/**
	 * Per post meta key holding the debounce state.
	 */
	private const META_KEY = '_rankkernel_indexnow';

	/**
	 * Settings instance, the owner of the debounce window contract.
	 *
	 * @var IndexNowSettings
	 */
	private IndexNowSettings $settings;

	/**
	 * Permalinks captured before an update, keyed by post id.
	 *
	 * @var array<int, string>
	 */
	private array $permalinks = [];

	/**
	 * Constructor.
	 *
	 * @param IndexNowSettings $settings Settings instance.
	 */
	public function __construct( IndexNowSettings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Remember the permalink a post has right now.
	 *
	 * Called before an update lands, so a later trash can submit the URL
	 * that actually existed rather than the __trashed variant.
	 *
	 * @param int    $postId    Post id.
	 * @param string $permalink Permalink at capture time.
	 * @return void
	 */
	public function snapshotPermalink( int $postId, string $permalink ): void {
		if ( $postId <= 0 || '' === $permalink ) {
			return;
		}

		$this->permalinks[ $postId ] = $permalink;
	}

	/**
	 * URLs to submit for a post transition.
	 *
	 * A trash transition returns the snapshot, the URL captured before
	 * the status changed. Every other signal submits the current
	 * permalink and refreshes the snapshot for a later trash.
	 *
	 * @param int    $postId    Post id.
	 * @param string $newStatus New post status.
	 * @param string $oldStatus Previous post status.
	 * @return string[] The result.
	 */
	public function postUrls( int $postId, string $newStatus, string $oldStatus ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- transition signature; the trash branch answers from the snapshot without needing the previous status.
		if ( 'trash' === $newStatus ) {
			$snapshot = $this->permalinks[ $postId ] ?? '';

			return '' === $snapshot ? [] : [ $snapshot ];
		}

		$permalink = $this->currentPermalink( $postId );

		if ( '' === $permalink ) {
			return [];
		}

		$this->snapshotPermalink( $postId, $permalink );

		return [ $permalink ];
	}

	/**
	 * Public URL of a term, empty when it cannot be resolved.
	 *
	 * @param int    $termId   Term id.
	 * @param string $taxonomy Taxonomy slug.
	 * @return string The result.
	 */
	public function termUrl( int $termId, string $taxonomy ): string {
		if ( $termId <= 0 || '' === $taxonomy ) {
			return '';
		}

		if ( function_exists( 'taxonomy_exists' ) && ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}

		if ( function_exists( 'is_taxonomy_viewable' ) && ! is_taxonomy_viewable( $taxonomy ) ) {
			return '';
		}

		if ( ! function_exists( 'get_term_link' ) ) {
			return '';
		}

		$link = get_term_link( $termId, $taxonomy );

		return is_string( $link ) ? $link : '';
	}

	/**
	 * Whether a URL was submitted inside the debounce window.
	 *
	 * @param int    $postId Post id.
	 * @param string $url    URL to check.
	 * @return bool The result.
	 */
	public function wasRecentlySubmitted( int $postId, string $url ): bool {
		if ( $postId <= 0 || '' === $url || ! function_exists( 'get_post_meta' ) ) {
			return false;
		}

		$stored = get_post_meta( $postId, self::META_KEY, true );

		if ( ! is_array( $stored ) ) {
			return false;
		}

		if ( (string) ( $stored['last_url'] ?? '' ) !== $url ) {
			return false;
		}

		return ( time() - (int) ( $stored['last_submitted'] ?? 0 ) ) < $this->debounceSeconds();
	}

	/**
	 * Record that a URL was just submitted for a post.
	 *
	 * @param int    $postId Post id.
	 * @param string $url    Submitted URL.
	 * @return void
	 */
	public function markSubmitted( int $postId, string $url ): void {
		if ( $postId <= 0 || '' === $url || ! function_exists( 'update_post_meta' ) ) {
			return;
		}

		update_post_meta(
			$postId,
			self::META_KEY,
			[
				'last_url'       => $url,
				'last_submitted' => time(),
			]
		);
	}

	/**
	 * Debounce window in seconds, owned by the settings class.
	 *
	 * Read through the injected instance so the window stays the
	 * settings class's contract rather than a value duplicated here.
	 *
	 * @return int The result.
	 */
	private function debounceSeconds(): int {
		return (int) $this->settings::DEBOUNCE_SECONDS;
	}

	/**
	 * Current permalink of a post, empty when it cannot be resolved.
	 *
	 * @param int $postId Post id.
	 * @return string The result.
	 */
	private function currentPermalink( int $postId ): string {
		if ( ! function_exists( 'get_permalink' ) ) {
			return '';
		}

		$permalink = get_permalink( $postId );

		return is_string( $permalink ) ? $permalink : '';
	}
}
