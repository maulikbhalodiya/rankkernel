<?php
/**
 * Chunked import runner, preview rows into stored data.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

use InvalidArgumentException;
use RankKernel\Modules\Redirects\RedirectRepository;
use RankKernel\Settings\SettingsStore;

/**
 * Execute one import chunk per request.
 *
 * Every chunk re-derives its rows server side through PreviewBuilder
 * over freshly fetched source data, so the run phase applies exactly
 * what the dry run previewed and the client never supplies row data.
 */
final class ImportRunner {
	/**
	 * Chunk kinds the runner accepts.
	 *
	 * @var string[]
	 */
	private const KINDS = [ 'posts', 'terms', 'sitewide', 'redirects', 'bulk' ];

	/**
	 * Maximum ids one bulk enumeration pulls; larger sets import in slices.
	 */
	private const BULK_IDS_MAX = 10000;

	/**
	 * Source readers by id.
	 *
	 * @var array<string, SourceReader>
	 */
	private array $readers;

	/**
	 * Redirect writer, same save path as the UI.
	 *
	 * @var RedirectRepository
	 */
	private RedirectRepository $redirects;

	/**
	 * Sitewide settings writer, same save path as the UI.
	 *
	 * @var SettingsStore
	 */
	private SettingsStore $settings;

	/**
	 * Bulk id enumerator, injectable for tests.
	 *
	 * @var callable|null
	 */
	private $bulkEnumerator;

	/**
	 * Constructor.
	 *
	 * @param array<string, SourceReader> $readers        Source readers by id.
	 * @param RedirectRepository|null     $redirects      Redirect writer.
	 * @param SettingsStore|null          $settings       Sitewide writer.
	 * @param callable|null               $bulkEnumerator Bulk id lister, fn( string $kind, string $type ): int[].
	 */
	public function __construct( array $readers = [], ?RedirectRepository $redirects = null, ?SettingsStore $settings = null, ?callable $bulkEnumerator = null ) {
		$this->readers        = $readers;
		$this->redirects      = $redirects ?? new RedirectRepository();
		$this->settings       = $settings ?? new SettingsStore();
		$this->bulkEnumerator = $bulkEnumerator;
	}

	/**
	 * Run one chunk.
	 *
	 * @param string               $source   Source id.
	 * @param string               $kind     Chunk kind, posts, terms, sitewide, redirects or bulk.
	 * @param int                  $offset   Chunk offset.
	 * @param int                  $limit    Chunk size.
	 * @param string               $mode     Conflict mode, skip or overwrite.
	 * @param array<int|string>    $excludes Excluded ref ids, redirect source urls for redirects.
	 * @param array<string, mixed> $options  Sitewide choices or the bulk rule.
	 * @return array{applied: int, skipped: int, failed: int, done: bool, total: int} The result.
	 * @throws InvalidArgumentException When source, kind, mode or limit is invalid.
	 */
	public function run( string $source, string $kind, int $offset, int $limit, string $mode, array $excludes = [], array $options = [] ): array {
		if ( ! isset( $this->readers[ $source ] ) ) {
			throw new InvalidArgumentException( 'Unknown import source.' );
		}

		if ( ! in_array( $kind, self::KINDS, true ) ) {
			throw new InvalidArgumentException( 'Unknown chunk kind.' );
		}

		if ( 'skip' !== $mode && 'overwrite' !== $mode ) {
			throw new InvalidArgumentException( 'Unknown conflict mode.' );
		}

		if ( $limit < 1 ) {
			throw new InvalidArgumentException( 'Chunk limit must be positive.' );
		}

		$reader = $this->readers[ $source ];

		if ( 'posts' === $kind ) {
			return $this->runPosts( $reader, $source, $offset, $limit, $mode, $excludes );
		}

		if ( 'terms' === $kind ) {
			return $this->runTerms( $reader, $source, $offset, $limit, $mode, $excludes );
		}

		if ( 'sitewide' === $kind ) {
			return $this->runSitewide( $reader, $source, $mode, $options );
		}

		if ( 'redirects' === $kind ) {
			return $this->runRedirects( $reader, $source, $mode, $excludes );
		}

		return $this->runBulk( $source, $offset, $limit, $mode, $options );
	}

