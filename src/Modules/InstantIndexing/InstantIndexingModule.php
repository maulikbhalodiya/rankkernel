<?php
/**
 * Instant Indexing module, wires the guarded auto submit path.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\InstantIndexing;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\ModuleInterface;

/**
 * Hard gated IndexNow auto submit module.
 *
 * Registers zero hooks and constructs zero clients while the module is
 * disabled. When enabled it serves the key file, snapshots permalinks
 * before updates, and signals publish, update and trash transitions to
 * the client. Failures are never queued: a failed URL rides the next
 * real publish.
 */
final class InstantIndexingModule implements ModuleInterface {
	/**
	 * Module id in the enable map.
	 */
	private const ID = 'instant-indexing';

	/**
	 * Term hooks mapped to the action they signal.
	 *
	 * The pre_delete_term hook is used rather than delete_term because
	 * the URL is only resolvable while the term row still exists, and
	 * delete_term fires after the row is gone.
	 */
	private const TERM_HOOKS = [
		'created_term'    => 'created',
		'edited_term'     => 'edited',
		'pre_delete_term' => 'deleted',
	];

	/**
	 * Actions onTermChange() accepts.
	 */
	private const TERM_ACTIONS = [ 'created', 'edited', 'deleted' ];

	/**
	 * Enable map, resolved from the option when not injected.
	 *
	 * @var ModuleEnableMap|null
	 */
	private ?ModuleEnableMap $enableMap;

	/**
	 * Settings instance, lazily constructed when not injected.
	 *
	 * @var IndexNowSettings|null
	 */
	private ?IndexNowSettings $settings;

	/**
	 * Client instance, lazily constructed when not injected.
	 *
	 * @var IndexNowClient|null
	 */
	private ?IndexNowClient $client;

	/**
	 * URL collector, lazily constructed.
	 *
	 * @var UrlCollector|null
	 */
	private ?UrlCollector $collector = null;

	/**
	 * Key file server, lazily constructed.
	 *
	 * @var KeyFileServer|null
	 */
	private ?KeyFileServer $server = null;

	/**
	 * Constructor.
	 *
	 * @param ModuleEnableMap|null  $enableMap Enable map, null resolves the shared map lazily.
	 * @param IndexNowSettings|null $settings  Settings, null constructs the default.
	 * @param IndexNowClient|null   $client    Client, null constructs the default.
	 */
	public function __construct(
		?ModuleEnableMap $enableMap = null,
		?IndexNowSettings $settings = null,
		?IndexNowClient $client = null
	) {
		$this->enableMap = $enableMap;
		$this->settings  = $settings;
		$this->client    = $client;
	}

	/**
	 * Module id.
	 *
	 * @return string The result.
	 */
	public function getId(): string {
		return self::ID;
	}

	/**
	 * Module name.
	 *
	 * @return string The result.
	 */
	public function getName(): string {
		return 'Instant Indexing (IndexNow)';
	}

	/**
	 * Boot priority.
	 *
	 * @return int The result.
	 */
	public function getPriority(): int {
		return 45;
	}

	/**
	 * Module ids this module depends on.
	 *
	 * IndexNow signals deltas and needs no sitemap, so a sitemap
	 * dependency would force this module off for no reason.
	 *
	 * @return string[] The result.
	 */
	public function dependsOn(): array {
		return [];
	}

	/**
	 * Whether the module id is in the enable map.
	 *
	 * @return bool The result.
	 */
	public function isEnabled(): bool {
		return $this->enableMap()->isEnabled( self::ID );
	}

