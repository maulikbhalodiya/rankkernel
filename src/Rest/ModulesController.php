<?php
/**
 * REST modules controller.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Rest;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\ModuleRegistry;
use RankKernel\Modules\Sitemaps\SitemapCache;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles POST /rankkernel/v1/modules/{id}.
 */
final class ModulesController {
	/**
	 * REST namespace.
	 */
	private const NAMESPACE = 'rankkernel/v1';

	/**
	 * Option name for enabled modules map.
	 */
	private const OPTION = 'rankkernel_modules';

	/**
	 * Register routes.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/modules/(?P<id>[a-z0-9-]+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'toggleModule' ],
				'permission_callback' => [ $this, 'checkPermission' ],
				'args'                => [
					'id'      => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ $this, 'validateModuleId' ],
					],
					'enabled' => [
						'required'          => true,
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_request_arg',
						'validate_callback' => 'rest_validate_request_arg',
					],
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
			__( 'Sorry, you are not allowed to manage RankKernel modules.', 'rankkernel' ),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Validate module id.
	 *
	 * @param string $value Module id.
	 * @return bool|WP_Error The result.
	 */
	public function validateModuleId( $value ): bool|WP_Error {
		if ( ModuleRegistry::has( (string) $value ) ) {
			return true;
		}

		return new WP_Error(
			'rankkernel_invalid_module',
			__( 'Unknown module id.', 'rankkernel' ),
			[ 'status' => 400 ]
		);
	}

	/**
	 * Toggle a module on/off.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error The result.
	 */
	public function toggleModule( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$moduleId = sanitize_text_field( (string) $request->get_param( 'id' ) );

		if ( ! ModuleRegistry::has( $moduleId ) ) {
			return new WP_Error(
				'rankkernel_invalid_module',
				__( 'Unknown module id.', 'rankkernel' ),
				[ 'status' => 400 ]
			);
		}

		if ( ModuleRegistry::isPlanned( $moduleId ) ) {
			return new WP_Error(
				'rankkernel_module_planned',
				__( 'This module is planned and cannot be enabled yet.', 'rankkernel' ),
				[ 'status' => 400 ]
			);
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		if ( ! is_array( $params ) || ! array_key_exists( 'enabled', $params ) ) {
			return new WP_Error(
				'rankkernel_missing_enabled',
				__( 'Missing required field: enabled.', 'rankkernel' ),
				[ 'status' => 400 ]
			);
		}

		$rawEnabled = $params['enabled'];

		if ( is_bool( $rawEnabled ) ) {
			$enabled = $rawEnabled;
		} else {
			$normalized = filter_var( $rawEnabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

			if ( null === $normalized ) {
				return new WP_Error(
					'rest_invalid_param',
					__( 'Invalid value for enabled: must be boolean.', 'rankkernel' ),
					[ 'status' => 400 ]
				);
			}

			$enabled = $normalized;
		}

		$current = ModuleEnableMap::normalizeList( get_option( self::OPTION, [] ) );
		$updated = $current;

		if ( $enabled ) {
			if ( ! in_array( $moduleId, $updated, true ) ) {
				$updated[] = $moduleId;
			}
		} else {
			$updated = array_values( array_filter( $updated, static fn( string $id ): bool => $id !== $moduleId ) );
		}

		// update_option() returns false both when the list is unchanged and
		// when the write fails, so an unchanged toggle is never written and
		// still counts as a success. A changed write that returns false is
		// reported instead of claiming the toggle persisted and flushing
		// rewrite rules for a state that was not stored.
		if ( $updated !== $current && ! update_option( self::OPTION, $updated ) ) {
			return new WP_Error(
				'rankkernel_storage_failed',
				__( 'The module status could not be saved.', 'rankkernel' ),
				[ 'status' => 500 ]
			);
		}

		if ( $updated === $current ) {
			return new WP_REST_Response(
				[
					'modules' => $updated,
					'message' => __( 'Module status updated. Changes take effect on the next request.', 'rankkernel' ),
				],
				200
			);
		}

		// Rewrite-based modules (sitemaps) register or drop rules depending on
		// the saved module list. Flushing in this request still uses the
		// in-request enable map and already-registered hooks, which can persist
		// rules for the old state. Invalidating the cached rules instead makes
		// the next request rebuild them from the newly saved module state.
		$rewriteInvalidated = true;

		if ( 'sitemaps' === $moduleId ) {
			// delete_option() returns false both when the option is absent and
			// when deletion fails. Absence is fine because the next request
			// regenerates the rules. A still-present option means invalidation
			// failed and must not be reported as fully successful.
			$deleted = delete_option( 'rewrite_rules' );

			if ( false === $deleted && ! empty( get_option( 'rewrite_rules' ) ) ) {
				$rewriteInvalidated = false;
			}
		} else {
			flush_rewrite_rules( false );
		}

		// The sitemap cache outlives the toggle: its invalidation hooks only
		// run while the module is on, so a toggle that skips this leaves the
		// pre-toggle XML served indefinitely.
		if ( 'sitemaps' === $moduleId ) {
			SitemapCache::invalidateAll();
		}

		$data = [
			'modules' => $updated,
			'message' => __( 'Module status updated. Changes take effect on the next request.', 'rankkernel' ),
		];

		if ( 'sitemaps' === $moduleId ) {
			$data['rewrite_rules_invalidated'] = $rewriteInvalidated;

			if ( ! $rewriteInvalidated ) {
				$data['message'] = __( 'Module status updated, but cached rewrite rules could not be refreshed. Save permalinks to complete the change.', 'rankkernel' );
			}
		}

		return new WP_REST_Response( $data, 200 );
	}
}
