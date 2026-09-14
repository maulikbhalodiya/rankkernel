<?php
/**
 * MetaPayload decoder tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Metadata\MetaPayload;

/**
 * Meta Payload Decode Test.
 */
final class MetaPayloadDecodeTest extends TestCase {
	/**
	 * Test serialized payload decodes.
	 */
	public function test_serialized_payload_decodes(): void {
		$raw = serialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- test fixture uses the existing stored serialization format.
			[
				'canonical' => 'https://example.com/hello/',
				'robots'    => [ 'index' => false ],
			]
		);

		$this->assertSame(
			[
				'canonical' => 'https://example.com/hello/',
				'robots'    => [ 'index' => false ],
			],
			MetaPayload::decodeMetaValue( $raw )
		);
	}

	/**
	 * Test json payload decodes.
	 */
	public function test_json_payload_decodes(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit tests run without WordPress loaded, wp_json_encode is unavailable here.
		$raw = (string) json_encode( [ 'canonical' => 'https://example.com/hello/' ] );

		$this->assertSame( [ 'canonical' => 'https://example.com/hello/' ], MetaPayload::decodeMetaValue( $raw ) );
	}

	/**
	 * Test array passes through.
	 */
	public function test_array_passes_through(): void {
		$payload = [ 'canonical' => 'https://example.com/hello/' ];

		$this->assertSame( $payload, MetaPayload::decodeMetaValue( $payload ) );
	}

	/**
	 * Test garbage string yields empty array.
	 */
	public function test_garbage_string_yields_empty_array(): void {
		$this->assertSame( [], MetaPayload::decodeMetaValue( 'not-json{{{' ) );
		$this->assertSame( [], MetaPayload::decodeMetaValue( '' ) );
		$this->assertSame( [], MetaPayload::decodeMetaValue( null ) );
		$this->assertSame( [], MetaPayload::decodeMetaValue( 42 ) );
	}

	/**
	 * Test serialized scalar yields empty array.
	 */
	public function test_serialized_scalar_yields_empty_array(): void {
		$this->assertSame( [], MetaPayload::decodeMetaValue( serialize( 'just-a-string' ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- test fixture uses the existing stored serialization format.
		$this->assertSame( [], MetaPayload::decodeMetaValue( serialize( 123 ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- test fixture uses the existing stored serialization format.
	}
}
