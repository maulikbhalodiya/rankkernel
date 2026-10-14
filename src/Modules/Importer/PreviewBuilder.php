<?php
/**
 * Preview builder, mapper outputs into dry-run rows.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

use InvalidArgumentException;
use RankKernel\Modules\Metadata\MetaPayload;

/**
 * Assembles the dry-run preview from every mapper.
 *
 * Pure over caller supplied data: post and term items carry their
 * source shaped data plus our existing payload for conflict
 * marking, so the builder never touches the database and stays
 * unit testable. Data fetching lives with the run phase caller.
 */
final class PreviewBuilder {
	/**
	 * Build the preview row lists for one source.
	 *
	 * @param string                                                                                                   $source        Source id, yoast, rank-math or aioseo.
	 * @param array<int, array{id: int, type: string, data: array<string, mixed>, existing: array<string, mixed>}>     $posts Post items.
	 * @param array<int, array{id: int, taxonomy: string, data: array<string, mixed>, existing: array<string, mixed>}> $terms Term items.
	 * @param array<string, mixed>                                                                                     $sitewideRaw   Raw sitewide option, shape depends on source.
	 * @param array<int, array<string, mixed>>                                                                         $redirectRaw   Raw redirect rows, shape depends on source.
	 * @param string                                                                                                   $mode          Conflict mode, skip or overwrite.
	 * @return array{rows: Row[], skipped: Row[]} The row lists.
	 */
	public static function buildPreview( string $source, array $posts, array $terms, array $sitewideRaw, array $redirectRaw, string $mode ): array {
		if ( 'skip' !== $mode && 'overwrite' !== $mode ) {
			throw new InvalidArgumentException( 'Unknown conflict mode.' );
		}

		if ( 'yoast' !== $source && 'rank-math' !== $source && 'aioseo' !== $source ) {
			throw new InvalidArgumentException( 'Unknown import source.' );
		}

		$rows    = [];
		$skipped = [];

		foreach ( $posts as $item ) {
			self::addMappedRow( $rows, $skipped, 'post', $source, $item, $sitewideRaw, $mode );
		}

		foreach ( $terms as $item ) {
			self::addMappedRow( $rows, $skipped, 'term', $source, $item, $sitewideRaw, $mode );
		}

		self::addSitewideRows( $rows, $skipped, $source, $sitewideRaw );
		self::addRedirectRows( $rows, $skipped, $source, $redirectRaw );

		return [
			'rows'    => $rows,
			'skipped' => $skipped,
		];
	}

	/**
	 * Map one post or term item into a takable or skipped row.
	 *
	 * @param Row[]                $rows       Takable rows, appended to.
	 * @param Row[]                $skipped    Skipped rows, appended to.
	 * @param string               $kind       Row kind, post or term.
	 * @param string               $source     Source id.
	 * @param array<string, mixed> $item       Item with id, type or taxonomy, data and existing.
	 * @param array<string, mixed> $sitewideRaw Raw sitewide option.
	 * @param string               $mode       Conflict mode.
	 */
	private static function addMappedRow( array &$rows, array &$skipped, string $kind, string $source, array $item, array $sitewideRaw, string $mode ): void {
		$id       = (int) ( $item['id'] ?? 0 );
		$data     = is_array( $item['data'] ?? null ) ? $item['data'] : [];
		$existing = is_array( $item['existing'] ?? null ) ? $item['existing'] : [];
		$label    = ( 'post' === $kind ? 'Post ' : 'Term ' ) . $id;

		if ( 'term' === $kind ) {
			$taxonomy = (string) ( $item['taxonomy'] ?? '' );
			$mapped   = self::mapTerm( $source, $data, $sitewideRaw, $taxonomy );
		} else {
			$type   = (string) ( $item['type'] ?? '' );
			$mapped = self::mapPost( $source, $data, $sitewideRaw, $type );
		}

		if ( [] === $mapped['mapped'] && [] === $mapped['warnings'] ) {
			$skipped[] = new Row( $kind, $source, $id, $label, [], [], [], 'No importable signal in the source data.' );

			return;
		}

		$conflicts = 'skip' === $mode ? self::findConflicts( $mapped['mapped'], $existing ) : [];

		$rows[] = new Row( $kind, $source, $id, $label, $mapped['mapped'], $conflicts, $mapped['warnings'] );
	}

	/**
	 * Dispatch one post item to its source mapper.
	 *
	 * @param string               $source      Source id.
	 * @param array<string, mixed> $data        Source shaped data.
	 * @param array<string, mixed> $sitewideRaw Raw sitewide option.
	 * @param string               $type        Post type.
	 * @return array{mapped: array<string, mixed>, warnings: string[]} The mapping.
	 */
	private static function mapPost( string $source, array $data, array $sitewideRaw, string $type ): array {
		if ( 'rank-math' === $source ) {
			return RankMathMapper::mapPost( $data, $sitewideRaw, $type );
		}

		if ( 'aioseo' === $source ) {
			return AioseoMapper::mapRow( $data );
		}

		return YoastMapper::mapPost( $data, $sitewideRaw, $type );
	}

