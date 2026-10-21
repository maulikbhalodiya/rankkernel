<?php
/**
 * Rank Math mapper, rank_math postmeta to our payload fields.
 *
 * Pure translator over already read arrays: postmeta values in,
 * partial payload fields out. No database or WordPress calls here,
 * so the mapping stays unit testable with fixture arrays. Rank Math
 * stores robots as full directive words (index, noindex, follow,
 * nofollow, noarchive, noimageindex, nosnippet) in a serialized
 * array, so the raw meta column is decoded before the words are read.
 * An empty or missing value means the post inherits the sitewide
 * default, which lives in the titles option under pt_{type}_robots.
 *
 * @package RankKernel
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Rank Math Mapper.
 */
final class RankMathMapper {
	/**
	 * Directives that change nothing against an indexed default.
	 *
	 * @var string[]
	 */
	private const NEUTRAL = [ 'index', 'follow' ];

	/**
	 * Directives that map to our robots flags.
	 *
	 * @var string[]
	 */
	private const FLAGGED = [ 'noarchive', 'noimageindex', 'nosnippet' ];

	/**
	 * Map one post's Rank Math postmeta to our payload fields.
	 *
	 * @param array<string, mixed> $meta     Bare postmeta values keyed by meta key.
	 * @param array<string, mixed> $sitewide The rank-math-options-titles option array.
	 * @param string               $postType Post type for the sitewide fallback.
	 * @return array{mapped: array<string, mixed>, warnings: string[]} The result.
	 */
	public static function mapPost( array $meta, array $sitewide, string $postType ): array {
		return self::map( $meta, $sitewide, 'pt_' . $postType . '_robots' );
	}

	/**
	 * Map one term's Rank Math termmeta to our payload fields.
	 *
	 * Termmeta keys mirror the postmeta keys (verified live: rank_math_title
	 * and siblings exist under termmeta), only the sitewide fallback moves
	 * to tax_{taxonomy}_robots (see their own class-taxonomy.php paper).
	 *
	 * @param array<string, mixed> $meta     Bare termmeta values keyed by meta key.
	 * @param array<string, mixed> $sitewide The rank-math-options-titles option array.
	 * @param string               $taxonomy Taxonomy for the sitewide fallback.
	 * @return array{mapped: array<string, mixed>, warnings: string[]} The result.
	 */
	public static function mapTerm( array $meta, array $sitewide, string $taxonomy ): array {
		return self::map( $meta, $sitewide, 'tax_' . $taxonomy . '_robots' );
	}

	/**
	 * Shared post and term mapping core, split only by robots key.
	 *
	 * @param array<string, mixed> $meta      Bare meta values keyed by meta key.
	 * @param array<string, mixed> $sitewide  The rank-math-options-titles option array.
	 * @param string               $robotsKey Sitewide robots key for the fallback.
	 * @return array{mapped: array<string, mixed>, warnings: string[]} The result.
	 */
	private static function map( array $meta, array $sitewide, string $robotsKey ): array {
		$mapped   = [];
		$warnings = [];

		$text = self::string( $meta, 'rank_math_title' );
		if ( '' !== $text ) {
			[ $translated, $unknown ] = TokenTranslator::translateRankMath( $text );
			$mapped['title']          = $translated;

			foreach ( $unknown as $token ) {
				$warnings[] = 'title: unknown token ' . $token;
			}
		}

		$description = self::string( $meta, 'rank_math_description' );
		if ( '' !== $description ) {
			[ $translated, $unknown ] = TokenTranslator::translateRankMath( $description );
			$mapped['description']    = $translated;

			foreach ( $unknown as $token ) {
				$warnings[] = 'description: unknown token ' . $token;
			}
		}

		$canonical = self::string( $meta, 'rank_math_canonical_url' );
		if ( '' !== $canonical ) {
			$mapped['canonical'] = $canonical;
		}

		$focus = self::string( $meta, 'rank_math_focus_keyword' );
		if ( '' !== $focus ) {
			$mapped['focus_keywords'] = [ $focus ];
		}

		$ogTitle = self::string( $meta, 'rank_math_facebook_title' );
		if ( '' !== $ogTitle ) {
			$mapped['og']['title'] = $ogTitle;
		}

		$ogImage = self::string( $meta, 'rank_math_facebook_image' );
		if ( '' !== $ogImage ) {
			$mapped['og']['image'] = $ogImage;
		}

		$ogImageId = self::string( $meta, 'rank_math_facebook_image_id' );
		if ( '' !== $ogImageId ) {
			$mapped['og']['image_id'] = (int) $ogImageId;
		}

		$twTitle = self::string( $meta, 'rank_math_twitter_title' );
		if ( '' !== $twTitle ) {
			$mapped['twitter']['title'] = $twTitle;
		}

		$crumb = self::string( $meta, 'rank_math_breadcrumb_title' );
		if ( '' !== $crumb ) {
			$mapped['flags']['breadcrumb_title'] = $crumb;
		}

		if ( 'on' === self::string( $meta, 'rank_math_pillar_content' ) ) {
			$mapped['flags']['pillar'] = true;
		}

		[ $robots, $robotWarnings ] = self::mapRobots( $meta, $sitewide, $robotsKey );
		if ( [] !== $robots ) {
			$mapped['robots'] = $robots;
		}

		return [
			'mapped'   => $mapped,
			'warnings' => array_merge( $warnings, $robotWarnings ),
		];
	}

