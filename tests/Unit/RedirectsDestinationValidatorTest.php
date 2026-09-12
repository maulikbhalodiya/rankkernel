<?php
/**
 * Destination validator tests, schemes plus cleaning.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Redirects\DestinationValidator;

final class RedirectsDestinationValidatorTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		Functions\when( 'wp_parse_url' )->alias(
			static function ( string $url, int $component = -1 ): mixed {
				return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test double backing the stubbed wp_parse_url with the native parser.
			}
		);
		Functions\when( 'home_url' )->alias( static fn ( string $path = '/' ): string => 'https://example.com' . $path );
		Functions\when( 'wp_allowed_protocols' )->alias( static fn (): array => [ 'http', 'https' ] );
	}

	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	public function test_relative_destination_allowed(): void {
		$validator = new DestinationValidator();

		$result = $validator->validate( '/new' );

		$this->assertTrue( $result['valid'] );
		$this->assertSame( '/new', $result['destination'] );
	}

	public function test_bare_slug_expands_to_path(): void {
		$validator = new DestinationValidator();

		$result = $validator->validate( 'new-page' );

		$this->assertTrue( $result['valid'] );
		$this->assertSame( '/new-page', $result['destination'] );
	}

	public function test_query_kept_fragment_stripped(): void {
		$validator = new DestinationValidator();

		$result = $validator->validate( '/new?x=1#sec' );

		$this->assertTrue( $result['valid'] );
		$this->assertSame( '/new?x=1', $result['destination'] );
	}

	public function test_unsafe_schemes_rejected(): void {
		$validator = new DestinationValidator();

		foreach ( [ 'javascript:alert(1)', 'data:text/html,x', 'vbscript:msgbox(1)', 'file:///etc/passwd', 'JavaScript:alert(1)' ] as $bad ) {
			$result = $validator->validate( $bad );

			$this->assertFalse( $result['valid'], $bad . ' must be rejected' );
			$this->assertSame( '', $result['destination'] );
		}
	}

	public function test_http_external_requires_allowlist(): void {
		$validator = new DestinationValidator();

		$denied = $validator->validate( 'https://external.example/x' );

		$this->assertFalse( $denied['valid'] );
		$this->assertSame( 'external host not allowlisted', $denied['reason'] );

		$allowed = $validator->validate( 'https://external.example/x', '301', [ 'external.example' ] );

		$this->assertTrue( $allowed['valid'] );
		$this->assertSame( 'https://external.example/x', $allowed['destination'] );
	}

	public function test_allowlist_match_is_case_insensitive(): void {
		$validator = new DestinationValidator();

		$result = $validator->validate( 'https://External.Example/x', '301', [ 'external.example' ] );

		$this->assertTrue( $result['valid'] );
	}

	public function test_same_host_absolute_folds_to_relative(): void {
		$validator = new DestinationValidator();

		$result = $validator->validate( 'https://example.com/new?x=1' );

		$this->assertTrue( $result['valid'] );
		$this->assertSame( '/new?x=1', $result['destination'] );
	}

	public function test_protocol_relative_external_rejected(): void {
		$validator = new DestinationValidator();

		$result = $validator->validate( '//evil.example/x' );

		$this->assertFalse( $result['valid'] );
	}

	public function test_crlf_rejected(): void {
		$validator = new DestinationValidator();

		$result = $validator->validate( "/new\r\nX-Injected: 1" );

		$this->assertFalse( $result['valid'] );
		$this->assertSame( 'control characters rejected', $result['reason'] );
	}

	public function test_control_characters_rejected(): void {
		$validator = new DestinationValidator();

		$result = $validator->validate( "/new\x00x" );

		$this->assertFalse( $result['valid'] );
	}

	public function test_empty_destination_rules(): void {
		$validator = new DestinationValidator();

		$redirect = $validator->validate( '', '301' );

		$this->assertFalse( $redirect['valid'] );
		$this->assertSame( 'empty destination', $redirect['reason'] );

		$gone = $validator->validate( '', '410' );

		$this->assertTrue( $gone['valid'] );
		$this->assertSame( '', $gone['destination'] );

		$legal = $validator->validate( '', '451' );

		$this->assertTrue( $legal['valid'] );
	}

	public function test_never_returns_raw_input(): void {
		$validator = new DestinationValidator();

		$result = $validator->validate( '  /new//page/  ' );

		$this->assertTrue( $result['valid'] );
		$this->assertSame( '/new/page', $result['destination'] );
	}
}
