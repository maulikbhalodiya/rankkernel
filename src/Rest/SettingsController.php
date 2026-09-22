<?php
/**
 * REST settings controller.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Rest;

defined( 'ABSPATH' ) || exit;

use RankKernel\Settings\SettingsStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles GET/POST /rankkernel/v1/settings.
 */
final class SettingsController {
	/**
	 * REST namespace.
	 */
	private const NAMESPACE = 'rankkernel/v1';

	/**
	 * Route base.
	 */
	private const ROUTE = '/settings';

	/**
	 * Settings store.
	 *
	 * @var SettingsStore
	 */
	private SettingsStore $store;

	/**
	 * Constructor.
	 *
	 * @param SettingsStore $store Store.
	 */
	public function __construct( SettingsStore $store ) {
		$this->store = $store;
	}

	/**
	 * Register routes.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'getSettings' ],
					'permission_callback' => [ $this, 'checkPermission' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'updateSettings' ],
					'permission_callback' => [ $this, 'checkPermission' ],
					'args'                => $this->getEndpointArgs(),
				],
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
			esc_html__( 'Sorry, you are not allowed to manage RankKernel settings.', 'rankkernel' ),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Get all settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response The result.
	 */
	public function getSettings( WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- unused parameter required by the REST callback signature.
		return new WP_REST_Response( $this->store->all(), 200 );
	}

	/**
	 * Update settings partially.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error The result.
	 */
	public function updateSettings( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		if ( ! is_array( $params ) || [] === $params ) {
			return new WP_Error(
				'rankkernel_invalid_params',
				esc_html__( 'No settings provided.', 'rankkernel' ),
				[ 'status' => 400 ]
			);
		}

		// Strict boolean handling for purge_on_uninstall: accept real bool/null,
		// normalize scalar via filter_var, 400 on invalid.
		if ( array_key_exists( 'purge_on_uninstall', $params ) && null !== $params['purge_on_uninstall'] ) {
			$raw = $params['purge_on_uninstall'];

			if ( ! is_bool( $raw ) ) {
				$normalized = filter_var( $raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

				if ( null === $normalized ) {
					return new WP_Error(
						'rest_invalid_param',
						esc_html__( 'Invalid value for purge_on_uninstall: must be boolean.', 'rankkernel' ),
						[ 'status' => 400 ]
					);
				}

				$params['purge_on_uninstall'] = $normalized;
			}
		}

		$result = $this->store->set( $params );

		if ( ! $result ) {
			return new WP_Error(
				'rankkernel_no_update',
				esc_html__( 'No valid settings to update.', 'rankkernel' ),
				[ 'status' => 400 ]
			);
		}

		return new WP_REST_Response( $this->store->all(), 200 );
	}

	/**
	 * Endpoint args for REST schema validation and sanitization.
	 *
	 * @return array<string, mixed>
	 */
	private function getEndpointArgs(): array {
		$args = [
			'site_represents'       => [
				'type'              => 'string',
				'enum'              => [ 'organization', 'person' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'org_name'              => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'org_logo'              => [
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			],
			'org_sameas'            => [
				'type'  => 'array',
				'items' => [
					'type'              => 'string',
					'sanitize_callback' => 'esc_url_raw',
				],
			],
			'website_search_action' => [ 'type' => 'boolean' ],
			'schema_breadcrumbs'    => [ 'type' => 'boolean' ],
			'schema_author'         => [ 'type' => 'boolean' ],
			'purge_on_uninstall'    => [ 'type' => [ 'boolean', 'null' ] ],
		];

		$textKeys = [
			'title_template',
			'description_template',
			'separator',
			'social_facebook',
			'social_twitter',
			'social_instagram',
			'social_linkedin',
			'social_youtube',
			'social_pinterest',
			'webmaster_google',
			'webmaster_bing',
			'webmaster_yandex',
			'webmaster_baidu',
			'webmaster_pinterest',
		];

		foreach ( $textKeys as $key ) {
			$args[ $key ] = [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			];
		}

		return $args;
	}
}
