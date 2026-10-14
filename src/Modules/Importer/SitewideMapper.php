<?php
/**
 * Sitewide settings mapper.
 *
 * Each source keeps per-post-type title and description templates, but
 * our settings hold a single global title_template and
 * description_template slot. This mapper turns every source template
 * into a named candidate, marks one recommended default per slot
 * (post type, else page, else first), and names everything it cannot
 * place so the preview never drops data silently. Post-type noindex
 * defaults become bulk rules the preview expands into stamped per-post
 * rows, because our side has no sitewide noindex slot.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Sitewide settings mapper.
 */
final class SitewideMapper {
	/**
	 * Yoast option names that are special pages, never post types.
	 *
	 * @var string[]
	 */
	private const YOAST_SPECIALS = [ 'home', 'author', 'date', 'search', '404' ];

	/**
	 * Map Yoast wpseo_titles to candidates, rules, and skips.
	 *
	 * @param array<string, mixed> $titles The wpseo_titles option.
	 * @return array{candidates: array<int, array{slot: string, source: string, value: string, unknowns: string[], recommended: bool}>, noindex_rules: array<int, array{kind: string, type: string, index: bool}>, skipped: array<int, array{label: string, reason: string}>} The mapping.
	 */
	public static function mapYoast( array $titles ): array {
		$candidates = [];
		$rules      = [];
		$skipped    = [];

		foreach ( $titles as $key => $raw ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			foreach ( [
				'title-'    => 'title_template',
				'metadesc-' => 'description_template',
			] as $prefix => $slot ) {
				if ( ! str_starts_with( $key, $prefix ) ) {
					continue;
				}

				$name = substr( $key, strlen( $prefix ) );

				if ( '' === $name || ! is_string( $raw ) || '' === trim( $raw ) ) {
					continue;
				}

				if ( str_starts_with( $name, 'tax-' ) || in_array( $name, self::YOAST_SPECIALS, true ) ) {
					$skipped[] = [
						'label'  => $key,
						'reason' => 'No single-slot equivalent; copy manually if needed.',
					];

					continue;
				}

				[ $value, $unknowns ] = TokenTranslator::translateYoast( $raw );

				$candidates[] = [
					'slot'        => $slot,
					'source'      => $name,
					'value'       => $value,
					'unknowns'    => $unknowns,
					'recommended' => false,
				];
			}

			if ( str_starts_with( $key, 'noindex-' ) ) {
				$name = substr( $key, strlen( 'noindex-' ) );

				if ( self::isNoindexOn( $raw ) ) {
					$rules[] = [
						'kind'  => str_starts_with( $name, 'tax-' ) ? 'term' : 'post',
						'type'  => str_starts_with( $name, 'tax-' ) ? substr( $name, 4 ) : $name,
						'index' => false,
					];
				}
			}
		}

		self::markRecommended( $candidates );

		return [
			'candidates'    => $candidates,
			'noindex_rules' => $rules,
			'skipped'       => $skipped,
		];
	}

	/**
	 * Map Rank Math titles option to candidates, rules, and skips.
	 *
	 * @param array<string, mixed> $titles The rank-math-options-titles option.
	 * @return array{candidates: array<int, array{slot: string, source: string, value: string, unknowns: string[], recommended: bool}>, noindex_rules: array<int, array{kind: string, type: string, index: bool}>, skipped: array<int, array{label: string, reason: string}>} The mapping.
	 */
	public static function mapRankMath( array $titles ): array {
		$candidates = [];
		$rules      = [];
		$skipped    = [];

		foreach ( $titles as $key => $raw ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			foreach ( [
				'pt_'  => 'post',
				'tax_' => 'term',
			] as $prefix => $kind ) {
				foreach ( [
					'_title'       => 'title_template',
					'_description' => 'description_template',
				] as $suffix => $slot ) {
					if ( ! str_starts_with( $key, $prefix ) || ! str_ends_with( $key, $suffix ) ) {
						continue;
					}

					$name = substr( $key, strlen( $prefix ), -strlen( $suffix ) );

					if ( '' === $name || ! is_string( $raw ) || '' === trim( $raw ) ) {
						continue;
					}

					if ( 'term' === $kind ) {
						$skipped[] = [
							'label'  => $key,
							'reason' => 'No single-slot equivalent; copy manually if needed.',
						];

						continue;
					}

					[ $value, $unknowns ] = TokenTranslator::translateRankMath( $raw );

					$candidates[] = [
						'slot'        => $slot,
						'source'      => $name,
						'value'       => $value,
						'unknowns'    => $unknowns,
						'recommended' => false,
					];
				}

				if ( ! str_starts_with( $key, $prefix ) || ! str_ends_with( $key, '_robots' ) ) {
					continue;
				}

				$name = substr( $key, strlen( $prefix ), -strlen( '_robots' ) );

				if ( '' !== $name && is_array( $raw ) && in_array( 'noindex', $raw, true ) ) {
					$rules[] = [
						'kind'  => $kind,
						'type'  => $name,
						'index' => false,
					];
				}
			}

			if ( str_starts_with( $key, 'homepage_' ) ) {
				$skipped[] = [
					'label'  => $key,
					'reason' => 'No single-slot equivalent; copy manually if needed.',
				];
			}
		}

		self::markRecommended( $candidates );

		return [
			'candidates'    => $candidates,
			'noindex_rules' => $rules,
			'skipped'       => $skipped,
		];
	}

