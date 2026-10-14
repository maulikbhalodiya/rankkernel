<?php
/**
 * Importer REST controller.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Rest;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Importer\ImportRunner;
use RankKernel\Modules\Importer\PayloadImporter;
use RankKernel\Modules\Importer\PreviewBuilder;
use RankKernel\Modules\Importer\SourceReader;
use RankKernel\Modules\Redirects\RedirectRepository;
use RankKernel\Settings\SettingsStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles POST /rankkernel/v1/import/{source}/preview and /run.
 *
 * Preview is display only: it builds rows for the first chunk so the
 * screen can show what would change. The run endpoint re-derives rows
 * server side per chunk, so no client supplied row data is trusted.
 */
final class ImportController {
	/**
	 * REST namespace.
	 */
	private const NAMESPACE = 'rankkernel/v1';

	/**
	 * Preview item cap per kind.
	 */
	public const PREVIEW_LIMIT = 100;

	/**
	 * Run chunk size cap.
	 */
	public const RUN_LIMIT_MAX = 200;

	/**
	 * Valid chunk kinds.
	 *
	 * @var string[]
	 */
	private const KINDS = [ 'posts', 'terms', 'sitewide', 'redirects', 'bulk' ];

	/**
	 * Valid conflict modes.
	 *
	 * @var string[]
	 */
	private const MODES = [ 'skip', 'overwrite' ];

	/**
	 * Source readers by id.
	 *
	 * @var array<string, SourceReader>
	 */
	private array $readers;

	/**
	 * Builds the chunk runner.
	 *
	 * @var callable
	 */
	private $runnerFactory;

	/**
	 * Build the controller.
	 *
	 * @param array<string, SourceReader> $readers       Source readers by id.
	 * @param callable|null               $runnerFactory Builds the runner.
	 */
	public function __construct( array $readers, ?callable $runnerFactory = null ) {
		$this->readers       = $readers;
		$this->runnerFactory = $runnerFactory ?? static function (): ImportRunner {
			return new ImportRunner( [], new RedirectRepository(), new SettingsStore() );
		};
	}

	/**
	 * Register routes.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/import/(?P<source>[a-z-]+)/preview',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'preview' ],
				'permission_callback' => [ $this, 'checkPermission' ],
				'args'                => [
					'source' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'mode'   => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
		register_rest_route(
			self::NAMESPACE,
			'/import/(?P<source>[a-z-]+)/run',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'runChunk' ],
				'permission_callback' => [ $this, 'checkPermission' ],
				'args'                => [
					'source' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'kind'   => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'mode'   => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
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
			__( 'Sorry, you are not allowed to run RankKernel imports.', 'rankkernel' ),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Read the body params.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed> The params.
	 */
	private function bodyParams( WP_REST_Request $request ): array {
		$params = (array) $request->get_json_params();
		if ( [] === $params ) {
			$params = (array) $request->get_params();
		}

		return $params;
	}

	/**
	 * Resolve the reader or return an error.
	 *
	 * @param string $source Source id.
	 * @return SourceReader|WP_Error The reader or error.
	 */
	private function readerFor( string $source ): SourceReader|WP_Error {
		if ( ! isset( $this->readers[ $source ] ) ) {
			return new WP_Error(
				'rankkernel_unknown_source',
				__( 'Unknown import source.', 'rankkernel' ),
				[ 'status' => 400 ]
			);
		}

		return $this->readers[ $source ];
	}

	/**
	 * Resolve the conflict mode or return an error.
	 *
	 * @param array<string, mixed> $params Body params.
	 * @return string|WP_Error The mode or error.
	 */
	private function modeFor( array $params ): string|WP_Error {
		$mode = isset( $params['mode'] ) ? sanitize_text_field( (string) $params['mode'] ) : 'skip';

		if ( ! in_array( $mode, self::MODES, true ) ) {
			return new WP_Error(
				'rankkernel_invalid_mode',
				__( 'Invalid conflict mode: must be skip or overwrite.', 'rankkernel' ),
				[ 'status' => 400 ]
			);
		}

		return $mode;
	}

	/**
	 * Preview the first chunk of importable rows.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error The result.
	 */
	public function preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$source = sanitize_text_field( (string) $request->get_param( 'source' ) );
		$reader = $this->readerFor( $source );
		if ( $reader instanceof WP_Error ) {
			return $reader;
		}

		$mode = $this->modeFor( $this->bodyParams( $request ) );
		if ( $mode instanceof WP_Error ) {
			return $mode;
		}

		$totals = $reader->detect();

		$posts = [];
		foreach ( $reader->fetchPosts( 0, self::PREVIEW_LIMIT ) as $item ) {
			$item['existing'] = PayloadImporter::readPost( $item['id'] );
			$posts[]          = $item;
		}

		$terms = [];
		foreach ( $reader->fetchTerms( 0, self::PREVIEW_LIMIT ) as $item ) {
			$item['existing'] = PayloadImporter::readTerm( $item['id'] );
			$terms[]          = $item;
		}

		$preview = PreviewBuilder::buildPreview(
			$source,
			$posts,
			$terms,
			$reader->fetchSitewideRaw(),
			$reader->fetchRedirectsRaw(),
			$mode
		);

		$rows = [];
		foreach ( $preview['rows'] as $row ) {
			$rows[] = get_object_vars( $row );
		}

		return new WP_REST_Response(
			[
				'source'  => $source,
				'mode'    => $mode,
				'totals'  => $totals,
				'rows'    => $rows,
				'skipped' => $preview['skipped'],
			],
			200
		);
	}

	/**
	 * Run one import chunk.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error The result.
	 */
	public function runChunk( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$source = sanitize_text_field( (string) $request->get_param( 'source' ) );
		$reader = $this->readerFor( $source );
		if ( $reader instanceof WP_Error ) {
			return $reader;
		}

		$params = $this->bodyParams( $request );
		$mode   = $this->modeFor( $params );
		if ( $mode instanceof WP_Error ) {
			return $mode;
		}

		$kind = isset( $params['kind'] ) ? sanitize_text_field( (string) $params['kind'] ) : sanitize_text_field( (string) $request->get_param( 'kind' ) );
		if ( ! in_array( $kind, self::KINDS, true ) ) {
			return new WP_Error(
				'rankkernel_invalid_kind',
				__( 'Invalid chunk kind.', 'rankkernel' ),
				[ 'status' => 400 ]
			);
		}

		$offset = isset( $params['offset'] ) ? max( 0, (int) $params['offset'] ) : 0;
		$limit  = isset( $params['limit'] ) ? (int) $params['limit'] : 50;
		$limit  = isset( $params['limit'] ) ? (int) $params['limit'] : 50;
		$limit  = max( 1, min( self::RUN_LIMIT_MAX, $limit ) );

		$excludes = ( isset( $params['excludes'] ) && is_array( $params['excludes'] ) ) ? array_values( array_filter( array_map( 'strval', $params['excludes'] ) ) ) : [];
		$options  = [];
		foreach ( [ 'choices', 'rule' ] as $key ) {
			if ( isset( $params[ $key ] ) && is_array( $params[ $key ] ) ) {
				$options[ $key ] = $params[ $key ];
			}
		}

		$factory = $this->runnerFactory;
		$result  = $factory()->run( $source, $kind, $offset, $limit, $mode, $excludes, $options );

		return new WP_REST_Response( $result, 200 );
	}
}
