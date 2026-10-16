<?php
/**
 * Token translator, source template tokens to our token set.
 *
 * Our renderer understands nine double percent tokens, the Yoast
 * compatible set pinned during Phase 0 discovery. Rank Math single
 * percent tokens normalize by doubling, and anything outside the nine
 * is reported so preview rows can flag it instead of interpolating
 * a token that would render verbatim on the page.
 *
 * @package RankKernel
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Token Translator.
 */
final class TokenTranslator {
	/**
	 * Token names our renderer understands.
	 *
	 * @var string[]
	 */
	private const KNOWN = [
		'title',
		'sitename',
		'sep',
		'excerpt',
		'date',
		'author',
		'category',
		'page',
		'currentdate',
	];

	/**
	 * Translate a Yoast double percent template.
	 *
	 * Known tokens pass through unchanged. Unknown tokens stay verbatim
	 * in the value and are returned so the caller can warn.
	 *
	 * @param string $value Raw template.
	 * @return array{0: string, 1: string[]} Translated value and unknown tokens.
	 */
	public static function translateYoast( string $value ): array {
		$unknown = [];

		$translated = (string) preg_replace_callback(
			'/%%([^%]+?)%%/',
			static function ( array $matches ) use ( &$unknown ): string {
				if ( ! in_array( $matches[1], self::KNOWN, true ) ) {
					$unknown[] = $matches[0];
				}

				return $matches[0];
			},
			$value
		);

		return [ $translated, array_values( array_unique( $unknown ) ) ];
	}

	/**
	 * Translate a Rank Math single percent template.
	 *
	 * Single percent tokens normalize to the double percent form first,
	 * then follow the same known versus unknown rule. A lone percent
	 * sign without a closing partner is not a token and stays untouched.
	 *
	 * @param string $value Raw template.
	 * @return array{0: string, 1: string[]} Translated value and unknown tokens.
	 */
	public static function translateRankMath( string $value ): array {
		$normalized = (string) preg_replace_callback(
			'/%([A-Za-z0-9_]+(?:\([^)]*\))?)%/',
			static function ( array $matches ): string {
				return '%%' . $matches[1] . '%%';
			},
			$value
		);

		return self::translateYoast( $normalized );
	}

	/**
	 * Translate an AIOSEO hash template.
	 *
	 * The name map mirrors Yoast SEO's own AIOSEO importer
	 * (aioseo-replacevar-service.php in wordpress-seo): the 29 plain
	 * names in the same order plus the custom field and taxonomy
	 * patterns. The result runs through the Yoast check, so names
	 * outside our own token set still surface as unknown.
	 *
	 * @param string $value Raw template.
	 * @return array{0: string, 1: string[]} Translated value and unknown tokens.
	 */
	public static function translateAioseo( string $value ): array {
		$mapped = str_replace(
			[
				'#archive_title',
				'#archive_date',
				'#attachment_caption',
				'#author_bio',
				'#author_first_name',
				'#author_last_name',
				'#author_name',
				'#blog_title',
				'#categories',
				'#current_date',
				'#current_day',
				'#current_month',
				'#current_year',
				'#parent_title',
				'#page_number',
				'#permalink',
				'#post_content',
				'#post_date',
				'#post_day',
				'#post_month',
				'#post_title',
				'#post_year',
				'#post_excerpt_only',
				'#post_excerpt',
				'#search_term',
				'#separator_sa',
				'#site_title',
				'#tagline',
				'#taxonomy_title',
				'#taxonomy_description',
			],
			[
				'%%archive_title%%',
				'%%date%%',
				'%%caption%%',
				'%%user_description%%',
				'%%author_first_name%%',
				'%%author_last_name%%',
				'%%name%%',
				'%%sitename%%',
				'%%category%%',
				'%%currentdate%%',
				'%%currentday%%',
				'%%currentmonth%%',
				'%%currentyear%%',
				'%%parent_title%%',
				'%%pagenumber%%',
				'%%permalink%%',
				'%%post_content%%',
				'%%date%%',
				'%%post_day%%',
				'%%post_month%%',
				'%%title%%',
				'%%post_year%%',
				'%%excerpt_only%%',
				'%%excerpt%%',
				'%%searchphrase%%',
				'%%sep%%',
				'%%sitename%%',
				'%%sitedesc%%',
				'%%category_title%%',
				'%%term_description%%',
			],
			$value
		);

		$mapped = (string) preg_replace_callback(
			'/#custom_field-([a-zA-Z0-9_-]+)/',
			static function ( array $matches ): string {
				return '%%cf_' . $matches[1] . '%%';
			},
			$mapped
		);

		$mapped = (string) preg_replace_callback(
			'/#tax_name-([a-zA-Z0-9_-]+)/',
			static function ( array $matches ): string {
				return '%%ct_' . $matches[1] . '%%';
			},
			$mapped
		);

		[ $translated, $unknown ] = self::translateYoast( $mapped );

		preg_match_all( '/#[A-Za-z_][A-Za-z0-9_-]*/', $translated, $leftover );

		return [ $translated, array_values( array_unique( array_merge( $unknown, $leftover[0] ) ) ) ];
	}
}
