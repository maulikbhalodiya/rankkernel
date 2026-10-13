<?php
/**
 * Scalar noindex flag for sitemap queries.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Metadata;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a scalar noindex flag beside each SEO payload.
 *
 * Sitemap exclusion used to scan every payload with a leading-wildcard
 * LIKE. The flag carries the same meaning as robots.index false in one
 * indexed meta row, so exclusion checks stay cheap. A meta hook sync on
 * every payload write and delete keeps flag and payload in agreement, and
 * the query predicate keeps the LIKE fallback for rows that predate the
 * flag, so nothing ever loses exclusion during the transition.
 */
final class NoindexFlag {
	/**
	 * Flag meta key, existence means noindex.
	 */
	public const FLAG = '_rankkernel_noindex';

	/**
	 * Post payload meta key.
	 */
	public const POST_PAYLOAD = '_rankkernel_meta_data';

	/**
	 * Term payload meta key.
	 */
	public const TERM_PAYLOAD = '_rankkernel_term_data';

	/**
	 * Whether a payload marks its object noindex.
	 *
	 * @param mixed $payload Sanitized payload or raw stored value.
	 * @return bool True when robots index is explicitly false.
	 */
	public static function isNoindex( mixed $payload ): bool {
		$data = is_array( $payload ) ? $payload : MetaPayload::decodeMetaValue( $payload );

		return isset( $data['robots'] ) && is_array( $data['robots'] )
			&& array_key_exists( 'index', $data['robots'] ) && ! $data['robots']['index'];
	}

	/**
	 * Exclusion fragment for a sitemap query.
	 *
	 * Keeps rows whose flag is set out, and rows with no flag at all fall
	 * back to the serialized LIKE check, so payloads saved before flags
	 * existed keep their exclusion. The single LIKE placeholder stays in
	 * place, so existing parameter lists are untouched.
	 *
	 * @param string $metaTable   Meta table name, already trusted.
	 * @param string $ownerColumn Owner id column in the meta table.
	 * @param string $ownerRef    Owner id reference in the outer query.
	 * @param string $payloadKey  Payload meta key.
	 * @return string SQL fragment starting with AND.
	 */
	public static function exclusionSql( string $metaTable, string $ownerColumn, string $ownerRef, string $payloadKey ): string {
		return " AND NOT EXISTS (SELECT 1 FROM {$metaTable} f WHERE f.{$ownerColumn} = {$ownerRef} AND f.meta_key = '" . self::FLAG . "')"
			. " AND NOT (NOT EXISTS (SELECT 1 FROM {$metaTable} f2 WHERE f2.{$ownerColumn} = {$ownerRef} AND f2.meta_key = '" . self::FLAG . "')"
			. " AND EXISTS (SELECT 1 FROM {$metaTable} m WHERE m.{$ownerColumn} = {$ownerRef} AND m.meta_key = '{$payloadKey}' AND m.meta_value LIKE %s))";
	}

	/**
	 * Sync the post flag after a payload write.
	 *
	 * @param int    $metaId    Meta row id, unused.
	 * @param int    $objectId  Post id.
	 * @param string $metaKey   Written meta key.
	 * @param mixed  $metaValue Written value.
	 */
	public static function onPostMetaWrite( int $metaId, int $objectId, string $metaKey, mixed $metaValue ): void {
		self::sync( 'post', $objectId, $metaKey, $metaValue );
	}

	/**
	 * Clear the post flag after a payload delete.
	 *
	 * @param int[]  $metaIds   Deleted meta row ids, unused.
	 * @param int    $objectId  Post id.
	 * @param string $metaKey   Deleted meta key.
	 * @param mixed  $metaValue Deleted value, unused.
	 */
	public static function onPostMetaDelete( array $metaIds, int $objectId, string $metaKey, mixed $metaValue ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- hook passes the deleted value, the flag clears unconditionally.
		self::clear( 'post', $objectId, $metaKey );
	}

	/**
	 * Sync the term flag after a payload write.
	 *
	 * @param int    $metaId    Meta row id, unused.
	 * @param int    $objectId  Term id.
	 * @param string $metaKey   Written meta key.
	 * @param mixed  $metaValue Written value.
	 */
	public static function onTermMetaWrite( int $metaId, int $objectId, string $metaKey, mixed $metaValue ): void {
		self::sync( 'term', $objectId, $metaKey, $metaValue );
	}

	/**
	 * Clear the term flag after a payload delete.
	 *
	 * @param int[]  $metaIds   Deleted meta row ids, unused.
	 * @param int    $objectId  Term id.
	 * @param string $metaKey   Deleted meta key.
	 * @param mixed  $metaValue Deleted value, unused.
	 */
	public static function onTermMetaDelete( array $metaIds, int $objectId, string $metaKey, mixed $metaValue ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- hook passes the deleted value, the flag clears unconditionally.
		self::clear( 'term', $objectId, $metaKey );
	}

	/**
	 * Set or clear the flag from a payload write.
	 *
	 * Writes a different key than the payload, so these hooks never refire.
	 *
	 * @param string $type      Either post or term.
	 * @param int    $objectId  Object id.
	 * @param string $metaKey   Written meta key.
	 * @param mixed  $metaValue Written value.
	 */
	private static function sync( string $type, int $objectId, string $metaKey, mixed $metaValue ): void {
		$payloadKey = 'post' === $type ? self::POST_PAYLOAD : self::TERM_PAYLOAD;

		if ( $metaKey !== $payloadKey || $objectId <= 0 ) {
			return;
		}

		if ( self::isNoindex( $metaValue ) ) {
			if ( 'post' === $type ) {
				if ( function_exists( 'update_post_meta' ) ) {
					update_post_meta( $objectId, self::FLAG, '1' );
				}
			} elseif ( function_exists( 'update_term_meta' ) ) {
				update_term_meta( $objectId, self::FLAG, '1' );
			}

			return;
		}

		self::clear( $type, $objectId, $metaKey );
	}

	/**
	 * Delete the flag when its payload goes away or stops being noindex.
	 *
	 * @param string $type     Either post or term.
	 * @param int    $objectId Object id.
	 * @param string $metaKey  Payload meta key.
	 */
	private static function clear( string $type, int $objectId, string $metaKey ): void {
		$payloadKey = 'post' === $type ? self::POST_PAYLOAD : self::TERM_PAYLOAD;

		if ( $metaKey !== $payloadKey || $objectId <= 0 ) {
			return;
		}

		if ( 'post' === $type ) {
			if ( function_exists( 'delete_post_meta' ) ) {
				delete_post_meta( $objectId, self::FLAG );
			}
		} elseif ( function_exists( 'delete_term_meta' ) ) {
			delete_term_meta( $objectId, self::FLAG );
		}
	}
}
