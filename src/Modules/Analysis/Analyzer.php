<?php
/**
 * Deterministic content analysis and scoring.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Runs every local check against a post and produces a score.
 *
 * The score is `earned / applicable * 100`, the same shape both competitors
 * use, so the number is familiar. A check that does not apply is left out of
 * the denominator rather than scored zero, which is the difference between an
 * honest score and a punished one.
 *
 * Nothing here reads an option, runs a query or calls out. The caller supplies
 * the content, the title, the description, the slug and the keywords, so the
 * same engine serves the editor, the REST parity path and the tests.
 */
final class Analyzer {
	/**
	 * Check set version.
	 *
	 * Bumped whenever a check is added, removed, reweighted or given a new
	 * threshold, so a stored score with an older version can be reported as
	 * needing a recheck instead of being presented as current.
	 */
	public const RULES_VERSION = 1;

	/**
	 * Score bands.
	 */
	public const BAND_GOOD    = 'good';
	public const BAND_IMPROVE = 'improve';
	public const BAND_PROBLEM = 'problem';

	/**
	 * Check status values.
	 */
	public const PASS    = 'pass';
	public const IMPROVE = 'improve';
	public const PROBLEM = 'problem';
	public const NA      = 'na';

	/**
	 * Check weights, matching the specification in the research gate.
	 *
	 * @var array<string, int>
	 */
	private const WEIGHTS = [
		'keyword_in_title'          => 36,
		'keyword_in_description'    => 2,
		'keyword_in_slug'           => 5,
		'keyword_in_opening'        => 3,
		'keyword_in_content'        => 3,
		'keyword_in_subheading'     => 3,
		'keyword_in_image_alt'      => 2,
		'keyword_density'           => 6,
		'keyword_distribution'      => 3,
		'keyword_uniqueness'        => 0,
		'title_starts_with_keyword' => 3,
		'title_has_number'          => 1,
		'title_has_power_word'      => 1,
		'title_sentiment'           => 1,
		'content_length'            => 8,
		'slug_length'               => 4,
		'internal_links'            => 5,
		'external_links'            => 4,
		'followed_external'         => 2,
		'generic_anchor_text'       => 3,
		'image_alt_quality'         => 3,
		'short_paragraphs'          => 3,
		'sentence_length'           => 3,
		'subheading_distribution'   => 3,
		'consecutive_sentences'     => 3,
		'passive_voice'             => 3,
		'transition_words'          => 3,
		'media'                     => 6,
		'single_h1'                 => 3,
		'table_of_contents'         => 2,
		'text_present'              => 3,
	];

	/**
	 * Words that add pull to a title.
	 *
	 * @var string[]
	 */
	private const POWER_WORDS = [ 'best', 'free', 'guide', 'how', 'new', 'proven', 'easy', 'fast', 'ultimate', 'complete', 'essential', 'simple', 'top', 'why', 'secret', 'step', 'expert', 'quick' ];

	/**
	 * Positive sentiment markers.
	 *
	 * @var string[]
	 */
	private const POSITIVE_WORDS = [ 'best', 'great', 'good', 'love', 'easy', 'free', 'help', 'improve', 'save', 'win', 'safe', 'fast', 'simple', 'boost', 'success' ];

	/**
	 * Negative sentiment markers.
	 *
	 * @var string[]
	 */
	private const NEGATIVE_WORDS = [ 'bad', 'worst', 'fail', 'error', 'problem', 'risk', 'stop', 'avoid', 'lose', 'hard', 'slow', 'broken', 'warn', 'never', 'mistake' ];

	/**
	 * Words that link two ideas, used by the transition check.
	 *
	 * @var string[]
	 */
	private const TRANSITION_WORDS = [ 'also', 'although', 'because', 'but', 'consequently', 'finally', 'first', 'for example', 'however', 'instead', 'meanwhile', 'moreover', 'next', 'since', 'therefore', 'though', 'thus', 'while', 'additionally', 'as a result' ];

	/**
	 * Pass threshold for the long sentence ratio.
	 */
	private const LONG_SENTENCE_LIMIT = 0.25;

	/**
	 * Word limit for a sentence, and for a paragraph.
	 */
	private const SENTENCE_WORD_LIMIT  = 20;
	private const PARAGRAPH_WORD_LIMIT = 120;

	/**
	 * Word gap allowed between subheadings.
	 */
	private const SUBHEADING_GAP = 300;

	/**
	 * Analyse content against a primary keyword and optional supporting ones.
	 *
	 * @param array<string, mixed> $input Analyse input.
	 * @return array<string, mixed> Score, band, checks and per keyword results.
	 */
	public function analyze( array $input ): array {
		$keywords = $this->keywords( $input );

		if ( [] === $keywords ) {
			return $this->emptyResult();
		}

		$context = $this->context( $input );
		$primary = $keywords[0];

		$checks     = array_merge(
			$this->keywordChecks( $primary, $context, true ),
			$this->contentChecks( $context )
		);
		$scored     = $this->score( $checks );
		$perKeyword = [];

		foreach ( $keywords as $index => $keyword ) {
			if ( 0 === $index ) {
				$perKeyword[] = [
					'keyword' => $keyword,
					'primary' => true,
					'score'   => $scored['score'],
					'band'    => $scored['band'],
					'checks'  => $scored['checks'],
				];

				continue;
			}

			$shared = $this->sharedChecks( $keyword, $context );
			$result = $this->score( $shared );

			$perKeyword[] = [
				'keyword' => $keyword,
				'primary' => false,
				'score'   => $result['score'],
				'band'    => $result['band'],
				'checks'  => $shared,
			];
		}

		return [
			'score'    => $scored['score'],
			'band'     => $scored['band'],
			'checks'   => $scored['checks'],
			'keywords' => $perKeyword,
		];
	}

