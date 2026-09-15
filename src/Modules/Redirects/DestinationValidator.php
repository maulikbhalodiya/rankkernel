<?php
/**
 * Destination validation for redirect targets.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Validates redirect destinations before storage and before sending.
 *
 * Policy: internal destinations (relative paths and same host absolute URLs)
 * are allowed, external destinations require an explicit host allowlist,
 * unsafe schemes and control characters are always rejected, and the caller
 * only ever sends the returned cleaned destination, never the raw input.
 */
final class DestinationValidator {
	/**
	 * Fallback protocol allowlist when the core helper is unavailable.
	 *
	 * @var string[]
	 */
	private const FALLBACK_PROTOCOLS = [ 'http', 'https' ];

	/**
	 * Validate a destination.
	 *
	 * Terminal codes 410 and 451 accept an empty target. Redirect codes
	 * require a concrete destination.
	 *
	 * @param string   $target       Raw destination as entered or stored.
	 * @param string   $code         Status code the rule will send.
	 * @param string[] $allowedHosts Lowercase or mixed case external hosts.
	 * @return array{valid: bool, destination: string, reason: string}
	 */
	public function validate( string $target, string $code = '301', array $allowedHosts = [] ): array {
		$target = trim( $target );
		$failed = static function ( string $reason ): array {
			return [
				'valid'       => false,
				'destination' => '',
				'reason'      => $reason,
			];
		};

		if ( in_array( $code, Normalizer::TERMINAL_CODES, true ) && '' === $target ) {
			return [
				'valid'       => true,
				'destination' => '',
				'reason'      => 'ok',
			];
		}

		if ( '' === $target ) {
			return $failed( 'empty destination' );
		}

		if ( 1 === preg_match( '/[\r\n\x00-\x1F\x7F]/', $target ) ) {
			return $failed( 'control characters rejected' );
		}

		$scheme = wp_parse_url( $target, PHP_URL_SCHEME );

		if ( is_string( $scheme ) && '' !== $scheme ) {
			$allowed = function_exists( 'wp_allowed_protocols' ) ? wp_allowed_protocols() : self::FALLBACK_PROTOCOLS;

			if ( ! is_array( $allowed ) || ! in_array( strtolower( $scheme ), $allowed, true ) ) {
				return $failed( 'unsafe scheme' );
			}
		}

		$host = wp_parse_url( $target, PHP_URL_HOST );

		if ( is_string( $host ) && '' !== $host ) {
			return $this->validateAbsolute( $target, $host, $allowedHosts, $failed );
		}

		return $this->validateRelative( $target );
	}

	/**
	 * Validate an absolute URL with a host part.
	 *
	 * Same host as home is internal and folds to a relative destination.
	 * Any other host must appear in the allowlist.
	 *
	 * @param string   $target       Trimmed destination.
	 * @param string   $host         Lowercase or raw host part.
	 * @param string[] $allowedHosts Allowlisted external hosts.
	 * @param callable $failed       Failure builder.
	 * @return array{valid: bool, destination: string, reason: string}
	 */
	private function validateAbsolute( string $target, string $host, array $allowedHosts, callable $failed ): array {
		$hostLower = strtolower( $host );
		$homeHost  = $this->homeHost();

		if ( '' !== $homeHost && $hostLower === $homeHost ) {
			$path  = wp_parse_url( $target, PHP_URL_PATH );
			$query = wp_parse_url( $target, PHP_URL_QUERY );

			$clean = Normalizer::normalize( is_string( $path ) && '' !== $path ? $path : '/' );

			if ( is_string( $query ) && '' !== $query ) {
				$clean .= '?' . $query;
			}

			return [
				'valid'       => true,
				'destination' => $clean,
				'reason'      => 'ok',
			];
		}

		foreach ( $allowedHosts as $allowed ) {
			if ( strtolower( (string) $allowed ) === $hostLower ) {
				return [
					'valid'       => true,
					'destination' => $target,
					'reason'      => 'ok',
				];
			}
		}

		return $failed( 'external host not allowlisted' );
	}

	/**
	 * Validate a relative destination, normalizing the path, keeping query.
	 *
	 * @param string $target Trimmed relative destination.
	 * @return array{valid: bool, destination: string, reason: string}
	 */
	private function validateRelative( string $target ): array {
		$hashCut = strpos( $target, '#' );

		if ( false !== $hashCut ) {
			$target = substr( $target, 0, (int) $hashCut );
		}

		$query = '';
		$qpos  = strpos( $target, '?' );

		if ( false !== $qpos ) {
			$query  = substr( $target, (int) $qpos + 1 );
			$target = substr( $target, 0, (int) $qpos );
		}

		$clean = Normalizer::normalize( $target );

		if ( '' !== $query ) {
			$clean .= '?' . $query;
		}

		return [
			'valid'       => true,
			'destination' => $clean,
			'reason'      => 'ok',
		];
	}

	/**
	 * Lowercase host of the home URL, empty when unavailable.
	 *
	 * @return string Home host or empty string.
	 */
	private function homeHost(): string {
		if ( ! function_exists( 'home_url' ) ) {
			return '';
		}

		$home = home_url( '/' );

		if ( ! is_string( $home ) || '' === $home ) {
			return '';
		}

		$host = wp_parse_url( $home, PHP_URL_HOST );

		if ( ! is_string( $host ) ) {
			return '';
		}

		return strtolower( $host );
	}
}