	/**
	 * Dispatch one term item to its source mapper.
	 *
	 * @param string               $source      Source id.
	 * @param array<string, mixed> $data        Source shaped data.
	 * @param array<string, mixed> $sitewideRaw Raw sitewide option.
	 * @param string               $taxonomy    Taxonomy.
	 * @return array{mapped: array<string, mixed>, warnings: string[]} The mapping.
	 */
	private static function mapTerm( string $source, array $data, array $sitewideRaw, string $taxonomy ): array {
		if ( 'rank-math' === $source ) {
			return RankMathMapper::mapTerm( $data, $sitewideRaw, $taxonomy );
		}

		if ( 'aioseo' === $source ) {
			return AioseoMapper::mapTermRow( $data );
		}

		return YoastMapper::mapTerm( $data, $sitewideRaw, $taxonomy );
	}

	/**
	 * Find fields the incoming data would not take in skip mode.
	 *
	 * Mirrors the PayloadImporter pristine rule: a field conflicts only
	 * when our stored value is set, differs from the payload default
	 * and differs from the incoming value.
	 *
	 * @param array<string, mixed> $incoming Incoming mapped fields.
	 * @param array<string, mixed> $existing Our stored payload.
	 * @return array<string, mixed> Field to existing value.
	 */
	private static function findConflicts( array $incoming, array $existing ): array {
		$defaults  = MetaPayload::defaults();
		$conflicts = [];

		foreach ( $incoming as $field => $value ) {
			if ( ! array_key_exists( $field, $existing ) ) {
				continue;
			}

			$old     = $existing[ $field ];
			$default = $defaults[ $field ] ?? null;

			if ( $old != $default && $old != $value ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- stored payloads mix int and string scalars, strict comparison would flag phantom conflicts.
				$conflicts[ $field ] = $old;
			}
		}

		return $conflicts;
	}

	/**
	 * Turn one sitewide mapping into candidate, bulk and skipped rows.
	 *
	 * @param Row[]                $rows        Takable rows, appended to.
	 * @param Row[]                $skipped     Skipped rows, appended to.
	 * @param string               $source      Source id.
	 * @param array<string, mixed> $sitewideRaw Raw sitewide option.
	 */
	private static function addSitewideRows( array &$rows, array &$skipped, string $source, array $sitewideRaw ): void {
		if ( 'rank-math' === $source ) {
			$mapped = SitewideMapper::mapRankMath( $sitewideRaw );
		} elseif ( 'aioseo' === $source ) {
			$mapped = SitewideMapper::mapAioseo(
				is_array( $sitewideRaw['appearance'] ?? null ) ? $sitewideRaw['appearance'] : [],
				is_array( $sitewideRaw['postTypes'] ?? null ) ? $sitewideRaw['postTypes'] : []
			);
		} else {
			$mapped = SitewideMapper::mapYoast( $sitewideRaw );
		}

		foreach ( $mapped['candidates'] as $candidate ) {
			$rows[] = new Row(
				'sitewide',
				$source,
				0,
				$candidate['slot'] . ' from ' . $candidate['source'],
				[
					'candidate_source' => $candidate['source'],
					'value'            => $candidate['value'],
				],
				[],
				$candidate['unknowns'],
				'',
				$candidate['slot'],
				$candidate['recommended']
			);
		}

		foreach ( $mapped['noindex_rules'] as $rule ) {
			$rows[] = new Row(
				'sitewide',
				$source,
				0,
				'All ' . $rule['type'] . ' ' . $rule['kind'] . 's: noindex',
				[ 'robots' => [ 'index' => $rule['index'] ] ],
				[],
				[],
				'',
				'',
				false,
				true
			);
		}

		foreach ( $mapped['skipped'] as $skip ) {
			$skipped[] = new Row( 'sitewide', $source, 0, $skip['label'], [], [], [], $skip['reason'] );
		}
	}

	/**
	 * Turn redirect mappings into takable rows and named skips.
	 *
	 * @param Row[]                            $rows        Takable rows, appended to.
	 * @param Row[]                            $skipped     Skipped rows, appended to.
	 * @param string                           $source      Source id.
	 * @param array<int, array<string, mixed>> $redirectRaw Raw redirect rows.
	 */
	private static function addRedirectRows( array &$rows, array &$skipped, string $source, array $redirectRaw ): void {
		if ( 'aioseo' === $source ) {
			if ( [] !== $redirectRaw ) {
				$skipped[] = new Row( 'redirect', $source, 0, 'AIOSEO redirects', [], [], [], 'AIOSEO redirects are Pro-only and not supported in v1.' );
			}

			return;
		}

		$mapped = 'rank-math' === $source ? RedirectMapper::mapRankMath( $redirectRaw ) : RedirectMapper::mapYoast( $redirectRaw );

		foreach ( $mapped as $row ) {
			if ( isset( $row['skip_reason'] ) ) {
				$skipped[] = new Row( 'redirect', $source, 0, (string) ( $row['source'] ?? '' ), [], [], [], (string) $row['skip_reason'] );

				continue;
			}

			$rows[] = new Row( 'redirect', $source, 0, (string) $row['source'], $row );
		}
	}
}
