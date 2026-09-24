<?php
/**
 * Instant Indexing module tests, enable gate, auto submit and URL paths.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\InstantIndexing\IndexNowClient;
use RankKernel\Modules\InstantIndexing\IndexNowSettings;
use RankKernel\Modules\InstantIndexing\InstantIndexingModule;
use RankKernel\Modules\ModuleEnableMap;
use WP_Post;

/**
 * Instant Indexing Module Test.
 */
final class InstantIndexingModuleTest extends TestCase {
	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Settings under test, keyed during setUp.
	 *
	 * @var IndexNowSettings
	 */
	private IndexNowSettings $settings;

	/**
	 * Number of transport calls made through the injected client.
	 *
	 * @var int
	 */
	private int $clientCalls = 0;

	/**
	 * URLs recorded from the submitted payloads, in submission order.
	 *
	 * @var string[]
	 */
	private array $submittedUrls = [];

	/**
	 * Option store backing the get_option and update_option stubs.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		// The suite models a site with pretty permalinks, which is the
		// state keyLocation() serves the root txt file from.
		$this->stored['permalink_structure'] = '/%postname%/';

		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ): mixed {
				return $this->stored[ $key ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $key, mixed $value, mixed $autoload = null ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress update_option signature.
				$this->stored[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $p = '' ): string => 'https://example.com' . $p );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $url ): string => rtrim( $url, '/' ) . '/' );
		Functions\when( 'add_query_arg' )->alias(
			static function ( string $key, string $value, string $url ): string {
				$separator = str_contains( $url, '?' ) ? '&' : '?';

				return $url . $separator . $key . '=' . rawurlencode( $value );
			}
		);
		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): string|int|false|null {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double mirrors wp_parse_url with the native parser.
				return parse_url( $url, $component );
			}
		);
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $data ): string => (string) json_encode( $data ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit tests run without WordPress loaded, wp_json_encode is unavailable here.

		$generated = 0;
		Functions\when( 'wp_generate_password' )->alias(
			static function ( int $length = 12 ) use ( &$generated ): string {
				++$generated;

				return str_pad( 'testkey' . $generated, $length, 'x' );
			}
		);

		Functions\when( 'get_permalink' )->alias( static fn ( int $id ): string => 'https://example.com/p' . $id );
		Functions\when( 'get_term_link' )->alias( static fn ( int $id ): string => 'https://example.com/t' . $id );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'wp_is_post_revision' )->justReturn( false );
		Functions\when( 'wp_is_post_autosave' )->justReturn( false );
		Functions\when( 'is_post_type_viewable' )->justReturn( true );
		Functions\when( 'is_post_status_viewable' )->justReturn( true );
		Functions\when( 'is_taxonomy_viewable' )->justReturn( true );
		Functions\when( 'get_post_type_object' )->justReturn( (object) [ 'name' => 'post' ] );
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'time' )->justReturn( 1000000000 );

		$this->settings = new IndexNowSettings();
		$this->settings->ensureKey();
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a client wired to a recording transport double.
	 *
	 * The transport records the number of calls and every URL from the
	 * submitted payload, so tests can assert which URLs were signalled.
	 *
	 * @return IndexNowClient Client under test.
	 */
	private function client(): IndexNowClient {
		$transport = function ( string $url, array $args ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- transport signature; the payload body carries the URL list this double records.
			++$this->clientCalls;

			$payload = json_decode( (string) ( $args['body'] ?? '' ), true );

			if ( is_array( $payload ) && is_array( $payload['urlList'] ?? null ) ) {
				foreach ( $payload['urlList'] as $submittedUrl ) {
					if ( is_string( $submittedUrl ) ) {
						$this->submittedUrls[] = $submittedUrl;
					}
				}
			}

			return [
				'response' => [ 'code' => 200 ],
				'body'     => '',
			];
		};

		return new IndexNowClient( $this->settings, $transport );
	}

