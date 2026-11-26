<?php
/**
 * Deterministic UTC timestamp formatting for sitemap lastmod values.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Sitemaps;

defined( 'ABSPATH' ) || exit;

/**
 * Format MySQL UTC timestamps without applying the site's timezone.
 */
final class SitemapDate {
	/**
	 * Convert a MySQL UTC timestamp to W3C while preserving the instant.
	 *
	 * Invalid, empty and zero dates return an empty string so callers keep
	 * their existing absence/fallback behavior instead of emitting a bogus
	 * timestamp.
	 *
	 * @param mixed $value MySQL UTC datetime string.
	 * @return string W3C timestamp or empty string.
	 */
	public static function w3cFromGmt( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( $value );

		if ( '' === $value || '0000-00-00 00:00:00' === $value ) {
			return '';
		}

		$datetime = \DateTimeImmutable::createFromFormat(
			'!Y-m-d H:i:s',
			$value,
			new \DateTimeZone( 'UTC' )
		);
		$errors   = \DateTimeImmutable::getLastErrors();

		if (
			false === $datetime
			|| ( is_array( $errors ) && ( 0 < $errors['warning_count'] || 0 < $errors['error_count'] ) )
			|| $datetime->format( 'Y-m-d H:i:s' ) !== $value
		) {
			return '';
		}

		$format = defined( 'DATE_W3C' ) ? DATE_W3C : 'Y-m-d\\TH:i:sP';

		return $datetime->format( $format );
	}
}
