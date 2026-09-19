<?php
/**
 * Llms.txt route tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Robots\LlmsGenerator;
use RankKernel\Modules\Robots\LlmsRouter;
use RankKernel\Modules\Robots\LlmsSettings;

/**
 * Llms Router Test.
 */
final class LlmsRouterTest extends TestCase {
	/**
	 * Options.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Transients.
	 *
	 * @var array<string, mixed>
	 */
	private array $transients = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		$this->options    = [];
		$this->transients = [];

		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->options[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value ): bool {
				$this->options[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'get_transient' )->alias(
			function ( string $key ): mixed {
				return $this->transients[ $key ] ?? false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( string $key, mixed $value, int $ttl = 0 ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress set_transient signature.
				$this->transients[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'Example Site' );
		Functions\when( 'wp_rand' )->justReturn( 12345 );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a router.
	 *
	 * @return LlmsRouter The result.
	 */
	private function router(): LlmsRouter {
		return new LlmsRouter( new LlmsGenerator(), new LlmsSettings() );
	}

	/**
	 * Test register wires the route and hooks.
	 */
	public function test_register_wires_route_and_hooks(): void {
		$rewrites = [];
		$filters  = [];
		$actions  = [];

		Functions\when( 'add_rewrite_rule' )->alias(
			static function ( string $regex, string $query, string $after ) use ( &$rewrites ): void {
				$rewrites[] = [ $regex, $query, $after ];
			}
		);
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook ) use ( &$filters ): bool {
				$filters[] = $hook;

				return true;
			}
		);
		Functions\when( 'add_action' )->alias(
			static function ( string $hook ) use ( &$actions ): bool {
				$actions[] = $hook;

				return true;
			}
		);

		$this->router()->register();

		$this->assertSame( 'index.php?rankkernel_llms=1', $rewrites[0][1] );
		$this->assertContains( 'query_vars', $filters );
		$this->assertContains( 'pre_get_posts', $actions );
	}

	/**
	 * Test add query vars appends the llms var.
	 */
	public function test_add_query_vars_appends(): void {
		$this->assertContains( LlmsRouter::QUERY_VAR, $this->router()->addQueryVars( [ 'p' ] ) );
	}

	/**
	 * Test content renders and caches by validator.
	 */
	public function test_content_renders_and_caches(): void {
		$router = $this->router();

		$first = $router->content();

		$this->assertStringContainsString( '# Example Site', $first );
		$this->assertIsArray( $this->transients['rankkernel_llms_md'] ?? null );

		$this->assertSame( $first, $router->content() );
	}

	/**
	 * Test invalidate bumps the validator option.
	 */
	public function test_invalidate_bumps_validator(): void {
		LlmsRouter::invalidate();

		$this->assertArrayHasKey( LlmsRouter::VALIDATOR_OPTION, $this->options );
	}
}
