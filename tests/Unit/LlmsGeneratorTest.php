<?php
/**
 * Llms.txt rendering and validation tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Robots\LlmsGenerator;

/**
 * Llms Generator Test.
 */
final class LlmsGeneratorTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test the document renders the H1, summary and curated sections.
	 */
	public function test_renders_heading_summary_and_content(): void {
		$content = "## Company\n\n- [About us](https://example.com/about): Who we are\n- [Pricing](https://example.com/pricing)\n";

		$out = ( new LlmsGenerator() )->render( 'Example Ltd', 'A short summary.', $content );

		$this->assertStringContainsString( "# Example Ltd\n", $out );
		$this->assertStringContainsString( '> A short summary.', $out );
		$this->assertStringContainsString( '## Company', $out );
		$this->assertStringContainsString( '- [About us](https://example.com/about): Who we are', $out );
		$this->assertStringEndsWith( "\n", $out );
	}

	/**
	 * Test a non-absolute link is rejected.
	 */
	public function test_relative_link_is_an_error(): void {
		$result = ( new LlmsGenerator() )->validate( "- [Relative](/about)\n" );

		$this->assertCount( 1, $result['errors'] );
	}

	/**
	 * Test a duplicate URL warns.
	 */
	public function test_duplicate_url_warns(): void {
		$content = "- [One](https://example.com/a)\n- [Two](https://example.com/a)\n";

		$result = ( new LlmsGenerator() )->validate( $content );

		$this->assertSame( [], $result['errors'] );
		$this->assertCount( 1, $result['warnings'] );
	}

	/**
	 * Test a clean content block passes.
	 */
	public function test_clean_content_passes(): void {
		$content = "## Company\n\n- [About](https://example.com/about): Who we are\n- [Contact](https://example.com/contact)\n";

		$result = ( new LlmsGenerator() )->validate( $content );

		$this->assertSame( [], $result['errors'] );
		$this->assertSame( [], $result['warnings'] );
	}

	/**
	 * Stub the WordPress surface the generated sections read.
	 *
	 * @param array<int, object>   $posts Posts to return from get_posts.
	 * @param array<int, object>   $terms Terms to return from get_terms.
	 * @param array<string, mixed> $meta  Post id to stored payload.
	 */
	private function stubContentSurface( array $posts, array $terms = [], array $meta = [] ): void {
		Functions\when( 'get_post_types' )->justReturn( [ 'post', 'page', 'attachment' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [ 'category' ] );
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\when( 'get_posts' )->justReturn( $posts );
		Functions\when( 'get_terms' )->justReturn( $terms );
		Functions\when( 'get_post_meta' )->alias(
			static fn ( int $id ): mixed => $meta[ $id ] ?? ''
		);
		Functions\when( 'get_permalink' )->alias(
			static fn ( int $id ): string => 'https://example.com/?p=' . $id
		);
		Functions\when( 'get_the_excerpt' )->alias(
			static fn ( int $id ): string => 'Excerpt for post ' . $id . '.'
		);
		Functions\when( 'wp_strip_all_tags' )->alias( static fn ( string $s ): string => strip_tags( $s ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'get_post_type_object' )->alias(
			static function ( string $type ): object {
				$names = [
					'post'       => 'Posts',
					'page'       => 'Pages',
					'attachment' => 'Media',
				];

				return (object) [ 'labels' => (object) [ 'name' => $names[ $type ] ?? $type ] ];
			}
		);
		Functions\when( 'get_taxonomy' )->alias(
			static function ( string $taxonomy ): object {
				return (object) [ 'labels' => (object) [ 'name' => 'category' === $taxonomy ? 'Categories' : ucfirst( $taxonomy ) ] ];
			}
		);
		Functions\when( 'get_term_link' )->alias(
			static function ( mixed $term ): string {
				$name = is_object( $term ) && property_exists( $term, 'name' ) ? (string) $term->name : '';

				return 'https://example.com/category/' . rawurlencode( $name ) . '/';
			}
		);
	}

	/**
	 * Make a post object for the content stubs.
	 *
	 * @param int    $id    Post id.
	 * @param string $title Post title.
	 * @return object The result.
	 */
	private function makePost( int $id, string $title ): object {
		return (object) [
			'ID'         => $id,
			'post_title' => $title,
		];
	}

	/**
	 * The generated document lists published posts under an H2 section (FUNC-01).
	 *
	 * With no curated content the shipped generator produced 13 bytes, a bare
	 * H1, so a site publishing llms.txt told crawlers it had no content. This
	 * is the ROADMAP 1.2 promise: H2 sections per post type with trimmed
	 * excerpts.
	 */
	public function test_auto_sections_list_posts_under_a_heading(): void {
		$this->stubContentSurface( [ $this->makePost( 1, 'Hello world' ), $this->makePost( 2, 'Second post' ) ] );

		$out = ( new LlmsGenerator() )->autoSections();

		$this->assertStringContainsString( '## Posts', $out );
		$this->assertStringContainsString( '- [Hello world](https://example.com/?p=1): Excerpt for post 1.', $out );
		$this->assertStringContainsString( '- [Second post](https://example.com/?p=2): Excerpt for post 2.', $out );
	}

	/**
	 * Attachments are never listed (FUNC-01).
	 *
	 * An attachment page carries no text of its own. The sitemap excludes them
	 * and the head noindexes them, so the document must not advertise them.
	 */
	public function test_auto_sections_exclude_attachments(): void {
		$this->stubContentSurface( [ $this->makePost( 1, 'Hello world' ) ] );

		$out = ( new LlmsGenerator() )->autoSections();

		$this->assertStringNotContainsString( '## Media', $out );
		$this->assertStringNotContainsString( 'attachment', $out );
	}

	/**
	 * A noindex entry is not advertised (FUNC-01).
	 *
	 * Advertising a page the operator asked not to be indexed would make the
	 * document contradict the head directives.
	 */
	public function test_auto_sections_exclude_noindex_entries(): void {
		$this->stubContentSurface(
			[ $this->makePost( 1, 'Public post' ), $this->makePost( 2, 'Hidden post' ) ],
			[],
			[ 2 => [ 'robots' => [ 'index' => false ] ] ]
		);

		$out = ( new LlmsGenerator() )->autoSections();

		$this->assertStringContainsString( 'Public post', $out );
		$this->assertStringNotContainsString( 'Hidden post', $out );
	}

	/**
	 * Taxonomies get their own H2 section (FUNC-01).
	 *
	 * @return void
	 */
	public function test_auto_sections_include_taxonomy_headings(): void {
		$term = (object) [ 'name' => 'Guides' ];

		$this->stubContentSurface( [ $this->makePost( 1, 'Hello world' ) ], [ $term ] );

		$out = ( new LlmsGenerator() )->autoSections();

		$this->assertStringContainsString( '## Categories', $out );
		$this->assertStringContainsString( '- [Guides](https://example.com/category/Guides/)', $out );
	}

	/**
	 * Pull the excerpt text back out of a rendered list item.
	 *
	 * @param string $markdown Rendered auto sections.
	 * @return string The excerpt, empty when there is none.
	 */
	private function excerptOf( string $markdown ): string {
		$line = '';

		foreach ( explode( "\n", $markdown ) as $candidate ) {
			if ( str_starts_with( $candidate, '- [' ) ) {
				$line = $candidate;
			}
		}

		$position = strpos( $line, '): ' );

		return false === $position ? '' : substr( $line, $position + 3 );
	}

	/**
	 * The excerpt is stripped and trimmed to the cap (FUNC-01).
	 *
	 * @return void
	 */
	public function test_auto_sections_trim_and_strip_excerpts(): void {
		Functions\when( 'get_the_excerpt' )->justReturn( '<p>' . str_repeat( 'word ', 200 ) . "</p>\n" );

		$posts = [ $this->makePost( 1, 'Long post' ) ];

		Functions\when( 'get_post_types' )->justReturn( [ 'post' ] );
		Functions\when( 'get_taxonomies' )->justReturn( [] );
		Functions\when( 'get_posts' )->justReturn( $posts );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/?p=1' );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn ( string $s ): string => strip_tags( $s ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'get_post_type_object' )->alias(
			static fn (): object => (object) [ 'labels' => (object) [ 'name' => 'Posts' ] ]
		);

		$out = ( new LlmsGenerator() )->autoSections();

		$this->assertStringNotContainsString( '<p>', $out, 'the excerpt must be stripped of markup' );
		$this->assertStringNotContainsString( "\n", $this->excerptOf( $out ), 'the excerpt must be collapsed to one line' );

		$excerpt = $this->excerptOf( $out );

		$this->assertStringEndsWith( '...', $excerpt, 'a long excerpt must be trimmed' );
		$this->assertLessThanOrEqual(
			LlmsGenerator::MAX_EXCERPT_CHARS + 3,
			strlen( $excerpt ),
			'the trimmed excerpt must respect the cap'
		);
	}

	/**
	 * A title containing Markdown cannot break the link it sits in (FUNC-01).
	 *
	 * @return void
	 */
	public function test_auto_sections_escape_markdown_in_titles(): void {
		$this->stubContentSurface( [ $this->makePost( 1, 'A [bracket] and (paren)' ) ] );

		$out = ( new LlmsGenerator() )->autoSections();

		$this->assertStringContainsString( 'A \\[bracket\\] and \\(paren\\)', $out );
	}

	/**
	 * Nothing selectable produces no sections at all (FUNC-01).
	 *
	 * @return void
	 */
	public function test_auto_sections_are_empty_when_nothing_is_selectable(): void {
		$this->stubContentSurface( [] );

		$this->assertSame( '', ( new LlmsGenerator() )->autoSections() );
	}
}
