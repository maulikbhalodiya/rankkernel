<?php
/**
 * REST Instant Indexing log controller.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Rest;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\InstantIndexing\LogFilters;
use RankKernel\Modules\InstantIndexing\LogQuery;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles GET /rankkernel/v1/instant-indexing/log.
 *
 * Read only. Authorization is the manage_options capability, and CSRF
 * protection is the core WordPress REST nonce, action wp_rest, which core
 * verifies itself for cookie authenticated requests before this class runs.
 * No nonce parameter is declared here on purpose: a hand rolled nonce would
 * duplicate and weaken the core check, the mistake this route avoids.
 *
 * Every row, total and count comes from LogQuery, the single shared read
 * layer. This class contains no SQL, no filtering and no counting of its
 * own, so the REST response and the admin table can never disagree.
 */
final class LogController {
	/**
	 * REST namespace.
	 */
	private const NAMESPACE = 'rankkernel/v1';

	/**
	 * Route base.
	 */
	private const ROUTE = '/instant-indexing/log';

	/**
	 * Register routes.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'getLog' ],
				'permission_callback' => [ $this, 'checkPermission' ],
				'args'                => $this->endpointArgs(),
			]
		);
	}

	/**
	 * Check permissions.
	 *
	 * @return bool|WP_Error The result.
	 */
	public function checkPermission(): bool|WP_Error {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			esc_html__( 'Sorry, you are not allowed to read the Instant Indexing log.', 'rankkernel' ),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Read one page of the log through the shared query layer.
	 *
	 * The args schema validates and sanitizes every parameter before
	 * dispatch reaches this handler, and LogQuery repeats the normalization
	 * plus the page size clamp, so an oversized page size can never reach a
	 * statement even when the handler is called outside the REST pipeline.
	 *
	 * The rows travel exactly as the shared layer returns them, including
	 * the integer row id. This reverses an earlier decision to withhold the
	 * id from this route: the AJAX refresh rebuilds each table row in the
	 * browser, so the retry control needs the id to address its row, and
	 * without it the control disappears after any filter, search or page
	 * click. The route already requires manage_options and returns only
	 * this screen own log rows, so the id is necessary for the admin UI to
	 * function and no wider exposure follows. Returning the shared shape
	 * unchanged also keeps the REST response and the admin table from
	 * disagreeing, which a second projection could reintroduce.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response The result.
	 */
	public function getLog( WP_REST_Request $request ): WP_REST_Response {
		$query = LogQuery::fromInput( $request->get_params(), LogQuery::PER_PAGE );

		return new WP_REST_Response(
			[
				'rows'          => $query->rows(),
				'page'          => $query->filters()->page(),
				'perPage'       => $query->filters()->perPage(),
				'filteredTotal' => $query->filteredTotal(),
				'total'         => $query->total(),
				'statusCounts'  => $query->statusCounts(),
				'sourceCounts'  => $query->sourceCounts(),
			],
			200
		);
	}

	/**
	 * Endpoint args for REST schema validation and sanitization.
	 *
	 * The enum values come from LogFilters, the single authority for the
	 * accepted statuses and sources, so this schema and the query
	 * normalization cannot drift. The admin page normalizes an unknown
	 * value to the unfiltered default; this schema rejects it with the 400
	 * core sends for an invalid parameter instead, because a REST caller
	 * should learn its input was wrong rather than receive silently
	 * rewritten results. The retry category stays out of the status enum
	 * for the same reason it has no tab: LogFilters::statuses() excludes
	 * it, so it is not an accepted filter value.
	 *
	 * The string args carry a custom sanitizer, so each one also declares
	 * rest_validate_request_arg to keep type and enum checks running before
	 * the sanitizer. The integer args keep the framework default, which
	 * validates the minimum and maximum bounds and never sees an unbounded
	 * page size succeed. rk_per_page is capped by LogFilters::MAX_PER_PAGE
	 * and LogQuery clamps it again.
	 *
	 * @return array<string, mixed> The result.
	 */
	private function endpointArgs(): array {
		return [
			's'           => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'rk_source'   => [
				'type'              => 'string',
				'enum'              => LogFilters::SOURCES,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'rk_status'   => [
				'type'              => 'string',
				'enum'              => LogFilters::statuses(),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'rk_paged'    => [
				'type'    => 'integer',
				'minimum' => 1,
			],
			'rk_per_page' => [
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => LogFilters::MAX_PER_PAGE,
			],
		];
	}
}
