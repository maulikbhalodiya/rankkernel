<?php
/**
 * MetadataBox tests, render, save, reset, localized state and wiring.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Admin\MetadataBox;
use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Metadata\TagsReplacer;
use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Metadata Box Test.
 */
final class MetadataBoxTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Option storage backing get option.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Stored payload returned by get post meta.
	 *
	 * @var mixed
	 */
	private mixed $storedMeta = [];

	/**
	 * Last payload captured from update post meta.
	 *
	 * @var mixed
	 */
	private mixed $savedMeta = null;

	/**
	 * Settings store shared with the box under test.
	 *
	 * @var SettingsStore
	 */
	private SettingsStore $settings;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_FILE' ) ) {
			define( 'RANKKERNEL_FILE', '/tmp/rankkernel.php' );
		}

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		if ( ! defined( 'RANKKERNEL_VERSION' ) ) {
			define( 'RANKKERNEL_VERSION', '0.1.0-test' );
		}

		$this->options    = [ 'rankkernel_settings' => [ 'separator' => '-' ] ];
		$this->storedMeta = [];
		$this->savedMeta  = null;
		$this->settings   = new SettingsStore();

		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'sanitize_key' )->alias( static fn ( string $v ): string => strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $v ) ) );
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $v ): string => filter_var( $v, FILTER_SANITIZE_URL ) ? filter_var( $v, FILTER_SANITIZE_URL ) : '' );
		Functions\when( 'absint' )->alias( static fn ( mixed $v ): int => abs( (int) $v ) );
		Functions\when( 'esc_html' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $v, string $d = '' ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress esc_html__ signature.
		Functions\when( 'esc_attr' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_textarea' )->alias( static fn ( string $v ): string => htmlspecialchars( $v, ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_url' )->alias( static fn ( string $v ): string => filter_var( $v, FILTER_SANITIZE_URL ) ? filter_var( $v, FILTER_SANITIZE_URL ) : $v );
		Functions\when( '__' )->alias( static fn ( string $v, string $d = '' ): string => $v ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress __ signature.
		Functions\when( 'checked' )->alias(
			static fn ( mixed $a, mixed $b, bool $display = true ): string => ( (string) $a === (string) $b && '' !== (string) $a ) || ( true === $a && true === $b ) ? 'checked="checked"' : '' // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress checked signature.
		);
		Functions\when( 'selected' )->alias(
			static fn ( mixed $a, mixed $b, bool $display = true ): string => ( (string) $a === (string) $b && '' !== (string) $a ) ? 'selected="selected"' : '' // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress selected signature.
		);

		$unslash = static function ( mixed $v ) use ( &$unslash ): mixed {
			if ( is_array( $v ) ) {
				$out = [];

				foreach ( $v as $k => $item ) {
					$out[ $k ] = $unslash( $item );
				}

				return $out;
			}

			return is_string( $v ) ? stripslashes( $v ) : $v;
		};

		Functions\when( 'wp_unslash' )->alias( $unslash );
		Functions\when( 'wp_nonce_field' )->justReturn( '' );
		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'get_post_meta' )->alias(
			function ( int $id, string $key, bool $single ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_meta signature.
				return $this->storedMeta;
			}
		);
		Functions\when( 'update_post_meta' )->alias(
			function ( int $id, string $key, mixed $value ): bool {
				$this->savedMeta  = $value;
				$this->storedMeta = $value;

				return true;
			}
		);
		Functions\when( 'wp_is_post_autosave' )->justReturn( false );
		Functions\when( 'wp_is_post_revision' )->justReturn( false );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'wp_die' )->alias(
			static function ( string $msg = '' ): void {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test-only exception message, never rendered to the browser.
				throw new \RuntimeException( 'wp_die: ' . $msg );
			}
		);
		Functions\when( 'get_permalink' )->alias( static fn ( int $id ): string => 'https://example.com/hello/' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_permalink signature.
		Functions\when( 'get_bloginfo' )->alias( static fn ( string $show = '' ): string => 'Example Site' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_bloginfo signature.
		Functions\when( 'site_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com' . $p );
		Functions\when( 'home_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com' . $p );
		Functions\when( 'rest_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com/wp-json/' . ltrim( $p, '/' ) );
		Functions\when( 'get_post_type' )->alias( static fn ( mixed $p = null ): string => 'post' ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_post_type signature.
		// The metabox gate calls get_current_screen. Stub it here so the
		// default is the Classic Editor path and the suite stays independent
		// of any get_current_screen patch left by a previously run test.
		Functions\when( 'get_current_screen' )->justReturn( null );
		Functions\when( 'get_post_type_object' )->alias(
			static function ( string $type ): object {
				return (object) [
					'name'      => $type,
					'rest_base' => 'posts',
				];
			}
		);
		Functions\when( 'get_query_var' )->justReturn( 0 );
		Functions\when( 'get_the_title' )->justReturn( 'Hello post' );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'get_the_excerpt' )->justReturn( '' );
		Functions\when( 'get_post_field' )->justReturn( '' );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn ( string $s ): string => strip_tags( $s ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- stub asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'get_the_date' )->justReturn( '' );
		Functions\when( 'get_the_category' )->justReturn( [] );
		Functions\when( 'get_the_author' )->justReturn( '' );
		Functions\when( 'date_i18n' )->alias( static fn ( string $f, mixed $t = null ): string => gmdate( $f ) ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress date_i18n signature.
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();

		$_POST = [];
		$_GET  = [];
	}

	/**
	 * Build a Context with a singular query for the given id.
	 *
	 * @param int $postId Post id.
	 * @return Context The result.
	 */
	private function makeContext( int $postId ): Context {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_singular' )->andReturn( true )->byDefault();
		$query->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'get_queried_object_id' )->andReturn( $postId )->byDefault();
		$query->shouldReceive( 'get' )->andReturn( 0 )->byDefault();

		return new Context( $query, $this->settings, new TagsReplacer() );
	}

	/**
	 * Build the box under test with an injected context.
	 *
	 * @return MetadataBox The result.
	 */
	private function newBox(): MetadataBox {
		return new MetadataBox(
			$this->settings,
			new TagsReplacer(),
			fn ( int $id ): Context => $this->makeContext( $id )
		);
	}

	/**
	 * A minimal classic editor POST body carrying the metabox fields.
	 *
	 * @return array<string, mixed>
	 */
	private function classicPost(): array {
		return [
			'rankkernel_meta_nonce'  => 'valid',
			'rankkernel_meta_fields' => '1',
			'rankkernel_meta_title'  => 'My title',
		];
	}

	/**
	 * Render the box for a post and return the markup.
	 *
	 * @param int $postId Post id.
	 * @return string The result.
	 */
	private function renderBox( int $postId = 7 ): string {
		$box  = $this->newBox();
		$post = (object) [ 'ID' => $postId ];

		ob_start();
		$box->renderBox( $post );

		return (string) ob_get_clean();
	}

	/**
	 * Save writes title, description, canonical and robots through the sanitizer.
	 */
	public function test_save_writes_fields_through_sanitizer(): void {
		$_POST = [
			'rankkernel_meta_nonce'       => 'valid',
			'rankkernel_meta_fields'      => '1',
			'rankkernel_meta_title'       => '<b>My title</b>',
			'rankkernel_meta_description' => 'My description',
			'rankkernel_meta_canonical'   => 'https://example.com/canonical/',
			'rankkernel_meta_robots'      => [
				'noindex'     => '1',
				'nosnippet'   => '1',
				'max_snippet' => '20',
			],
		];

		$this->newBox()->handleSave( 11, (object) [ 'ID' => 11 ] );

		$this->assertIsArray( $this->savedMeta );
		$this->assertSame( 'My title', $this->savedMeta['title'] );
		$this->assertSame( 'My description', $this->savedMeta['description'] );
		$this->assertSame( 'https://example.com/canonical/', $this->savedMeta['canonical'] );
		$this->assertFalse( $this->savedMeta['robots']['index'] );
		$this->assertTrue( $this->savedMeta['robots']['follow'] );
		$this->assertTrue( $this->savedMeta['robots']['nosnippet'] );
		$this->assertSame( 20, $this->savedMeta['robots']['max_snippet'] );
	}

	/**
	 * The Classic save path normalizes blank or malformed robots budgets.
	 *
	 * The metabox posts a max_image_preview select whose empty option is an
	 * empty string, so the save path must normalize blanks and malformed
	 * strings to null rather than 0, matching the REST path.
	 */
	public function test_classic_save_normalizes_blank_robots_budgets_to_null(): void {
		$_POST = [
			'rankkernel_meta_nonce'  => 'valid',
			'rankkernel_meta_fields' => '1',
			'rankkernel_meta_robots' => [
				'max_snippet'       => '',
				'max_video_preview' => 'abc',
				'max_image_preview' => '',
			],
		];

		$this->newBox()->handleSave( 11, (object) [ 'ID' => 11 ] );

		$this->assertIsArray( $this->savedMeta );
		$this->assertNull( $this->savedMeta['robots']['max_snippet'] );
		$this->assertNull( $this->savedMeta['robots']['max_video_preview'] );
		$this->assertNull( $this->savedMeta['robots']['max_image_preview'] );
	}

	/**
	 * Save preserves unrelated subtrees that were not submitted.
	 */
	public function test_save_preserves_unrelated_subtrees(): void {
		$this->storedMeta = [
			'canonical'      => 'https://example.com/keep/',
			'robots'         => [
				'index'  => true,
				'follow' => true,
			],
			'focus_keywords' => [ 'keep' ],
			'schema'         => [
				'type'   => 'Article',
				'fields' => [ 'headline' => 'Old headline' ],
			],
			'flags'          => [ 'pillar' => true ],
		];

		$_POST = $this->classicPost();

		$this->newBox()->handleSave( 11, (object) [ 'ID' => 11 ] );

		$this->assertIsArray( $this->savedMeta );
		$this->assertSame( 'My title', $this->savedMeta['title'] );
		$this->assertSame( 'https://example.com/keep/', $this->savedMeta['canonical'] );
		$this->assertSame( [ 'keep' ], $this->savedMeta['focus_keywords'] );
		$this->assertSame( 'Article', $this->savedMeta['schema']['type'] );
		$this->assertSame( 'Old headline', $this->savedMeta['schema']['fields']['headline'] );
		$this->assertTrue( $this->savedMeta['flags']['pillar'] );
	}

	/**
	 * Save is blocked without the edit post capability.
	 */
	public function test_save_blocked_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'update_post_meta' )->never();

		$_POST = $this->classicPost();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		$this->newBox()->handleSave( 11, (object) [ 'ID' => 11 ] );
	}

	/**
	 * Save is blocked on a bad nonce.
	 */
	public function test_save_blocked_on_bad_nonce(): void {
		Functions\when( 'check_admin_referer' )->justReturn( false );
		Functions\expect( 'update_post_meta' )->never();

		$_POST = $this->classicPost();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		$this->newBox()->handleSave( 11, (object) [ 'ID' => 11 ] );
	}

	/**
	 * Save is skipped when the metabox fields are absent from POST.
	 *
	 * That is the Gutenberg path, where the REST meta field is the writer and
	 * the classic save must not run. The capability is set false to prove the
	 * fields guard returns before the capability check instead of calling
	 * wp_die() on a request that never carried the metabox.
	 */
	public function test_save_skipped_when_metabox_fields_absent(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\expect( 'update_post_meta' )->never();

		$_POST = [ 'rankkernel_meta_nonce' => 'valid' ];

		$this->newBox()->handleSave( 11, (object) [ 'ID' => 11 ] );

		$this->assertNull( $this->savedMeta );
	}

	/**
	 * Reset clears only the title override and leaves everything else intact.
	 */
	public function test_reset_clears_only_title_override(): void {
		$this->storedMeta = [
			'title'          => 'Custom title',
			'description'    => 'Custom description',
			'focus_keywords' => [ 'keep' ],
			'schema'         => [
				'type'   => 'Article',
				'fields' => [ 'headline' => 'Old headline' ],
			],
			'flags'          => [ 'pillar' => true ],
		];

		$_POST = [
			'rankkernel_meta_nonce'       => 'valid',
			'rankkernel_meta_fields'      => '1',
			'rankkernel_meta_description' => 'Custom description',
			'rankkernel_meta_reset'       => [ 'title' => '1' ],
		];

		$this->newBox()->handleSave( 11, (object) [ 'ID' => 11 ] );

		$this->assertIsArray( $this->savedMeta );
		$this->assertSame( '', $this->savedMeta['title'] );
		$this->assertSame( 'Custom description', $this->savedMeta['description'] );
		$this->assertSame( [ 'keep' ], $this->savedMeta['focus_keywords'] );
		$this->assertSame( 'Article', $this->savedMeta['schema']['type'] );
		$this->assertTrue( $this->savedMeta['flags']['pillar'] );
	}

	/**
	 * Malformed stored payload fails safe and the view still renders.
	 */
	public function test_malformed_payload_fails_safe_and_renders(): void {
		$this->storedMeta = '{broken json';

		$out = $this->renderBox();

		$this->assertStringContainsString( 'name="rankkernel_meta_title"', $out );
		$this->assertStringContainsString( 'General', $out );
	}

	/**
	 * Malformed subtree types fail safe and the view still renders.
	 */
	public function test_malformed_subtree_types_fail_safe(): void {
		$this->storedMeta = [
			'robots' => 'not-an-array',
			'og'     => 'not-an-array',
		];

		$out = $this->renderBox();

		$this->assertStringContainsString( 'name="rankkernel_meta_robots[noindex]"', $out );
	}

	/**
	 * Localized preview returns the template, then the override, then the template.
	 */
	public function test_localized_preview_three_states(): void {
		$box = $this->newBox();

		$this->storedMeta = [];
		$initial          = $box->localizedState( 7 );
		$this->assertSame( 'Hello post - Example Site', $initial['templates']['title'] );

		$this->storedMeta = [
			'title'       => 'Custom title',
			'description' => 'Custom description',
		];
		$overridden       = $box->localizedState( 7 );
		$this->assertSame( 'Custom title', $overridden['templates']['title'] );
		$this->assertSame( 'Custom description', $overridden['templates']['description'] );

		$_POST = [
			'rankkernel_meta_nonce'       => 'valid',
			'rankkernel_meta_fields'      => '1',
			'rankkernel_meta_description' => 'Custom description',
			'rankkernel_meta_reset'       => [ 'title' => '1' ],
		];
		$box->handleSave( 7, (object) [ 'ID' => 7 ] );

		$reset = $box->localizedState( 7 );
		$this->assertSame( 'Hello post - Example Site', $reset['templates']['title'] );
		$this->assertSame( 'Custom description', $reset['templates']['description'] );
	}

	/**
	 * The localized contract exposes every documented key.
	 */
	public function test_localized_contract_keys(): void {
		$this->storedMeta = [];

		$state = $this->newBox()->localizedState( 7 );

		$expectedKeys = [ 'postId', 'permalink', 'siteUrl', 'siteName', 'homeUrl', 'templates', 'tokens', 'tokenLabels', 'limits', 'defaults', 'strings', 'restPath' ];

		foreach ( $expectedKeys as $key ) {
			$this->assertArrayHasKey( $key, $state );
		}

		$this->assertSame( 7, $state['postId'] );
		$this->assertSame( 60, $state['limits']['title'] );
		$this->assertSame( 160, $state['limits']['description'] );
		$this->assertSame( 'Hello post', $state['tokens']['title'] );
		$this->assertSame( 'Example Site', $state['tokens']['sitename'] );
		$this->assertSame( '-', $state['tokens']['sep'] );
	}

	/**
	 * Token labels only cover tokens the backend can resolve.
	 */
	public function test_token_labels_only_cover_resolvable_tokens(): void {
		$this->storedMeta = [];

		$state = $this->newBox()->localizedState( 7 );

		$expected = [ '%%title%%', '%%sitename%%', '%%sep%%', '%%excerpt%%', '%%date%%', '%%author%%', '%%category%%', '%%page%%', '%%currentdate%%' ];

		$this->assertSame( $expected, array_keys( $state['tokenLabels'] ) );
		$this->assertSame( $expected, array_map( static fn ( string $name ): string => '%%' . $name . '%%', array_keys( $state['tokens'] ) ) );
	}

	/**
	 * The advertised tokens equal the tokens the backend resolves exactly.
	 *
	 * TagsReplacer::SUPPORTED_TOKENS is the canonical backend list, so the
	 * editor can never advertise a token the backend cannot resolve and can
	 * never hide one it can.
	 */
	public function test_token_labels_match_tags_replacer_supported_tokens(): void {
		$this->storedMeta = [];

		$state      = $this->newBox()->localizedState( 7 );
		$advertised = array_map(
			static fn ( string $token ): string => trim( $token, '%' ),
			array_keys( $state['tokenLabels'] )
		);

		$this->assertSame( TagsReplacer::SUPPORTED_TOKENS, $advertised );
	}

	/**
	 * A singular post with an empty stored override resolves %%title%%.
	 *
	 * The editor builds a synthetic singular query. WP_Query rebuilds the
	 * queried id through get_queried_object(), which resets it to null when
	 * the queried object is unset, so the id was lost and %%title%% came
	 * back empty while %%sep%% and %%sitename%% resolved. The synthetic
	 * query must seed the post object so the title token resolves.
	 */
	public function test_singular_post_with_empty_override_resolves_title_token(): void {
		Functions\when( 'get_post' )->justReturn(
			(object) [
				'ID'         => 78771,
				'post_title' => 'Real Post Title',
				'post_type'  => 'post',
			]
		);
		Functions\when( 'get_the_title' )->justReturn( 'Real Post Title' );

		$this->storedMeta = [];

		$box = new MetadataBox(
			$this->settings,
			new TagsReplacer(),
			null,
			static function (): WP_Query {
				return new class() extends WP_Query {
					/**
					 * Queried object id.
					 *
					 * @var int|null
					 */
					public $queried_object_id = 0;

					/**
					 * Queried object.
					 *
					 * @var object|null
					 */
					public $queried_object = null;

					/**
					 * Queried post.
					 *
					 * @var object|null
					 */
					public $post = null;

					/**
					 * Whether the query is singular.
					 *
					 * @var bool
					 */
					public $is_singular = false;

					/**
					 * Whether the query is singular, mirroring WP_Query.
					 *
					 * @param mixed $post Optional post type filter, unused here.
					 * @return bool The result.
					 */
					public function is_singular( $post = '' ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- mirrors the WordPress WP_Query signature.
						return (bool) $this->is_singular;
					}

					/**
					 * Get the queried object, mirroring WP_Query.
					 *
					 * @return mixed The result.
					 */
					public function get_queried_object(): mixed {
						if ( isset( $this->queried_object ) ) {
							return $this->queried_object;
						}

						$this->queried_object    = null;
						$this->queried_object_id = null;

						return null;
					}

					/**
					 * Get the queried object id, mirroring WP_Query.
					 *
					 * @return int The result.
					 */
					public function get_queried_object_id(): int {
						$this->get_queried_object();

						return isset( $this->queried_object_id ) ? (int) $this->queried_object_id : 0;
					}
				};
			}
		);

		$resolved = $box->effectiveTitleFor( 78771 );

		$this->assertSame( 'Real Post Title - Example Site', $resolved );
		$this->assertNotSame( '', $resolved );
		$this->assertStringNotContainsString( '%%title%%', $resolved );

		// The frontend resolves the identical template through the same
		// primitives, so the two surfaces cannot disagree on this post.
		$frontend = new Context( $this->frontendQuery( 78771 ), $this->settings, new TagsReplacer() );

		$this->assertSame(
			$frontend->resolved( 'title_template', '%%title%% %%sep%% %%sitename%%' ),
			$resolved
		);
	}

	/**
	 * Build a frontend shaped singular query for the given post id.
	 *
	 * Mirrors the global $wp_query on a singular request: the queried
	 * object and its id are both populated.
	 *
	 * @param int $postId Post id.
	 * @return WP_Query The result.
	 */
	private function frontendQuery( int $postId ): WP_Query {
		$query = Mockery::mock( WP_Query::class );
		$query->shouldReceive( 'is_singular' )->andReturn( true )->byDefault();
		$query->shouldReceive( 'is_search' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_404' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_feed' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_preview' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_category' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tag' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_tax' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_home' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_front_page' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'is_archive' )->andReturn( false )->byDefault();
		$query->shouldReceive( 'get_queried_object_id' )->andReturn( $postId )->byDefault();
		$query->shouldReceive( 'get_queried_object' )->andReturn( (object) [ 'ID' => $postId ] )->byDefault();
		$query->shouldReceive( 'get' )->andReturn( 0 )->byDefault();

		return $query;
	}

	/**
	 * The view renders field names, tab structure, reset and robots controls.
	 */
	public function test_view_renders_fields_tabs_reset_and_robots(): void {
		$this->storedMeta = [];

		$out = $this->renderBox();

		$this->assertStringContainsString( 'name="rankkernel_meta_title"', $out );
		$this->assertStringContainsString( 'id="rankkernel-meta-title"', $out );
		$this->assertStringContainsString( 'name="rankkernel_meta_description"', $out );
		$this->assertStringContainsString( 'id="rankkernel-meta-description"', $out );
		$this->assertStringContainsString( 'name="rankkernel_meta_fields"', $out );

		$this->assertStringContainsString( 'data-rk-tab="general"', $out );
		$this->assertStringContainsString( 'data-rk-tab="social"', $out );
		$this->assertStringContainsString( 'data-rk-tab="advanced"', $out );
		$this->assertStringContainsString( 'data-rk-panel="general"', $out );
		$this->assertStringContainsString( 'data-rk-panel="social"', $out );
		$this->assertStringContainsString( 'data-rk-panel="advanced"', $out );

		$this->assertStringContainsString( 'name="rankkernel_meta_reset[title]"', $out );
		$this->assertStringContainsString( 'id="rankkernel-meta-reset-title"', $out );
		$this->assertStringContainsString( 'name="rankkernel_meta_reset[description]"', $out );
		$this->assertStringContainsString( 'id="rankkernel-meta-reset-description"', $out );

		$this->assertStringContainsString( 'name="rankkernel_meta_robots[noindex]"', $out );
		$this->assertStringContainsString( 'id="rankkernel-meta-robots-noindex"', $out );
		$this->assertStringContainsString( 'name="rankkernel_meta_robots[nofollow]"', $out );
		$this->assertStringContainsString( 'id="rankkernel-meta-robots-nofollow"', $out );
		$this->assertStringContainsString( 'name="rankkernel_meta_robots[noarchive]"', $out );
		$this->assertStringContainsString( 'name="rankkernel_meta_robots[nosnippet]"', $out );
		$this->assertStringContainsString( 'name="rankkernel_meta_robots[noimageindex]"', $out );
		$this->assertStringContainsString( 'name="rankkernel_meta_robots[max_snippet]"', $out );
		$this->assertStringContainsString( 'name="rankkernel_meta_robots[max_image_preview]"', $out );
		$this->assertStringContainsString( 'name="rankkernel_meta_robots[max_video_preview]"', $out );

		$this->assertStringContainsString( 'name="rankkernel_meta_canonical"', $out );
		$this->assertStringContainsString( 'name="rankkernel_meta_og[title]"', $out );
		$this->assertStringContainsString( 'name="rankkernel_meta_twitter[card]"', $out );
		$this->assertStringContainsString( 'data-rk-token="%%title%%"', $out );
	}

	/**
	 * The Schema tab validation notice uses the shared notice component.
	 *
	 * Classic shares the design system with the sidebar, so validation
	 * feedback must not fall back to raw WordPress admin notices.
	 */
	public function test_schema_validation_notice_uses_shared_component(): void {
		$this->storedMeta = [
			'schema' => [
				'type'   => 'Event',
				'fields' => [],
			],
		];

		$warn = $this->renderBox();
		$this->assertStringContainsString( 'rk-classic-notice rk-is-warn', $warn );
		$this->assertStringContainsString( 'Name (headline) is required for Event.', $warn );

		$this->storedMeta = [
			'schema' => [
				'type'   => 'Event',
				'fields' => [
					'headline'     => 'My event',
					'startDate'    => '2026-01-01',
					'locationName' => 'Venue',
				],
			],
		];

		$ok = $this->renderBox();
		$this->assertStringContainsString( 'rk-classic-notice rk-is-ok', $ok );
	}

	/**
	 * Every hook the classic editor script binds to exists in the view.
	 *
	 * The view is the canonical side of the contract. This extracts the data
	 * attribute selectors and the element ids metadata-editor.js reads from
	 * the DOM and fails with a readable list whenever the rendered markup
	 * stops providing one, which is exactly the runtime break this locks.
	 */
	public function test_classic_editor_script_binds_only_to_rendered_hooks(): void {
		$this->storedMeta = [];

		$markup = $this->renderBox();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads a local plugin asset, not a remote URL.
		$script = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/metadata-editor.js' );

		$attributes = $this->extract_queried_data_attributes( $script );
		$ids        = $this->extract_looked_up_element_ids( $script );

		$this->assertNotSame( [], $attributes, 'The contract extractor found no data attributes, so the extraction logic is broken.' );
		$this->assertNotSame( [], $ids, 'The contract extractor found no element ids, so the extraction logic is broken.' );

		$missing = [];

		foreach ( $attributes as $attribute ) {
			if ( false === strpos( $markup, $attribute ) ) {
				$missing[] = 'data attribute ' . $attribute;
			}
		}

		foreach ( $ids as $id ) {
			if ( false === strpos( $markup, 'id="' . $id . '"' ) ) {
				$missing[] = 'element id ' . $id;
			}
		}

		$this->assertSame(
			[],
			$missing,
			"metadata-editor.js queries hooks the view does not render:\n - " . implode( "\n - ", $missing )
		);
	}

	/**
	 * The block editor sidebar does not bind to the metabox view.
	 *
	 * It reads and writes post meta through wp.data, so it must stay free of
	 * DOM lookups against the classic view. Extracting the same contract and
	 * finding it empty documents and locks that separation.
	 */
	public function test_block_editor_sidebar_does_not_bind_to_the_metabox_view(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads a local plugin asset, not a remote URL.
		$script = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/metadata-sidebar.js' );

		$this->assertSame( [], $this->extract_queried_data_attributes( $script ) );
		$this->assertSame( [], $this->extract_looked_up_element_ids( $script ) );
	}

	/**
	 * Assert the sidebar payload always carries flags.
	 *
	 * MetaPayload::sanitize() reseeds omitted keys from its defaults, and
	 * WordPress applies it to the submitted value only. A payload without flags
	 * therefore silently resets the stored pillar, cornerstone and breadcrumb
	 * title on every save, so the producer must send all three.
	 */
	public function test_block_editor_payload_sends_flags(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads a local plugin asset, not a remote URL.
		$script = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/metadata-sidebar.js' );

		$this->assertMatchesRegularExpression(
			'/flags:\s*\{\s*pillar:.*cornerstone:.*breadcrumb_title:/s',
			$script
		);
	}

	/**
	 * Assert the REST payload normalizes the schema shape.
	 *
	 * The schema subtree is declared as an object and schemaObject() rejects an
	 * array, so the boundary normalizer must convert the empty-list default
	 * before the payload reaches editPost().
	 */
	public function test_block_editor_payload_normalizes_schema_shape(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads a local plugin asset, not a remote URL.
		$script = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/metadata-sidebar.js' );

		$this->assertMatchesRegularExpression( '/next\.schema = schemaObject\( next\.schema \);/', $script );
	}

	/**
	 * Extract the data attributes a script queries from the DOM.
	 *
	 * Only reads are part of the contract: attribute selectors ([data-...])
	 * and getAttribute( 'data-...' ) lookups. Attributes written through
	 * setAttribute() are deliberately skipped because the view does not need
	 * to render them for the script to run.
	 *
	 * @param string $script Script source.
	 * @return array<int, string> Queried data attribute names.
	 */
	private function extract_queried_data_attributes( string $script ): array {
		preg_match_all( '/\[\s*(data-[a-z0-9-]+)/', $script, $inSelectors );
		preg_match_all( "/getAttribute\(\s*'(data-[a-z0-9-]+)'/", $script, $inGetter );

		return array_values( array_unique( array_merge( $inSelectors[1], $inGetter[1] ) ) );
	}

	/**
	 * Extract the element ids a script looks up by string literal.
	 *
	 * View ids live in the rankkernel-meta namespace. Filtering on that
	 * namespace keeps DOM API names and CSS class literals out of the
	 * contract without needing an ignore list.
	 *
	 * @param string $script Script source.
	 * @return array<int, string> Looked up element ids.
	 */
	private function extract_looked_up_element_ids( string $script ): array {
		preg_match_all( "/'(rankkernel-meta[a-z0-9-]*)'/", $script, $matches );

		return array_values( array_unique( $matches[1] ) );
	}

	/**
	 * Add boxes registers the high priority normal box for supported types.
	 */
	public function test_add_boxes_registers_supported_types(): void {
		Functions\when( 'get_post_types' )->alias( static fn ( array $a ): array => [ 'post', 'page' ] ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_post_types signature.
		Functions\expect( 'add_meta_box' )->once()->andReturnUsing(
			static function ( string $id, string $title, callable $cb, string $type, string $context, string $priority ): void {
				\PHPUnit\Framework\Assert::assertSame( 'rankkernel-meta', $id );
				\PHPUnit\Framework\Assert::assertSame( 'post', $type );
				\PHPUnit\Framework\Assert::assertSame( 'normal', $context );
				\PHPUnit\Framework\Assert::assertSame( 'high', $priority );
			}
		);

		$this->newBox()->addBoxes( 'post', (object) [ 'ID' => 1 ] );
	}

	/**
	 * Add boxes skips attachment and unknown types.
	 */
	public function test_add_boxes_skips_attachment_and_unknown(): void {
		Functions\when( 'get_post_types' )->alias( static fn ( array $a ): array => [ 'post', 'page' ] ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_post_types signature.
		Functions\expect( 'add_meta_box' )->never();

		$box = $this->newBox();
		$box->addBoxes( 'attachment', (object) [ 'ID' => 1 ] );
		$box->addBoxes( 'evil', (object) [ 'ID' => 1 ] );

		$this->assertTrue( true );
	}

	/**
	 * Classic assets enqueue only on the post edit screens.
	 */
	public function test_classic_enqueue_gates_to_post_screens(): void {
		$screen            = new \stdClass();
		$screen->base      = 'post';
		$screen->post_type = 'post';

		Functions\when( 'get_current_screen' )->alias( static fn (): \stdClass => $screen );
		Functions\when( 'get_post_types' )->alias( static fn ( array $a ): array => [ 'post', 'page' ] ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_post_types signature.
		Functions\when( 'get_the_ID' )->justReturn( 0 );
		Functions\when( 'plugins_url' )->alias( static fn ( string $p, string $f = '' ): string => 'https://example.com/plugins/rankkernel/' . $p ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress plugins_url signature.

		$scripts   = [];
		$styles    = [];
		$enqueued  = [];
		$localized = [];

		Functions\when( 'wp_register_style' )->alias(
			static function ( string $h, string $src, array $deps = [], string $ver = '' ) use ( &$styles ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_register_style signature.
				$styles[ $h ] = $src;
			}
		);
		Functions\when( 'wp_enqueue_style' )->alias(
			static function ( string $h ) use ( &$enqueued ): void {
				$enqueued[] = 'style:' . $h;
			}
		);
		Functions\when( 'wp_register_script' )->alias(
			static function ( string $h, string $src, array $deps = [], string $ver = '', bool $footer = true ) use ( &$scripts ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress wp_register_script signature.
				$scripts[ $h ] = $src;
			}
		);
		Functions\when( 'wp_enqueue_script' )->alias(
			static function ( string $h ) use ( &$enqueued ): void {
				$enqueued[] = 'script:' . $h;
			}
		);
		Functions\when( 'wp_localize_script' )->alias(
			static function ( string $h, string $name, array $data ) use ( &$localized ): void {
				$localized[ $name ] = $data;
			}
		);

		$this->newBox()->enqueueAssets( 'post.php' );

		$this->assertArrayHasKey( 'rankkernel-metadata-editor', $scripts );
		$this->assertStringContainsString( 'metadata-editor.js', $scripts['rankkernel-metadata-editor'] );
		$this->assertArrayHasKey( 'rankkernel-metadata-editor', $styles );
		$this->assertStringContainsString( 'metadata-editor.css', $styles['rankkernel-metadata-editor'] );
		$this->assertContains( 'script:rankkernel-metadata-editor', $enqueued );
		$this->assertArrayHasKey( 'rankkernelMetaEditor', $localized );
	}

	/**
	 * Classic assets do not enqueue off the post edit screens.
	 */
	public function test_classic_enqueue_skipped_off_post_screens(): void {
		Functions\when( 'get_post_types' )->alias( static fn ( array $a ): array => [ 'post', 'page' ] ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_post_types signature.
		Functions\when( 'get_the_ID' )->justReturn( 0 );
		Functions\expect( 'wp_register_script' )->never();
		Functions\expect( 'wp_enqueue_script' )->never();

		$this->newBox()->enqueueAssets( 'edit.php' );

		$this->assertTrue( true );
	}

	/**
	 * The block editor sidebar script declares the required dependencies.
	 */
	public function test_block_editor_assets_dependencies(): void {
		$screen            = new \stdClass();
		$screen->base      = 'post';
		$screen->post_type = 'post';

		Functions\when( 'get_current_screen' )->alias( static fn (): \stdClass => $screen );
		Functions\when( 'get_post_types' )->alias( static fn ( array $a ): array => [ 'post', 'page' ] ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_post_types signature.
		Functions\when( 'get_the_ID' )->justReturn( 0 );
		Functions\when( 'plugins_url' )->alias( static fn ( string $p, string $f = '' ): string => 'https://example.com/plugins/rankkernel/' . $p ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress plugins_url signature.

		$deps      = [];
		$localized = [];

		Functions\when( 'wp_register_script' )->alias(
			static function ( string $h, string $src, array $registered = [] ) use ( &$deps ): void {
				$deps[ $h ] = [
					'src' => $src,
					'tax' => $registered,
				];
			}
		);
		Functions\when( 'wp_enqueue_script' )->justReturn( null );
		Functions\when( 'wp_localize_script' )->alias(
			static function ( string $h, string $name, array $data ) use ( &$localized ): void {
				$localized[ $name ] = $data;
			}
		);

		$this->newBox()->enqueueEditorAssets();

		$this->assertArrayHasKey( 'rankkernel-metadata-sidebar', $deps );
		$this->assertStringContainsString( 'metadata-sidebar.js', $deps['rankkernel-metadata-sidebar']['src'] );
		$this->assertSame( [ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ], $deps['rankkernel-metadata-sidebar']['tax'] );
		$this->assertArrayHasKey( 'rankkernelMetaEditor', $localized );
	}

	/**
	 * Build a screen double carrying only the block editor flag.
	 *
	 * Mirrors the slice of WP_Screen the metabox gate reads, and exposes
	 * is_block_editor() as a real method so the method_exists guard
	 * exercised by MetadataBox sees it.
	 *
	 * @param bool $isBlockEditor Whether the screen reports the block editor.
	 * @return object The result.
	 */
	private function screenDouble( bool $isBlockEditor ): object {
		return new class( $isBlockEditor ) {
			/**
			 * Block editor flag.
			 *
			 * @var bool
			 */
			private bool $blockEditor;

			/**
			 * Constructor.
			 *
			 * @param bool $blockEditor Block editor flag.
			 */
			public function __construct( bool $blockEditor ) {
				$this->blockEditor = $blockEditor;
			}

			/**
			 * Whether the screen is the block editor.
			 *
			 * @return bool The result.
			 */
			public function is_block_editor(): bool {
				return $this->blockEditor;
			}
		};
	}

	/**
	 * The metabox is not registered on the block editor screen.
	 *
	 * Gutenberg already renders the same controls through the sidebar, so
	 * registering the box there duplicates every field.
	 */
	public function test_add_boxes_skipped_on_block_editor_screen(): void {
		Functions\when( 'get_post_types' )->alias( static fn ( array $a ): array => [ 'post', 'page' ] ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_post_types signature.
		Functions\when( 'get_current_screen' )->alias( fn (): object => $this->screenDouble( true ) );
		Functions\expect( 'add_meta_box' )->never();

		$this->newBox()->addBoxes( 'post', (object) [ 'ID' => 1 ] );

		$this->assertTrue( true );
	}

	/**
	 * The metabox is registered when the screen is not the block editor.
	 */
	public function test_add_boxes_registered_on_classic_screen(): void {
		Functions\when( 'get_post_types' )->alias( static fn ( array $a ): array => [ 'post', 'page' ] ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_post_types signature.
		Functions\when( 'get_current_screen' )->alias( fn (): object => $this->screenDouble( false ) );
		Functions\expect( 'add_meta_box' )->once()->andReturnUsing(
			static function ( string $id, string $title, callable $cb, string $type, string $context, string $priority ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress add_meta_box signature.
				\PHPUnit\Framework\Assert::assertSame( 'rankkernel-meta', $id );
				\PHPUnit\Framework\Assert::assertSame( 'post', $type );
			}
		);

		$this->newBox()->addBoxes( 'post', (object) [ 'ID' => 1 ] );
	}

	/**
	 * The metabox is registered when no screen is available.
	 *
	 * A null screen must fail safe to Classic Editor behavior.
	 */
	public function test_add_boxes_registered_when_no_screen_available(): void {
		Functions\when( 'get_post_types' )->alias( static fn ( array $a ): array => [ 'post', 'page' ] ); // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress get_post_types signature.
		Functions\when( 'get_current_screen' )->justReturn( null );
		Functions\expect( 'add_meta_box' )->once();

		$this->newBox()->addBoxes( 'post', (object) [ 'ID' => 1 ] );
	}

	/**
	 * A fresh install resolves the default templates to title and excerpt.
	 *
	 * No saved settings means the store defaults apply, so the effective
	 * title is "title sep sitename" and the description is the excerpt.
	 */
	public function test_default_templates_resolve_fresh_install(): void {
		$this->options  = [];
		$this->settings = new SettingsStore();

		Functions\when( 'get_the_excerpt' )->justReturn( 'Fresh install excerpt' );

		$box = $this->newBox();

		$expectedTitle = 'Hello post ' . SettingsStore::defaults()['separator'] . ' Example Site';

		$this->assertSame( $expectedTitle, $box->effectiveTitleFor( 7 ) );
		$this->assertSame( 'Fresh install excerpt', $box->effectiveDescriptionFor( 7 ) );
	}

	/**
	 * A stored custom template always overrides the default.
	 */
	public function test_stored_templates_override_defaults(): void {
		$this->options  = [
			'rankkernel_settings' => [
				'separator'            => '-',
				'title_template'       => 'Custom: %%title%%',
				'description_template' => 'Desc: %%title%%',
			],
		];
		$this->settings = new SettingsStore();

		$box = $this->newBox();

		$this->assertSame( 'Custom: Hello post', $box->effectiveTitleFor( 7 ) );
		$this->assertSame( 'Desc: Hello post', $box->effectiveDescriptionFor( 7 ) );
	}
}
