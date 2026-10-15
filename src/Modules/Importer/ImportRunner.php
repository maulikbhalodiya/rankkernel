<?php
/**
 * Chunked import runner, preview rows into stored data.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

defined( 'ABSPATH' ) || exit;

use InvalidArgumentException;
use RankKernel\Modules\Redirects\Normalizer;
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
	 * @param string[]             $excludes Excluded row identities, post:{id}, term:{taxonomy}:{id}, redirect:{match}:{source}.
	 * @param array<string, mixed> $options  Nested choices map for sitewide, rule identity string for bulk.
	 * @return array{applied: int, skipped: int, failed: int, done: bool, total: int} The result.
	 * @throws InvalidArgumentException When source, kind, mode, limit, exclusion, choice or rule is invalid.
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

		return $this->runBulk( $reader, $source, $offset, $limit, $mode, $options );
	}

	/**
	 * Validate exclusion identities for one chunk kind.
	 *
	 * Posts and terms arrive in slices, so a chunk can only check the
	 * identity shape: exact matching happens per row below, and an
	 * identity from another slice simply matches nothing here. Redirects
	 * re-derive their full set every call, so they validate strictly at
	 * the call site instead of using this helper.
	 *
	 * @param array<int|string> $excludes Raw exclusion values.
	 * @param string            $kind     Chunk kind, post or term.
	 * @return array<string, true> Identity lookup set.
	 * @throws InvalidArgumentException When an identity is malformed.
	 */
	private static function exclusionSet( array $excludes, string $kind ): array {
		$pattern = 'term' === $kind ? '/^term:[A-Za-z0-9_\-]+:\d+$/' : '/^post:\d+$/';
		$set     = [];

		foreach ( $excludes as $exclude ) {
			$identity = (string) $exclude;

			if ( 1 !== preg_match( $pattern, $identity ) ) {
				throw new InvalidArgumentException( 'Unknown exclusion.' );
			}

			$set[ $identity ] = true;
		}

		return $set;
	}

	/**
	 * Run one posts chunk.
	 *
	 * @param SourceReader      $reader   Source reader.
	 * @param string            $source   Source id.
	 * @param int               $offset   Chunk offset.
	 * @param int               $limit    Chunk size.
	 * @param string            $mode     Conflict mode.
	 * @param array<int|string> $excludes Excluded post identities, post:{id}.
	 * @return array{applied: int, skipped: int, failed: int, done: bool, total: int} The result.
	 */
	private function runPosts( SourceReader $reader, string $source, int $offset, int $limit, string $mode, array $excludes ): array {
		$excluded     = self::exclusionSet( $excludes, 'post' );
		$items        = $reader->fetchPosts( $offset, $limit );
		$withExisting = [];

		foreach ( $items as $item ) {
			$item['existing'] = PayloadImporter::readPost( (int) $item['id'] );
			$withExisting[]   = $item;
		}

		$preview = PreviewBuilder::buildPreview( $source, $withExisting, [], [], [], $mode );
		$apply   = [];

		foreach ( $preview['rows'] as $row ) {
			if ( isset( $excluded[ $row->identity ] ) ) {
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
	 * @param array<int|string> $excludes Excluded term identities, term:{taxonomy}:{id}.
	 * @return array{applied: int, skipped: int, failed: int, done: bool, total: int} The result.
	 */
	private function runTerms( SourceReader $reader, string $source, int $offset, int $limit, string $mode, array $excludes ): array {
		$excluded     = self::exclusionSet( $excludes, 'term' );
		$items        = $reader->fetchTerms( $offset, $limit );
		$withExisting = [];

		foreach ( $items as $item ) {
			$item['existing'] = PayloadImporter::readTerm( (int) $item['id'] );
			$withExisting[]   = $item;
		}

		$preview = PreviewBuilder::buildPreview( $source, [], $withExisting, [], [], $mode );
		$apply   = [];

		foreach ( $preview['rows'] as $row ) {
			if ( isset( $excluded[ $row->identity ] ) ) {
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
	 * Choices arrive nested under options choices as slot to candidate
	 * identity, sitewide:{slot}:{source}. Every choice is validated
	 * against the re-derived candidate set, so a stale or forged value
	 * throws instead of writing an unseen template. Slots without a
	 * choice stay unchanged.
	 *
	 * @param SourceReader         $reader  Source reader.
	 * @param string               $source  Source id.
	 * @param string               $mode    Conflict mode.
	 * @param array<string, mixed> $options Run options with the choices map.
	 * @return array{applied: int, skipped: int, failed: int, done: bool, total: int} The result.
	 * @throws InvalidArgumentException When a slot choice is unknown.
	 */
	private function runSitewide( SourceReader $reader, string $source, string $mode, array $options ): array {
		$preview = PreviewBuilder::buildPreview( $source, [], [], $reader->fetchSitewideRaw(), [], $mode );
		$choices = is_array( $options['choices'] ?? null ) ? $options['choices'] : [];
		$counts  = [
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

			$bySlot[ $row->slot ][ $row->identity ] = $row;
		}

		foreach ( $choices as $slot => $identity ) {
			if ( ! isset( $bySlot[ (string) $slot ] ) || ! isset( $bySlot[ (string) $slot ][ (string) $identity ] ) ) {
				throw new InvalidArgumentException( 'Unknown sitewide choice.' );
			}
		}

		foreach ( $bySlot as $slot => $candidates ) {
			if ( ! isset( $choices[ $slot ] ) ) {
				$counts['skipped'] += count( $candidates );

				continue;
			}

			$picked = $candidates[ (string) $choices[ $slot ] ];
			$value  = $picked->fields['value'] ?? '';

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
	 * Exclusions are validated against the full re-derived set, so an
	 * unknown identity throws instead of passing silently. Rows that
	 * already exist are counted skipped, which makes repeated chunks
	 * and retried runs idempotent.
	 *
	 * @param SourceReader      $reader   Source reader.
	 * @param string            $source   Source id.
	 * @param string            $mode     Conflict mode.
	 * @param array<int|string> $excludes Excluded redirect identities, redirect:{match}:{source}.
	 * @return array{applied: int, skipped: int, failed: int, done: bool, total: int} The result.
	 * @throws InvalidArgumentException When an exclusion is unknown.
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
		$skip    = [];

		$valid = [];

		foreach ( $preview['rows'] as $row ) {
			if ( 'redirect' === $row->kind ) {
				$valid[ $row->identity ] = $row;
			}
		}

		foreach ( $excludes as $exclude ) {
			if ( ! isset( $valid[ (string) $exclude ] ) ) {
				throw new InvalidArgumentException( 'Unknown redirect exclusion.' );
			}

			$skip[ (string) $exclude ] = true;
		}

		foreach ( $valid as $identity => $row ) {
			if ( isset( $skip[ $identity ] ) ) {
				++$counts['skipped'];

				continue;
			}

			$matchType  = (string) ( $row->fields['match_type'] ?? 'exact' );
			$from       = (string) ( $row->fields['source'] ?? '' );
			$normalized = Normalizer::normalizeSource( $from, $matchType );

			if ( '' === $normalized || Normalizer::isBlockedSource( $normalized ) ) {
				++$counts['skipped'];

				continue;
			}

			if ( null !== $this->redirects->find( $matchType, Normalizer::hash( $matchType, $normalized ) ) ) {
				++$counts['skipped'];

				continue;
			}

			$id = $this->redirects->insert(
				[
					'source'     => $from,
					'target'     => (string) ( $row->fields['target'] ?? '' ),
					'code'       => (int) ( $row->fields['code'] ?? 301 ),
					'match_type' => $matchType,
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
	 * The rule arrives as its preview identity, bulk:{kind}:{type},
	 * and is validated against the re-derived mapper rules, so a
	 * forged type can never stamp unrelated content. Ids enumerate
	 * in bounded slices through BulkScope, never one capped array.
	 *
	 * @param SourceReader         $reader  Source reader.
	 * @param string               $source  Source id.
	 * @param int                  $offset  Chunk offset.
	 * @param int                  $limit   Chunk size.
	 * @param string               $mode    Conflict mode.
	 * @param array<string, mixed> $options Bulk rule wrapper with the rule identity.
	 * @return array{applied: int, skipped: int, failed: int, done: bool, total: int} The result.
	 * @throws InvalidArgumentException When the bulk rule is unknown.
	 */
	private function runBulk( SourceReader $reader, string $source, int $offset, int $limit, string $mode, array $options ): array {
		$identity = is_string( $options['rule'] ?? null ) ? $options['rule'] : '';
		$preview  = PreviewBuilder::buildPreview( $source, [], [], $reader->fetchSitewideRaw(), [], $mode );
		$valid    = [];

		foreach ( $preview['rows'] as $row ) {
			if ( $row->bulk && '' !== $row->identity ) {
				$valid[] = $row->identity;
			}
		}

		if ( '' === $identity || ! in_array( $identity, $valid, true ) ) {
			throw new InvalidArgumentException( 'Unknown bulk rule.' );
		}

		$parts = explode( ':', $identity );
		$kind  = $parts[1] ?? '';
		$type  = $parts[2] ?? '';

		if ( null !== $this->bulkEnumerator ) {
			$all   = call_user_func( $this->bulkEnumerator, $kind, $type );
			$all   = is_array( $all ) ? array_map( 'intval', $all ) : [];
			$total = count( $all );
			$slice = array_slice( $all, $offset, $limit );
		} else {
			$total = BulkScope::count( $kind, $type );
			$slice = BulkScope::ids( $kind, $type, $offset, $limit );
		}

		$apply = [];

		foreach ( $slice as $id ) {
			$apply[] = 'post' === $kind
				? [
					'post_id' => $id,
					'fields'  => [ 'robots' => [ 'index' => false ] ],
				]
				: [
					'term_id' => $id,
					'fields'  => [ 'robots' => [ 'index' => false ] ],
				];
		}

		$counts = 'post' === $kind
			? PayloadImporter::applyRows( $apply, $mode )
			: PayloadImporter::applyTermRows( $apply, $mode );

		$counts['done']  = $offset + $limit >= $total;
		$counts['total'] = $total;

		return $counts;
	}
}
