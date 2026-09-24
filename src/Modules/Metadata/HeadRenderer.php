<?php
/**
 * Head renderer, single-pass title + wp_head tag emission.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Metadata;

defined( 'ABSPATH' ) || exit;

use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Emits all head tags in ONE wp_head@1 pass; resolves title via pre_get_document_title.
 */
final class HeadRenderer {
	/**
	 * Cached context (built once per request).
	 *
	 * @var Context|null
	 */
	private ?Context $context = null;

	/**
	 * Settings store.
	 *
	 * @var SettingsStore
	 */
	private SettingsStore $settings;

	/**
	 * Tags replacer.
	 *
	 * @var TagsReplacer
	 */
	private TagsReplacer $replacer;

	/**
	 * Optional injected context (tests).
	 *
	 * @var Context|null
	 */
	private ?Context $injectedContext = null;

	/**
	 * Optional injected query (tests).
	 *
	 * @var WP_Query|null
	 */
	private ?WP_Query $injectedQuery = null;

	/**
	 * Memoized resolved titles keyed by context hash.
	 *
	 * Performance optimization: avoids redundant title template token parsing and regex replacement
	 * passes across multiple calls (e.g. title(), renderOgTags(), and renderTwitterTags()) in a single request.
	 * Context and settings are assumed immutable per request execution thread.
	 *
	 * @var array<string, string>
	 */
	private array $resolvedTitleMemo = [];

	/**
	 * Constructor.
	 *
	 * @param SettingsStore     $settings Settings store.
	 * @param TagsReplacer|null $replacer Optional replacer.
	 * @param Context|null      $context  Optional pre-built context (for tests).
	 * @param WP_Query|null     $query   Optional query (for tests).
	 */
	public function __construct(
		SettingsStore $settings,
		?TagsReplacer $replacer = null,
		?Context $context = null,
		?WP_Query $query = null
	) {
		$this->settings        = $settings;
		$this->replacer        = $replacer ?? new TagsReplacer();
		$this->injectedContext = $context;
		$this->injectedQuery   = $query;
	}

	/**
	 * Register hooks.
	 */
	public function boot(): void {
		add_action( 'wp_head', [ $this, 'render' ], 1 );
		add_filter( 'pre_get_document_title', [ $this, 'title' ], 10 );

		// Core adds rel_canonical at wp_head 10. RankKernel owns the canonical for
		// every context it renders, so core must not emit a second, conflicting one.
		remove_action( 'wp_head', 'rel_canonical' );

		// Core owns the single robots tag through wp_robots. RankKernel contributes
		// directives through that filter instead of echoing its own tag, so there is
		// exactly one tag, core defaults survive, and other plugins still merge.
		// Priority 99 runs last so RankKernel's restrictive wins.
		add_filter( 'wp_robots', [ $this, 'filterRobots' ], 99 );
	}

	/**
	 * Resolve document title.
	 *
	 * @param mixed $title WP-computed title passed to the filter.
	 * @return mixed Resolved title or original unchanged.
	 */
	public function title( mixed $title ): mixed {
		$ctx = $this->getContext();

		// Previews are not indexable URLs, return WP default untouched.
		if ( 'preview' === $ctx->queriedType() ) {
			return $title;
		}

		$hash = $ctx->hash();

		if ( array_key_exists( $hash, $this->resolvedTitleMemo ) ) {
			return $this->resolvedTitleMemo[ $hash ];
		}

		$meta         = $ctx->meta();
		$payloadTitle = isset( $meta['title'] ) ? trim( (string) $meta['title'] ) : '';

		// Payload title takes precedence.
		if ( '' !== $payloadTitle ) {
			// Treat raw payload title as literal; resolve ONLY if it contains a token.
			if ( 1 === preg_match( '/%%[a-z_]+%%/', $payloadTitle ) ) {
				$resolved = $this->replacer->replace( $ctx, $payloadTitle, 'title' );

				if ( '' !== trim( $resolved ) ) {
					$this->resolvedTitleMemo[ $hash ] = $resolved;

					return $resolved;
				}
			} else {
				$this->resolvedTitleMemo[ $hash ] = $payloadTitle;

				return $payloadTitle;
			}
		}

		// Settings template fallback.
		$template = (string) $this->settings->get( 'title_template', '%%title%% %%sep%% %%sitename%%' );

		if ( '' !== trim( $template ) ) {
			$resolved = $this->replacer->replace( $ctx, $template, 'title_template' );

			if ( '' !== trim( $resolved ) ) {
				$this->resolvedTitleMemo[ $hash ] = $resolved;

				return $resolved;
			}
		}

		// Fall back to WP default, never return '' which would break themes.
		// Deliberately uncached: the WP filter arg is not the Context title.
		return $title;
	}