	/**
	 * Run one posts chunk.
	 *
	 * @param SourceReader      $reader   Source reader.
	 * @param string            $source   Source id.
	 * @param int               $offset   Chunk offset.
	 * @param int               $limit    Chunk size.
	 * @param string            $mode     Conflict mode.
	 * @param array<int|string> $excludes Excluded post ids.
	 * @return array{applied: int, skipped: int, failed: int, done: bool, total: int} The result.
	 */
	private function runPosts( SourceReader $reader, string $source, int $offset, int $limit, string $mode, array $excludes ): array {
		$items        = $reader->fetchPosts( $offset, $limit );
		$withExisting = [];

		foreach ( $items as $item ) {
			$item['existing'] = PayloadImporter::readPost( (int) $item['id'] );
			$withExisting[]   = $item;
		}

		$preview = PreviewBuilder::buildPreview( $source, $withExisting, [], [], [], $mode );
		$apply   = [];

		foreach ( $preview['rows'] as $row ) {
			if ( in_array( $row->refId, $excludes, true ) ) {
				continue;
			}

			$apply[] = [
				'post_id' => $row->refId,
				'fields'  => $row->fields,
			];
		}

		$counts             = PayloadImporter::applyRows( $apply, $mode );
		$counts['skipped'] += count( $preview['rows'] ) - count( $apply ) + count( $preview['skipped'] );
		$counts['done']     = count( $items ) < $limit;
		$counts['total']    = $reader->detect()['posts'];

		return $counts;
	}

	/**
	 * Run one terms chunk.
	 *
	 * @param SourceReader      $reader   Source reader.
	 * @param string            $source   Source id.
	 * @param int               $offset   Chunk offset.
	 * @param int               $limit    Chunk size.
	 * @param string            $mode     Conflict mode.
	 * @param array<int|string> $excludes Excluded term ids.
	 * @return array{applied: int, skipped: int, failed: int, done: bool, total: int} The result.
	 */
	private function runTerms( SourceReader $reader, string $source, int $offset, int $limit, string $mode, array $excludes ): array {
		$items        = $reader->fetchTerms( $offset, $limit );
		$withExisting = [];

		foreach ( $items as $item ) {
			$item['existing'] = PayloadImporter::readTerm( (int) $item['id'] );
			$withExisting[]   = $item;
		}

		$preview = PreviewBuilder::buildPreview( $source, [], $withExisting, [], [], $mode );
		$apply   = [];

		foreach ( $preview['rows'] as $row ) {
			if ( in_array( $row->refId, $excludes, true ) ) {
				continue;
			}

			$apply[] = [
				'term_id' => $row->refId,
				'fields'  => $row->fields,
			];
		}

		$counts             = PayloadImporter::applyTermRows( $apply, $mode );
		$counts['skipped'] += count( $preview['rows'] ) - count( $apply ) + count( $preview['skipped'] );
		$counts['done']     = count( $items ) < $limit;
		$counts['total']    = $reader->detect()['terms'];

		return $counts;
	}

	/**
	 * Apply the chosen sitewide candidates.
	 *
	 * @param SourceReader         $reader  Source reader.
	 * @param string               $source  Source id.
	 * @param string               $mode    Conflict mode.
	 * @param array<string, mixed> $options Sitewide choices by slot.
	 * @return array{applied: int, skipped: int, failed: int, done: bool, total: int} The result.
	 * @throws InvalidArgumentException When a slot choice is unknown.
	 */
	private function runSitewide( SourceReader $reader, string $source, string $mode, array $options ): array {
		$preview = PreviewBuilder::buildPreview( $source, [], [], $reader->fetchSitewideRaw(), [], $mode );
		$choices = $options;

		unset( $choices['rule'] );
		$counts = [
			'applied' => 0,
			'skipped' => 0,
			'failed'  => 0,
			'done'    => true,
			'total'   => 1,
		];

		$bySlot = [];

		foreach ( $preview['rows'] as $row ) {
			if ( 'sitewide' !== $row->kind || $row->bulk || '' === $row->slot ) {
				continue;
			}

			$bySlot[ $row->slot ][] = $row;
		}

		foreach ( $bySlot as $slot => $candidates ) {
			$names = [];

			foreach ( $candidates as $candidate ) {
				$sourceName = is_string( $candidate->fields['candidate_source'] ?? null ) ? $candidate->fields['candidate_source'] : '';

				if ( '' !== $sourceName ) {
					$names[] = $sourceName;
				}
			}

			if ( isset( $choices[ $slot ] ) && ! in_array( (string) $choices[ $slot ], $names, true ) ) {
				throw new InvalidArgumentException( 'Unknown sitewide choice.' );
			}
		}

		foreach ( array_keys( $choices ) as $slot ) {
			if ( ! isset( $bySlot[ (string) $slot ] ) ) {
				throw new InvalidArgumentException( 'Unknown sitewide choice.' );
			}
		}

		foreach ( $bySlot as $slot => $candidates ) {
			if ( ! isset( $choices[ $slot ] ) ) {
				$counts['skipped'] += count( $candidates );

				continue;
			}

			$wanted = (string) $choices[ $slot ];
			$picked = null;

			foreach ( $candidates as $candidate ) {
				$sourceName = is_string( $candidate->fields['candidate_source'] ?? null ) ? $candidate->fields['candidate_source'] : '';

				if ( $sourceName === $wanted ) {
					$picked = $candidate;

					break;
				}
			}

			if ( null === $picked ) {
				continue;
			}

			$value = $picked->fields['value'] ?? '';

			if ( ! is_string( $value ) || '' === $value ) {
				++$counts['skipped'];

				continue;
			}

			if ( $this->settings->set( [ $slot => $value ] ) ) {
				++$counts['applied'];
			} else {
				++$counts['failed'];
			}

			$counts['skipped'] += count( $candidates ) - 1;
		}

		$counts['skipped'] += count( $preview['skipped'] );

		return $counts;
	}

