<?php
/**
 * Adversarial security tests at save time and at send time.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\CsvHandler;
use RankKernel\Modules\Redirects\DestinationValidator;
use RankKernel\Modules\Redirects\HitCounter;
use RankKernel\Modules\Redirects\Matcher;
use RankKernel\Modules\Redirects\RedirectCache;
use RankKernel\Modules\Redirects\Redirector;
use RankKernel\Modules\Redirects\RedirectRepository;

/**
 * Attacks the redirect pipeline from both ends.
 *
 * Save time cases prove hostile input is rejected before storage.
 * Send time cases plant hostile rows straight into the table to prove
 * the dispatcher revalidates and never sends a raw stored value.
 */
final class RedirectsSecurityTest extends TestCase {
	/**
	 * Fake database.
	 */
	private RedirectsFakeDb $db;

	/**
	 * Option store.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Transient store.
	 *
	 * @var array<string, mixed>
	 */
	private array $transients = [];

	/**
	 * Sent redirects.
	 *
	 * @var array<int, array{location: string, status: int}>
	 */
	private array $redirects = [];

	/**
	 * Original request URI for restoration.
	 *
	 * @var string|null
	 */
	private ?string $originalUri = null;

	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			define( 'RANKKERNEL_TESTING', true );
		}

		$this->db        = new RedirectsFakeDb();
		$GLOBALS['wpdb'] = $this->db; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->options    = [];
		$this->transients = [];
		$this->redirects  = [];

		if ( isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {
			$this->originalUri = $_SERVER['REQUEST_URI'];
		}

		Redirector::resetSent();

		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $path = '/' ): string => 'https://example.com' . $path );
		Functions\when( 'wp_allowed_protocols' )->alias( static fn (): array => [ 'http', 'https' ] );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'get_query_var' )->justReturn( '' );
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
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
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );
		Functions\when( 'get_transient' )->alias(
			function ( string $key ): mixed {
				return $this->transients[ $key ] ?? false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( string $key, mixed $value ): bool {
				$this->transients[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'wp_redirect' )->alias(
			function ( string $location, int $status = 302 ): bool {
				$this->redirects[] = [
					'location' => $location,
					'status'   => $status,
				];

				return true;
			}
		);
		Functions\when( 'status_header' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( true );
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-01-01 00:00:00' );
		Functions\when( 'wp_unslash' )->alias( static fn ( string $v ): string => stripslashes( $v ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $v ): string => $v );
		Functions\when( '__' )->alias( static fn ( string $v ): string => $v );
	}

	protected function tearDown(): void {
		if ( null !== $this->originalUri ) {
			$_SERVER['REQUEST_URI'] = $this->originalUri;
		} else {
			unset( $_SERVER['REQUEST_URI'] );
		}

		unset( $GLOBALS['wpdb'] );
		Redirector::resetSent();
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Plant a hostile row straight into the table, bypassing validation.
	 */
	private function plantRow( string $source, string $target, string $code = '301' ): void {
		$this->db->rows[ $this->db->nextId ] = [
			'id'            => $this->db->nextId,
			'match_type'    => 'exact',
			'source_hash'   => hash( 'sha256', 'exact|' . $source ),
			'source'        => $source,
			'target'        => $target,
			'code'          => $code,
			'is_active'     => 1,
			'created'       => '2026-01-01 00:00:00',
			'hits'          => 0,
			'last_accessed' => null,
		];

		++$this->db->nextId;
	}

	/**
	 * Dispatch one request URI.
	 */
	private function dispatch( string $uri ): void {
		$_SERVER['REQUEST_URI'] = $uri;

		$repo = new RedirectRepository( $this->db );

		( new Redirector( $repo, new RedirectCache(), new HitCounter(), new \RankKernel\Modules\Redirects\RedirectsSettings() ) )->maybeRedirect();
	}

	public function test_scheme_case_variants_rejected_at_save(): void {
		$validator = new DestinationValidator();

		foreach ( [ 'JAVASCRIPT:alert(1)', 'JaVaScRiPt:x', 'DATA:text/html,x', 'VBSCRIPT:x', 'FILE:///etc/passwd' ] as $target ) {
			$this->assertFalse(
				$validator->validate( $target, '301' )['valid'],
				'Unsafe scheme must be rejected: ' . $target
			);
		}
	}

	public function test_padded_javascript_rejected_at_save(): void {
		$validator = new DestinationValidator();

		$this->assertFalse( $validator->validate( '  javascript:alert(1)', '301' )['valid'] );
		$this->assertFalse( $validator->validate( "javascript\t:alert(1)", '301' )['valid'] );
	}

	public function test_crlf_and_control_characters_rejected_at_save(): void {
		$validator = new DestinationValidator();

		$this->assertFalse( $validator->validate( "/new\r\nLocation: evil", '301' )['valid'] );
		$this->assertFalse( $validator->validate( "/ne\x00w", '301' )['valid'] );
		$this->assertFalse( $validator->validate( "/ne\x7fw", '301' )['valid'] );

		$trimmed = $validator->validate( "/new\x00", '301' );

		$this->assertTrue( $trimmed['valid'] );
		$this->assertSame( '/new', $trimmed['destination'], 'Edge null bytes trim away, nothing hostile survives' );
	}

	public function test_protocol_relative_external_blocked_without_allowlist(): void {
		$validator = new DestinationValidator();

		$this->assertFalse( $validator->validate( '//evil.example/path', '301' )['valid'] );
		$this->assertFalse( $validator->validate( 'https://user:pass@evil.example/', '301' )['valid'] );
		$this->assertTrue( $validator->validate( 'https://evil.example/x', '301', [ 'evil.example' ] )['valid'] );
	}

	public function test_send_never_emits_raw_crlf_row(): void {
		$this->plantRow( '/old', "/new\r\nX-Evil: 1" );

		$this->dispatch( '/old' );

		$this->assertSame( [], $this->redirects, 'A stored CRLF destination must never send' );
	}

	public function test_send_never_emits_raw_javascript_row(): void {
		$this->plantRow( '/old', 'javascript:alert(1)' );

		$this->dispatch( '/old' );

		$this->assertSame( [], $this->redirects, 'A stored script destination must never send' );
	}

	public function test_send_never_emits_raw_external_row(): void {
		$this->plantRow( '/old', 'https://evil.example/x' );

		$this->dispatch( '/old' );

		$this->assertSame( [], $this->redirects, 'A stored external destination must never send without an allowlist' );
	}

	public function test_query_manipulation_cannot_change_the_match(): void {
		$this->plantRow( '/old', '/new' );

		$this->dispatch( '/old?x=/other&redirect=https://evil.example' );

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/new?x=/other&redirect=https://evil.example', $this->redirects[0]['location'] );
	}

	public function test_malformed_regex_fails_closed(): void {
		$winner = Matcher::pick_winner(
			'/anything',
			[
				[
					'id'         => 1,
					'match_type' => 'regex',
					'source'     => '(',
					'target'     => '/x',
					'code'       => '301',
					'is_active'  => 1,
				],
			]
		);

		$this->assertNull( $winner, 'A malformed pattern must fail closed' );
	}

	public function test_overlong_regex_fails_closed(): void {
		$winner = Matcher::pick_winner(
			'/anything',
			[
				[
					'id'         => 1,
					'match_type' => 'regex',
					'source'     => str_repeat( 'a', 201 ),
					'target'     => '/x',
					'code'       => '301',
					'is_active'  => 1,
				],
			]
		);

		$this->assertNull( $winner, 'An overlong pattern must fail closed' );
	}

	public function test_wildcard_metacharacters_match_literally(): void {
		$rule = [
			'id'         => 1,
			'match_type' => 'wildcard',
			'source'     => '/old.(plus)',
			'target'     => '/new',
			'code'       => '301',
			'is_active'  => 1,
		];

		$this->assertNotNull( Matcher::pick_winner( '/old.(plus)', [ $rule ] ) );
		$this->assertNull( Matcher::pick_winner( '/oldXplus', [ $rule ] ), 'Pattern metacharacters must stay literal' );
	}

	public function test_formula_cells_neutralized_both_directions(): void {
		foreach ( [ '=cmd', '+cmd', '-cmd', '@cmd' ] as $cell ) {
			$this->assertStringStartsWith( "'", CsvHandler::sanitize_cell( $cell ), 'Import must neutralize: ' . $cell );
			$this->assertStringStartsWith( "'", CsvHandler::escape_cell( $cell ), 'Export must neutralize: ' . $cell );
		}

		foreach ( [ "\tcmd", "\rcmd" ] as $cell ) {
			$this->assertStringStartsWith( "'", CsvHandler::escape_cell( $cell ), 'Export must neutralize: ' . bin2hex( $cell ) );
			$this->assertSame( 'cmd', CsvHandler::sanitize_cell( $cell ), 'Import trims whitespace triggers away' );
		}

		$this->assertSame( 'plain', CsvHandler::sanitize_cell( 'plain' ) );
		$this->assertSame( '/old', CsvHandler::escape_cell( '/old' ) );
	}

	public function test_traversal_input_round_trips_literally(): void {
		$repo = new RedirectRepository( $this->db );
		$id   = $repo->insert(
			[
				'source' => '/a/../b',
				'target' => '/new',
			]
		);

		$this->assertGreaterThan( 0, $id );
		$this->assertSame( '/a/../b', $this->db->rows[ $id ]['source'] );

		$this->dispatch( '/a/../b' );

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( '/new', $this->redirects[0]['location'] );
	}

	public function test_encoded_traversal_canonicalizes_before_match(): void {
		$repo = new RedirectRepository( $this->db );
		$id   = $repo->insert(
			[
				'source' => '/%2e%2e/b',
				'target' => '/new',
			]
		);

		$this->assertGreaterThan( 0, $id );

		$found = $repo->lookup( '/%2E%2E/b' );

		$this->assertNotNull( $found, 'Equivalent spellings must hash identically' );
		$this->assertSame( $id, (int) $found['id'] );
	}
}
