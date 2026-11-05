<?php
/**
 * Payload importer, mapper rows into our postmeta with conflict modes.
 *
 * Applies preview rows through the same save path the metabox uses:
 * read the stored payload defensively, merge incoming over existing,
 * sanitize, then update_post_meta with strict false as failure. Skip
 * mode fills only pristine fields (equal to MetaPayload defaults), so
 * a re-run changes zero rows. Overwrite mode takes all incoming.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Metadata\MetaPayload;

/**
 * Payload Importer.
 *
 * Writes mapper rows into our postmeta and termmeta through the same
 * save path the edit screens use: read the stored payload defensively,
 * merge incoming over existing, sanitize, then update with strict
 * false as failure. Skip mode fills only pristine fields, so a re-run
 * changes zero rows. Overwrite mode takes all incoming.
 */
final class PayloadImporter {
	/**
	 * Meta key for our post payload.
	 */
	private const META_KEY = '_rankkernel_meta_data';

	/**
	 * Meta key for our term payload.
	 */
	private const TERM_META_KEY = '_rankkernel_term_data';

	/**
	 * Apply mapper rows to posts.
	 *
	 * @param array<int, array{post_id: int, fields: array<string, mixed>}> $rows Preview rows.
	 * @param string                                                        $mode Conflict mode, skip or overwrite.
	 * @return array{applied: int, skipped: int, failed: int} The result.
	 */
	public static function applyRows( array $rows, string $mode ): array {
		self::validateMode( $mode );

		$counts = [
			'applied' => 0,
			'skipped' => 0,
			'failed'  => 0,
		];

		foreach ( $rows as $row ) {
			$result = self::applyRow( (int) $row['post_id'], $row['fields'], $mode );

			++$counts[ $result ];
		}

		return $counts;
	}

	/**
	 * Apply mapper rows to terms.
	 *
	 * @param array<int, array{term_id: int, fields: array<string, mixed>}> $rows Preview rows.
	 * @param string                                                        $mode Conflict mode, skip or overwrite.
	 * @return array{applied: int, skipped: int, failed: int} The result.
	 */
	public static function applyTermRows( array $rows, string $mode ): array {
		self::validateMode( $mode );

		$counts = [
			'applied' => 0,
			'skipped' => 0,
			'failed'  => 0,
		];

		foreach ( $rows as $row ) {
			$result = self::applyTermRow( (int) $row['term_id'], $row['fields'], $mode );

			++$counts[ $result ];
		}

		return $counts;
	}

	/**
	 * Reject an unknown conflict mode.
	 *
	 * @param string $mode Conflict mode.
	 * @throws \InvalidArgumentException When the mode is unknown.
	 */
	private static function validateMode( string $mode ): void {
		if ( 'skip' !== $mode && 'overwrite' !== $mode ) {
			throw new \InvalidArgumentException( 'Unknown conflict mode.' );
		}
	}

	/**
	 * Apply one row, returning which counter it feeds.
	 *
	 * @param int                  $postId Post id.
	 * @param array<string, mixed> $fields Incoming partial payload.
	 * @param string               $mode   Conflict mode.
	 * @return string One of applied, skipped, failed.
	 */
	private static function applyRow( int $postId, array $fields, string $mode ): string {
		$merged = self::mergeFields( self::readPayload( $postId ), $fields, $mode );

		if ( null === $merged ) {
			return 'skipped';
		}

		$saved = function_exists( 'update_post_meta' ) ? update_post_meta( $postId, self::META_KEY, MetaPayload::sanitize( $merged ) ) : false;

		return false === $saved ? 'failed' : 'applied';
	}

	/**
	 * Apply one term row, returning which counter it feeds.
	 *
	 * @param int                  $termId Term id.
	 * @param array<string, mixed> $fields Incoming partial payload.
	 * @param string               $mode   Conflict mode.
	 * @return string One of applied, skipped, failed.
	 */
	private static function applyTermRow( int $termId, array $fields, string $mode ): string {
		$merged = self::mergeFields( self::readTermPayload( $termId ), $fields, $mode );

		if ( null === $merged ) {
			return 'skipped';
		}

		$saved = function_exists( 'update_term_meta' ) ? update_term_meta( $termId, self::TERM_META_KEY, MetaPayload::sanitize( $merged ) ) : false;

		return false === $saved ? 'failed' : 'applied';
	}

