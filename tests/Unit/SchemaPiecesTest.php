<?php
/**
 * Schema piece tests, gating truth tables, required fields, @id shapes.
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
use RankKernel\Modules\Schema\Pieces\ArticlePiece;
use RankKernel\Modules\Schema\Pieces\BreadcrumbPiece;
use RankKernel\Modules\Schema\Pieces\OrganizationPiece;
use RankKernel\Modules\Schema\Pieces\PersonPiece;
use RankKernel\Modules\Schema\Pieces\WebpagePiece;
use RankKernel\Modules\Schema\Pieces\WebsitePiece;
use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Schema Pieces Test.
 */
final class SchemaPiecesTest extends TestCase {
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
	 * @param array<string, mixed> $flags Method => return value overrides.
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
			'get_queried_object'    => (object) [ 'name' => 'News' ],
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
	 * @param WP_Query $query             Query.
	 * @param array    $settingsOverrides Settings Overrides.
	 * @return Context The result.
	 */
	private function makeContext( WP_Query $query, array $settingsOverrides = [] ): Context {
		$stored = array_merge( SettingsStore::defaults(), $settingsOverrides );
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ) use ( $stored ) {
				if ( 'rankkernel_settings' === $key ) {
					return $stored;
				}

				return $fallback;
			}
		);

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
	 * Test organization always needed with id and fallback name.
	 */
	public function test_organization_always_needed_with_id_and_fallback_name(): void {
		foreach ( [ 'post', 'home', 'search', '404', 'feed' ] as $type ) {
			$flags = [ 'is_singular' => false ];

			if ( 'post' === $type ) {
				$flags['is_singular'] = true;
			} elseif ( 'home' === $type ) {
				$flags['is_home'] = true;
			} elseif ( 'search' === $type ) {
				$flags['is_search'] = true;
			} elseif ( '404' === $type ) {
				$flags['is_404'] = true;
			} elseif ( 'feed' === $type ) {
				$flags['is_feed'] = true;
			}

			$ctx   = $this->makeContext( $this->makeQuery( $flags ) );
			$piece = new OrganizationPiece( new SettingsStore() );

			$this->assertTrue( $piece->isNeeded( $ctx ), "organization needed on {$type}" );
		}
		$ctx   = $this->makeContext( $this->singularQuery() );
		$build = ( new OrganizationPiece( new SettingsStore() ) )->build( $ctx );

		$this->assertSame( 'Organization', $build['@type'] );
		$this->assertSame( 'https://example.com/#organization', $build['@id'] );
		$this->assertSame( 'My Site', $build['name'] );
	}

	/**
	 * Test organization logo and sameas.
	 */
	public function test_organization_logo_and_sameas(): void {
		$ctx = $this->makeContext(
			$this->singularQuery(),
			[
				'org_name'   => 'Acme',
				'org_logo'   => 'https://example.com/logo.png',
				'org_sameas' => [ 'https://example.com/social', '' ],
			]
		);

		$build = ( new OrganizationPiece( new SettingsStore() ) )->build( $ctx );

		$this->assertSame( 'Acme', $build['name'] );
		$this->assertSame( 'ImageObject', $build['logo']['@type'] );
		$this->assertSame( 'https://example.com/logo.png', $build['logo']['url'] );
		$this->assertSame( [ 'https://example.com/social' ], $build['sameAs'] );
	}

	/**
	 * Test organization omits logo and sameas when empty.
	 */
	public function test_organization_omits_logo_and_sameas_when_empty(): void {
		$ctx   = $this->makeContext( $this->singularQuery() );
		$build = ( new OrganizationPiece( new SettingsStore() ) )->build( $ctx );

		$this->assertArrayNotHasKey( 'logo', $build );
		$this->assertArrayNotHasKey( 'sameAs', $build );
	}

	/**
	 * Test website always needed with search action.
	 */
	public function test_website_always_needed_with_search_action(): void {
		$ctx   = $this->makeContext( $this->singularQuery() );
		$piece = new WebsitePiece( new SettingsStore() );

		$this->assertTrue( $piece->isNeeded( $ctx ) );

		$build = $piece->build( $ctx );

		$this->assertSame( 'WebSite', $build['@type'] );
		$this->assertSame( 'https://example.com/#website', $build['@id'] );
		$this->assertSame( 'My Site', $build['name'] );
		$this->assertSame( 'https://example.com/', $build['url'] );
		$this->assertSame( 'SearchAction', $build['potentialAction']['@type'] );
		$this->assertStringContainsString( '{search_term_string}', $build['potentialAction']['target'] );
	}

	/**
	 * Test website search action off.
	 */
	public function test_website_search_action_off(): void {
		$ctx   = $this->makeContext( $this->singularQuery(), [ 'website_search_action' => false ] );
		$build = ( new WebsitePiece( new SettingsStore() ) )->build( $ctx );

		$this->assertArrayNotHasKey( 'potentialAction', $build );
	}

	/**
	 * Test webpage needed truth table.
	 */
	public function test_webpage_needed_truth_table(): void {
		$cases = [
			'singular' => [ [ 'is_singular' => true ], true ],
			'home'     => [ [ 'is_home' => true ], true ],
			'term'     => [ [ 'is_category' => true ], true ],
			'archive'  => [ [ 'is_archive' => true ], true ],
			'search'   => [ [ 'is_search' => true ], false ],
			'404'      => [ [ 'is_404' => true ], false ],
			'feed'     => [ [ 'is_feed' => true ], false ],
		];

		foreach ( $cases as $label => [ $flags, $expected ] ) {
			$ctx = $this->makeContext( $this->makeQuery( $flags ) );

			$this->assertSame( $expected, ( new WebpagePiece( new SettingsStore() ) )->isNeeded( $ctx ), "webpage on {$label}" );
		}
	}

	/**
	 * Test webpage singular shape.
	 */
	public function test_webpage_singular_shape(): void {
		$ctx   = $this->makeContext( $this->singularQuery() );
		$build = ( new WebpagePiece( new SettingsStore() ) )->build( $ctx );

		$this->assertSame( 'WebPage', $build['@type'] );
		$this->assertSame( 'https://example.com/hello/#webpage', $build['@id'] );
		$this->assertSame( 'https://example.com/hello/', $build['url'] );
		$this->assertArrayHasKey( 'name', $build );
		$this->assertArrayHasKey( 'datePublished', $build );
		$this->assertArrayHasKey( 'dateModified', $build );
		$this->assertSame( 'https://example.com/hello/#breadcrumb', $build['breadcrumb']['@id'] );
		$this->assertSame( 'en_US', $build['inLanguage'] );
	}

	/**
	 * Test webpage uses manual description override.
	 */
	public function test_webpage_uses_manual_description_override(): void {
		Functions\when( 'get_post_meta' )->alias(
			static fn ( int $id, string $key, bool $single ): mixed => [ // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_meta signature.
				'schema' => [ 'fields' => [ 'description' => 'Custom desc' ] ],
			]
		);

		$ctx   = $this->makeContext( $this->singularQuery() );
		$build = ( new WebpagePiece( new SettingsStore() ) )->build( $ctx );

		$this->assertSame( 'Custom desc', $build['description'] );
	}

	/**
	 * Test article uses manual description override.
	 */
	public function test_article_uses_manual_description_override(): void {
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $id, string $key, bool $single ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_meta signature.
				return [
					'title'  => '',
					'schema' => [ 'fields' => [ 'description' => 'Custom desc' ] ],
				];
			}
		);

		$ctx   = $this->makeContext( $this->singularQuery() );
		$build = ( new ArticlePiece( new SettingsStore() ) )->build( $ctx );

		$this->assertSame( 'Custom desc', $build['description'] );
	}

	/**
	 * Test person skipped on empty name.
	 */
	public function test_person_skipped_on_empty_name(): void {
		Functions\when( 'get_post_field' )->alias(
			static fn ( string $field, int $id ): string => 'post_author' === $field ? '7' : '' // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_field signature.
		);
		Functions\when( 'get_the_author_meta' )->justReturn( '' );

		$ctx   = $this->makeContext( $this->singularQuery() );
		$build = ( new PersonPiece() )->build( $ctx );

		$this->assertSame( [], $build );
	}

	/**
	 * Test organization skipped on empty name.
	 */
	public function test_organization_skipped_on_empty_name(): void {
		Functions\when( 'get_bloginfo' )->justReturn( '' );

		$ctx   = $this->makeContext( $this->singularQuery() );
		$build = ( new OrganizationPiece( new SettingsStore() ) )->build( $ctx );

		$this->assertSame( [], $build );
	}

	/**
	 * Test website skipped on empty name.
	 */
	public function test_website_skipped_on_empty_name(): void {
		Functions\when( 'get_bloginfo' )->justReturn( '' );

		$ctx   = $this->makeContext( $this->singularQuery() );
		$build = ( new WebsitePiece( new SettingsStore() ) )->build( $ctx );

		$this->assertSame( [], $build );
	}

	/**
	 * Test webpage archive is collection page.
	 */
	public function test_webpage_archive_is_collection_page(): void {
		$ctx   = $this->makeContext( $this->makeQuery( [ 'is_category' => true ], 9 ) );
		$build = ( new WebpagePiece( new SettingsStore() ) )->build( $ctx );

		$this->assertSame( 'CollectionPage', $build['@type'] );
		$this->assertSame( 'https://example.com/cat/news/#webpage', $build['@id'] );
	}

	/**
	 * Test breadcrumb skips home needs singular and term.
	 */
	public function test_breadcrumb_skips_home_needs_singular_and_term(): void {
		$homeCtx = $this->makeContext( $this->makeQuery( [ 'is_home' => true ] ) );
		$this->assertFalse( ( new BreadcrumbPiece() )->isNeeded( $homeCtx ) );

		$singularCtx = $this->makeContext( $this->singularQuery() );
		$this->assertTrue( ( new BreadcrumbPiece() )->isNeeded( $singularCtx ) );

		$termCtx = $this->makeContext( $this->makeQuery( [ 'is_category' => true ], 9 ) );
		$this->assertTrue( ( new BreadcrumbPiece() )->isNeeded( $termCtx ) );

		$searchCtx = $this->makeContext( $this->makeQuery( [ 'is_search' => true ] ) );
		$this->assertFalse( ( new BreadcrumbPiece() )->isNeeded( $searchCtx ) );
	}

	/**
	 * Test breadcrumb shape positions and id.
	 */
	public function test_breadcrumb_shape_positions_and_id(): void {
		$ctx   = $this->makeContext( $this->singularQuery() );
		$build = ( new BreadcrumbPiece() )->build( $ctx );

		$this->assertSame( 'BreadcrumbList', $build['@type'] );
		$this->assertSame( 'https://example.com/hello/#breadcrumb', $build['@id'] );
		$this->assertCount( 2, $build['itemListElement'] );
		$this->assertSame( 1, $build['itemListElement'][0]['position'] );
		$this->assertSame( 'My Site', $build['itemListElement'][0]['name'] );
		$this->assertSame( 'https://example.com/', $build['itemListElement'][0]['item'] );
		$this->assertSame( 2, $build['itemListElement'][1]['position'] );
		$this->assertSame( 'Hello Post', $build['itemListElement'][1]['name'] );
		$this->assertSame( 'https://example.com/hello/', $build['itemListElement'][1]['item'] );
	}

	/**
	 * Test breadcrumb trail filter supplies full trail.
	 */
	public function test_breadcrumb_trail_filter_supplies_full_trail(): void {
		$ctx = $this->makeContext( $this->singularQuery() );

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value ): mixed {
				if ( 'rankkernel/schema/breadcrumb_trail' === $hook ) {
					return [
						[
							'name' => 'Home',
							'url'  => 'https://example.com/',
						],
						[
							'name' => 'Sec',
							'url'  => 'https://example.com/sec/',
						],
						[
							'name' => 'Hello Post',
							'url'  => 'https://example.com/hello/',
						],
					];
				}

				return $value;
			}
		);

		$build = ( new BreadcrumbPiece() )->build( $ctx );

		$this->assertCount( 3, $build['itemListElement'] );
		$this->assertSame( 3, $build['itemListElement'][2]['position'] );
	}

	/**
	 * Test person needed singular with author and author archive.
	 */
	public function test_person_needed_singular_with_author_and_author_archive(): void {
		Functions\when( 'get_post_field' )->alias(
			static fn ( string $field, int $id ): string => 'post_author' === $field ? '7' : '' // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_field signature.
		);

		$singularCtx = $this->makeContext( $this->singularQuery() );
		$this->assertTrue( ( new PersonPiece() )->isNeeded( $singularCtx ) );

		Functions\when( 'get_post_field' )->justReturn( '' );
		$noAuthorCtx = $this->makeContext( $this->singularQuery() );
		$this->assertFalse( ( new PersonPiece() )->isNeeded( $noAuthorCtx ) );

		Functions\when( 'is_author' )->justReturn( true );
		$archiveCtx = $this->makeContext(
			$this->makeQuery(
				[
					'is_archive' => true,
					'is_author'  => true,
				],
				7
			)
		);
		$this->assertTrue( ( new PersonPiece() )->isNeeded( $archiveCtx ) );
	}

	/**
	 * Test person build shape and id.
	 */
	public function test_person_build_shape_and_id(): void {
		Functions\when( 'get_post_field' )->alias(
			static fn ( string $field, int $id ): string => 'post_author' === $field ? '7' : '' // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_field signature.
		);

		$ctx   = $this->makeContext( $this->singularQuery() );
		$build = ( new PersonPiece() )->build( $ctx );

		$this->assertSame( 'Person', $build['@type'] );
		$this->assertSame( 'https://example.com/author/bob/#author', $build['@id'] );
		$this->assertSame( 'Bob', $build['name'] );
		$this->assertSame( 'https://example.com/author/bob/', $build['url'] );
	}

	/**
	 * Test person fallback id without author url.
	 */
	public function test_person_fallback_id_without_author_url(): void {
		Functions\when( 'get_post_field' )->alias(
			static fn ( string $field, int $id ): string => 'post_author' === $field ? '7' : '' // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_field signature.
		);
		Functions\when( 'get_author_posts_url' )->justReturn( '' );

		$ctx   = $this->makeContext( $this->singularQuery() );
		$build = ( new PersonPiece() )->build( $ctx );

		$this->assertSame( 'https://example.com/hello/#author', $build['@id'] );
	}

	/**
	 * Test article needed only for posts not pages or attachments.
	 */
	public function test_article_needed_only_for_posts_not_pages_or_attachments(): void {
		$singularCtx = $this->makeContext( $this->singularQuery() );

		Functions\when( 'get_post_type' )->justReturn( 'post' );
		$this->assertTrue( ( new ArticlePiece() )->isNeeded( $singularCtx ) );

		Functions\when( 'get_post_type' )->justReturn( 'book' );
		$this->assertTrue( ( new ArticlePiece() )->isNeeded( $singularCtx ) );

		Functions\when( 'get_post_type' )->justReturn( 'page' );
		$this->assertFalse( ( new ArticlePiece() )->isNeeded( $singularCtx ) );

		Functions\when( 'get_post_type' )->justReturn( 'attachment' );
		$this->assertFalse( ( new ArticlePiece() )->isNeeded( $singularCtx ) );

		Functions\when( 'get_post_type' )->justReturn( 'post' );
		$homeCtx = $this->makeContext( $this->makeQuery( [ 'is_home' => true ] ) );
		$this->assertFalse( ( new ArticlePiece() )->isNeeded( $homeCtx ) );
	}

	/**
	 * Test article post is blog posting with required fields.
	 */
	public function test_article_post_is_blog_posting_with_required_fields(): void {
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_post_field' )->alias(
			static fn ( string $field, int $id ): string => 'post_author' === $field ? '7' : '' // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_field signature.
		);

		$ctx   = $this->makeContext( $this->singularQuery() );
		$build = ( new ArticlePiece() )->build( $ctx );

		$this->assertSame( 'BlogPosting', $build['@type'] );
		$this->assertSame( 'https://example.com/hello/#article', $build['@id'] );
		$this->assertSame( 'Hello Post', $build['headline'] );
		$this->assertArrayHasKey( 'datePublished', $build );
		$this->assertSame( 'https://example.com/author/bob/#author', $build['author']['@id'] );
		$this->assertSame( 'https://example.com/#organization', $build['publisher']['@id'] );
		$this->assertSame( 'https://example.com/hello/#webpage', $build['mainEntityOfPage']['@id'] );
	}

	/**
	 * Test article cpt is article type.
	 */
	public function test_article_cpt_is_article_type(): void {
		Functions\when( 'get_post_type' )->justReturn( 'book' );

		$ctx   = $this->makeContext( $this->singularQuery() );
		$build = ( new ArticlePiece() )->build( $ctx );

		$this->assertSame( 'Article', $build['@type'] );
	}
}