	/**
	 * Normalise the keyword list, primary first.
	 *
	 * @param array<string, mixed> $input Analyse input.
	 * @return string[] Keywords.
	 */
	private function keywords( array $input ): array {
		$raw  = $input['keywords'] ?? [];
		$out  = [];
		$seen = [];

		if ( ! is_array( $raw ) ) {
			return [];
		}

		foreach ( $raw as $keyword ) {
			$keyword = trim( (string) $keyword );
			$key     = KeywordMatcher::normalize( $keyword );

			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$out[]        = $keyword;
		}

		return $out;
	}

	/**
	 * Build the reusable analysis context.
	 *
	 * @param array<string, mixed> $input Analyse input.
	 * @return array<string, mixed> Context.
	 */
	private function context( array $input ): array {
		$html    = (string) ( $input['html'] ?? '' );
		$text    = TextStats::plainText( $html );
		$anchors = TextStats::links( $html );
		$links   = $this->classifyLinks( $anchors, (string) ( $input['site_url'] ?? '' ) );
		$media   = TextStats::media( $html );

		return [
			'html'         => $html,
			'text'         => $text,
			'title'        => trim( (string) ( $input['title'] ?? '' ) ),
			'description'  => trim( (string) ( $input['description'] ?? '' ) ),
			'slug'         => trim( (string) ( $input['slug'] ?? '' ) ),
			'featuredAlt'  => (string) ( $input['featured_alt'] ?? '' ),
			'words'        => TextStats::wordCount( $text ),
			'sentences'    => TextStats::sentences( $text ),
			'paragraphs'   => TextStats::paragraphs( $text ),
			'headings'     => TextStats::headings( $html ),
			'alts'         => TextStats::imageAlts( $html ),
			'links'        => $links,
			'anchors'      => $anchors,
			'media'        => $media,
			'usedKeywords' => is_array( $input['used_keywords'] ?? null ) ? $input['used_keywords'] : null,
		];
	}

	/**
	 * Split links into internal and external and record rel usage.
	 *
	 * @param array<int, array{href: string, rel: string, text: string}> $links Anchors.
	 * @param string                                                     $siteUrl Site URL.
	 * @return array<string, int|bool> Totals.
	 */
	private function classifyLinks( array $links, string $siteUrl ): array {
		$siteHost = wp_parse_url( $siteUrl, PHP_URL_HOST );
		$host     = strtolower( is_string( $siteHost ) ? $siteHost : '' );
		$internal = 0;
		$external = 0;
		$followed = 0;

		foreach ( $links as $link ) {
			$href = trim( (string) $link['href'] );

			if ( '' === $href || str_starts_with( $href, '#' ) || str_starts_with( $href, 'mailto:' ) || str_starts_with( $href, 'tel:' ) ) {
				continue;
			}

			$linkRaw  = wp_parse_url( $href, PHP_URL_HOST );
			$linkHost = strtolower( is_string( $linkRaw ) ? $linkRaw : '' );

			if ( '' === $linkHost || ( '' !== $host && $linkHost === $host ) ) {
				++$internal;
				continue;
			}

			++$external;

			if ( ! str_contains( (string) $link['rel'], 'nofollow' ) ) {
				++$followed;
			}
		}

		return [
			'internal' => $internal,
			'external' => $external,
			'followed' => $followed,
		];
	}

	/**
	 * Primary keyword checks.
	 *
	 * @param string               $keyword Primary keyword.
	 * @param array<string, mixed> $context Context.
	 * @param bool                 $primary Whether this is the primary keyword.
	 * @return array<int, array<string, mixed>> Check results.
	 */
	private function keywordChecks( string $keyword, array $context, bool $primary ): array {
		$checks = $this->sharedChecks( $keyword, $context );

		$checks[] = $this->check( 'keyword_in_title', 'seo', $keyword, $context['title'], $primary );
		$checks[] = $this->check( 'keyword_in_description', 'seo', $keyword, $context['description'], $primary );
		$checks[] = $this->check( 'keyword_in_slug', 'seo', $keyword, (string) str_replace( [ '-', '_' ], ' ', $context['slug'] ), $primary );
		$checks[] = $this->openingCheck( $keyword, $context );
		$checks[] = $this->imageAltCheck( $keyword, $context );
		$checks[] = $this->titlePositionCheck( $keyword, $context );
		$checks[] = $this->uniquenessCheck( $keyword, $context );

		return $checks;
	}

	/**
	 * Checks shared by the primary and every supporting keyword.
	 *
	 * @param string               $keyword Keyword.
	 * @param array<string, mixed> $context Context.
	 * @return array<int, array<string, mixed>> Check results.
	 */
	private function sharedChecks( string $keyword, array $context ): array {
		return [
			$this->check( 'keyword_in_content', 'seo', $keyword, $context['text'], true ),
			$this->subheadingCheck( $keyword, $context ),
			$this->densityCheck( $keyword, $context ),
			$this->distributionCheck( $keyword, $context ),
		];
	}

	/**
	 * Content checks that do not depend on a keyword.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<int, array<string, mixed>> Check results.
	 */
	private function contentChecks( array $context ): array {
		return array_merge(
			[
				$this->lengthCheck( $context ),
				$this->slugLengthCheck( $context ),
			],
			$this->linkChecks( $context ),
			[ $this->genericAnchorCheck( $context ) ],
			$this->titleReadability( $context ),
			[
				$this->paragraphCheck( $context ),
				$this->sentenceCheck( $context ),
				$this->subheadingDistributionCheck( $context ),
				$this->consecutiveCheck( $context ),
				$this->passiveCheck( $context ),
				$this->transitionCheck( $context ),
				$this->imageAltQualityCheck( $context ),
				$this->mediaCheck( $context ),
				$this->h1Check( $context ),
				$this->tocCheck( $context ),
				$this->textPresenceCheck( $context ),
			]
		);
	}

