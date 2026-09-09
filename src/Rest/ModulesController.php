<?php
/**
 * REST modules controller.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Rest;

use RankKernel\Modules\ModuleRegistry;
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
     * Known optional module ids.
     *
     * Delegates to ModuleRegistry, single source of truth.
     *
     * @var string[]
     */
    public const KNOWN_MODULES = [
        'metadata',
        'sitemaps',
        'schema',
        'breadcrumbs',
        'importer',
        'redirects',
        '404',
        'instant-indexing',
        'robots',
        'image-seo',
        'gutenberg',
        'ai',
        'headless',
    ];

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
                    'id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => [ $this, 'validateModuleId' ],
                    ],
                ],
            ]
        );
    }

    /**
     * Check permissions.
     *
     * @return bool|WP_Error
     */
    public function checkPermission(): bool|WP_Error {
        if (current_user_can('manage_options')) {
            return true;
        }

        return new WP_Error(
            'rest_forbidden',
            esc_html__('Sorry, you are not allowed to manage RankKernel modules.', 'rankkernel'),
            [ 'status' => 403 ]
        );
    }

    /**
     * Validate module id.
     *
     * @param string $value Module id.
     * @return bool|WP_Error
     */
    public function validateModuleId( $value ): bool|WP_Error {
        if (ModuleRegistry::has((string) $value)) {
            return true;
        }

        return new WP_Error(
            'rankkernel_invalid_module',
            esc_html__('Unknown module id.', 'rankkernel'),
            [ 'status' => 400 ]
        );
    }

    /**
     * Toggle a module on/off.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    public function toggleModule( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $moduleId = sanitize_text_field((string) $request->get_param('id'));

        if (! ModuleRegistry::has($moduleId)) {
            return new WP_Error(
                'rankkernel_invalid_module',
                esc_html__('Unknown module id.', 'rankkernel'),
                [ 'status' => 400 ]
            );
        }

        $params = $request->get_json_params();
        if (! is_array($params)) {
            $params = $request->get_params();
        }

        if (! is_array($params) || ! array_key_exists('enabled', $params)) {
            return new WP_Error(
                'rankkernel_missing_enabled',
                esc_html__('Missing required field: enabled.', 'rankkernel'),
                [ 'status' => 400 ]
            );
        }

        $rawEnabled = $params['enabled'];

        if (is_bool($rawEnabled)) {
            $enabled = $rawEnabled;
        } else {
            $normalized = filter_var($rawEnabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if (null === $normalized) {
                return new WP_Error(
                    'rest_invalid_param',
                    esc_html__('Invalid value for enabled: must be boolean.', 'rankkernel'),
                    [ 'status' => 400 ]
                );
            }

            $enabled = $normalized;
        }

        $current = get_option(self::OPTION, []);
        if (! is_array($current)) {
            $current = [];
        }

        $current = array_map('strval', $current);

        if ($enabled) {
            if (! in_array($moduleId, $current, true)) {
                $current[] = $moduleId;
            }
        } else {
            $current = array_values(array_filter($current, static fn( string $id ): bool => $id !== $moduleId));
        }

        update_option(self::OPTION, $current);

        // Rewrite-based modules (sitemaps) register or drop rules depending
        // on this list, so the cached rules must regenerate.
        flush_rewrite_rules(false);

        return new WP_REST_Response(
            [
                'modules' => $current,
                'message' => esc_html__(
                    'Module status updated. Changes take effect on the next request.',
                    'rankkernel'
                ),
            ],
            200
        );
    }
}