	/**
	 * Emit all head tags in ONE pass.
	 */
	public function render(): void {
		// Previews are not indexable URLs, emit no SEO tags.
		$ctxEarly = $this->getContext();
		if ( 'preview' === $ctxEarly->queriedType() ) {
			return;
		}

		// Feed pages: skip all tags (return early).
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return;
		}

		$ctx = $ctxEarly;

		if ( 'feed' === $ctx->queriedType() ) {
			return;
		}

		$meta = $ctx->meta();

		// Description.
		$description = $this->resolveDescription( $ctx, $meta );

		if ( '' !== $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
		}

		// Robots are emitted exactly once by core wp_robots, merged in filterRobots().

		// Canonical (omitted on search/404).
		$canonical = $this->resolveCanonical( $ctx, $meta );

		if ( '' !== $canonical ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
		}

		// OG tags.
		$this->renderOgTags( $ctx, $meta, $description );

		// Twitter tags.
		$this->renderTwitterTags( $ctx, $meta );

		// Webmaster verification.
		$this->renderWebmasterTags();

		// R2 slot: after webmaster codes, before R4 comment, fires for Schema module.
		do_action( 'rankkernel/head/after_tags', $ctx ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

		// R3: rel="prev"/"next" intentionally not emitted (Google deprecated 2019).

		// R4: No dedicated Slack tags, OG + Twitter already cover Slack's unfurler.
	}

	/**
	 * Get or build context (once per request, reused by title() and render()).
	 *
	 * @return Context The result.
	 */
	private function getContext(): Context {
		if ( null !== $this->injectedContext ) {
			return $this->injectedContext;
		}

		if ( null !== $this->context ) {
			return $this->context;
		}

		$query = $this->injectedQuery;

		if ( null === $query ) {
			global $wp_query;

			if ( isset( $wp_query ) && $wp_query instanceof WP_Query ) {
				$query = $wp_query;
			} else {
				$query = new WP_Query();
			}
		}

		$this->context = new Context( $query, $this->settings, $this->replacer );

		return $this->context;
	}

	/**
	 * Get resolved document title (payload → template → ctx title) without leaking tokens.
	 *
	 * Performance optimization: memoizes the resolved title by Context hash and aligns field keys
	 * ('title', 'title_template') with title() so TagsReplacer memo achieves 100% hits across passes.
	 *
	 * @param Context $ctx Context.
	 * @return string The result.
	 */
	private function getResolvedTitle( Context $ctx ): string {
		$hash = $ctx->hash();

		if ( array_key_exists( $hash, $this->resolvedTitleMemo ) ) {
			return $this->resolvedTitleMemo[ $hash ];
		}

		$meta         = $ctx->meta();
		$payloadTitle = isset( $meta['title'] ) ? trim( (string) $meta['title'] ) : '';

		if ( '' !== $payloadTitle ) {
			if ( 1 === preg_match( '/%%[a-z_]+%%/', $payloadTitle ) ) {
				$resolved = $this->replacer->replace( $ctx, $payloadTitle, 'title' );

				if ( '' !== trim( $resolved ) ) {
					$this->resolvedTitleMemo[ $hash ] = $resolved;

					return $resolved;
				}
			} else {
				$this->resolvedTitleMemo[ $hash ] = $payloadTitle;

				return $payloadTitle;
			}
		}

		$template = (string) $this->settings->get( 'title_template', '%%title%% %%sep%% %%sitename%%' );

		if ( '' !== trim( $template ) ) {
			$resolved = $this->replacer->replace( $ctx, $template, 'title_template' );

			if ( '' !== trim( $resolved ) ) {
				$this->resolvedTitleMemo[ $hash ] = $resolved;

				return $resolved;
			}
		}

		// This fallback depends on the caller: title() returns the WP filter arg,
		// this path returns Context title. Caching it under the shared hash would
		// let whichever caller runs first poison the other, so it stays uncached.
		return $ctx->title();
	}