	/**
	 * Wire services, no hooks.
	 *
	 * Returns before anything is constructed while disabled, so a
	 * disabled module registers zero hooks and constructs zero clients.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		$this->settings()->ensureKey();
	}

	/**
	 * Register the hooks, none of them while disabled.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		$this->server()->register();

		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action( 'pre_post_update', [ $this, 'onPrePostUpdate' ], 10, 1 );
		add_action( 'transition_post_status', [ $this, 'onTransitionPostStatus' ], 10, 3 );

		foreach ( self::TERM_HOOKS as $hook => $action ) {
			add_action(
				$hook,
				function ( mixed $termId = null, mixed $ttId = null, mixed $taxonomy = null ) use ( $action ): void {
					$this->onTermChange( (int) $termId, (int) $ttId, (string) $taxonomy, $action );
				},
				10,
				3
			);
		}
	}

	/**
	 * Submit URLs through the client, never throwing.
	 *
	 * The debounce lives on the post signal path, which knows its post
	 * id; this entry point is also the manual path, where no post id
	 * exists and a human click is never suppressed. A thrown transport
	 * failure is logged and returned as an empty summary.
	 *
	 * @param array<int|string, mixed> $urls   URLs to submit.
	 * @param string                   $source Submitting surface, auto or manual.
	 * @return array<string, mixed> Outcome summary.
	 */
	public function submitUrls( array $urls, string $source ): array {
		$list = [];

		foreach ( $urls as $url ) {
			if ( is_string( $url ) && '' !== $url ) {
				$list[] = $url;
			}
		}

		if ( [] === $list ) {
			return self::emptyResult();
		}

		try {
			return $this->client()->submit( $list, $source );
		} catch ( \Throwable $error ) {
			$this->settings()->logEntry( $list[0], 0, $source, $error->getMessage() );

			return self::emptyResult();
		}
	}

	/**
	 * Snapshot the permalink before an update lands.
	 *
	 * @param mixed $postId Post id passed by the pre_post_update action.
	 * @return void
	 */
	public function onPrePostUpdate( mixed $postId = null ): void {
		if ( ! $this->isEnabled() || ! function_exists( 'get_permalink' ) ) {
			return;
		}

		$id = is_numeric( $postId ) ? (int) $postId : 0;

		if ( $id <= 0 ) {
			return;
		}

		$permalink = get_permalink( $id );

		if ( is_string( $permalink ) && '' !== $permalink ) {
			$this->collector()->snapshotPermalink( $id, $permalink );
		}
	}

	/**
	 * Handle a post status transition.
	 *
	 * Guard order: revisions and autosaves first, then the post type
	 * viewability, then the signal test. A signal is a transition into
	 * publish, a publish to publish update, or a transition into trash
	 * from a public status. Going from publish to draft is deliberately
	 * not a signal, matching the sitemaps precedent.
	 *
	 * @param string $newStatus New post status.
	 * @param string $oldStatus Previous post status.
	 * @param mixed  $post      Post object passed by the action.
	 * @return void
	 */
	public function onTransitionPostStatus( string $newStatus, string $oldStatus, mixed $post ): void {
		if ( ! $this->isEnabled() || ! $this->settings()->getAutoSubmit() ) {
			return;
		}

		$postId = $this->postId( $post );

		if ( $postId <= 0 || $this->isRevisionOrAutosave( $postId ) ) {
			return;
		}

		$postType = $this->postType( $post );

		if ( '' === $postType || ! $this->isViewablePostType( $postType ) ) {
			return;
		}

		if ( ! $this->isSignal( $newStatus, $oldStatus ) ) {
			return;
		}

		$urls = $this->collector()->postUrls( $postId, $newStatus, $oldStatus );

		if ( [] === $urls ) {
			return;
		}

		// The debounce applies to publish and update signals only. A trash
		// event is a distinct fact for the engine, so a URL that was just
		// signalled as updated is still signalled once as removed.
		if ( 'trash' !== $newStatus && $this->collector()->wasRecentlySubmitted( $postId, $urls[0] ) ) {
			return;
		}

		$result = $this->submitUrls( $urls, 'auto' );

		// Mark the attempt, not just the acceptance: the spec debounces the
		// same URL for ten minutes, and an unmarked failure would let every
		// later save retry against the endpoint inside that window.
		if ( [] !== ( $result['results'] ?? [] ) ) {
			$this->collector()->markSubmitted( $postId, $urls[0] );
		}
	}