	/**
	 * Build a check result.
	 *
	 * @param string $id       Check id.
	 * @param string $category Category.
	 * @param string $status   Status.
	 * @param int    $earned   Points earned.
	 * @param string $message  Message.
	 * @return array<string, mixed> Result.
	 */
	private function result( string $id, string $category, string $status, int $earned, string $message ): array {
		return [
			'id'       => $id,
			'category' => $category,
			'status'   => $status,
			'weight'   => self::WEIGHTS[ $id ] ?? 0,
			'earned'   => ( self::NA === $status ) ? 0 : $earned,
			'message'  => $message,
		];
	}

	/**
	 * Pass or problem, weighted in full.
	 *
	 * @param string $id       Check id.
	 * @param string $category Category.
	 * @param string $keyword  Keyword.
	 * @param string $haystack Text to search.
	 * @param bool   $applicable Whether the check applies.
	 * @return array<string, mixed> Result.
	 */
	private function check( string $id, string $category, string $keyword, string $haystack, bool $applicable ): array {
		if ( ! $applicable || '' === trim( $haystack ) ) {
			return $this->result( $id, $category, self::NA, 0, $this->missingMessage( $id ) );
		}

		$weight = self::WEIGHTS[ $id ] ?? 0;

		if ( KeywordMatcher::contains( $haystack, $keyword ) ) {
			return $this->result( $id, $category, self::PASS, $weight, $this->passMessage( $id, $keyword ) );
		}

		return $this->result( $id, $category, self::PROBLEM, 0, $this->failMessage( $id, $keyword ) );
	}

	/**
	 * Keyword inside the opening ten percent, or the whole text when short.
	 *
	 * @param string               $keyword Keyword.
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function openingCheck( string $keyword, array $context ): array {
		$words = KeywordMatcher::words( $context['text'] );

		if ( [] === $words ) {
			return $this->result( 'keyword_in_opening', 'seo', self::NA, 0, $this->missingMessage( 'keyword_in_opening' ) );
		}

		$limit   = ( count( $words ) > 400 ) ? (int) floor( count( $words ) * 0.10 ) : count( $words );
		$opening = implode( ' ', array_slice( $words, 0, max( 1, $limit ) ) );
		$weight  = self::WEIGHTS['keyword_in_opening'];

		if ( KeywordMatcher::contains( $opening, $keyword ) ) {
			return $this->result( 'keyword_in_opening', 'seo', self::PASS, $weight, $this->passMessage( 'keyword_in_opening', $keyword ) );
		}

		return $this->result( 'keyword_in_opening', 'seo', self::PROBLEM, 0, $this->failMessage( 'keyword_in_opening', $keyword ) );
	}

	/**
	 * Keyword in any subheading.
	 *
	 * @param string               $keyword Keyword.
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function subheadingCheck( string $keyword, array $context ): array {
		$texts = [];

		foreach ( $context['headings'] as $heading ) {
			if ( $heading['level'] >= 2 ) {
				$texts[] = $heading['text'];
			}
		}

		if ( [] === $texts ) {
			return $this->result( 'keyword_in_subheading', 'seo', self::NA, 0, $this->missingMessage( 'keyword_in_subheading' ) );
		}

		return $this->check( 'keyword_in_subheading', 'seo', $keyword, implode( "\n", $texts ), true );
	}

	/**
	 * Keyword in any image alt.
	 *
	 * @param string               $keyword Keyword.
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function imageAltCheck( string $keyword, array $context ): array {
		$alts = $context['alts'];

		if ( '' !== $context['featuredAlt'] ) {
			$alts[] = $context['featuredAlt'];
		}

		if ( [] === $alts ) {
			return $this->result( 'keyword_in_image_alt', 'seo', self::NA, 0, $this->missingMessage( 'keyword_in_image_alt' ) );
		}

		return $this->check( 'keyword_in_image_alt', 'seo', $keyword, implode( "\n", $alts ), true );
	}

	/**
	 * Position of the keyword in the first half of the title.
	 *
	 * @param string               $keyword Keyword.
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function titlePositionCheck( string $keyword, array $context ): array {
		$title = $context['title'];

		if ( '' === $title ) {
			return $this->result( 'title_starts_with_keyword', 'title', self::NA, 0, $this->missingMessage( 'title_starts_with_keyword' ) );
		}

		$weight = self::WEIGHTS['title_starts_with_keyword'];
		$needle = KeywordMatcher::normalize( $keyword );
		$hay    = KeywordMatcher::normalize( $title );
		$at     = ( '' === $needle ) ? false : mb_strpos( $hay, $needle, 0, 'UTF-8' );

		if ( false !== $at && $at < (int) floor( mb_strlen( $hay, 'UTF-8' ) / 2 ) ) {
			return $this->result( 'title_starts_with_keyword', 'title', self::PASS, $weight, $this->passMessage( 'title_starts_with_keyword', $keyword ) );
		}

		return $this->result( 'title_starts_with_keyword', 'title', self::PROBLEM, 0, $this->failMessage( 'title_starts_with_keyword', $keyword ) );
	}

	/**
	 * Whether the keyword is already used by another post.
	 *
	 * Advisory only, so it carries no weight. It is not applicable until the
	 * caller supplies the stored keyword list.
	 *
	 * @param string               $keyword Keyword.
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function uniquenessCheck( string $keyword, array $context ): array {
		if ( null === $context['usedKeywords'] ) {
			return $this->result( 'keyword_uniqueness', 'seo', self::NA, 0, $this->missingMessage( 'keyword_uniqueness' ) );
		}

		$needle = KeywordMatcher::normalize( $keyword );

		foreach ( $context['usedKeywords'] as $used ) {
			if ( KeywordMatcher::normalize( (string) $used ) === $needle ) {
				return $this->result( 'keyword_uniqueness', 'seo', self::IMPROVE, 0, __( 'Other content already targets this keyword.', 'rankkernel' ) );
			}
		}

		return $this->result( 'keyword_uniqueness', 'seo', self::PASS, 0, __( 'This keyword is not used elsewhere.', 'rankkernel' ) );
	}

	/**
	 * Density band, reported with the real count and never asking for more.
	 *
	 * @param string               $keyword Keyword.
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function densityCheck( string $keyword, array $context ): array {
		if ( [] === KeywordMatcher::words( $context['text'] ) ) {
			return $this->result( 'keyword_density', 'seo', self::NA, 0, $this->missingMessage( 'keyword_density' ) );
		}

		$count   = KeywordMatcher::occurrences( $context['text'], $keyword );
		$density = KeywordMatcher::density( $context['text'], $keyword );

		if ( 0 === $count ) {
			$message = KeywordMatcher::contains( $context['text'], $keyword )
				? __( 'Your keyword appears in a natural variation, which is fine.', 'rankkernel' )
				: __( 'Your keyword does not appear in the content yet.', 'rankkernel' );

			return $this->result( 'keyword_density', 'seo', self::PROBLEM, 0, $message );
		}

		/* translators: 1: occurrence count, 2: density percentage. */
		$template = __( 'Your keyword appears %1$d time(s), a density of %2$s percent.', 'rankkernel' );
		$message  = sprintf( $template, $count, number_format_i18n( $density, 2 ) );

