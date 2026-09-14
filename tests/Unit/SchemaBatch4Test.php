<?php
/**
 * Schema batch 4 tests, Pro giveaway pieces.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Metadata\MetaPayload;
use RankKernel\Modules\Schema\Pieces\CarouselPiece;
use RankKernel\Modules\Schema\Pieces\ClaimReviewPiece;
use RankKernel\Modules\Schema\Pieces\DatasetPiece;
use RankKernel\Modules\Schema\Pieces\ItemListPiece;
use RankKernel\Modules\Schema\Pieces\MoviePiece;
use RankKernel\Modules\Schema\Pieces\PodcastEpisodePiece;
use RankKernel\Modules\Schema\Pieces\QaPagePiece;
use RankKernel\Modules\Schema\Pieces\WebpagePiece;
use RankKernel\Modules\Schema\SchemaModule;
use RankKernel\Modules\Schema\SchemaTypes;
use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Pro giveaway schema pieces, all free here.
 */
final class SchemaBatch4Test extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $v ): string => trim( $v ) );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn ( string $s ): string => strip_tags( $s ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'absint' )->alias( static fn ( mixed $v ): int => abs( (int) $v ) );
		Functions\when( 'mysql2date' )->alias(
			static fn ( string $format, string $date ): string => (string) gmdate( $format, (int) strtotime( $date ) )
		);
		Functions\when( 'wp_kses_post' )->alias( static fn ( string $v ): string => $v );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'is_front_page' )->justReturn( false );
		Functions\when( 'is_author' )->justReturn( false );
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) {
				if ( 'rankkernel_settings' === $key ) {
					return [];
				}

				return $fallback;
			}
		);
		Functions\when( 'get_post_meta' )->justReturn( [] );
		Functions\when( 'get_term_meta' )->justReturn( [] );
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '/' ): string => 'https://example.com' . $path );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $v ): string => rtrim( $v, '/' ) . '/' );
		Functions\when( 'get_bloginfo' )->justReturn( 'My Site' );
		Functions\when( 'get_locale' )->justReturn( 'en_US' );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/hello/' );
		Functions\when( 'get_the_title' )->justReturn( 'Hello Post' );
		Functions\when( 'get_the_excerpt' )->justReturn( 'Excerpt text' );
		Functions\when( 'get_post_field' )->justReturn( '' );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_the_date' )->justReturn( '2026-01-01T00:00:00+00:00' );
		Functions\when( 'get_the_modified_date' )->justReturn( '2026-02-01T00:00:00+00:00' );
		Functions\when( 'get_author_posts_url' )->justReturn( 'https://example.com/author/bob/' );
		Functions\when( 'get_the_author_meta' )->justReturn( 'Bob' );
		Functions\when( 'get_the_author' )->justReturn( 'Bob' );
		Functions\when( 'get_the_category' )->justReturn( [] );
		Functions\when( 'date_i18n' )->justReturn( 'Jan 1, 2026' );
		Functions\when( 'single_term_title' )->justReturn( '' );
		Functions\when( 'get_term_link' )->justReturn( 'https://example.com/cat/news/' );
		Functions\when( 'wp_get_attachment_image_url' )->justReturn( '' );
		Functions\when( 'wp_get_attachment_url' )->justReturn( '' );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
		Functions\when( 'single_post_title' )->justReturn( '' );
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $v, int $o = 0 ): string => (string) json_encode( $v, $o ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit tests run without WordPress loaded, wp_json_encode is unavailable here.
		Functions\when( '__' )->alias( static fn ( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a query mock for a scenario.
	 *
	 * @param array<string, mixed> $flags Method to return value overrides.
	 * @param int                  $id    Id.
	 * @return WP_Query The result.
	 */
	private function makeQuery( array $flags = [], int $id = 1 ): WP_Query {
		$defaults = [
			'is_singular'           => false,
			'is_search'             => false,
			'is_404'                => false,
			'is_feed'               => false,
			'is_preview'            => false,
			'is_category'           => false,
			'is_tag'                => false,
			'is_tax'                => false,
			'is_home'               => false,
			'is_front_page'         => false,
			'is_archive'            => false,
			'is_author'             => false,
			'is_date'               => false,
			'is_post_type_archive'  => false,
			'get_queried_object_id' => $id,
			'get'                   => 0,
		];

		$query = Mockery::mock( WP_Query::class );

		foreach ( array_merge( $defaults, $flags ) as $method => $value ) {
			$query->shouldReceive( $method )->andReturn( $value )->byDefault();
		}

		return $query;
	}

	/**
	 * Make Context.
	 *
	 * @param WP_Query $query Query.
	 * @return Context The result.
	 */
	private function makeContext( WP_Query $query ): Context {
		return new Context( $query, new SettingsStore() );
	}

	/**
	 * Singular Query.
	 *
	 * @param int $id Id.
	 * @return WP_Query The result.
	 */
	private function singularQuery( int $id = 1 ): WP_Query {
		return $this->makeQuery( [ 'is_singular' => true ], $id );
	}

	/**
	 * Stub Post Meta.
	 *
	 * @param array<string, mixed> $meta Raw post meta payload.
	 */
	private function stubPostMeta( array $meta ): void {
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $id, string $key, bool $single ) use ( $meta ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_meta signature.
				if ( '_rankkernel_meta_data' === $key ) {
					return $meta;
				}

				return [];
			}
		);
	}

	/**
	 * Schema Context.
	 *
	 * @param array<string, mixed> $schema Raw schema payload.
	 * @return Context The result.
	 */
	private function schemaContext( array $schema ): Context {
		$this->stubPostMeta( [ 'schema' => $schema ] );

		return $this->makeContext( $this->singularQuery() );
	}

	/**
	 * Stub Author.
	 *
	 * @param int $authorId Author Id.
	 */
	private function stubAuthor( int $authorId ): void {
		Functions\when( 'get_post_field' )->alias(
			static function ( string $field, int $id ) use ( $authorId ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_field signature.
				if ( 'post_author' === $field ) {
					return $authorId;
				}

				return '';
			}
		);
	}

	/**
	 * Stub Featured Image.
	 *
	 * @param string $url Url.
	 */
	private function stubFeaturedImage( string $url ): void {
		Functions\when( 'get_post_thumbnail_id' )->alias( static fn ( int $id ): int => 5 ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_post_thumbnail_id signature.
		Functions\when( 'wp_get_attachment_image_url' )->alias( static fn ( int $id, string $size ): string => $url ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_get_attachment_image_url signature.
	}

	/**
	 * Test type list covers batch4 types.
	 */
	public function test_type_list_covers_batch4_types(): void {
		foreach ( [ 'Movie', 'ClaimReview', 'Dataset', 'PodcastEpisode', 'Carousel', 'QAPage', 'ItemList' ] as $type ) {
			$this->assertSame( $type, SchemaTypes::normalize( $type ) );
		}

		$this->assertSame( 'Article', SchemaTypes::normalize( 'Speakable' ) );
		$this->assertSame( 'Article', SchemaTypes::normalize( 'AboutPage' ) );
	}

	/**
	 * Test movie not needed for wrong type or empty name.
	 */
	public function test_movie_not_needed_for_wrong_type_or_empty_name(): void {
		$piece = new MoviePiece();

		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [] ) ) );
		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [ 'type' => 'Product' ] ) ) );

		Functions\when( 'get_the_title' )->justReturn( '' );
		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [ 'type' => 'Movie' ] ) ) );
	}

	/**
	 * Test movie builds full structure with ids.
	 */
	public function test_movie_builds_full_structure_with_ids(): void {
		$ctx = $this->schemaContext(
			[
				'type'   => 'Movie',
				'fields' => [
					'headline'    => 'Great Film',
					'dateCreated' => '2026-03-01 10:00:00',
					'director'    => 'Jane Doe',
					'ratingValue' => '4.5',
					'reviewCount' => '12',
				],
			]
		);

		$this->stubFeaturedImage( 'https://example.com/poster.jpg' );

		$piece = new MoviePiece();

		$this->assertTrue( $piece->isNeeded( $ctx ) );

		$build = $piece->build( $ctx );

		$this->assertSame( 'Movie', $build['@type'] );
		$this->assertSame( 'https://example.com/hello/#movie', $build['@id'] );
		$this->assertSame( 'Great Film', $build['name'] );
		$this->assertSame( 'Excerpt text', $build['description'] );
		$this->assertSame( '2026-03-01T10:00:00+00:00', $build['dateCreated'] );
		$this->assertSame( 'Person', $build['director']['@type'] );
		$this->assertSame( 'Jane Doe', $build['director']['name'] );
		$this->assertSame( '4.5', $build['aggregateRating']['ratingValue'] );
		$this->assertSame( 12, $build['aggregateRating']['reviewCount'] );
		$this->assertSame( 'https://example.com/poster.jpg', $build['image'] );
	}

	/**
	 * Test movie drops invalid date and rating.
	 */
	public function test_movie_drops_invalid_date_and_rating(): void {
		$build = ( new MoviePiece() )->build(
			$this->schemaContext(
				[
					'type'   => 'Movie',
					'fields' => [
						'dateCreated' => 'not a date',
						'ratingValue' => '9',
						'reviewCount' => '3',
					],
				]
			)
		);

		$this->assertSame( 'Hello Post', $build['name'] );
		$this->assertArrayNotHasKey( 'dateCreated', $build );
		$this->assertArrayNotHasKey( 'aggregateRating', $build );
		$this->assertArrayNotHasKey( 'director', $build );
		$this->assertArrayNotHasKey( 'image', $build );
	}

	/**
	 * Test claimreview needs claim text.
	 */
	public function test_claimreview_needs_claim_text(): void {
		$piece = new ClaimReviewPiece();

		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [] ) ) );
		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [ 'type' => 'ClaimReview' ] ) ) );
		$this->assertFalse(
			$piece->isNeeded(
				$this->schemaContext(
					[
						'type'   => 'Article',
						'fields' => [ 'claimReviewed' => 'Earth is round' ],
					]
				)
			)
		);
	}

	/**
	 * Test claimreview builds full structure with rating defaults.
	 */
	public function test_claimreview_builds_full_structure_with_rating_defaults(): void {
		$ctx = $this->schemaContext(
			[
				'type'   => 'ClaimReview',
				'fields' => [
					'claimReviewed' => 'Earth is round',
					'ratingValue'   => '4',
				],
			]
		);

		$piece = new ClaimReviewPiece();

		$this->assertTrue( $piece->isNeeded( $ctx ) );

		$build = $piece->build( $ctx );

		$this->assertSame( 'ClaimReview', $build['@type'] );
		$this->assertSame( 'https://example.com/hello/#claimreview', $build['@id'] );
		$this->assertSame( 'Earth is round', $build['claimReviewed'] );
		$this->assertSame( 'Rating', $build['reviewRating']['@type'] );
		$this->assertSame( '4', $build['reviewRating']['ratingValue'] );
		$this->assertSame( '5', $build['reviewRating']['bestRating'] );
		$this->assertSame( '1', $build['reviewRating']['worstRating'] );
		$this->assertSame( 'https://example.com/#organization', $build['author']['@id'] );
		$this->assertSame( '2026-01-01T00:00:00+00:00', $build['datePublished'] );
		$this->assertSame( 'https://example.com/hello/', $build['url'] );
	}

	/**
	 * Test claimreview honors rating bounds and drops invalid.
	 */
	public function test_claimreview_honors_rating_bounds_and_drops_invalid(): void {
		$build = ( new ClaimReviewPiece() )->build(
			$this->schemaContext(
				[
					'type'   => 'ClaimReview',
					'fields' => [
						'claimReviewed' => 'Earth is round',
						'ratingValue'   => '3',
						'bestRating'    => '10',
						'worstRating'   => '0',
						'datePublished' => '2026-04-02 08:00:00',
					],
				]
			)
		);

		$this->assertSame( '10', $build['reviewRating']['bestRating'] );
		$this->assertSame( '0', $build['reviewRating']['worstRating'] );
		$this->assertSame( '2026-04-02T08:00:00+00:00', $build['datePublished'] );

		$noRating = ( new ClaimReviewPiece() )->build(
			$this->schemaContext(
				[
					'type'   => 'ClaimReview',
					'fields' => [
						'claimReviewed' => 'Earth is round',
						'ratingValue'   => 'excellent',
					],
				]
			)
		);

		$this->assertArrayNotHasKey( 'reviewRating', $noRating );
	}

	/**
	 * Test dataset not needed for wrong type or empty name.
	 */
	public function test_dataset_not_needed_for_wrong_type_or_empty_name(): void {
		$piece = new DatasetPiece();

		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [] ) ) );
		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [ 'type' => 'Movie' ] ) ) );

		Functions\when( 'get_the_title' )->justReturn( '' );
		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [ 'type' => 'Dataset' ] ) ) );
	}

	/**
	 * Test dataset builds distribution and validates license.
	 */
	public function test_dataset_builds_distribution_and_validates_license(): void {
		$ctx = $this->schemaContext(
			[
				'type'   => 'Dataset',
				'fields' => [
					'headline'           => 'Climate Records',
					'license'            => 'https://example.com/license',
					'distributionUrl'    => 'https://example.com/data.csv',
					'distributionFormat' => 'text/csv',
				],
			]
		);

		$piece = new DatasetPiece();

		$this->assertTrue( $piece->isNeeded( $ctx ) );

		$build = $piece->build( $ctx );

		$this->assertSame( 'Dataset', $build['@type'] );
		$this->assertSame( 'https://example.com/hello/#dataset', $build['@id'] );
		$this->assertSame( 'Climate Records', $build['name'] );
		$this->assertSame( 'https://example.com/license', $build['license'] );
		$this->assertSame( 'DataDownload', $build['distribution']['@type'] );
		$this->assertSame( 'https://example.com/data.csv', $build['distribution']['contentUrl'] );
		$this->assertSame( 'text/csv', $build['distribution']['encodingFormat'] );
	}

	/**
	 * Test dataset omits empty license and distribution.
	 */
	public function test_dataset_omits_empty_license_and_distribution(): void {
		$build = ( new DatasetPiece() )->build( $this->schemaContext( [ 'type' => 'Dataset' ] ) );

		$this->assertArrayNotHasKey( 'license', $build );
		$this->assertArrayNotHasKey( 'distribution', $build );
	}

	/**
	 * Test podcast not needed for wrong type or empty name.
	 */
	public function test_podcast_not_needed_for_wrong_type_or_empty_name(): void {
		$piece = new PodcastEpisodePiece();

		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [] ) ) );
		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [ 'type' => 'Dataset' ] ) ) );

		Functions\when( 'get_the_title' )->justReturn( '' );
		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [ 'type' => 'PodcastEpisode' ] ) ) );
	}

	/**
	 * Test podcast builds series media and duration.
	 */
	public function test_podcast_builds_series_media_and_duration(): void {
		$ctx = $this->schemaContext(
			[
				'type'   => 'PodcastEpisode',
				'fields' => [
					'headline'   => 'Episode One',
					'duration'   => '45',
					'seriesName' => 'My Show',
					'contentUrl' => 'https://example.com/ep1.mp3',
				],
			]
		);

		$piece = new PodcastEpisodePiece();

		$this->assertTrue( $piece->isNeeded( $ctx ) );

		$build = $piece->build( $ctx );

		$this->assertSame( 'PodcastEpisode', $build['@type'] );
		$this->assertSame( 'https://example.com/hello/#podcast', $build['@id'] );
		$this->assertSame( 'Episode One', $build['name'] );
		$this->assertSame( '2026-01-01T00:00:00+00:00', $build['datePublished'] );
		$this->assertSame( 'PT45M', $build['duration'] );
		$this->assertSame( 'PodcastSeries', $build['partOfSeries']['@type'] );
		$this->assertSame( 'My Show', $build['partOfSeries']['name'] );
		$this->assertSame( 'MediaObject', $build['associatedMedia']['@type'] );
		$this->assertSame( 'https://example.com/ep1.mp3', $build['associatedMedia']['contentUrl'] );
	}

	/**
	 * Test podcast omits empty series media and bad duration.
	 */
	public function test_podcast_omits_empty_series_media_and_bad_duration(): void {
		$build = ( new PodcastEpisodePiece() )->build(
			$this->schemaContext(
				[
					'type'   => 'PodcastEpisode',
					'fields' => [ 'duration' => 'sometime' ],
				]
			)
		);

		$this->assertArrayNotHasKey( 'partOfSeries', $build );
		$this->assertArrayNotHasKey( 'associatedMedia', $build );
		$this->assertArrayNotHasKey( 'duration', $build );
	}

	/**
	 * Test carousel not needed when empty or invalid.
	 */
	public function test_carousel_not_needed_when_empty_or_invalid(): void {
		$piece = new CarouselPiece();

		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [] ) ) );
		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [ 'type' => 'Carousel' ] ) ) );
		$this->assertFalse(
			$piece->isNeeded( $this->schemaContext( [ 'carousel' => [ [ 'name' => 'No type' ], 'junk' ] ] ) )
		);
	}

	/**
	 * Test carousel builds positions from one.
	 */
	public function test_carousel_builds_positions_from_one(): void {
		$ctx = $this->schemaContext(
			[
				'carousel' => [
					[
						'@type' => 'Article',
						'name'  => 'First',
					],
					[ 'name' => 'Missing type' ],
					'junk',
					[
						'@type' => 'Recipe',
						'name'  => 'Second',
					],
				],
			]
		);

		$piece = new CarouselPiece();

		$this->assertTrue( $piece->isNeeded( $ctx ) );

		$build = $piece->build( $ctx );

		$this->assertSame( 'ItemList', $build['@type'] );
		$this->assertSame( 'https://example.com/hello/#carousel', $build['@id'] );
		$this->assertCount( 2, $build['itemListElement'] );
		$this->assertSame( 1, $build['itemListElement'][0]['position'] );
		$this->assertSame( 'ListItem', $build['itemListElement'][0]['@type'] );
		$this->assertSame( 'Article', $build['itemListElement'][0]['item']['@type'] );
		$this->assertSame( 2, $build['itemListElement'][1]['position'] );
		$this->assertSame( 'Recipe', $build['itemListElement'][1]['item']['@type'] );
	}

	/**
	 * Test carousel caps at fifty items.
	 */
	public function test_carousel_caps_at_fifty_items(): void {
		$nodes = [];

		for ( $i = 1; $i <= 51; $i++ ) {
			$nodes[] = [
				'@type' => 'Article',
				'name'  => 'Node ' . $i,
			];
		}

		$build = ( new CarouselPiece() )->build( $this->schemaContext( [ 'carousel' => $nodes ] ) );

		$this->assertCount( 50, $build['itemListElement'] );
		$this->assertSame( 50, $build['itemListElement'][49]['position'] );
		$this->assertSame( 'Node 50', $build['itemListElement'][49]['item']['name'] );
	}

	/**
	 * Test qapage needs question and answer.
	 */
	public function test_qapage_needs_question_and_answer(): void {
		$piece = new QaPagePiece();

		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [] ) ) );
		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [ 'type' => 'QAPage' ] ) ) );
		$this->assertFalse(
			$piece->isNeeded(
				$this->schemaContext(
					[
						'type'   => 'QAPage',
						'fields' => [ 'question' => 'Only a question' ],
					]
				)
			)
		);
	}

	/**
	 * Test qapage builds plain answer without author.
	 */
	public function test_qapage_builds_plain_answer_without_author(): void {
		$ctx = $this->schemaContext(
			[
				'type'   => 'QAPage',
				'fields' => [
					'question' => 'What is this?',
					'answer'   => 'A test answer.',
				],
			]
		);

		$piece = new QaPagePiece();

		$this->assertTrue( $piece->isNeeded( $ctx ) );

		$build = $piece->build( $ctx );

		$this->assertSame( 'QAPage', $build['@type'] );
		$this->assertSame( 'https://example.com/hello/#qapage', $build['@id'] );
		$this->assertSame( 'Question', $build['mainEntity']['@type'] );
		$this->assertSame( 'What is this?', $build['mainEntity']['name'] );
		$this->assertSame( 'Answer', $build['mainEntity']['acceptedAnswer']['@type'] );
		$this->assertSame( 'A test answer.', $build['mainEntity']['acceptedAnswer']['text'] );
		$this->assertArrayNotHasKey( 'author', $build['mainEntity']['acceptedAnswer'] );
		$this->assertSame( 'Hello Post', $build['name'] );
	}

	/**
	 * Test qapage uses faq fallback and author ref.
	 */
	public function test_qapage_uses_faq_fallback_and_author_ref(): void {
		$ctx = $this->schemaContext(
			[
				'type'   => 'QAPage',
				'fields' => [ 'answerAuthor' => 'Jane' ],
				'faq'    => [
					'questions' => [
						[
							'question' => 'Fallback Q?',
							'answer'   => 'Fallback A.',
						],
					],
				],
			]
		);

		$this->stubAuthor( 7 );

		$piece = new QaPagePiece();

		$this->assertTrue( $piece->isNeeded( $ctx ) );

		$build = $piece->build( $ctx );

		$this->assertSame( 'Fallback Q?', $build['mainEntity']['name'] );
		$this->assertSame( 'Fallback A.', $build['mainEntity']['acceptedAnswer']['text'] );
		$this->assertSame( 'https://example.com/author/bob/#author', $build['mainEntity']['acceptedAnswer']['author']['@id'] );
	}

	/**
	 * Test itemlist needs type and valid items.
	 */
	public function test_itemlist_needs_type_and_valid_items(): void {
		$piece = new ItemListPiece();

		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [] ) ) );
		$this->assertFalse( $piece->isNeeded( $this->schemaContext( [ 'type' => 'ItemList' ] ) ) );
		$this->assertFalse(
			$piece->isNeeded(
				$this->schemaContext(
					[
						'type'  => 'Carousel',
						'items' => [ [ '@type' => 'Article' ] ],
					]
				)
			)
		);
	}

	/**
	 * Test itemlist builds positions and optional text.
	 */
	public function test_itemlist_builds_positions_and_optional_text(): void {
		$ctx = $this->schemaContext(
			[
				'type'   => 'ItemList',
				'fields' => [
					'headline'    => 'My List',
					'description' => 'List intro',
				],
				'items'  => [
					[
						'@type' => 'Article',
						'name'  => 'First',
					],
					[ 'name' => 'Missing type' ],
					[
						'@type' => 'Recipe',
						'name'  => 'Second',
					],
				],
			]
		);

		$piece = new ItemListPiece();

		$this->assertTrue( $piece->isNeeded( $ctx ) );

		$build = $piece->build( $ctx );

		$this->assertSame( 'ItemList', $build['@type'] );
		$this->assertSame( 'https://example.com/hello/#itemlist', $build['@id'] );
		$this->assertSame( 'My List', $build['name'] );
		$this->assertSame( 'List intro', $build['description'] );
		$this->assertCount( 2, $build['itemListElement'] );
		$this->assertSame( 1, $build['itemListElement'][0]['position'] );
		$this->assertSame( 2, $build['itemListElement'][1]['position'] );
	}

	/**
	 * Test itemlist caps at fifty items.
	 */
	public function test_itemlist_caps_at_fifty_items(): void {
		$nodes = [];

		for ( $i = 1; $i <= 51; $i++ ) {
			$nodes[] = [
				'@type' => 'Article',
				'name'  => 'Node ' . $i,
			];
		}

		$build = ( new ItemListPiece() )->build(
			$this->schemaContext(
				[
					'type'  => 'ItemList',
					'items' => $nodes,
				]
			)
		);

		$this->assertCount( 50, $build['itemListElement'] );
		$this->assertSame( 50, $build['itemListElement'][49]['position'] );
	}

	/**
	 * Test webpage omits speakable about mentions when absent.
	 */
	public function test_webpage_omits_speakable_about_mentions_when_absent(): void {
		$ctx   = $this->makeContext( $this->singularQuery() );
		$build = ( new WebpagePiece( new SettingsStore() ) )->build( $ctx );

		$this->assertArrayNotHasKey( 'speakable', $build );
		$this->assertArrayNotHasKey( 'about', $build );
		$this->assertArrayNotHasKey( 'mentions', $build );
	}

	/**
	 * Test webpage appends speakable about and mentions.
	 */
	public function test_webpage_appends_speakable_about_and_mentions(): void {
		$this->stubPostMeta(
			[
				'schema' => [
					'type'   => 'Article',
					'fields' => [
						'speakable' => ".entry-content\n.headline\n\n",
						'about'     => 'Climate, Science, ',
						'mentions'  => 'Jane Doe',
					],
				],
			]
		);

		$ctx   = $this->makeContext( $this->singularQuery() );
		$build = ( new WebpagePiece( new SettingsStore() ) )->build( $ctx );

		$this->assertSame( 'SpeakableSpecification', $build['speakable']['@type'] );
		$this->assertSame( [ '.entry-content', '.headline' ], $build['speakable']['cssSelector'] );
		$this->assertSame( 'Thing', $build['about'][0]['@type'] );
		$this->assertSame( 'Climate', $build['about'][0]['name'] );
		$this->assertSame( 'Science', $build['about'][1]['name'] );
		$this->assertCount( 2, $build['about'] );
		$this->assertSame( 'Jane Doe', $build['mentions'][0]['name'] );
	}

	/**
	 * Test webpage caps speakable and named things at twenty.
	 */
	public function test_webpage_caps_speakable_and_named_things_at_twenty(): void {
		$selectors = [];

		for ( $i = 1; $i <= 21; $i++ ) {
			$selectors[] = '.sel-' . $i;
		}

		$names = [];

		for ( $i = 1; $i <= 21; $i++ ) {
			$names[] = 'Name ' . $i;
		}

		$this->stubPostMeta(
			[
				'schema' => [
					'type'   => 'Article',
					'fields' => [
						'speakable' => implode( "\n", $selectors ),
						'about'     => implode( ',', $names ),
						'mentions'  => implode( ',', $names ),
					],
				],
			]
		);

		$ctx   = $this->makeContext( $this->singularQuery() );
		$build = ( new WebpagePiece( new SettingsStore() ) )->build( $ctx );

		$this->assertCount( 20, $build['speakable']['cssSelector'] );
		$this->assertCount( 20, $build['about'] );
		$this->assertCount( 20, $build['mentions'] );
	}

	/**
	 * Test node list helper keeps scalars and drops objects.
	 */
	public function test_node_list_helper_keeps_scalars_and_drops_objects(): void {
		$object       = new \stdClass();
		$object->name = 'Bad';

		$clean = MetaPayload::sanitizeNodeList(
			[
				[
					'@type' => 'Article',
					'name'  => 'Good',
					'count' => 3,
					'ok'    => true,
					'nil'   => null,
				],
				'junk',
				42,
				[ 'name' => 'No type kept here' ],
				$object,
			]
		);

		$this->assertCount( 2, $clean );
		$this->assertSame( 'Article', $clean[0]['@type'] );
		$this->assertSame( 3, $clean[0]['count'] );
		$this->assertTrue( $clean[0]['ok'] );
		$this->assertNull( $clean[0]['nil'] );
		$this->assertSame( 'No type kept here', $clean[1]['name'] );
	}

	/**
	 * Test node list helper enforces key and depth caps.
	 */
	public function test_node_list_helper_enforces_key_and_depth_caps(): void {
		$wide = [];

		for ( $i = 1; $i <= 55; $i++ ) {
			$wide[ 'key' . $i ] = 'v' . $i;
		}

		$clean = MetaPayload::sanitizeNodeList( [ $wide ], 50, 50 );

		$this->assertCount( 50, $clean[0] );

		$deep = [ '@type' => 'Article' ];
		$ref  = &$deep;

		for ( $i = 1; $i <= 7; $i++ ) {
			$ref['child'] = [];
			$ref          = &$ref['child'];
		}

		$ref['leaf'] = 'too deep';

		$cut = MetaPayload::sanitizeNodeList( [ $deep ] );

		$this->assertSame( 'Article', $cut[0]['@type'] );
		$this->assertArrayNotHasKey( 'leaf', $cut[0]['child']['child']['child']['child']['child'] ?? [] );
	}

	/**
	 * Test node list helper caps items and rejects non arrays.
	 */
	public function test_node_list_helper_caps_items_and_rejects_non_arrays(): void {
		$nodes = [];

		for ( $i = 1; $i <= 51; $i++ ) {
			$nodes[] = [
				'@type' => 'Article',
				'name'  => 'Node ' . $i,
			];
		}

		$this->assertCount( 50, MetaPayload::sanitizeNodeList( $nodes ) );
		$this->assertSame( [], MetaPayload::sanitizeNodeList( 'junk' ) );
		$this->assertSame( [], MetaPayload::sanitizeNodeList( [] ) );
	}

	/**
	 * Test payload sanitizes carousel and items with defaults compatible.
	 */
	public function test_payload_sanitizes_carousel_and_items_with_defaults_compatible(): void {
		$empty = MetaPayload::sanitize( [] );

		$this->assertSame( [], $empty['schema'] );

		$listShape = MetaPayload::sanitize( [ 'schema' => [] ] );

		$this->assertSame( [], $listShape['schema'] );

		$object = new \stdClass();

		$sanitized = MetaPayload::sanitize(
			[
				'schema' => [
					'type'     => 'Carousel',
					'carousel' => [
						[
							'@type' => 'Article',
							'name'  => 'Kept',
						],
						$object,
						'junk',
					],
					'items'    => [
						[
							'@type' => 'Recipe',
							'name'  => 'Dish',
						],
					],
				],
			]
		);

		$this->assertSame( 'Carousel', $sanitized['schema']['type'] );
		$this->assertCount( 1, $sanitized['schema']['carousel'] );
		$this->assertSame( 'Article', $sanitized['schema']['carousel'][0]['@type'] );
		$this->assertCount( 1, $sanitized['schema']['items'] );
		$this->assertSame( 'Recipe', $sanitized['schema']['items'][0]['@type'] );

		$missing = MetaPayload::sanitize( [ 'schema' => [ 'type' => 'Article' ] ] );

		$this->assertSame( [], $missing['schema']['carousel'] );
		$this->assertSame( [], $missing['schema']['items'] );
	}

	/**
	 * Test rest schema lists carousel and items.
	 */
	public function test_rest_schema_lists_carousel_and_items(): void {
		$schema = MetaPayload::restSchema();

		$this->assertArrayHasKey( 'carousel', $schema['properties']['schema']['properties'] );
		$this->assertArrayHasKey( 'items', $schema['properties']['schema']['properties'] );
	}

	/**
	 * Test generator wiring includes batch4 pieces.
	 */
	public function test_generator_wiring_includes_batch4_pieces(): void {
		$cases = [
			[
				'schema' => [
					'type'   => 'Movie',
					'fields' => [ 'headline' => 'Great Film' ],
				],
				'node'   => 'Movie',
			],
			[
				'schema' => [
					'type'   => 'ClaimReview',
					'fields' => [ 'claimReviewed' => 'Earth is round' ],
				],
				'node'   => 'ClaimReview',
			],
			[
				'schema' => [
					'type'   => 'Dataset',
					'fields' => [ 'headline' => 'Climate Records' ],
				],
				'node'   => 'Dataset',
			],
			[
				'schema' => [
					'type'   => 'PodcastEpisode',
					'fields' => [ 'headline' => 'Episode One' ],
				],
				'node'   => 'PodcastEpisode',
			],
			[
				'schema' => [
					'carousel' => [
						[
							'@type' => 'Article',
							'name'  => 'First',
						],
					],
				],
				'node'   => 'ItemList',
			],
			[
				'schema' => [
					'type'   => 'QAPage',
					'fields' => [
						'question' => 'Q?',
						'answer'   => 'A.',
					],
				],
				'node'   => 'QAPage',
			],
			[
				'schema' => [
					'type'  => 'ItemList',
					'items' => [
						[
							'@type' => 'Article',
							'name'  => 'First',
						],
					],
				],
				'node'   => 'ItemList',
			],
		];

		foreach ( $cases as $case ) {
			$ctx    = $this->schemaContext( $case['schema'] );
			$module = new SchemaModule( null, null, null, $ctx );
			$graph  = $module->getGenerator()->generate( $ctx )['@graph'];
			$types  = array_column( $graph, '@type' );

			$this->assertContains( $case['node'], $types, 'Missing ' . $case['node'] );
		}
	}
}
