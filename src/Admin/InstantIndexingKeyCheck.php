<?php
/**
 * Instant Indexing key file check, server side fetch of the own key file.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\InstantIndexing\IndexNowSettings;

/**
 * Verifies the plugin own key file over HTTP without exposing the key.
 *
 * The check requests only the location the settings class advertises,
 * never a caller supplied URL, so it cannot be steered at another host.
 * The response body is compared in memory and never returned, callers
 * receive only the status code plus whether the body matched. All
 * WordPress HTTP calls are guarded so the class stays safe when the
 * HTTP API is unavailable.
 */
final class InstantIndexingKeyCheck {
	/**
	 * Check the own key file, returning only the code plus the verdict.
	 *
	 * An empty key or an empty location cannot verify, and a missing
	 * HTTP API or a transport error reports code zero without a match.
	 * A check passes only on a 200 whose trimmed body equals the stored
	 * key byte for byte.
	 *
	 * @param IndexNowSettings $settings Settings owning the key plus the location.
	 * @return array{code: int, ok: bool} The result.
	 */
	public function check( IndexNowSettings $settings ): array {
		$expected = $settings->getKey();
		$url      = $settings->keyLocation();

		if ( '' === $expected || '' === $url ) {
			return [
				'code' => 0,
				'ok'   => false,
			];
		}

		if ( ! function_exists( 'wp_safe_remote_get' ) ) {
			return [
				'code' => 0,
				'ok'   => false,
			];
		}

		$response = wp_safe_remote_get( $url, [ 'timeout' => 10 ] );

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return [
				'code' => 0,
				'ok'   => false,
			];
		}

		$code = 0;

		if ( function_exists( 'wp_remote_retrieve_response_code' ) ) {
			$code = (int) wp_remote_retrieve_response_code( $response );
		}

		$body = '';

		if ( function_exists( 'wp_remote_retrieve_body' ) ) {
			$body = (string) wp_remote_retrieve_body( $response );
		}

		$matched = 200 === $code && hash_equals( $expected, trim( $body ) );

		return [
			'code' => $code,
			'ok'   => $matched,
		];
	}
}