	/**
	 * Map the robots directive array plus the sitewide fallback.
	 *
	 * A row is emitted only when the post carries a consequential
	 * directive of its own or the sitewide default actually noindexes
	 * the type. Neutral directives (index, follow) against an indexed
	 * default need no row: writing our own default back would flood
	 * the preview with no-op changes.
	 *
	 * Index and follow are neutral on purpose. Rank Math stores the
	 * resolved directive set on the post, so a post that merely mirrors
	 * the sitewide default already carries an index directive, and the
	 * stored value cannot tell an inherited index from one the author
	 * picked on a noindex default site. The sitewide default therefore
	 * decides, and the importer follows it.
	 *
	 * @param array<string, mixed> $meta      Bare meta values keyed by meta key.
	 * @param array<string, mixed> $sitewide  The rank-math-options-titles option array.
	 * @param string               $robotsKey Sitewide robots key for the fallback.
	 * @return array{0: array<string, mixed>, 1: string[]} The result.
	 */
	private static function mapRobots( array $meta, array $sitewide, string $robotsKey ): array {
		$raw = self::directives( $meta['rank_math_robots'] ?? null );

		$warnings = [];
		foreach ( $raw as $directive ) {
			if ( ! in_array( $directive, array_merge( self::NEUTRAL, [ 'noindex', 'nofollow' ], self::FLAGGED ), true ) ) {
				$warnings[] = 'robots: unknown directive ' . $directive;
			}
		}

		$consequential = array_values( array_diff( $raw, self::NEUTRAL ) );
		$hasOwn        = [] !== array_intersect( $consequential, array_merge( [ 'noindex', 'nofollow' ], self::FLAGGED ) );

		$sitewideRobots = self::directives( $sitewide[ $robotsKey ] ?? null );
		$index          = ! in_array( 'noindex', $sitewideRobots, true );

		if ( in_array( 'noindex', $raw, true ) ) {
			$index = false;
		}

		if ( ! $hasOwn && $index ) {
			return [ [], $warnings ];
		}

		$robots = [ 'index' => $index ];

		if ( in_array( 'nofollow', $raw, true ) ) {
			$robots['follow'] = false;
		}

		foreach ( self::FLAGGED as $flag ) {
			if ( in_array( $flag, $raw, true ) ) {
				$robots[ $flag ] = true;
			}
		}

		return [ $robots, $warnings ];
	}

	/**
	 * Decode a stored robots value to its directive list.
	 *
	 * Rank Math stores robots as an array of directive names, so the
	 * postmeta column holds a serialized array. The decoded array, a
	 * single directive word and an unreadable value are all accepted,
	 * and anything unreadable yields no directives, which leaves the
	 * post on the sitewide default.
	 *
	 * The keyed shape is read as directive => on. Rank Math only ever
	 * writes the plain list, or its own normaliser shape of directive
	 * => true, so a keyed value of false is read as the directive being
	 * off. That is a tolerant reading, not a format Rank Math produces.
	 *
	 * @param mixed $raw Stored robots value.
	 * @return string[] Directive names.
	 */
	private static function directives( mixed $raw ): array {
		if ( is_array( $raw ) ) {
			return self::directiveNames( $raw );
		}

		if ( ! is_string( $raw ) ) {
			return [];
		}

		$trimmed = trim( $raw );

		if ( '' === $trimmed ) {
			return [];
		}

		if ( str_starts_with( $trimmed, 'a:' ) ) {
			return self::directiveNames( self::decodeArray( $trimmed ) );
		}

		return self::directiveNames( [ $trimmed ] );
	}

	/**
	 * Decode a serialized array without instantiating objects.
	 *
	 * The value comes from a third party table, so object creation stays
	 * disabled and a malformed payload yields an empty array instead of
	 * a warning. Same precedent as the redirect mapper.
	 *
	 * @param string $raw Serialized value.
	 * @return array<int|string, mixed> Decoded array, empty when unreadable.
	 */
	private static function decodeArray( string $raw ): array {
		if ( ! preg_match( '/^a:\d+:\{.*\}$/s', $raw ) ) {
			return [];
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- swallows the warning a malformed third party value raises, the value is untrusted.
		set_error_handler(
			static function (): bool {
				return true;
			}
		);

		try {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- decodes the source plugin robots column with object creation disabled, same precedent as RedirectMapper.
			$decoded = unserialize( $raw, [ 'allowed_classes' => false ] );
		} finally {
			restore_error_handler();
		}

		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Normalise a decoded robots array to its directive names.
	 *
	 * Rank Math writes a plain list of names. Its own normaliser turns
	 * that list into directive => true, so the keyed shape is read as
	 * well: the key names the directive and the value says whether the
	 * directive is on.
	 *
	 * @param array<int|string, mixed> $raw Decoded robots array.
	 * @return string[] Directive names.
	 */
	private static function directiveNames( array $raw ): array {
		$out = [];

		foreach ( $raw as $key => $value ) {
			if ( is_int( $key ) ) {
				$name = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';

				if ( '' !== $name ) {
					$out[] = $name;
				}

				continue;
			}

			$name = strtolower( trim( (string) $key ) );

			if ( '' === $name || ! self::directiveOn( $value ) ) {
				continue;
			}

			$out[] = $name;
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Whether a keyed robots value marks its directive as on.
	 *
	 * @param mixed $value Decoded value.
	 * @return bool The result.
	 */
	private static function directiveOn( mixed $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return 0 !== (int) $value;
		}

		if ( ! is_string( $value ) ) {
			return false;
		}

		$value = strtolower( trim( $value ) );

		return '' !== $value && '0' !== $value && 'false' !== $value;
	}

	/**
	 * Read one string value from the meta array.
	 *
	 * @param array<string, mixed> $meta Bare postmeta values keyed by meta key.
	 * @param string               $key  Meta key.
	 * @return string The result.
	 */
	private static function string( array $meta, string $key ): string {
		if ( ! isset( $meta[ $key ] ) || ! is_scalar( $meta[ $key ] ) ) {
			return '';
		}

		return trim( (string) $meta[ $key ] );
	}
}
