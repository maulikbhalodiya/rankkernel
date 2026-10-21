<?php
/**
 * Yoast mapper, legacy postmeta to our payload fields.
 *
 * Pure translator over already read arrays: postmeta values in,
 * partial payload fields out. No database or WordPress calls here,
 * so the mapping stays unit testable with fixture arrays. The robots
 * triple follows the semantics Rank Math's own Yoast importer uses:
 * zero or a missing key falls back to the sitewide default, one is
 * noindex, two forces index over a noindex default.
 *
 * @package RankKernel
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Yoast Mapper.
 */
final class YoastMapper {
	/**
	 * Map one post's Yoast legacy postmeta to our payload fields.
	 *
	 * @param array<string, mixed> $meta     Bare postmeta values keyed by meta key.
	 * @param array<string, mixed> $sitewide The wpseo_titles option array.
	 * @param string               $postType Post type for the sitewide fallback.
	 * @return array{mapped: array<string, mixed>, warnings: string[]} The result.
	 */
	public static function mapPost( array $meta, array $sitewide, string $postType ): array {
		$mapped   = [];
		$warnings = [];

		$text = self::string( $meta, '_yoast_wpseo_title' );
		if ( '' !== $text ) {
			[ $translated, $unknown ] = TokenTranslator::translateYoast( $text );
			$mapped['title']          = $translated;

			foreach ( $unknown as $token ) {
				$warnings[] = 'title: unknown token ' . $token;
			}
		}

		$description = self::string( $meta, '_yoast_wpseo_metadesc' );
		if ( '' !== $description ) {
			[ $translated, $unknown ] = TokenTranslator::translateYoast( $description );
			$mapped['description']    = $translated;

			foreach ( $unknown as $token ) {
				$warnings[] = 'description: unknown token ' . $token;
			}
		}

		$canonical = self::string( $meta, '_yoast_wpseo_canonical' );
		if ( '' !== $canonical ) {
			$mapped['canonical'] = $canonical;
		}

		$focus = self::string( $meta, '_yoast_wpseo_focuskw' );
		if ( '' !== $focus ) {
			$mapped['focus_keywords'] = [ $focus ];
		}

		$ogTitle = self::string( $meta, '_yoast_wpseo_opengraph-title' );
		if ( '' !== $ogTitle ) {
			$mapped['og']['title'] = $ogTitle;
		}

		$ogImage = self::string( $meta, '_yoast_wpseo_opengraph-image' );
		if ( '' !== $ogImage ) {
			$mapped['og']['image'] = $ogImage;
		}

		$twTitle = self::string( $meta, '_yoast_wpseo_twitter-title' );
		if ( '' !== $twTitle ) {
			$mapped['twitter']['title'] = $twTitle;
		}

		$crumb = self::string( $meta, '_yoast_wpseo_bctitle' );
		if ( '' !== $crumb ) {
			$mapped['flags']['breadcrumb_title'] = $crumb;
		}

		if ( '' !== self::string( $meta, '_yoast_wpseo_is_cornerstone' ) ) {
			$mapped['flags']['cornerstone'] = true;
		}

		$robots = self::mapRobots( $meta, $sitewide, $postType );
		if ( [] !== $robots ) {
			$mapped['robots'] = $robots;
		}

		return [
			'mapped'   => $mapped,
			'warnings' => $warnings,
		];
	}