	/**
	 * Handle a term change.
	 *
	 * @param int    $termId   Term id.
	 * @param int    $ttId     Term taxonomy id.
	 * @param string $taxonomy Taxonomy slug.
	 * @param string $action   Hook action, created, edited or deleted.
	 * @return void
	 */
	public function onTermChange( int $termId, int $ttId, string $taxonomy, string $action ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- term hook signature; the URL path never needs the term taxonomy id.
		if ( ! $this->isEnabled() || ! $this->settings()->getAutoSubmit() ) {
			return;
		}

		if ( ! in_array( $action, self::TERM_ACTIONS, true ) ) {
			return;
		}

		$url = $this->collector()->termUrl( $termId, $taxonomy );

		if ( '' === $url ) {
			return;
		}

		$this->submitUrls( [ $url ], 'auto' );
	}

	/**
	 * Empty outcome summary matching the client result shape.
	 *
	 * @return array<string, mixed> The result.
	 */
	private static function emptyResult(): array {
		return [
			'accepted'  => 0,
			'permanent' => 0,
			'transient' => 0,
			'results'   => [],
		];
	}

	/**
	 * Resolve the enable map, reading the option once when not injected.
	 *
	 * @return ModuleEnableMap The result.
	 */
	private function enableMap(): ModuleEnableMap {
		return $this->enableMap ??= new ModuleEnableMap();
	}

	/**
	 * Resolve the settings, constructing the default when not injected.
	 *
	 * @return IndexNowSettings The result.
	 */
	private function settings(): IndexNowSettings {
		return $this->settings ??= new IndexNowSettings();
	}

	/**
	 * Resolve the client, constructing the default when not injected.
	 *
	 * @return IndexNowClient The result.
	 */
	private function client(): IndexNowClient {
		return $this->client ??= new IndexNowClient( $this->settings() );
	}

	/**
	 * Resolve the URL collector.
	 *
	 * @return UrlCollector The result.
	 */
	private function collector(): UrlCollector {
		return $this->collector ??= new UrlCollector( $this->settings() );
	}

	/**
	 * Resolve the key file server.
	 *
	 * @return KeyFileServer The result.
	 */
	private function server(): KeyFileServer {
		return $this->server ??= new KeyFileServer( $this->settings() );
	}

	/**
	 * Post id from the mixed object a hook hands over.
	 *
	 * @param mixed $post Post object.
	 * @return int The result.
	 */
	private function postId( mixed $post ): int {
		if ( ! is_object( $post ) || ! isset( $post->ID ) || ! is_numeric( $post->ID ) ) {
			return 0;
		}

		return (int) $post->ID;
	}

	/**
	 * Post type from the mixed object a hook hands over.
	 *
	 * @param mixed $post Post object.
	 * @return string The result.
	 */
	private function postType( mixed $post ): string {
		if ( ! is_object( $post ) || ! isset( $post->post_type ) || ! is_string( $post->post_type ) ) {
			return '';
		}

		return $post->post_type;
	}

	/**
	 * Whether the post id is a revision or an autosave.
	 *
	 * @param int $postId Post id.
	 * @return bool The result.
	 */
	private function isRevisionOrAutosave( int $postId ): bool {
		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $postId ) ) {
			return true;
		}

		return function_exists( 'wp_is_post_autosave' ) && (bool) wp_is_post_autosave( $postId );
	}

	/**
	 * Whether the post type is publicly viewable.
	 *
	 * @param string $postType Post type.
	 * @return bool The result.
	 */
	private function isViewablePostType( string $postType ): bool {
		return function_exists( 'is_post_type_viewable' ) && (bool) is_post_type_viewable( $postType );
	}

	/**
	 * Whether the post status is publicly viewable.
	 *
	 * @param string $status Post status.
	 * @return bool The result.
	 */
	private function isViewableStatus( string $status ): bool {
		return function_exists( 'is_post_status_viewable' ) && (bool) is_post_status_viewable( $status );
	}

	/**
	 * Whether a transition is a submit signal.
	 *
	 * @param string $newStatus New post status.
	 * @param string $oldStatus Previous post status.
	 * @return bool The result.
	 */
	private function isSignal( string $newStatus, string $oldStatus ): bool {
		if ( 'publish' === $newStatus ) {
			return true;
		}

		return 'trash' === $newStatus && $this->isViewableStatus( $oldStatus );
	}
}
