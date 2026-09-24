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

use RankKernel\Modules\Metadata\MetaPayload;
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
		// The endpoint args schema validates and sanitizes every param before
		// WP_REST_Server::dispatch() calls this handler. get_params() returns
		// those validated, sanitized values for JSON and form encoded bodies
		// alike, so the store never receives raw input.
		$params = $request->get_params();

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
	 * A custom sanitize_callback replaces the framework default, so every arg
	 * that carries one also declares rest_validate_request_arg: type and enum
	 * checks keep running before the sanitizer.
	 *
	 * Dynamic schema_default_{post_type} keys stay out of this static schema on
	 * purpose. SettingsStore::set() validates them against SchemaTypes::SUPPORTED
	 * before they persist.
	 *
	 * @return array<string, mixed>
	 */
	private function getEndpointArgs(): array {
		$args = [
			'social_default_image'    => [
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'social_default_image_id' => [
				'type'              => 'integer',
				'sanitize_callback' => static function ( mixed $value ): int {
					return is_numeric( $value ) && (int) $value > 0 ? absint( $value ) : 0;
				},
				'validate_callback' => 'rest_validate_request_arg',
			],
			'twitter_site'            => [
				'type'              => 'string',
				'sanitize_callback' => [ MetaPayload::class, 'sanitizeTwitterHandle' ],
				'validate_callback' => 'rest_validate_request_arg',
			],
			'site_represents'         => [
				'type'              => 'string',
				'enum'              => [ 'organization', 'person' ],
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'org_name'                => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'org_logo'                => [
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'org_sameas'              => [
				'type'              => 'array',
				'items'             => [
					'type'              => 'string',
					'format'            => 'uri',
					'sanitize_callback' => 'esc_url_raw',
				],
				'validate_callback' => 'rest_validate_request_arg',
			],
			'website_search_action'   => [ 'type' => 'boolean' ],
			'schema_breadcrumbs'      => [ 'type' => 'boolean' ],
			'schema_author'           => [ 'type' => 'boolean' ],
			'purge_on_uninstall'      => [ 'type' => [ 'boolean', 'null' ] ],
		];

		$text_keys = [
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

		foreach ( $text_keys as $key ) {
			$args[ $key ] = [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			];
		}

		return $args;
	}
}
