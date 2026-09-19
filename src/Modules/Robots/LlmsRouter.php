<?php
/**
 * Virtual llms.txt endpoint.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Robots;

use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Serves llms.txt as a virtual Markdown response.
 *
 * The route is only evaluated on the llms.txt request, so no normal page
 * view pays for it. Responses carry Content-Type text/markdown and an
 * X-Robots-Tag noindex header, and the body is cached behind a validator
 * that is bumped on content changes.
 */
final class LlmsRouter {
	/**
	 * Query var marking a llms.txt request.
	 */
	public const QUERY_VAR = 'rankkernel_llms';

	/**
	 * Option holding the content validator.
	 */
	public const VALIDATOR_OPTION = 'rankkernel_llms_validator';

	/**
	 * Transient holding the cached document.
	 */
	private const TRANSIENT = 'rankkernel_llms_md';

	/**
	 * Generator.
	 *
	 * @var LlmsGenerator
	 */
	private LlmsGenerator $generator;

	/**
	 * Settings.
	 *
	 * @var LlmsSettings
	 */
	private LlmsSettings $settings;

	/**
	 * Constructor.
	 *
	 * @param LlmsGenerator $generator Generator.
	 * @param LlmsSettings  $settings  Settings.
	 */
	public function __construct( LlmsGenerator $generator, LlmsSettings $settings ) {
		$this->generator = $generator;
		$this->settings  = $settings;
	}

	/**
	 * Register the route and hooks.
	 */
	public function register(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
		add_filter( 'query_vars', [ $this, 'addQueryVars' ] );
		add_action( 'pre_get_posts', [ $this, 'intercept' ], 1 );
	}

	/**
	 * Add the llms query var.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[] The result.
	 */
	public function addQueryVars( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Serve llms.txt when requested.
	 *
	 * @param WP_Query $query Main query.
	 */
	public function intercept( WP_Query $query ): void {
		if ( ! $query->is_main_query() ) {
			return;
		}

		if ( empty( get_query_var( self::QUERY_VAR ) ) ) {
			return;
		}

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/markdown; charset=UTF-8' );
			header( 'X-Robots-Tag: noindex, follow' );
			nocache_headers();
		}

		echo $this->content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw Markdown response body.

		$this->finishRender();
	}

	/**
	 * Build or fetch the cached llms.txt document.
	 *
	 * @return string The result.
	 */
	public function content(): string {
		$validator = (string) get_option( self::VALIDATOR_OPTION, '' );
		$cached    = get_transient( self::TRANSIENT );

		if ( is_array( $cached ) && isset( $cached['validator'], $cached['md'] ) && (string) $cached['validator'] === $validator ) {
			return (string) $cached['md'];
		}

		$markdown = $this->generator->render(
			(string) get_bloginfo( 'name' ),
			(string) $this->settings->get( 'summary', '' ),
			(string) $this->settings->get( 'content', '' )
		);

		set_transient(
			self::TRANSIENT,
			[
				'validator' => $validator,
				'md'        => $markdown,
			],
			0
		);

		return $markdown;
	}

	/**
	 * Bump the validator so the next request rebuilds the document.
	 */
	public static function invalidate(): void {
		update_option( self::VALIDATOR_OPTION, (string) time() . '-' . (string) wp_rand(), false );
	}

	/**
	 * Terminate the request unless running under tests.
	 */
	private function finishRender(): void {
		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			exit;
		}
	}
}