	/**
	 * Resolve description with fallback chain.
	 *
	 * @param Context              $ctx  Context.
	 * @param array<string, mixed> $meta Meta payload.
	 * @return string The result.
	 */
	private function resolveDescription( Context $ctx, array $meta ): string {
		$desc = isset( $meta['description'] ) ? trim( (string) $meta['description'] ) : '';

		if ( '' !== $desc ) {
			return $desc;
		}

		// Settings description template resolved via TagsReplacer.
		$tpl = (string) $this->settings->get( 'description_template', '' );

		if ( '' !== trim( $tpl ) ) {
			$resolved = $this->replacer->replace( $ctx, $tpl, 'description' );

			if ( '' !== trim( $resolved ) ) {
				return trim( $resolved );
			}
		}

		// Fallback: trimmed excerpt (singular) / taxonomy description / ''.
		if ( $ctx->isSingular() ) {
			$excerpt = $ctx->excerpt();

			if ( '' !== $excerpt ) {
				return $excerpt;
			}
		} elseif ( 'term' === $ctx->queriedType() ) {
			$termDesc = get_term_field( 'description', $ctx->queriedId() );

			if ( is_string( $termDesc ) && '' !== trim( $termDesc ) ) {
				$clean = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $termDesc ) : strip_tags( $termDesc ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- strip_tags fallback preserves inner text where wp_kses_post and wp_strip_all_tags are unavailable, matching the MetaPayload fallback.

				return trim( $clean );
			}

			if ( ! is_wp_error( $termDesc ) && is_string( $termDesc ) ) {
				return trim( $termDesc );
			}
		}

		return '';
	}

	/**
	 * Merge RankKernel robots directives into core's wp_robots array.
	 *
	 * Runs on the wp_robots filter so core emits the single robots tag for the
	 * page. Directives merge most restrictive wins, so a noindex from either
	 * side survives and any max budget keeps the tighter of the two values.
	 *
	 * @param array<string, mixed> $robots Directives from core and other plugins.
	 * @return array<string, mixed> Merged directives.
	 */
	public function filterRobots( array $robots ): array {
		$ctx = $this->getContext();

		// Feeds and previews render no RankKernel head output, core stays untouched.
		if ( in_array( $ctx->queriedType(), [ 'feed', 'preview' ], true ) ) {
			return $robots;
		}

		return $this->mergeRobots( $robots, $this->robotsDirectives( $ctx, $ctx->meta() ) );
	}

	/**
	 * Translate payload robots settings into wp_robots directive keys.
	 *
	 * @param Context              $ctx  Context.
	 * @param array<string, mixed> $meta Meta payload.
	 * @return array<string, mixed> Directive map.
	 */
	private function robotsDirectives( Context $ctx, array $meta ): array {
		$robots = $meta['robots'] ?? MetaPayload::defaults()['robots'];

		if ( ! is_array( $robots ) ) {
			$robots = MetaPayload::defaults()['robots'];
		}

		// Noindex rules: search and 404 are noindex, follow.
		if ( in_array( $ctx->queriedType(), [ 'search', '404' ], true ) ) {
			$robots['index']  = false;
			$robots['follow'] = true;
		}

		$directives = [];

		if ( empty( $robots['index'] ) ) {
			$directives['noindex'] = true;
		}

		if ( empty( $robots['follow'] ) ) {
			$directives['nofollow'] = true;
		}

		foreach ( [ 'noarchive', 'noimageindex', 'nosnippet' ] as $flag ) {
			if ( ! empty( $robots[ $flag ] ) ) {
				$directives[ $flag ] = true;
			}
		}

		if ( isset( $robots['max_snippet'] ) && null !== $robots['max_snippet'] && '' !== $robots['max_snippet'] ) {
			$directives['max-snippet'] = (int) $robots['max_snippet'];
		}

		if (
			isset( $robots['max_image_preview'] )
			&& null !== $robots['max_image_preview']
			&& '' !== $robots['max_image_preview']
		) {
			$directives['max-image-preview'] = (string) $robots['max_image_preview'];
		}

		if (
			isset( $robots['max_video_preview'] )
			&& null !== $robots['max_video_preview']
			&& '' !== $robots['max_video_preview']
		) {
			$directives['max-video-preview'] = (int) $robots['max_video_preview'];
		}

		return $directives;
	}

