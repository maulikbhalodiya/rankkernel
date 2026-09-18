<?php
/**
 * Robots.txt directive normalisation, validation and AI crawler presets.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Robots;

defined( 'ABSPATH' ) || exit;

/**
 * Pure helpers for robots.txt content. No state, no hooks.
 */
final class RobotsDirectives {
	/**
	 * Maximum accepted size in bytes. Far below the 500 KiB crawler limit.
	 */
	public const MAX_BYTES = 102400;

	/**
	 * Directive names the editor accepts.
	 *
	 * @var string[]
	 */
	private const ALLOWED = [ 'user-agent', 'disallow', 'allow', 'sitemap', 'crawl-delay' ];

	/**
	 * AI crawler presets, keyed by a stable slug.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private const PRESETS = [
		'gptbot'          => [
			'label'  => 'GPTBot (OpenAI)',
			'agents' => [ 'GPTBot' ],
		],
		'oai-searchbot'   => [
			'label'  => 'OAI-SearchBot (OpenAI)',
			'agents' => [ 'OAI-SearchBot' ],
		],
		'chatgpt-user'    => [
			'label'  => 'ChatGPT-User (OpenAI)',
			'agents' => [ 'ChatGPT-User' ],
		],
		'claudebot'       => [
			'label'  => 'ClaudeBot (Anthropic)',
			'agents' => [ 'ClaudeBot' ],
		],
		'perplexitybot'   => [
			'label'  => 'PerplexityBot',
			'agents' => [ 'PerplexityBot' ],
		],
		'google-extended' => [
			'label'  => 'Google-Extended',
			'agents' => [ 'Google-Extended' ],
		],
		'ccbot'           => [
			'label'  => 'CCBot (Common Crawl)',
			'agents' => [ 'CCBot' ],
		],
	];

	/**
	 * Get the AI crawler presets.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function presets(): array {
		/**
		 * Filter the AI crawler presets.
		 *
		 * @param array<string, array<string, mixed>> $presets Presets keyed by slug.
		 */
		$filtered = apply_filters( 'rankkernel/robots/presets', self::PRESETS ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

		if ( ! is_array( $filtered ) || [] === $filtered ) {
			return self::PRESETS;
		}

		return $filtered;
	}

	/**
	 * Get the known preset slugs.
	 *
	 * @return string[]
	 */
	public static function presetSlugs(): array {
		return array_map( 'strval', array_keys( self::presets() ) );
	}

	/**
	 * Filter a raw preset selection to known, unique slugs.
	 *
	 * @param mixed $value Raw value (array or comma separated string).
	 * @return string[]
	 */
	public static function sanitizePresets( mixed $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$known = self::presetSlugs();
		$out   = [];

		foreach ( $value as $item ) {
			$slug = sanitize_key( (string) $item );

			if ( in_array( $slug, $known, true ) && ! in_array( $slug, $out, true ) ) {
				$out[] = $slug;
			}
		}

		return $out;
	}

	/**
	 * Keep a sitemap URL only when it is absolute and http(s).
	 *
	 * @param string $value Raw URL.
	 * @return string Sanitised URL or an empty string.
	 */
	public static function sanitizeSitemapUrl( string $value ): string {
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		$url = esc_url_raw( $value );

		return self::isAbsoluteHttpUrl( $url ) ? $url : '';
	}

	/**
	 * Normalise raw robots.txt text.
	 *
	 * Converts line endings to LF, strips a UTF-8 BOM and control
	 * characters, drops invalid UTF-8 and caps the size.
	 *
	 * @param string $text Raw text.
	 * @return string Normalised text.
	 */
	public static function normalize( string $text ): string {
		$text = str_replace( [ "\r\n", "\r" ], "\n", $text );
		$text = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $text );
		$text = str_replace( "\xEF\xBB\xBF", '', $text );
		$text = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );

		if ( function_exists( 'wp_check_invalid_utf8' ) ) {
			$text = wp_check_invalid_utf8( $text, true );
		}

		if ( strlen( $text ) > self::MAX_BYTES ) {
			$text = substr( $text, 0, self::MAX_BYTES );
		}

		return $text;
	}

	/**
	 * Validate a normalised robots.txt block.
	 *
	 * @param string $text Normalised text.
	 * @return array{errors: string[], warnings: string[]} Messages, empty when clean.
	 */
	public static function validate( string $text ): array {
		$errors   = [];
		$warnings = [];
		$homeHost = self::hostOf( home_url( '/' ) );
		$lines    = explode( "\n", $text );

		foreach ( $lines as $index => $line ) {
			$line = trim( $line );

			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				continue;
			}

			$position = strpos( $line, ':' );

			if ( false === $position ) {
				/* translators: %d: line number. */
				$errors[] = sprintf( __( 'Line %d is not a directive.', 'rankkernel' ), $index + 1 );
				continue;
			}

			$name  = strtolower( trim( substr( $line, 0, $position ) ) );
			$value = trim( substr( $line, $position + 1 ) );

			if ( ! in_array( $name, self::ALLOWED, true ) ) {
				/* translators: 1: line number, 2: directive name. */
				$errors[] = sprintf( __( 'Line %1$d: unknown directive "%2$s".', 'rankkernel' ), $index + 1, $name );
				continue;
			}

			if ( 'sitemap' === $name ) {
				if ( ! self::isAbsoluteHttpUrl( $value ) ) {
					/* translators: %d: line number. */
					$errors[] = sprintf( __( 'Line %d: the sitemap URL must be absolute and http(s).', 'rankkernel' ), $index + 1 );
					continue;
				}

				$host = self::hostOf( $value );

				if ( '' !== $homeHost && '' !== $host && $host !== $homeHost ) {
					/* translators: 1: line number, 2: host name. */
					$warnings[] = sprintf( __( 'Line %1$d: the sitemap URL points at a different host (%2$s).', 'rankkernel' ), $index + 1, $host );
				}
			}

			if ( 'crawl-delay' === $name ) {
				/* translators: %d: line number. */
				$warnings[] = sprintf( __( 'Line %d: crawl-delay is not supported by Google.', 'rankkernel' ), $index + 1 );
			}
		}

		return [
			'errors'   => $errors,
			'warnings' => $warnings,
		];
	}

	/**
	 * Whether a URL is absolute and http(s) with a host.
	 *
	 * @param string $url URL.
	 * @return bool The result.
	 */
	public static function isAbsoluteHttpUrl( string $url ): bool {
		if ( '' === $url ) {
			return false;
		}

		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

		if ( ! is_string( $scheme ) || ! in_array( strtolower( $scheme ), [ 'http', 'https' ], true ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );

		return is_string( $host ) && '' !== $host;
	}

	/**
	 * Lowercased host of a URL, or an empty string.
	 *
	 * @param string $url URL.
	 * @return string The result.
	 */
	private static function hostOf( string $url ): string {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		return is_string( $host ) ? strtolower( $host ) : '';
	}
}
