<?php
/**
 * Rank Math mapper, rank_math postmeta to our payload fields.
 *
 * Pure translator over already read arrays: postmeta values in,
 * partial payload fields out. No database or WordPress calls here,
 * so the mapping stays unit testable with fixture arrays. Rank Math
 * stores robots as full directive words (index, noindex, follow,
 * nofollow, noarchive, noimageindex, nosnippet); an empty or missing
 * array means the post inherits the sitewide default, which lives in
 * the titles option under pt_{type}_robots.
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
	 * @param array<string, mixed> $meta      Bare meta values keyed by meta key.
	 * @param array<string, mixed> $sitewide  The rank-math-options-titles option array.
	 * @param string               $robotsKey Sitewide robots key for the fallback.
	 * @return array{0: array<string, mixed>, 1: string[]} The result.
	 */
	private static function mapRobots( array $meta, array $sitewide, string $robotsKey ): array {
		$raw = $meta['rank_math_robots'] ?? [];
		$raw = is_array( $raw ) ? array_values( array_map( 'strval', $raw ) ) : [];

		$warnings = [];
		foreach ( $raw as $directive ) {
			if ( ! in_array( $directive, array_merge( self::NEUTRAL, [ 'noindex', 'nofollow' ], self::FLAGGED ), true ) ) {
				$warnings[] = 'robots: unknown directive ' . $directive;
			}
		}

		$consequential = array_values( array_diff( $raw, self::NEUTRAL ) );
		$hasOwn        = [] !== array_intersect( $consequential, array_merge( [ 'noindex', 'nofollow' ], self::FLAGGED ) );

		$sitewideRobots = $sitewide[ $robotsKey ] ?? [];
		$sitewideRobots = is_array( $sitewideRobots ) ? $sitewideRobots : [];
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