	/**
	 * Build the module under test with a toggled enable map.
	 *
	 * @param bool $enabled    Whether the module id is in the enable map.
	 * @param bool $autoSubmit Whether auto submit is on.
	 * @return InstantIndexingModule Module under test.
	 */
	private function module( bool $enabled, bool $autoSubmit ): InstantIndexingModule {
		Functions\when( 'get_option' )->alias(
			function ( string $key, mixed $fallback = false ) use ( $enabled ): mixed {
				if ( 'rankkernel_modules' === $key ) {
					return $enabled ? [ 'instant-indexing' ] : [];
				}

				return $this->stored[ $key ] ?? $fallback;
			}
		);

		// ModuleEnableMap reads the option in its constructor, so the enabled
		// aware stub must be installed before the map is built.
		$map = new ModuleEnableMap();

		$this->settings->set( [ 'auto_submit' => $autoSubmit ] );

		return new InstantIndexingModule( $map, $this->settings, $this->client() );
	}

	/**
	 * Build a post double.
	 *
	 * @param int $id Post id.
	 * @return WP_Post Post double.
	 */
	private function post( int $id ): WP_Post {
		$post     = Mockery::mock( WP_Post::class );
		$post->ID = $id;

		return $post;
	}

	/**
	 * Track post meta in memory so debounce reads answer debounce writes.
	 *
	 * @param array<string, mixed> $meta Meta store, passed by reference.
	 * @return void
	 */
	private function trackPostMeta( array &$meta ): void {
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $id, string $key, bool $single = false ) use ( &$meta ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- stub mirrors the WordPress get_post_meta signature.
				return $meta[ $key ] ?? '';
			}
		);
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $id, string $key, mixed $value ) use ( &$meta ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub mirrors the WordPress update_post_meta signature.
				$meta[ $key ] = $value;
				return true;
			}
		);
	}

	/**
	 * Read a private property from a module under test.
	 *
	 * @param object $target Object to read from.
	 * @param string $name   Property name.
	 * @return mixed The result.
	 */
	private function privateProperty( object $target, string $name ): mixed {
		$property = new \ReflectionProperty( $target, $name );
		$property->setAccessible( true );

		return $property->getValue( $target );
	}

	/**
	 * Test module identity and boot contract values.
	 */
	public function test_module_identity(): void {
		$module = $this->module( true, true );

		$this->assertSame( 'instant-indexing', $module->getId() );
		$this->assertSame( 'Instant Indexing (IndexNow)', $module->getName() );
		$this->assertSame( 45, $module->getPriority() );
		$this->assertSame( [], $module->dependsOn() );
	}

	/**
	 * Test a disabled module registers zero hooks.
	 */
	public function test_disabled_module_registers_zero_hooks(): void {
		$hooks = [];
		Functions\when( 'add_action' )->alias(
			static function ( string $hook ) use ( &$hooks ): bool {
				$hooks[] = $hook;
				return true;
			}
		);
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook ) use ( &$hooks ): bool {
				$hooks[] = $hook;
				return true;
			}
		);

		$module = $this->module( false, true );
		$module->register();
		$module->boot();

		$this->assertSame( [], $hooks );
	}

	/**
	 * Test a disabled module constructs neither a client nor settings.
	 */
	public function test_disabled_module_constructs_no_client(): void {
		$hooks = [];
		Functions\when( 'add_action' )->alias(
			static function ( string $hook ) use ( &$hooks ): bool {
				$hooks[] = $hook;
				return true;
			}
		);
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook ) use ( &$hooks ): bool {
				$hooks[] = $hook;
				return true;
			}
		);
		Functions\when( 'get_option' )->alias(
			static function ( string $key, mixed $fallback = false ): mixed {
				return 'rankkernel_modules' === $key ? [] : $fallback;
			}
		);

		// No client and no settings are injected, so the lazy constructors
		// are the only way either dependency could appear.
		$module = new InstantIndexingModule( new ModuleEnableMap() );

		$module->register();
		$module->boot();

		$this->assertSame( [], $hooks );
		$this->assertNull( $this->privateProperty( $module, 'client' ) );
		$this->assertNull( $this->privateProperty( $module, 'settings' ) );
	}

	/**
	 * Test an enabled module registers no cron or scheduler hooks.
	 */
	public function test_enabled_module_registers_no_cron_hooks(): void {
		$hooks = [];
		Functions\when( 'add_action' )->alias(
			static function ( string $hook ) use ( &$hooks ): bool {
				$hooks[] = $hook;
				return true;
			}
		);
		Functions\when( 'add_filter' )->alias(
			static function ( string $hook ) use ( &$hooks ): bool {
				$hooks[] = $hook;
				return true;
			}
		);

		$module = $this->module( true, true );
		$module->boot();

		$scheduled = array_filter(
			$hooks,
			static function ( string $hook ): bool {
				return str_contains( $hook, 'cron' )
					|| str_contains( $hook, 'schedule' )
					|| str_contains( $hook, 'action_scheduler' );
			}
		);

		$this->assertSame( [], array_values( $scheduled ) );

		// The exact hook set is pinned, so any added scheduling hook fails here.
		$this->assertSame(
			[
				'parse_request',
				'pre_post_update',
				'transition_post_status',
				'created_term',
				'edited_term',
				'pre_delete_term',
			],
			$hooks
		);
	}

	/**
	 * Test enabling the module alone still sends nothing.
	 */
	public function test_enabling_the_module_alone_sends_nothing(): void {
		$module = $this->module( true, false );
		$module->boot();

		$module->onTransitionPostStatus( 'publish', 'draft', $this->post( 42 ) );

		$this->assertSame( 0, $this->clientCalls, 'auto_submit off must produce zero outbound calls' );
	}

	/**
	 * Test auto submit on publish submits the permalink once.
	 */
	public function test_auto_submit_on_publish_submits_the_permalink(): void {
		$module = $this->module( true, true );
		$module->boot();

		$module->onTransitionPostStatus( 'publish', 'draft', $this->post( 42 ) );

		$this->assertSame( 1, $this->clientCalls );
	}

	/**
	 * Test a revision is never submitted.
	 */
	public function test_revision_is_never_submitted(): void {
		Functions\when( 'wp_is_post_revision' )->justReturn( 7 );

		$module = $this->module( true, true );
		$module->boot();

		$module->onTransitionPostStatus( 'publish', 'draft', $this->post( 7 ) );

		$this->assertSame( 0, $this->clientCalls );
	}

	/**
	 * Test an autosave is never submitted.
	 */
	public function test_autosave_is_never_submitted(): void {
		Functions\when( 'wp_is_post_autosave' )->justReturn( 9 );

		$module = $this->module( true, true );
		$module->boot();

		$module->onTransitionPostStatus( 'publish', 'draft', $this->post( 9 ) );

		$this->assertSame( 0, $this->clientCalls );
	}

	/**
	 * Test a non public post type is never submitted.
	 */
	public function test_non_public_post_type_is_never_submitted(): void {
		Functions\when( 'is_post_type_viewable' )->justReturn( false );

		$module = $this->module( true, true );
		$module->boot();

		$module->onTransitionPostStatus( 'publish', 'draft', $this->post( 5 ) );

		$this->assertSame( 0, $this->clientCalls );
	}

	/**
	 * Test a password protected post is never submitted.
	 */
	public function test_password_protected_post_is_never_submitted(): void {
		$module = $this->module( true, true );
		$module->boot();

		$post                = $this->post( 61 );
		$post->post_password = 'secret';

		$module->onTransitionPostStatus( 'publish', 'draft', $post );

		$this->assertSame( 0, $this->clientCalls, 'a password protected post must never be signalled' );
	}

	/**
	 * Test going from public to non public is not a publish signal.
	 */
	public function test_unpublishing_a_draft_submits_nothing(): void {
		$module = $this->module( true, true );
		$module->boot();

		$module->onTransitionPostStatus( 'draft', 'publish', $this->post( 3 ) );

		$this->assertSame( 0, $this->clientCalls, 'going from public to non public is not a publish signal' );
	}

	/**
	 * Test the same URL inside the debounce window submits once.
	 */
	public function test_same_url_inside_the_debounce_window_submits_once(): void {
		$meta = [];
		$this->trackPostMeta( $meta );
		Functions\when( 'time' )->justReturn( 1000 );

		$module = $this->module( true, true );
		$module->boot();

		$post = $this->post( 11 );

		$module->onTransitionPostStatus( 'publish', 'draft', $post );
		$module->onTransitionPostStatus( 'publish', 'publish', $post );

		$this->assertSame( 1, $this->clientCalls );
	}

	/**
	 * Test a changed permalink submits the previous and current URL.
	 */
	public function test_a_changed_permalink_submits_the_previous_and_current_url(): void {
		$permalink = 'https://example.com/old-slug';
		Functions\when( 'get_permalink' )->alias(
			static function ( int $id ) use ( &$permalink ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub answers the stateful permalink, the id is unused.
				return $permalink;
			}
		);

		$module = $this->module( true, true );
		$module->boot();

		$post = $this->post( 44 );

		// The pre_post_update hook snapshots the URL that is live right now.
		$module->onPrePostUpdate( 44 );

		$permalink = 'https://example.com/new-slug';

		$module->onTransitionPostStatus( 'publish', 'publish', $post );

		$this->assertSame( 1, $this->clientCalls, 'both URLs ride one request' );
		$this->assertSame( [ 'https://example.com/old-slug', 'https://example.com/new-slug' ], $this->submittedUrls );
	}

	/**
	 * Test a trash signal is not debounced away by the update before it.
	 */
	public function test_trash_bypasses_the_debounce_window(): void {
		$meta = [];
		$this->trackPostMeta( $meta );
		Functions\when( 'time' )->justReturn( 1000 );

		$module = $this->module( true, true );
		$module->boot();

		$post = $this->post( 31 );

		$module->onTransitionPostStatus( 'publish', 'draft', $post );
		$callsAfterPublish = $this->clientCalls;

		$module->onTransitionPostStatus( 'trash', 'publish', $post );

		$this->assertSame( 1, $callsAfterPublish );
		$this->assertSame( 2, $this->clientCalls, 'a trash signal is a distinct fact and is never debounced away' );
	}

	/**
	 * Test trashing submits the permalink captured before the trash.
	 */
	public function test_trashing_submits_the_permalink_captured_before_the_trash(): void {
		$permalink = 'https://example.com/live-slug';
		Functions\when( 'get_permalink' )->alias(
			static function ( int $id ) use ( &$permalink ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- stub answers the stateful permalink, the id is unused.
				return $permalink;
			}
		);

		$module = $this->module( true, true );
		$module->boot();

		$post = $this->post( 21 );

		$module->onTransitionPostStatus( 'publish', 'publish', $post );
		$callsAfterUpdate = $this->clientCalls;

		// WordPress reports the __trashed slug after the trash lands, so
		// the only way to signal the URL that was live is the snapshot.
		$permalink = 'https://example.com/live-slug__trashed';

		$module->onTransitionPostStatus( 'trash', 'publish', $post );

		$this->assertSame( 1, $callsAfterUpdate );
		$this->assertCount( 2, $this->submittedUrls );
		$this->assertSame( 'https://example.com/live-slug', $this->submittedUrls[1] );
		$this->assertNotContains( 'https://example.com/live-slug__trashed', $this->submittedUrls );
	}

	/**
	 * Test a created term submits the term URL.
	 */
	public function test_term_create_submits_the_term_url(): void {
		$module = $this->module( true, true );
		$module->boot();

		$module->onTermChange( 8, 8, 'category', 'created' );

		$this->assertSame( 1, $this->clientCalls );
	}

	/**
	 * Test the delete callback accepts the two argument core signature.
	 */
	public function test_pre_delete_term_callback_receives_the_taxonomy(): void {
		$callbacks = [];
		Functions\when( 'add_action' )->alias(
			static function ( string $hook, mixed $callback = null ) use ( &$callbacks ): bool {
				$callbacks[ $hook ] = $callback;
				return true;
			}
		);

		$module = $this->module( true, true );
		$module->boot();

		$this->assertArrayHasKey( 'pre_delete_term', $callbacks );

		$callback = $callbacks['pre_delete_term'];
		$this->assertIsCallable( $callback );

		// WordPress core fires pre_delete_term with the term id and the
		// taxonomy only, not the term taxonomy id.
		$callback( 8, 'category' );

		$this->assertSame( 1, $this->clientCalls );
		$this->assertSame( [ 'https://example.com/t8' ], $this->submittedUrls );
	}
}
