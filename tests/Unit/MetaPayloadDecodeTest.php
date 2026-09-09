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

final class MetaPayloadDecodeTest extends TestCase {
    public function test_serialized_payload_decodes(): void {
        $raw = serialize([ 'canonical' => 'https://example.com/hello/', 'robots' => [ 'index' => false ] ]);

        $this->assertSame(
            [ 'canonical' => 'https://example.com/hello/', 'robots' => [ 'index' => false ] ],
            MetaPayload::decodeMetaValue($raw)
        );
    }

    public function test_json_payload_decodes(): void {
        $raw = (string) json_encode([ 'canonical' => 'https://example.com/hello/' ]);

        $this->assertSame([ 'canonical' => 'https://example.com/hello/' ], MetaPayload::decodeMetaValue($raw));
    }

    public function test_array_passes_through(): void {
        $payload = [ 'canonical' => 'https://example.com/hello/' ];

        $this->assertSame($payload, MetaPayload::decodeMetaValue($payload));
    }

    public function test_garbage_string_yields_empty_array(): void {
        $this->assertSame([], MetaPayload::decodeMetaValue('not-json{{{'));
        $this->assertSame([], MetaPayload::decodeMetaValue(''));
        $this->assertSame([], MetaPayload::decodeMetaValue(null));
        $this->assertSame([], MetaPayload::decodeMetaValue(42));
    }

    public function test_serialized_scalar_yields_empty_array(): void {
        $this->assertSame([], MetaPayload::decodeMetaValue(serialize('just-a-string')));
        $this->assertSame([], MetaPayload::decodeMetaValue(serialize(123)));
    }
}
