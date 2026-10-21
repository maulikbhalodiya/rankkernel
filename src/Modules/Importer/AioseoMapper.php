<?php
/**
 * AIOSEO mapper, aioseo_posts row to our payload fields.
 *
 * Pure translator over one already read database row: row values in,
 * partial payload fields out. No database or WordPress calls here, so
 * the mapping stays unit testable with fixture arrays. A row with
 * robots_default set inherits the sitewide settings, and our side
 * defaults to indexed, so such rows emit no robots fields at all.
 *
 * @package RankKernel
 */

declare(strict_types=1);

namespace RankKernel\Modules\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * AIOSEO Mapper.
 */
final class AioseoMapper {
	/**
	 * Map one AIOSEO post row to our payload fields.
	 *
	 * @param array<string, mixed> $row Bare aioseo_posts column values.
	 * @return array{mapped: array<string, mixed>, warnings: string[]} The result.
	 */
	public static function mapRow( array $row ): array {
		$mapped   = [];
		$warnings = [];

		$text = self::string( $row, 'title' );
		if ( '' !== $text ) {
			[ $translated, $unknown ] = TokenTranslator::translateAioseo( $text );
			$mapped['title']          = $translated;

			foreach ( $unknown as $token ) {
				$warnings[] = 'title: unknown token ' . $token;
			}
		}

		$description = self::string( $row, 'description' );
		if ( '' !== $description ) {
			[ $translated, $unknown ] = TokenTranslator::translateAioseo( $description );
			$mapped['description']    = $translated;

			foreach ( $unknown as $token ) {
				$warnings[] = 'description: unknown token ' . $token;
			}
		}

		$canonical = self::string( $row, 'canonical_url' );
		if ( '' !== $canonical ) {
			$mapped['canonical'] = $canonical;
		}

		$focus = self::focusKeyword( $row, $warnings );
		if ( '' !== $focus ) {
			$mapped['focus_keywords'] = [ $focus ];
		}

		$ogTitle = self::string( $row, 'og_title' );
		if ( '' !== $ogTitle ) {
			$mapped['og']['title'] = $ogTitle;
		}

		$ogDescription = self::string( $row, 'og_description' );
		if ( '' !== $ogDescription ) {
			$mapped['og']['description'] = $ogDescription;
		}

		$ogImage = self::string( $row, 'og_image_url' );
		if ( '' !== $ogImage ) {
			$mapped['og']['image'] = $ogImage;
		}

		$useOg = ! empty( $row['twitter_use_og'] );
		if ( $useOg ) {
			foreach ( [ 'title', 'description', 'image' ] as $field ) {
				if ( isset( $mapped['og'][ $field ] ) ) {
					$mapped['twitter'][ $field ] = $mapped['og'][ $field ];
				}
			}
		}

		$twTitle = self::string( $row, 'twitter_title' );
		if ( '' !== $twTitle ) {
			$mapped['twitter']['title'] = $twTitle;
		}

		$twImage = self::string( $row, 'twitter_image_url' );
		if ( '' !== $twImage ) {
			$mapped['twitter']['image'] = $twImage;
		}

		$robots = self::mapRobots( $row );
		if ( [] !== $robots ) {
			$mapped['robots'] = $robots;
		}

		return [
			'mapped'   => $mapped,
			'warnings' => $warnings,
		];
	}

	/**
	 * Map one AIOSEO term row to our payload fields.
	 *
	 * AIOSEO keeps a single Post model and no termmeta writes anywhere,
	 * so term SEO lives in aioseo_posts rows with the identical column
	 * shape as post rows. The row mapper therefore serves both.
	 *
	 * @param array<string, mixed> $row Bare aioseo_posts column values.
	 * @return array{mapped: array<string, mixed>, warnings: string[]} The result.
	 */
	public static function mapTermRow( array $row ): array {
		return self::mapRow( $row );
	}

	/**
	 * Map the robots columns.
	 *
	 * A default row inherits the sitewide settings, which on our side
	 * means indexed, so it emits nothing: writing our own default back
	 * would flood the preview with no-op changes.
	 *
	 * @param array<string, mixed> $row Bare aioseo_posts column values.
	 * @return array<string, mixed> The result.
	 */
	private static function mapRobots( array $row ): array {
		if ( ! empty( $row['robots_default'] ) ) {
			return [];
		}

		$hasOwn = ! empty( $row['robots_noindex'] )
			|| ! empty( $row['robots_nofollow'] )
			|| ! empty( $row['robots_noarchive'] )
			|| ! empty( $row['robots_nosnippet'] )
			|| ! empty( $row['robots_noimageindex'] );

		if ( ! $hasOwn ) {
			return [];
		}

		$robots = [];

		if ( ! empty( $row['robots_noindex'] ) ) {
			$robots['index'] = false;
		}

		if ( ! empty( $row['robots_nofollow'] ) ) {
			$robots['follow'] = false;
		}

		foreach ( [ 'noarchive', 'nosnippet', 'noimageindex' ] as $flag ) {
			if ( ! empty( $row[ 'robots_' . $flag ] ) ) {
				$robots[ $flag ] = true;
			}
		}

		foreach (
			[
				'max_snippet'       => 'robots_max_snippet',
				'max_image_preview' => 'robots_max_imagepreview',
				'max_video_preview' => 'robots_max_videopreview',
			] as $field => $column
		) {
			if ( isset( $row[ $column ] ) && is_numeric( $row[ $column ] ) && 0 <= (int) $row[ $column ] ) {
				$robots[ $field ] = (int) $row[ $column ];
			}
		}

		return $robots;
	}

	/**
	 * Read the focus keyphrase from the keyphrases JSON column.
	 *
	 * @param array<string, mixed> $row      Bare aioseo_posts column values.
	 * @param string[]             $warnings Warning list to append to.
	 * @return string The result.
	 */
	private static function focusKeyword( array $row, array &$warnings ): string {
		if ( ! isset( $row['keyphrases'] ) || ! is_string( $row['keyphrases'] ) || '' === trim( $row['keyphrases'] ) ) {
			return '';
		}

		$decoded = json_decode( $row['keyphrases'], true );

		if ( ! is_array( $decoded ) || ! isset( $decoded['focus']['keyphrase'] ) || ! is_string( $decoded['focus']['keyphrase'] ) ) {
			$warnings[] = 'keyphrases: unreadable JSON';
			return '';
		}

		return trim( $decoded['focus']['keyphrase'] );
	}

	/**
	 * Read one string value from the row.
	 *
	 * @param array<string, mixed> $row Bare aioseo_posts column values.
	 * @param string               $key Column name.
	 * @return string The result.
	 */
	private static function string( array $row, string $key ): string {
		if ( ! isset( $row[ $key ] ) || ! is_scalar( $row[ $key ] ) ) {
			return '';
		}

		return trim( (string) $row[ $key ] );
	}
}
