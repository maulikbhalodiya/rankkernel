<?php
/**
 * Breadcrumbs module settings tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Breadcrumbs\BreadcrumbsSettings;

/**
 * Breadcrumbs Settings Test.
 */
final class BreadcrumbsSettingsTest extends TestCase {
	/**
	 * Option store.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Captured update option calls.
	 *
	 * @var array<int, array{option: string, value: mixed, autoload: mixed}>
	 */
	private array $updates = [];

	/**
	 * Taxonomies registered per post type.
	 *
	 * @var array<string, string[]>
	 */
	private array $objectTaxes = [];

	/**
	 * Public flag per taxonomy.
	 *
	 * @var array<string, bool>
	 */
	private array $taxPublic = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value, mixed $autoload = null ): bool {
				$this->options[ $key ] = $value;
				$this->updates[]       = [
					'option'   => $key,
					'value'    => $value,
					'autoload' => $autoload,
				];

				return true;
			}
		);
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'sanitize_key' )->alias( static fn ( string $v ): string => strtolower( (string) preg_replace( '/[^a-z0-9_\-]/', '', $v ) ) );
		Functions\when( 'get_object_taxonomies' )->alias(
			function ( string $postType ): array {
				return $this->objectTaxes[ $postType ] ?? [];
			}
		);
		Functions\when( 'get_taxonomy' )->alias(
			function ( string $taxonomy ): mixed {
				if ( ! array_key_exists( $taxonomy, $this->taxPublic ) ) {
					return false;
				}

				return (object) [
					'name'   => $taxonomy,
					'public' => $this->taxPublic[ $taxonomy ],
				];
			}
		);
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test defaults exact.
	 */
	public function test_defaults_exact(): void {
		$this->assertSame(
			[
				'separator'          => '/',
				'home_label'         => 'Home',
				'show_home'          => true,
				'show_current'       => true,
				'hide_on_front_page' => true,
				'show_blog_page'     => true,
				'show_ancestors'     => true,
			],
			BreadcrumbsSettings::defaults()
		);
	}

	/**
	 * Test option name.
	 */
	public function test_option_name(): void {
		$this->assertSame( 'rankkernel_breadcrumbs_settings', BreadcrumbsSettings::OPTION );
	}

	/**
	 * Test all merges stored over defaults.
	 */
	public function test_all_merges_stored_over_defaults(): void {
		$this->options[ BreadcrumbsSettings::OPTION ] = [ 'home_label' => 'Start' ];

		$settings = new BreadcrumbsSettings();

		$this->assertSame( 'Start', $settings->get( 'home_label' ) );
		$this->assertSame( '/', $settings->get( 'separator' ) );
		$this->assertSame( 'fallback', $settings->get( 'missing_key', 'fallback' ) );
	}

	/**
	 * Test set whitelists unknown keys and enables autoload.
	 */
	public function test_set_whitelists_unknown_keys_and_enables_autoload(): void {
		$settings = new BreadcrumbsSettings();

		$result = $settings->set(
			[
				'home_label' => 'Start',
				'evil_key'   => 'x',
			]
		);

		$this->assertTrue( $result );
		$this->assertSame( 'Start', $settings->get( 'home_label' ) );
		$this->assertCount( 1, $this->updates );
		$this->assertSame( BreadcrumbsSettings::OPTION, $this->updates[0]['option'] );
		$this->assertTrue( $this->updates[0]['autoload'] );
		$this->assertArrayNotHasKey( 'evil_key', (array) $this->updates[0]['value'] );
	}

	/**
	 * Test set unknown only returns false without write.
	 */
	public function test_set_unknown_only_returns_false_without_write(): void {
		Functions\expect( 'update_option' )->never();

		$settings = new BreadcrumbsSettings();

		$this->assertFalse( $settings->set( [ 'evil_key' => 'x' ] ) );
	}

	/**
	 * Test separator and home label strip HTML.
	 */
	public function test_text_settings_strip_html(): void {
		$settings = new BreadcrumbsSettings();

		$settings->set(
			[
				'separator'  => '<b>/</b>',
				'home_label' => '<script>Home</script>',
			]
		);

		$this->assertSame( '/', $settings->get( 'separator' ) );
		$this->assertSame( 'Home', $settings->get( 'home_label' ) );
	}

	/**
	 * Test boolean strings normalized.
	 */
	public function test_boolean_strings_normalized(): void {
		$settings = new BreadcrumbsSettings();

		$settings->set(
			[
				'show_home'      => '0',
				'show_current'   => 'yes',
				'show_blog_page' => 1,
				'show_ancestors' => '',
			]
		);

		$this->assertFalse( $settings->get( 'show_home' ) );
		$this->assertTrue( $settings->get( 'show_current' ) );
		$this->assertTrue( $settings->get( 'show_blog_page' ) );
		$this->assertFalse( $settings->get( 'show_ancestors' ) );
	}

	/**
	 * Test taxonomy mapping keeps a public taxonomy for the type.
	 */
	public function test_taxonomy_mapping_keeps_public_taxonomy_for_type(): void {
		$this->objectTaxes = [ 'post' => [ 'category', 'post_tag' ] ];
		$this->taxPublic   = [
			'category' => true,
			'post_tag' => true,
		];

		$settings = new BreadcrumbsSettings();

		$this->assertTrue( $settings->set( [ 'primary_taxonomy_post' => 'category' ] ) );
		$this->assertSame( 'category', $settings->get( 'primary_taxonomy_post' ) );
	}

	/**
	 * Test taxonomy mapping rejects other type taxonomies.
	 */
	public function test_taxonomy_mapping_rejects_other_type_taxonomies(): void {
		$this->objectTaxes = [ 'post' => [ 'category' ] ];
		$this->taxPublic   = [ 'category' => true ];

		$settings = new BreadcrumbsSettings();

		$this->assertTrue( $settings->set( [ 'primary_taxonomy_post' => 'genre' ] ) );
		$this->assertSame( '', $settings->get( 'primary_taxonomy_post' ) );
	}

	/**
	 * Test taxonomy mapping rejects private taxonomies.
	 */
	public function test_taxonomy_mapping_rejects_private_taxonomies(): void {
		$this->objectTaxes = [ 'post' => [ 'category', 'internal' ] ];
		$this->taxPublic   = [
			'category' => true,
			'internal' => false,
		];

		$settings = new BreadcrumbsSettings();

		$this->assertTrue( $settings->set( [ 'primary_taxonomy_post' => 'internal' ] ) );
		$this->assertSame( '', $settings->get( 'primary_taxonomy_post' ) );
	}

	/**
	 * Test uppercase dynamic keys are dropped by the pattern.
	 */
	public function test_uppercase_dynamic_keys_dropped(): void {
		$settings = new BreadcrumbsSettings();

		$this->assertFalse( $settings->set( [ 'primary_taxonomy_Post' => 'category' ] ) );
		$this->assertArrayNotHasKey( BreadcrumbsSettings::OPTION, $this->options );
	}

	/**
	 * Test ensure schema seeds defaults with autoload.
	 */
	public function test_ensure_schema_seeds_defaults_with_autoload(): void {
		$settings = new BreadcrumbsSettings();

		$settings->ensureSchema();

		$this->assertSame( 'Home', $settings->get( 'home_label' ) );
		$this->assertCount( 1, $this->updates );
		$this->assertTrue( $this->updates[0]['autoload'] );
	}

	/**
	 * Test ensure schema keeps stored values.
	 */
	public function test_ensure_schema_keeps_stored_values(): void {
		$this->options[ BreadcrumbsSettings::OPTION ] = [ 'show_home' => false ];

		$settings = new BreadcrumbsSettings();

		$settings->ensureSchema();

		$this->assertFalse( $settings->get( 'show_home' ) );
		$this->assertTrue( $settings->get( 'show_current' ) );
		$this->assertSame( [], $this->updates );
	}
}