	/**
	 * Map AIOSEO global and per-type appearance to candidates and rules.
	 *
	 * The robotsMeta shape (default/noindex flags) was not dumped live
	 * during Phase 0; verify against a live AIOSEO install during the
	 * Phase 5 end-to-end pass and adjust this method if it differs.
	 *
	 * @param array<string, mixed> $appearance The searchAppearance section.
	 * @param array<string, mixed> $postTypes The dynamic postTypes section.
	 * @return array{candidates: array<int, array{slot: string, source: string, value: string, unknowns: string[], recommended: bool}>, noindex_rules: array<int, array{kind: string, type: string, index: bool}>, skipped: array<int, array{label: string, reason: string}>} The mapping.
	 */
	public static function mapAioseo( array $appearance, array $postTypes ): array {
		$candidates = [];
		$rules      = [];
		$skipped    = [];

		foreach ( $postTypes as $type => $settings ) {
			if ( ! is_string( $type ) || ! is_array( $settings ) ) {
				continue;
			}

			foreach ( [
				'title'           => 'title_template',
				'metaDescription' => 'description_template',
			] as $field => $slot ) {
				$raw = $settings[ $field ] ?? '';

				if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
					continue;
				}

				[ $value, $unknowns ] = TokenTranslator::translateAioseo( $raw );

				$candidates[] = [
					'slot'        => $slot,
					'source'      => $type,
					'value'       => $value,
					'unknowns'    => $unknowns,
					'recommended' => false,
				];
			}

			$robots = $settings['robotsMeta'] ?? null;

			if ( is_array( $robots ) && true === ( $robots['noindex'] ?? false ) ) {
				$rules[] = [
					'kind'  => 'post',
					'type'  => $type,
					'index' => false,
				];
			}
		}

		foreach ( [
			'siteTitle'       => 'title_template',
			'metaDescription' => 'description_template',
		] as $field => $slot ) {
			$raw = $appearance[ $field ] ?? '';

			if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
				continue;
			}

			[ $value, $unknowns ] = TokenTranslator::translateAioseo( $raw );

			$candidates[] = [
				'slot'        => $slot,
				'source'      => 'site',
				'value'       => $value,
				'unknowns'    => $unknowns,
				'recommended' => false,
			];
		}

		self::markRecommended( $candidates );

		return [
			'candidates'    => $candidates,
			'noindex_rules' => $rules,
			'skipped'       => [],
		];
	}

	/**
	 * Whether a Yoast noindex value means noindexed.
	 *
	 * @param mixed $raw Raw option value.
	 * @return bool True when noindexed.
	 */
	private static function isNoindexOn( mixed $raw ): bool {
		if ( true === $raw || 1 === $raw || '1' === $raw ) {
			return true;
		}

		return is_string( $raw ) && 'noindex' === strtolower( trim( $raw ) );
	}

	/**
	 * Mark one recommended candidate per slot: post, else page, else first.
	 *
	 * @param array<int, array{slot: string, source: string, value: string, unknowns: string[], recommended: bool}> $candidates The candidates, updated in place.
	 */
	private static function markRecommended( array &$candidates ): void {
		foreach ( [ 'title_template', 'description_template' ] as $slot ) {
			$fallback = null;

			foreach ( $candidates as $i => $candidate ) {
				if ( $candidate['slot'] !== $slot ) {
					continue;
				}

				if ( null === $fallback ) {
					$fallback = $i;
				}

				if ( 'post' === $candidate['source'] ) {
					$candidates[ $i ]['recommended'] = true;
					$fallback                        = null;

					break;
				}

				if ( 'page' === $candidate['source'] ) {
					$fallback = $i;
				}
			}

			if ( null !== $fallback ) {
				$candidates[ $fallback ]['recommended'] = true;
			}
		}
	}
}