	/**
	 * Merge two directive maps, keeping the most restrictive value.
	 *
	 * @param array<string, mixed> $base Directives from core and other plugins.
	 * @param array<string, mixed> $ours RankKernel directives.
	 * @return array<string, mixed> Merged directives.
	 */
	private function mergeRobots( array $base, array $ours ): array {
		$merged = $base;

		foreach ( [ 'noindex', 'nofollow', 'noarchive', 'noimageindex', 'nosnippet' ] as $flag ) {
			if ( ! empty( $ours[ $flag ] ) ) {
				$merged[ $flag ] = true;
			}
		}

		foreach ( [ 'max-snippet', 'max-video-preview' ] as $budget ) {
			if ( ! isset( $ours[ $budget ] ) ) {
				continue;
			}

			$merged[ $budget ] = isset( $merged[ $budget ] )
				? self::tighterBudget( (int) $merged[ $budget ], (int) $ours[ $budget ] )
				: $ours[ $budget ];
		}

		if ( isset( $ours['max-image-preview'] ) ) {
			$merged['max-image-preview'] = isset( $merged['max-image-preview'] )
				? self::tighterImagePreview( (string) $merged['max-image-preview'], (string) $ours['max-image-preview'] )
				: $ours['max-image-preview'];
		}

		return $merged;
	}

	/**
	 * Tighter of two numeric snippet or video budgets.
	 *
	 * A negative value means no limit, so any non negative value is tighter.
	 *
	 * @param int $a First budget.
	 * @param int $b Second budget.
	 * @return int Tighter budget.
	 */
	private static function tighterBudget( int $a, int $b ): int {
		if ( $a < 0 ) {
			return $b;
		}

		if ( $b < 0 ) {
			return $a;
		}

		return min( $a, $b );
	}

	/**
	 * Tighter of two max-image-preview values.
	 *
	 * @param string $a First value.
	 * @param string $b Second value.
	 * @return string Tighter value.
	 */
	private static function tighterImagePreview( string $a, string $b ): string {
		$rank = [
			'none'     => 0,
			'standard' => 1,
			'large'    => 2,
		];

		return ( $rank[ $a ] ?? 2 ) <= ( $rank[ $b ] ?? 2 ) ? $a : $b;
	}

	/**
	 * Resolve canonical URL (omitted on search/404).
	 *
	 * @param Context              $ctx  Context.
	 * @param array<string, mixed> $meta Meta.
	 * @return string The result.
	 */
	private function resolveCanonical( Context $ctx, array $meta ): string {
		if ( in_array( $ctx->queriedType(), [ 'search', '404' ], true ) ) {
			return '';
		}

		$canonical = isset( $meta['canonical'] ) ? trim( (string) $meta['canonical'] ) : '';

		if ( '' !== $canonical ) {
			return $canonical;
		}

		return $ctx->permalink();
	}

	/**
	 * Render OG tags.
	 *
	 * @param Context              $ctx         Context.
	 * @param array<string, mixed> $meta        Meta.
	 * @param string               $description Resolved description for fallback.
	 */
	private function renderOgTags( Context $ctx, array $meta, string $description ): void {
		$og = $meta['og'] ?? [];

		if ( ! is_array( $og ) ) {
			$og = [];
		}

		// og:title.
		$ogTitle = isset( $og['title'] ) ? trim( (string) $og['title'] ) : '';

		if ( '' === $ogTitle ) {
			$ogTitle = $this->getResolvedTitle( $ctx );
		}

		if ( '' !== $ogTitle ) {
			echo '<meta property="og:title" content="' . esc_attr( $ogTitle ) . '" />' . "\n";
		}

		// og:description.
		$ogDesc = isset( $og['description'] ) ? trim( (string) $og['description'] ) : '';

		if ( '' === $ogDesc ) {
			$ogDesc = $description;
		}

		if ( '' !== $ogDesc ) {
			echo '<meta property="og:description" content="' . esc_attr( $ogDesc ) . '" />' . "\n";
		}

		// og:url.
		if ( ! in_array( $ctx->queriedType(), [ 'search', '404' ], true ) ) {
			$ogUrl = $ctx->permalink();

			if ( '' !== $ogUrl ) {
				echo '<meta property="og:url" content="' . esc_url( $ogUrl ) . '" />' . "\n";
			}
		}

		// og:type.
		$ogType = isset( $og['type'] ) ? trim( (string) $og['type'] ) : '';

		if ( '' === $ogType ) {
			if ( $ctx->isSingular() ) {
				$ogType = 'article';
			} elseif ( 'home' === $ctx->queriedType() ) {
				$ogType = 'website';
			} else {
				$ogType = 'website';
			}
		}

		if ( '' !== $ogType ) {
			echo '<meta property="og:type" content="' . esc_attr( $ogType ) . '" />' . "\n";
		}

		// og:image (+ width/height only when emitted URL came from that attachment).
		$ogImage = $ctx->ogImage();

		if ( '' !== $ogImage ) {
			echo '<meta property="og:image" content="' . esc_url( $ogImage ) . '" />' . "\n";

			$attachmentId = $ctx->ogImageAttachmentId();

			if ( $attachmentId > 0 && function_exists( 'wp_get_attachment_image_src' ) ) {
				$src = wp_get_attachment_image_src( $attachmentId, 'large' );

				if ( is_array( $src ) ) {
					echo '<meta property="og:image:width" content="' . esc_attr( (string) $src[1] ) . '" />' . "\n";
					echo '<meta property="og:image:height" content="' . esc_attr( (string) $src[2] ) . '" />' . "\n";
				}
			}
		}

		// og:site_name.
		$siteName = $ctx->siteName();

		if ( '' !== $siteName ) {
			echo '<meta property="og:site_name" content="' . esc_attr( $siteName ) . '" />' . "\n";
		}

		// og:locale.
		$locale = function_exists( 'get_locale' ) ? (string) get_locale() : '';

		if ( '' !== $locale ) {
			echo '<meta property="og:locale" content="' . esc_attr( $locale ) . '" />' . "\n";
		}
	}