		// A ceiling only. Google states there is no optimal density and defines
		// stuffing as repetition that reads unnaturally, so a minimum would only
		// pressure a writer into repeating the phrase.
		if ( $density <= 2.5 ) {
			return $this->result(
				'keyword_density',
				'seo',
				self::PASS,
				self::WEIGHTS['keyword_density'],
				$message . ' ' . __( 'Google states there is no ideal keyword density, so a lower figure is fine.', 'rankkernel' )
			);
		}

		return $this->result(
			'keyword_density',
			'seo',
			self::IMPROVE,
			2,
			$message . ' ' . __( 'Above 2.5 percent the repetition can read as unnatural, which Google defines as keyword stuffing.', 'rankkernel' )
		);
	}

	/**
	 * No large stretch of the content is left without a mention.
	 *
	 * @param string               $keyword Keyword.
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function distributionCheck( string $keyword, array $context ): array {
		$sentences = $context['sentences'];

		if ( count( $sentences ) < 15 ) {
			return $this->result( 'keyword_distribution', 'seo', self::NA, 0, $this->missingMessage( 'keyword_distribution' ) );
		}

		$window = 5;
		$total  = count( $sentences );
		$worst  = 0;

		for ( $start = 0; $start + $window <= $total; $start++ ) {
			$slice = implode( ' ', array_slice( $sentences, $start, $window ) );

			if ( ! KeywordMatcher::contains( $slice, $keyword ) ) {
				++$worst;
			}
		}

		$ratio  = $worst / max( 1, $total - $window + 1 );
		$weight = self::WEIGHTS['keyword_distribution'];

		if ( 0 === $worst ) {
			return $this->result( 'keyword_distribution', 'seo', self::PASS, $weight, __( 'Your keyword is spread evenly through the content.', 'rankkernel' ) );
		}

		if ( $ratio <= 0.5 ) {
			return $this->result( 'keyword_distribution', 'seo', self::IMPROVE, 1, __( 'Your keyword is used, but a few sections never mention it.', 'rankkernel' ) );
		}

		return $this->result( 'keyword_distribution', 'seo', self::PROBLEM, 0, __( 'Large parts of the content never mention your keyword.', 'rankkernel' ) );
	}

	/**
	 * Content length in words, with partial credit.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function lengthCheck( array $context ): array {
		$words = $context['words'];

		if ( 0 === $words ) {
			return $this->result( 'content_length', 'seo', self::NA, 0, $this->missingMessage( 'content_length' ) );
		}

		if ( $words >= 2500 ) {
			$earned = 8;
		} elseif ( $words >= 2000 ) {
			$earned = 5;
		} elseif ( $words >= 1500 ) {
			$earned = 4;
		} elseif ( $words >= 1000 ) {
			$earned = 3;
		} elseif ( $words >= 600 ) {
			$earned = 2;
		} else {
			$earned = 0;
		}

		/* translators: %d: word count. */
		$template = __( 'The content is %d words long. Google states there is no ideal word count, so treat this as a completeness signal rather than a length requirement.', 'rankkernel' );
		$message  = sprintf( $template, $words );

		if ( $earned >= self::WEIGHTS['content_length'] ) {
			return $this->result( 'content_length', 'seo', self::PASS, $earned, $message );
		}

		return $this->result( 'content_length', 'seo', self::IMPROVE, $earned, $message );
	}

	/**
	 * Slug length in characters.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function slugLengthCheck( array $context ): array {
		if ( '' === $context['slug'] ) {
			return $this->result( 'slug_length', 'seo', self::NA, 0, $this->missingMessage( 'slug_length' ) );
		}

		$length = mb_strlen( $context['slug'], 'UTF-8' );
		$weight = self::WEIGHTS['slug_length'];

		/* translators: %d: character count. */
		$message = sprintf( __( 'The URL is %d characters long.', 'rankkernel' ), $length );

		if ( $length <= 75 ) {
			return $this->result( 'slug_length', 'seo', self::PASS, $weight, $message );
		}

		return $this->result( 'slug_length', 'seo', self::IMPROVE, 0, $message );
	}

	/**
	 * Internal and external link presence.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<int, array<string, mixed>> Results.
	 */
	private function linkChecks( array $context ): array {
		$links = $context['links'];

		$internal = ( $links['internal'] > 0 )
			? $this->result( 'internal_links', 'seo', self::PASS, self::WEIGHTS['internal_links'], __( 'The content links to another page on this site.', 'rankkernel' ) )
			: $this->result( 'internal_links', 'seo', self::PROBLEM, 0, __( 'No internal links found. Link to related content.', 'rankkernel' ) );

		$external = ( $links['external'] > 0 )
			? $this->result( 'external_links', 'seo', self::PASS, self::WEIGHTS['external_links'], __( 'The content links out to an external source.', 'rankkernel' ) )
			: $this->result( 'external_links', 'seo', self::PROBLEM, 0, __( 'No outbound links found. Cite a source or reference.', 'rankkernel' ) );

		if ( 0 === $links['external'] ) {
			$followed = $this->result( 'followed_external', 'seo', self::NA, 0, __( 'There are no outbound links to check.', 'rankkernel' ) );
		} elseif ( $links['followed'] > 0 ) {
			$followed = $this->result( 'followed_external', 'seo', self::PASS, self::WEIGHTS['followed_external'], __( 'At least one outbound link is followed.', 'rankkernel' ) );
		} else {
			$followed = $this->result( 'followed_external', 'seo', self::IMPROVE, 0, __( 'Every outbound link is nofollow.', 'rankkernel' ) );
		}

		return [ $internal, $external, $followed ];
	}

	/**
	 * In content links whose anchor text is generic or a bare URL.
	 *
	 * Google documents that anchor text must be descriptive and concise, and
	 * names click here and bare URLs as the exact failure to avoid.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function genericAnchorCheck( array $context ): array {
		$anchors = $context['anchors'];
		$generic = [ 'click here', 'read more', 'this', 'here', 'link', 'website' ];
		$total   = 0;
		$flagged = 0;

		foreach ( $anchors as $anchor ) {
			$href = trim( (string) $anchor['href'] );

			if ( '' === $href || str_starts_with( $href, '#' ) ) {
				continue;
			}

			++$total;

			$raw  = trim( (string) $anchor['text'] );
			$text = KeywordMatcher::normalize( $raw );

			if ( '' !== $text && in_array( $text, $generic, true ) ) {
				++$flagged;
				continue;
			}

			if ( 1 === preg_match( '~^https?://[^ ]+$~i', $raw ) ) {
				++$flagged;
			}
		}

		if ( 0 === $total ) {
			return $this->result( 'generic_anchor_text', 'seo', self::NA, 0, __( 'Add a link to check this.', 'rankkernel' ) );
		}

		$weight = self::WEIGHTS['generic_anchor_text'];

		if ( 0 === $flagged ) {
			return $this->result( 'generic_anchor_text', 'seo', self::PASS, $weight, __( 'Every link explains where it goes. Anchor text should describe the destination, which is Google guidance.', 'rankkernel' ) );
		}

		/* translators: %d: number of links with generic anchor text. */
		$message = sprintf( __( '%d link(s) use generic anchor text such as click here or a bare URL. Anchor text should describe the destination, which is Google guidance.', 'rankkernel' ), $flagged );

		return $this->result( 'generic_anchor_text', 'seo', self::IMPROVE, 0, $message );
	}

	/**
	 * Title readability, three checks.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<int, array<string, mixed>> Results.
	 */
	private function titleReadability( array $context ): array {
		$title = $context['title'];

		if ( '' === $title ) {
			return [
				$this->result( 'title_has_number', 'title', self::NA, 0, $this->missingMessage( 'title_has_number' ) ),
				$this->result( 'title_has_power_word', 'title', self::NA, 0, $this->missingMessage( 'title_has_power_word' ) ),
				$this->result( 'title_sentiment', 'title', self::NA, 0, $this->missingMessage( 'title_sentiment' ) ),
			];
		}

		$words = array_map( 'strtolower', KeywordMatcher::words( $title ) );

		$number = preg_match( '/\d/', $title ) ? self::PASS : self::IMPROVE;
		$power  = array_intersect( $words, self::POWER_WORDS ) ? self::PASS : self::IMPROVE;
		$mood   = array_intersect( $words, array_merge( self::POSITIVE_WORDS, self::NEGATIVE_WORDS ) ) ? self::PASS : self::IMPROVE;

		return [
			$this->result( 'title_has_number', 'title', $number, ( self::PASS === $number ) ? 1 : 0, ( self::PASS === $number ) ? __( 'The title contains a number.', 'rankkernel' ) : __( 'Consider a number in the title.', 'rankkernel' ) ),
			$this->result( 'title_has_power_word', 'title', $power, ( self::PASS === $power ) ? 1 : 0, ( self::PASS === $power ) ? __( 'The title contains a power word.', 'rankkernel' ) : __( 'Consider a power word in the title.', 'rankkernel' ) ),
			$this->result( 'title_sentiment', 'title', $mood, ( self::PASS === $mood ) ? 1 : 0, ( self::PASS === $mood ) ? __( 'The title carries a positive or negative sentiment.', 'rankkernel' ) : __( 'The title reads neutral.', 'rankkernel' ) ),
		];
	}

	/**
	 * Every paragraph must stay under the word limit.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function paragraphCheck( array $context ): array {
		$paragraphs = $context['paragraphs'];

		if ( [] === $paragraphs ) {
			return $this->result( 'short_paragraphs', 'readability', self::NA, 0, $this->missingMessage( 'short_paragraphs' ) );
		}

		$longest = TextStats::longestParagraph( $paragraphs );
		$weight  = self::WEIGHTS['short_paragraphs'];

		if ( $longest <= self::PARAGRAPH_WORD_LIMIT ) {
			return $this->result( 'short_paragraphs', 'readability', self::PASS, $weight, __( 'The paragraphs are short enough to scan.', 'rankkernel' ) );
		}

		return $this->result( 'short_paragraphs', 'readability', self::IMPROVE, 0, __( 'At least one paragraph is long. Short paragraphs are easier to read.', 'rankkernel' ) );
	}

	/**
	 * Share of long sentences.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function sentenceCheck( array $context ): array {
		$sentences = $context['sentences'];

		if ( [] === $sentences ) {
			return $this->result( 'sentence_length', 'readability', self::NA, 0, $this->missingMessage( 'sentence_length' ) );
		}

		$ratio  = TextStats::longSentenceRatio( $sentences, self::SENTENCE_WORD_LIMIT );
		$weight = self::WEIGHTS['sentence_length'];

		/* translators: %s: percentage of long sentences. */
		$message = sprintf( __( '%s percent of the sentences are longer than 20 words.', 'rankkernel' ), number_format_i18n( $ratio * 100, 0 ) );

		if ( $ratio <= self::LONG_SENTENCE_LIMIT ) {
			return $this->result( 'sentence_length', 'readability', self::PASS, $weight, $message );
		}

		return $this->result( 'sentence_length', 'readability', self::IMPROVE, 0, $message );
	}

	/**
	 * No stretch of words without a subheading.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function subheadingDistributionCheck( array $context ): array {
		$headings = $context['headings'];
		$words    = $context['words'];

		if ( 0 === $words ) {
			return $this->result( 'subheading_distribution', 'readability', self::NA, 0, $this->missingMessage( 'subheading_distribution' ) );
		}

		$subheadings = 0;

		foreach ( $headings as $heading ) {
			if ( $heading['level'] >= 2 ) {
				++$subheadings;
			}
		}

		$weight = self::WEIGHTS['subheading_distribution'];

		if ( 0 === $subheadings ) {
			if ( $words <= self::SUBHEADING_GAP ) {
				return $this->result( 'subheading_distribution', 'readability', self::NA, 0, __( 'Short enough that subheadings are optional.', 'rankkernel' ) );
			}

			return $this->result( 'subheading_distribution', 'readability', self::IMPROVE, 0, __( 'No subheadings found. Consider splitting the content.', 'rankkernel' ) );
		}

		$gap = (int) ceil( $words / $subheadings );

		if ( $gap <= self::SUBHEADING_GAP ) {
			return $this->result( 'subheading_distribution', 'readability', self::PASS, $weight, __( 'The content is broken up by subheadings.', 'rankkernel' ) );
		}

		return $this->result( 'subheading_distribution', 'readability', self::IMPROVE, 0, __( 'A long stretch has no subheading. Add one to break it up.', 'rankkernel' ) );
	}

	/**
	 * Runs of sentences opening with the same word.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function consecutiveCheck( array $context ): array {
		$sentences = $context['sentences'];

		if ( count( $sentences ) < 3 ) {
			return $this->result( 'consecutive_sentences', 'readability', self::NA, 0, $this->missingMessage( 'consecutive_sentences' ) );
		}

		$run    = TextStats::longestRepeatedOpening( $sentences );
		$weight = self::WEIGHTS['consecutive_sentences'];

		if ( $run < 3 ) {
			return $this->result( 'consecutive_sentences', 'readability', self::PASS, $weight, __( 'No run of sentences opens with the same word.', 'rankkernel' ) );
		}

		return $this->result( 'consecutive_sentences', 'readability', self::IMPROVE, 0, __( 'Several sentences in a row start with the same word. Vary the openings.', 'rankkernel' ) );
	}

	/**
	 * Share of sentences that read as passive voice.
	 *
	 * This is a heuristic, not a parser. It looks for a form of "to be"
	 * followed by a past participle ending in "ed", which catches the common
	 * cases and stays quiet on the rest.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function passiveCheck( array $context ): array {
		$sentences = $context['sentences'];

		if ( [] === $sentences ) {
			return $this->result( 'passive_voice', 'readability', self::NA, 0, $this->missingMessage( 'passive_voice' ) );
		}

		$passive = 0;

		foreach ( $sentences as $sentence ) {
			if ( preg_match( '/\b(is|are|was|were|be|been|being)\s+(\w+ed|done|made|given|taken|seen|known|shown|held|built|sent|found|kept|left|written|read)\b/i', $sentence ) ) {
				++$passive;
			}
		}

		$ratio  = $passive / count( $sentences );
		$weight = self::WEIGHTS['passive_voice'];

		/* translators: %s: percentage of passive sentences. */
		$message = sprintf( __( '%s percent of the sentences read as passive voice.', 'rankkernel' ), number_format_i18n( $ratio * 100, 0 ) );

		if ( $ratio <= 0.10 ) {
			return $this->result( 'passive_voice', 'readability', self::PASS, $weight, $message );
		}

		return $this->result( 'passive_voice', 'readability', self::IMPROVE, 0, $message );
	}

	/**
	 * Share of sentences carrying a transition word.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function transitionCheck( array $context ): array {
		$sentences = $context['sentences'];

		if ( [] === $sentences ) {
			return $this->result( 'transition_words', 'readability', self::NA, 0, $this->missingMessage( 'transition_words' ) );
		}

		$weight = self::WEIGHTS['transition_words'];

		if ( $context['words'] <= 200 ) {
			return $this->result( 'transition_words', 'readability', self::NA, 0, __( 'Short enough that transition words are optional.', 'rankkernel' ) );
		}

		$found = 0;

		foreach ( $sentences as $sentence ) {
			$needle = ' ' . KeywordMatcher::normalize( $sentence ) . ' ';

			foreach ( self::TRANSITION_WORDS as $word ) {
				if ( str_contains( $needle, ' ' . $word . ' ' ) ) {
					++$found;
					break;
				}
			}
		}

		$ratio = $found / count( $sentences );

		/* translators: %s: percentage of sentences with a transition word. */
		$message = sprintf( __( '%s percent of the sentences use a transition word.', 'rankkernel' ), number_format_i18n( $ratio * 100, 0 ) );

		if ( $ratio >= 0.30 ) {
			return $this->result( 'transition_words', 'readability', self::PASS, $weight, $message );
		}

		return $this->result( 'transition_words', 'readability', self::IMPROVE, 0, $message );
	}

	/**
	 * Alt coverage across content images, and stuffed alt text.
	 *
	 * Google documents alt as required for accessibility and understanding,
	 * and names stuffed alt as its own negative example. Requiring the
	 * keyword in every alt would contradict that, so this reports coverage
	 * and stuffing instead of keyword presence.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function imageAltQualityCheck( array $context ): array {
		$alts = $context['alts'];

		if ( '' !== $context['featuredAlt'] ) {
			$alts[] = $context['featuredAlt'];
		}

		if ( [] === $alts ) {
			return $this->result( 'image_alt_quality', 'seo', self::NA, 0, __( 'Add an image to check this.', 'rankkernel' ) );
		}

		$missing = 0;
		$stuffed = 0;

		foreach ( $alts as $alt ) {
			$alt = trim( (string) $alt );

			if ( '' === $alt ) {
				++$missing;
				continue;
			}

			if ( count( KeywordMatcher::words( $alt ) ) > 20 ) {
				++$stuffed;
			}
		}

		$weight = self::WEIGHTS['image_alt_quality'];

		if ( 0 === $missing && 0 === $stuffed ) {
			return $this->result( 'image_alt_quality', 'seo', self::PASS, $weight, __( 'Every image has descriptive alt text. Alt text is for accessibility and understanding, not a keyword slot, which is Google guidance.', 'rankkernel' ) );
		}

		/* translators: 1: images with no alt text, 2: images with stuffed alt text. */
		$message = sprintf( __( '%1$d image(s) have no alt text and %2$d look stuffed. Alt text is for accessibility and understanding, not a keyword slot, which is Google guidance.', 'rankkernel' ), $missing, $stuffed );

		return $this->result( 'image_alt_quality', 'seo', self::IMPROVE, 0, $message );
	}

	/**
	 * Images and video, with partial credit.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function mediaCheck( array $context ): array {
		$media    = $context['media'];
		$images   = (int) $media['images'];
		$videos   = (int) $media['videos'];
		$imgScore = match ( true ) {
			4 <= $images => 6,
			3 === $images => 4,
			2 === $images => 2,
			1 === $images => 1,
			default => 0,
		};
		$vidScore = min( 2, $videos );
		$earned   = min( self::WEIGHTS['media'], $imgScore + $vidScore );

		if ( 0 === $images && 0 === $videos ) {
			return $this->result( 'media', 'readability', self::IMPROVE, 0, __( 'No images or video found. Media helps a reader stay.', 'rankkernel' ) );
		}

		if ( $earned >= self::WEIGHTS['media'] ) {
			return $this->result( 'media', 'readability', self::PASS, $earned, __( 'The content includes enough media.', 'rankkernel' ) );
		}

		return $this->result( 'media', 'readability', self::IMPROVE, $earned, __( 'Consider one or two more images or a video.', 'rankkernel' ) );
	}

	/**
	 * Only one h1 in the content.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function h1Check( array $context ): array {
		$h1 = 0;

		foreach ( $context['headings'] as $heading ) {
			if ( 1 === $heading['level'] ) {
				++$h1;
			}
		}

		$weight = self::WEIGHTS['single_h1'];

		if ( $h1 < 2 ) {
			return $this->result( 'single_h1', 'readability', self::PASS, $weight, __( 'The content has at most one h1.', 'rankkernel' ) );
		}

		return $this->result( 'single_h1', 'readability', self::IMPROVE, 0, __( 'More than one h1 found. Keep a single h1 per page.', 'rankkernel' ) );
	}

	/**
	 * A table of contents is present.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function tocCheck( array $context ): array {
		$weight = self::WEIGHTS['table_of_contents'];

		if ( TextStats::hasToc( $context['html'] ) ) {
			return $this->result( 'table_of_contents', 'readability', self::PASS, $weight, __( 'The content has a table of contents.', 'rankkernel' ) );
		}

		if ( $context['words'] < 1500 ) {
			return $this->result( 'table_of_contents', 'readability', self::NA, 0, __( 'Short enough that a table of contents is optional.', 'rankkernel' ) );
		}

		return $this->result( 'table_of_contents', 'readability', self::IMPROVE, 0, __( 'Long content reads better with a table of contents.', 'rankkernel' ) );
	}

	/**
	 * There is enough text to analyse.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed> Result.
	 */
	private function textPresenceCheck( array $context ): array {
		$length = mb_strlen( $context['text'], 'UTF-8' );
		$weight = self::WEIGHTS['text_present'];

		if ( $length >= 50 ) {
			return $this->result( 'text_present', 'readability', self::PASS, $weight, __( 'The content has enough text to analyse.', 'rankkernel' ) );
		}

		return $this->result( 'text_present', 'readability', self::PROBLEM, 0, __( 'Add content so the analysis has something to read.', 'rankkernel' ) );
	}

	/**
	 * Message for a check that does not apply.
	 *
	 * @param string $id Check id.
	 * @return string Message.
	 */
	private function missingMessage( string $id ): string {
		switch ( $id ) {
			case 'keyword_in_title':
			case 'title_starts_with_keyword':
				return __( 'Add an SEO title to check this.', 'rankkernel' );

			case 'keyword_in_description':
				return __( 'Add a meta description to check this.', 'rankkernel' );

			case 'keyword_in_image_alt':
				return __( 'Add an image or set a featured image to check this.', 'rankkernel' );

			case 'keyword_in_subheading':
				return __( 'Add a subheading to check this.', 'rankkernel' );

			case 'generic_anchor_text':
				return __( 'Add a link to check this.', 'rankkernel' );

			case 'image_alt_quality':
				return __( 'Add an image to check this.', 'rankkernel' );
		}

		return __( 'Not applicable yet.', 'rankkernel' );
	}

	/**
	 * Pass message for a keyword check.
	 *
	 * @param string $id      Check id.
	 * @param string $keyword Keyword.
	 * @return string Message.
	 */
	private function passMessage( string $id, string $keyword ): string {
		switch ( $id ) {
			case 'keyword_in_title':
				/* translators: %s: keyword. */
				return sprintf( __( 'Your keyword "%s" appears in the SEO title.', 'rankkernel' ), $keyword );

			case 'keyword_in_description':
				/* translators: %s: keyword. */
				return sprintf( __( 'Your keyword "%s" appears in the meta description. The description is a display and click through signal, not a ranking factor, which is Google guidance.', 'rankkernel' ), $keyword );

			case 'keyword_in_slug':
				/* translators: %s: keyword. */
				return sprintf( __( 'Your keyword "%s" appears in the URL.', 'rankkernel' ), $keyword );

			case 'keyword_in_opening':
				/* translators: %s: keyword. */
				return sprintf( __( 'Your keyword "%s" appears near the beginning.', 'rankkernel' ), $keyword );

			case 'keyword_in_content':
				/* translators: %s: keyword. */
				return sprintf( __( 'Your keyword "%s" appears in the content.', 'rankkernel' ), $keyword );

			case 'keyword_in_subheading':
				/* translators: %s: keyword. */
				return sprintf( __( 'Your keyword "%s" appears in a subheading.', 'rankkernel' ), $keyword );

			case 'keyword_in_image_alt':
				/* translators: %s: keyword. */
				return sprintf( __( 'Your keyword "%s" appears in an image alt.', 'rankkernel' ), $keyword );

			case 'title_starts_with_keyword':
				/* translators: %s: keyword. */
				return sprintf( __( 'Your keyword "%s" is near the start of the title.', 'rankkernel' ), $keyword );
		}

		/* translators: %s: keyword. */
		return sprintf( __( 'Your keyword "%s" is used well here.', 'rankkernel' ), $keyword );
	}

	/**
	 * Fail message for a keyword check.
	 *
	 * @param string $id      Check id.
	 * @param string $keyword Keyword.
	 * @return string Message.
	 */
	private function failMessage( string $id, string $keyword ): string {
		switch ( $id ) {
			case 'keyword_in_title':
				/* translators: %s: keyword. */
				return sprintf( __( 'Your keyword "%s" does not appear in the SEO title.', 'rankkernel' ), $keyword );

			case 'keyword_in_description':
				/* translators: %s: keyword. */
				return sprintf( __( 'Your keyword "%s" does not appear in the meta description. The description is a display and click through signal, not a ranking factor.', 'rankkernel' ), $keyword );

			case 'keyword_in_slug':
				/* translators: %s: keyword. */
				return sprintf( __( 'Your keyword "%s" does not appear in the URL.', 'rankkernel' ), $keyword );

			case 'keyword_in_opening':
				/* translators: %s: keyword. */
				return sprintf( __( 'Your keyword "%s" does not appear near the beginning.', 'rankkernel' ), $keyword );

			case 'keyword_in_content':
				/* translators: %s: keyword. */
				return sprintf( __( 'Your keyword "%s" does not appear in the content.', 'rankkernel' ), $keyword );

			case 'keyword_in_subheading':
				/* translators: %s: keyword. */
				return sprintf( __( 'Your keyword "%s" does not appear in a subheading.', 'rankkernel' ), $keyword );

			case 'keyword_in_image_alt':
				/* translators: %s: keyword. */
				return sprintf( __( 'Your keyword "%s" does not appear in an image alt.', 'rankkernel' ), $keyword );

			case 'title_starts_with_keyword':
				/* translators: %s: keyword. */
				return sprintf( __( 'Your keyword "%s" is not near the start of the title.', 'rankkernel' ), $keyword );
		}

		/* translators: %s: keyword. */
		return sprintf( __( 'Your keyword "%s" is missing here.', 'rankkernel' ), $keyword );
	}

	/**
	 * Score a set of checks.
	 *
	 * @param array<int, array<string, mixed>> $checks Checks.
	 * @return array<string, mixed> Score, band and the checks.
	 */
	private function score( array $checks ): array {
		$earned = 0;
		$max    = 0;

		foreach ( $checks as $check ) {
			if ( self::NA === $check['status'] ) {
				continue;
			}

			$earned += (int) $check['earned'];
			$max    += (int) $check['weight'];
		}

		$score = ( $max > 0 ) ? (int) round( ( $earned / $max ) * 100 ) : 0;

		return [
			'score'  => $score,
			'band'   => $this->band( $score ),
			'checks' => $checks,
		];
	}

	/**
	 * Map a score to a band.
	 *
	 * @param int $score Score.
	 * @return string Band.
	 */
	private function band( int $score ): string {
		if ( $score >= 81 ) {
			return self::BAND_GOOD;
		}

		if ( $score >= 51 ) {
			return self::BAND_IMPROVE;
		}

		return self::BAND_PROBLEM;
	}

	/**
	 * Result for a post with no keywords.
	 *
	 * @return array<string, mixed> Result.
	 */
	private function emptyResult(): array {
		return [
			'score'    => 0,
			'band'     => self::BAND_PROBLEM,
			'checks'   => [],
			'keywords' => [],
		];
	}
}