	/**
	 * Insert redirect rows through the repository.
	 *
	 * @param SourceReader      $reader   Source reader.
	 * @param string            $source   Source id.
	 * @param string            $mode     Conflict mode.
	 * @param array<int|string> $excludes Excluded redirect source urls.
	 * @return array{applied: int, skipped: int, failed: int, done: bool, total: int} The result.
	 */
	private function runRedirects( SourceReader $reader, string $source, string $mode, array $excludes ): array {
		$preview = PreviewBuilder::buildPreview( $source, [], [], [], $reader->fetchRedirectsRaw(), $mode );
		$counts  = [
			'applied' => 0,
			'skipped' => count( $preview['skipped'] ),
			'failed'  => 0,
			'done'    => true,
			'total'   => $reader->detect()['redirects'],
		];

		foreach ( $preview['rows'] as $row ) {
			if ( 'redirect' !== $row->kind ) {
				continue;
			}

			$from = (string) ( $row->fields['source'] ?? '' );

			if ( in_array( $from, $excludes, true ) ) {
				++$counts['skipped'];

				continue;
			}

			$id = $this->redirects->insert(
				[
					'source'     => $from,
					'target'     => (string) ( $row->fields['target'] ?? '' ),
					'code'       => (int) ( $row->fields['code'] ?? 301 ),
					'match_type' => (string) ( $row->fields['match_type'] ?? 'exact' ),
					'is_active'  => 1,
				]
			);

			if ( $id > 0 ) {
				++$counts['applied'];
			} else {
				++$counts['skipped'];
			}
		}

		return $counts;
	}

	/**
	 * Stamp one bulk noindex rule over a local id slice.
	 *
	 * @param string               $source  Source id.
	 * @param int                  $offset  Chunk offset.
	 * @param int                  $limit   Chunk size.
	 * @param string               $mode    Conflict mode.
	 * @param array<string, mixed> $options Bulk rule wrapper.
	 * @return array{applied: int, skipped: int, failed: int, done: bool, total: int} The result.
	 * @throws InvalidArgumentException When the bulk rule is unknown.
	 */
	private function runBulk( string $source, int $offset, int $limit, string $mode, array $options ): array {
		$rule = is_array( $options['rule'] ?? null ) ? $options['rule'] : [];

		if ( ( $rule['kind'] ?? '' ) !== 'post' && ( $rule['kind'] ?? '' ) !== 'term' ) {
			throw new InvalidArgumentException( 'Unknown bulk rule.' );
		}

		$type = (string) ( $rule['type'] ?? '' );

		if ( '' === $type ) {
			throw new InvalidArgumentException( 'Unknown bulk rule.' );
		}

		$ids   = $this->bulkIds( (string) $rule['kind'], $type );
		$slice = array_slice( $ids, $offset, $limit );
		$apply = [];

		foreach ( $slice as $id ) {
			$apply[] = 'post' === $rule['kind']
				? [
					'post_id' => $id,
					'fields'  => [ 'robots' => [ 'index' => false ] ],
				]
				: [
					'term_id' => $id,
					'fields'  => [ 'robots' => [ 'index' => false ] ],
				];
		}

		$counts = 'post' === $rule['kind']
			? PayloadImporter::applyRows( $apply, $mode )
			: PayloadImporter::applyTermRows( $apply, $mode );

		$counts['done']  = $offset + $limit >= count( $ids );
		$counts['total'] = count( $ids );

		return $counts;
	}

	/**
	 * List local ids of one post type or taxonomy.
	 *
	 * @param string $kind Post or term.
	 * @param string $type Post type or taxonomy.
	 * @return int[] The ids.
	 */
	private function bulkIds( string $kind, string $type ): array {
		if ( null !== $this->bulkEnumerator ) {
			$ids = call_user_func( $this->bulkEnumerator, $kind, $type );

			return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
		}

		if ( ! function_exists( 'get_posts' ) ) {
			return [];
		}

		if ( 'term' === $kind ) {
			if ( ! function_exists( 'get_terms' ) ) {
				return [];
			}

			return array_map(
				'intval',
				get_terms(
					[
						'taxonomy'   => $type,
						'hide_empty' => false,
						'fields'     => 'ids',
						'number'     => self::BULK_IDS_MAX,
					]
				)
			);
		}

		return array_map(
			'intval',
			get_posts(
				[
					'post_type'      => $type,
					'posts_per_page' => self::BULK_IDS_MAX,
					'offset'         => 0,
					'fields'         => 'ids',
				]
			)
		);
	}
}