	/**
	 * Merge incoming fields over existing per the mode.
	 *
	 * @param array<string, mixed> $existing Stored payload.
	 * @param array<string, mixed> $fields   Incoming partial payload.
	 * @param string               $mode     Conflict mode.
	 * @return array<string, mixed>|null Merged payload, null when nothing taken.
	 */
	private static function mergeFields( array $existing, array $fields, string $mode ): ?array {
		$defaults = MetaPayload::defaults();
		$merged   = $existing;
		$changed  = false;

		foreach ( $fields as $key => $incoming ) {
			$taken = self::takeField( $existing, $defaults, $merged, (string) $key, $incoming, $mode );

			if ( $taken ) {
				$changed = true;
			}
		}

		return $changed ? $merged : null;
	}

	/**
	 * Take one incoming field into the merged payload per the mode.
	 *
	 * @param array<string, mixed> $existing Read payload.
	 * @param array<string, mixed> $defaults Payload defaults.
	 * @param array<string, mixed> $merged   Merged payload, by reference.
	 * @param string               $key      Field key.
	 * @param mixed                $incoming Incoming value.
	 * @param string               $mode     Conflict mode.
	 * @return bool Whether the field was taken.
	 */
	private static function takeField( array $existing, array $defaults, array &$merged, string $key, mixed $incoming, string $mode ): bool {
		if ( is_array( $incoming ) && isset( $existing[ $key ] ) && is_array( $existing[ $key ] ) ) {
			$taken = false;

			foreach ( $incoming as $sub => $value ) {
				if ( 'overwrite' === $mode || self::pristine( $existing[ $key ][ $sub ] ?? null, $defaults[ $key ][ $sub ] ?? null ) ) {
					$merged[ $key ][ $sub ] = $value;
					$taken                  = true;
				}
			}

			return $taken;
		}

		if ( 'overwrite' === $mode || self::pristine( $existing[ $key ] ?? null, $defaults[ $key ] ?? null ) ) {
			$merged[ $key ] = $incoming;

			return true;
		}

		return false;
	}

	/**
	 * Whether a stored value is pristine, equal to its default.
	 *
	 * @param mixed $stored       Stored value.
	 * @param mixed $defaultValue Default value.
	 * @return bool The result.
	 */
	private static function pristine( mixed $stored, mixed $defaultValue ): bool {
		if ( null === $stored ) {
			return true;
		}

		return $stored === $defaultValue;
	}

	/**
	 * Read the stored post payload for preview conflict marking.
	 *
	 * Same read path the apply step uses, so preview conflicts can
	 * never drift from what the import actually does.
	 *
	 * @param int $postId Post id.
	 * @return array<string, mixed> The result.
	 */
	public static function readPost( int $postId ): array {
		return self::readPayload( $postId );
	}

	/**
	 * Read the stored term payload for preview conflict marking.
	 *
	 * @param int $termId Term id.
	 * @return array<string, mixed> The result.
	 */
	public static function readTerm( int $termId ): array {
		return self::readTermPayload( $termId );
	}

	/**
	 * Read the stored payload, defensive on any shape.
	 *
	 * Mirrors the metabox read path so both agree on what stored means.
	 *
	 * @param int $postId Post id.
	 * @return array<string, mixed> The result.
	 */
	private static function readPayload( int $postId ): array {
		$raw = function_exists( 'get_post_meta' ) ? get_post_meta( $postId, self::META_KEY, true ) : [];

		if ( is_array( $raw ) ) {
			return $raw;
		}

		return MetaPayload::decodeMetaValue( $raw );
	}

	/**
	 * Read the stored term payload, defensive on any shape.
	 *
	 * @param int $termId Term id.
	 * @return array<string, mixed> The result.
	 */
	private static function readTermPayload( int $termId ): array {
		$raw = function_exists( 'get_term_meta' ) ? get_term_meta( $termId, self::TERM_META_KEY, true ) : [];

		if ( is_array( $raw ) ) {
			return $raw;
		}

		return MetaPayload::decodeMetaValue( $raw );
	}
}