	/**
	 * Map one term's Yoast taxonomy row to our payload fields.
	 *
	 * Rows come from the wpseo_taxonomy_meta option, taxonomy keyed,
	 * then term id keyed. The noindex vocabulary there is a word, not
	 * the post triple: noindex means noindex, index forces index, and
	 * default or a missing key inherits the sitewide default, which
	 * lives under noindex-tax-{taxonomy} (see Yoast's own
	 * class-wpseo-option-titles.php enriched defaults).
	 *
	 * @param array<string, mixed> $row      Bare taxonomy row values.
	 * @param array<string, mixed> $sitewide The wpseo_titles option array.
	 * @param string               $taxonomy Taxonomy for the sitewide fallback.
	 * @return array{mapped: array<string, mixed>, warnings: string[]} The result.
	 */
	public static function mapTerm( array $row, array $sitewide, string $taxonomy ): array {
		$mapped   = [];
		$warnings = [];

		$text = self::string( $row, 'wpseo_title' );
		if ( '' !== $text ) {
			[ $translated, $unknown ] = TokenTranslator::translateYoast( $text );
			$mapped['title']          = $translated;

			foreach ( $unknown as $token ) {
				$warnings[] = 'title: unknown token ' . $token;
			}
		}

		$description = self::string( $row, 'wpseo_desc' );
		if ( '' !== $description ) {
			[ $translated, $unknown ] = TokenTranslator::translateYoast( $description );
			$mapped['description']    = $translated;

			foreach ( $unknown as $token ) {
				$warnings[] = 'description: unknown token ' . $token;
			}
		}

		$canonical = self::string( $row, 'wpseo_canonical' );
		if ( '' !== $canonical ) {
			$mapped['canonical'] = $canonical;
		}

		$focus = self::string( $row, 'wpseo_focuskw' );
		if ( '' !== $focus ) {
			$mapped['focus_keywords'] = [ $focus ];
		}

		$ogTitle = self::string( $row, 'wpseo_opengraph-title' );
		if ( '' !== $ogTitle ) {
			$mapped['og']['title'] = $ogTitle;
		}

		$ogImage = self::string( $row, 'wpseo_opengraph-image' );
		if ( '' !== $ogImage ) {
			$mapped['og']['image'] = $ogImage;
		}

		$twTitle = self::string( $row, 'wpseo_twitter-title' );
		if ( '' !== $twTitle ) {
			$mapped['twitter']['title'] = $twTitle;
		}

		$crumb = self::string( $row, 'wpseo_bctitle' );
		if ( '' !== $crumb ) {
			$mapped['flags']['breadcrumb_title'] = $crumb;
		}

		$robots = self::mapTermRobots( $row, $sitewide, $taxonomy );
		if ( [] !== $robots ) {
			$mapped['robots'] = $robots;
		}

		return [
			'mapped'   => $mapped,
			'warnings' => $warnings,
		];
	}

	/**
	 * Map one term's noindex word plus the sitewide taxonomy default.
	 *
	 * @param array<string, mixed> $row      Bare taxonomy row values.
	 * @param array<string, mixed> $sitewide The wpseo_titles option array.
	 * @param string               $taxonomy Taxonomy for the sitewide fallback.
	 * @return array<string, mixed> The result.
	 */
	private static function mapTermRobots( array $row, array $sitewide, string $taxonomy ): array {
		$word   = self::string( $row, 'wpseo_noindex' );
		$hasOwn = '' !== $word && 'default' !== $word;

		if ( 'noindex' === $word ) {
			$index = false;
		} elseif ( 'index' === $word ) {
			$index = true;
		} else {
			$index = empty( $sitewide[ 'noindex-tax-' . $taxonomy ] );
		}

		if ( ! $hasOwn && $index ) {
			return [];
		}

		return [ 'index' => $index ];
	}

	/**
	 * Map the robots triple plus the advanced comma list.
	 *
	 * A row is emitted only when the post carries a robots signal of
	 * its own or the sitewide default actually noindexes the type.
	 * An indexed default needs no row: writing our own default back
	 * would flood the preview with no-op changes.
	 *
	 * @param array<string, mixed> $meta     Bare postmeta values keyed by meta key.
	 * @param array<string, mixed> $sitewide The wpseo_titles option array.
	 * @param string               $postType Post type for the sitewide fallback.
	 * @return array<string, mixed> The result.
	 */
	private static function mapRobots( array $meta, array $sitewide, string $postType ): array {
		$triple = self::string( $meta, '_yoast_wpseo_meta-robots-noindex' );
		$hasOwn = '' !== $triple
			|| '' !== self::string( $meta, '_yoast_wpseo_meta-robots-nofollow' )
			|| '' !== self::string( $meta, '_yoast_wpseo_meta-robots-adv' );

		if ( '1' === $triple ) {
			$index = false;
		} elseif ( '2' === $triple ) {
			$index = true;
		} else {
			$index = empty( $sitewide[ 'noindex-' . $postType ] );
		}

		if ( ! $hasOwn && $index ) {
			return [];
		}

		$robots = [ 'index' => $index ];

		if ( '' !== self::string( $meta, '_yoast_wpseo_meta-robots-nofollow' ) ) {
			$robots['follow'] = false;
		}

		$adv = self::string( $meta, '_yoast_wpseo_meta-robots-adv' );
		if ( '' !== $adv ) {
			foreach ( explode( ',', $adv ) as $directive ) {
				$directive = trim( $directive );

				if ( in_array( $directive, [ 'noarchive', 'noimageindex', 'nosnippet' ], true ) ) {
					$robots[ $directive ] = true;
				}
			}
		}

		return $robots;
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