	/**
	 * Render Twitter tags (fallback chain mirrors OG).
	 *
	 * @param Context              $ctx  Context.
	 * @param array<string, mixed> $meta Meta.
	 */
	private function renderTwitterTags( Context $ctx, array $meta ): void {
		$twitter = $meta['twitter'] ?? [];
		$og      = $meta['og'] ?? [];

		if ( ! is_array( $twitter ) ) {
			$twitter = [];
		}

		if ( ! is_array( $og ) ) {
			$og = [];
		}

		// twitter:card (payload -> default summary_large_image).
		$card = isset( $twitter['card'] ) ? trim( (string) $twitter['card'] ) : '';

		if ( '' === $card ) {
			$card = 'summary_large_image';
		}

		echo '<meta name="twitter:card" content="' . esc_attr( $card ) . '" />' . "\n";

		// twitter:title fallback: payload twitter.title -> og.title -> resolved title.
		$twTitle = isset( $twitter['title'] ) ? trim( (string) $twitter['title'] ) : '';

		if ( '' === $twTitle ) {
			$twTitle = isset( $og['title'] ) ? trim( (string) $og['title'] ) : '';

			if ( '' === $twTitle ) {
				$twTitle = $this->getResolvedTitle( $ctx );
			}
		}

		if ( '' !== $twTitle ) {
			echo '<meta name="twitter:title" content="' . esc_attr( $twTitle ) . '" />' . "\n";
		}

		// twitter:description fallback: twitter.description -> og.description -> resolved description.
		$twDesc = isset( $twitter['description'] ) ? trim( (string) $twitter['description'] ) : '';

		if ( '' === $twDesc ) {
			$twDesc = isset( $og['description'] ) ? trim( (string) $og['description'] ) : '';

			if ( '' === $twDesc ) {
				$twDesc = $this->resolveDescription( $ctx, $meta );
			}
		}

		if ( '' !== $twDesc ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $twDesc ) . '" />' . "\n";
		}

		// twitter:image fallback: twitter.image -> twitter.image_id -> og.image chain.
		$twImage = isset( $twitter['image'] ) ? trim( (string) $twitter['image'] ) : '';

		if (
			'' === $twImage
			&& isset( $twitter['image_id'] )
			&& (int) $twitter['image_id'] > 0
			&& function_exists( 'wp_get_attachment_image_url' )
		) {
			$url = wp_get_attachment_image_url( (int) $twitter['image_id'], 'large' );

			if ( is_string( $url ) && '' !== $url ) {
				$twImage = $url;
			}
		}

		if ( '' === $twImage ) {
			$twImage = $ctx->ogImage();
		}

		if ( '' !== $twImage ) {
			echo '<meta name="twitter:image" content="' . esc_url( $twImage ) . '" />' . "\n";
		}
	}

	/**
	 * Render webmaster verification tags from settings.
	 */
	private function renderWebmasterTags(): void {
		$map = [
			'google'    => 'google-site-verification',
			'bing'      => 'msvalidate.01',
			'yandex'    => 'yandex-verification',
			'baidu'     => 'baidu-site-verification',
			'pinterest' => 'p:domain_verify',
		];

		foreach ( $map as $key => $metaName ) {
			$val = trim( (string) $this->settings->get( 'webmaster_' . $key, '' ) );

			if ( '' !== $val ) {
				echo '<meta name="' . esc_attr( $metaName ) . '" content="' . esc_attr( $val ) . '" />' . "\n";
			}
		}
	}
}
