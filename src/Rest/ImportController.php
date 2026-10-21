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

use RankKernel\Modules\Importer\BulkScope;
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
	public const PREVIEW_LIMIT_MAX = 5000;

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
	 * Valid preview kinds, same set as chunk kinds.
	 *
	 * @var string[]
	 */
	private const PREVIEW_KINDS = [ 'posts', 'terms', 'sitewide', 'redirects', 'bulk' ];

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
						'validate_callback' => 'rest_validate_request_arg',
					],
					'mode'   => [
						'required'          => false,
						'type'              => 'string',
						'enum'              => self::MODES,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'kind'   => [
						'required'          => false,
						'type'              => 'string',
						'enum'              => self::PREVIEW_KINDS,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'offset' => [
						'required'          => false,
						'type'              => 'integer',
						'minimum'           => 0,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'limit'  => [
						'required'          => false,
						'type'              => 'integer',
						'minimum'           => 1,
						'maximum'           => self::PREVIEW_LIMIT_MAX,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'q'      => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
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
					'source'   => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'kind'     => [
						'required'          => true,
						'type'              => 'string',
						'enum'              => self::KINDS,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'mode'     => [
						'required'          => false,
						'type'              => 'string',
						'enum'              => self::MODES,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'offset'   => [
						'required'          => false,
						'type'              => 'integer',
						'minimum'           => 0,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'limit'    => [
						'required'          => false,
						'type'              => 'integer',
						'minimum'           => 1,
						'maximum'           => self::RUN_LIMIT_MAX,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'excludes' => [
						'required'          => false,
						'type'              => 'array',
						'items'             => [ 'type' => 'string' ],
						'sanitize_callback' => 'rest_sanitize_request_arg',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'choices'  => [
						'required'          => false,
						'type'              => 'object',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'rule'     => [
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
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
	 * Preview one page of importable rows.
	 *
	 * Posts, terms and redirects page through offset and limit so the
	 * browser never holds an unbounded list; sitewide and bulk sets
	 * are small enough to return whole. Bulk rule rows carry their
	 * affected local object count.
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

		$params = $this->bodyParams( $request );
		$mode   = $this->modeFor( $params );
		if ( $mode instanceof WP_Error ) {
			return $mode;
		}

		$kind = isset( $params['kind'] ) ? sanitize_text_field( (string) $params['kind'] ) : 'posts';

		if ( ! in_array( $kind, self::PREVIEW_KINDS, true ) ) {
			return new WP_Error(
				'rankkernel_invalid_kind',
				__( 'Invalid preview kind.', 'rankkernel' ),
				[ 'status' => 400 ]
			);
		}

		$offset = isset( $params['offset'] ) ? max( 0, (int) $params['offset'] ) : 0;
		$limit  = isset( $params['limit'] ) ? (int) $params['limit'] : 50;
		$limit  = max( 1, min( self::PREVIEW_LIMIT_MAX, $limit ) );

		$q = isset( $params['q'] ) ? sanitize_text_field( (string) $params['q'] ) : '';
		$q = substr( $q, 0, 100 );

		$totals = $reader->detect();
		$page   = $this->previewPage( $reader, $source, $kind, $offset, $limit, $mode, $totals, $q );

		return new WP_REST_Response(
			array_merge(
				[
					'source' => $source,
					'mode'   => $mode,
					'kind'   => $kind,
					'offset' => $offset,
					'limit'  => $limit,
					'totals' => $totals,
					'q'      => $q,
				],
				$page
			),
			200
		);
	}

	/**
	 * Build one preview page for a kind.
	 *
	 * @param SourceReader         $reader  Source reader.
	 * @param string               $source  Source id.
	 * @param string               $kind    Preview kind.
	 * @param int                  $offset  Page offset.
	 * @param int                  $limit   Page size.
	 * @param string               $mode    Conflict mode.
	 * @param array<string, mixed> $totals  Detect totals.
	 * @param string               $search  Optional search string.
	 * @return array{rows: array<int, array<string, mixed>>, skipped: array<int, mixed>, hasMore: bool, kindTotal: int} The page.
	 */
	private function previewPage( SourceReader $reader, string $source, string $kind, int $offset, int $limit, string $mode, array $totals, string $search = '' ): array {
		if ( 'posts' === $kind || 'terms' === $kind ) {
			$raw   = 'posts' === $kind ? $reader->fetchPosts( $offset, $limit, $search ) : $reader->fetchTerms( $offset, $limit, $search );
			$items = [];

			foreach ( $raw as $item ) {
				$item['existing'] = 'posts' === $kind ? PayloadImporter::readPost( (int) $item['id'] ) : PayloadImporter::readTerm( (int) $item['id'] );
				$items[]          = $item;
			}

			$preview = PreviewBuilder::buildPreview(
				$source,
				'posts' === $kind ? $items : [],
				'terms' === $kind ? $items : [],
				[],
				[],
				$mode
			);

			return [
				'rows'      => self::rowArrays( $preview['rows'] ),
				'skipped'   => $preview['skipped'],
				'hasMore'   => count( $items ) >= $limit,
				'kindTotal' => (int) ( $totals[ $kind ] ?? 0 ),
			];
		}

		if ( 'redirects' === $kind ) {
			$raw    = $reader->fetchRedirectsRaw();
			$search = trim( $search );

			if ( '' !== $search ) {
				$needle = mb_strtolower( $search );
				$raw    = array_values(
					array_filter(
						$raw,
						static function ( $row ) use ( $needle ): bool {
							foreach ( $row as $value ) {
								if ( is_string( $value ) && str_contains( mb_strtolower( $value ), $needle ) ) {
									return true;
								}
							}

							return false;
						}
					)
				);
			}

			$preview = PreviewBuilder::buildPreview( $source, [], [], [], $raw, $mode );
			$rows    = self::rowArrays( $preview['rows'] );

			return [
				'rows'      => array_slice( $rows, $offset, $limit ),
				'skipped'   => $preview['skipped'],
				'hasMore'   => $offset + $limit < count( $rows ),
				'kindTotal' => count( $rows ),
			];
		}

		$preview = PreviewBuilder::buildPreview( $source, [], [], $reader->fetchSitewideRaw(), [], $mode );
		$rows    = self::rowArrays( $preview['rows'] );

		if ( 'bulk' === $kind ) {
			$rows = array_values( array_filter( $rows, static fn( $row ) => true === ( $row['bulk'] ?? false ) ) );

			return [
				'rows'      => $rows,
				'skipped'   => [],
				'hasMore'   => false,
				'kindTotal' => count( $rows ),
			];
		}

		return [
			'rows'      => $rows,
			'skipped'   => $preview['skipped'],
			'hasMore'   => false,
			'kindTotal' => count( $rows ) + count( $preview['skipped'] ),
		];
	}

	/**
	 * Map rows to arrays, enriching bulk rows with affected counts.
	 *
	 * @param array<int, object> $rows Preview rows.
	 * @return array<int, array<string, mixed>> The row arrays.
	 */
	private static function rowArrays( array $rows ): array {
		$out = [];

		foreach ( $rows as $row ) {
			$vars = get_object_vars( $row );

			if ( true === ( $vars['bulk'] ?? false ) ) {
				$fields           = is_array( $vars['fields'] ?? null ) ? $vars['fields'] : [];
				$vars['affected'] = BulkScope::count(
					(string) ( $fields['rule_kind'] ?? '' ),
					(string) ( $fields['rule_type'] ?? '' )
				);
			}

			if ( 'post' === ( $vars['kind'] ?? '' ) || 'term' === ( $vars['kind'] ?? '' ) ) {
				$vars += self::objectLabel( (string) $vars['kind'], (int) $vars['refId'] );
			}

			$out[] = $vars;
		}

		return $out;
	}

	/**
	 * Resolve the human title and edit link for a post or term row.
	 *
	 * Display only: identities, fields and conflict logic never see
	 * these values. Missing or uneditable objects fall back to an
	 * empty pair so the row keeps its Post {id} label without a link.
	 * Values travel raw in JSON and are escaped at render time.
	 *
	 * @param string $kind  Row kind, post or term.
	 * @param int    $refId Post or term id.
	 * @return array{title: string, editUrl: string} The display pair.
	 */
	private static function objectLabel( string $kind, int $refId ): array {
		if ( $refId < 1 || ! function_exists( 'current_user_can' ) ) {
			return [
				'title'   => '',
				'editUrl' => '',
			];
		}

		if ( 'term' === $kind ) {
			if ( ! function_exists( 'get_term' ) ) {
				return [
					'title'   => '',
					'editUrl' => '',
				];
			}

			$term = get_term( $refId );

			if ( ! $term instanceof \WP_Term ) {
				return [
					'title'   => '',
					'editUrl' => '',
				];
			}

			$taxonomy = function_exists( 'get_taxonomy' ) ? get_taxonomy( $term->taxonomy ) : null;
			$cap      = ( $taxonomy instanceof \WP_Taxonomy ) ? $taxonomy->cap->manage_terms : '';

			if ( '' === $cap || ! current_user_can( $cap ) ) {
				return [
					'title'   => '',
					'editUrl' => '',
				];
			}

			$link = function_exists( 'get_edit_term_link' ) ? (string) get_edit_term_link( $refId, $term->taxonomy, 'post' ) : '';

			return [
				'title'   => $term->name . ' (' . $term->taxonomy . ')',
				'editUrl' => $link,
			];
		}

		if ( ! function_exists( 'get_post' ) || ! current_user_can( 'edit_post', $refId ) ) {
			return [
				'title'   => '',
				'editUrl' => '',
			];
		}

		$post = get_post( $refId );

		if ( ! $post instanceof \WP_Post ) {
			return [
				'title'   => '',
				'editUrl' => '',
			];
		}

		$title = function_exists( 'get_the_title' ) ? (string) get_the_title( $post ) : '';
		$link  = function_exists( 'get_edit_post_link' ) ? (string) get_edit_post_link( $refId, 'raw' ) : '';

		return [
			'title'   => $title,
			'editUrl' => $link,
		];
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
		$limit  = max( 1, min( self::RUN_LIMIT_MAX, $limit ) );

		$excludes = ( isset( $params['excludes'] ) && is_array( $params['excludes'] ) ) ? array_values( array_filter( array_map( 'strval', $params['excludes'] ) ) ) : [];
		$options  = [];

		if ( isset( $params['choices'] ) && is_array( $params['choices'] ) ) {
			$choices = [];

			foreach ( $params['choices'] as $slot => $identity ) {
				if ( is_string( $identity ) ) {
					$choices[ sanitize_text_field( (string) $slot ) ] = sanitize_text_field( $identity );
				}
			}

			$options['choices'] = $choices;
		}

		if ( isset( $params['rule'] ) && is_string( $params['rule'] ) ) {
			$options['rule'] = sanitize_text_field( $params['rule'] );
		}

		$factory = $this->runnerFactory;

		try {
			$result = $factory()->run( $source, $kind, $offset, $limit, $mode, $excludes, $options );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error(
				'rankkernel_invalid_run',
				$e->getMessage(),
				[ 'status' => 400 ]
			);
		}

		return new WP_REST_Response( $result, 200 );
	}
}
