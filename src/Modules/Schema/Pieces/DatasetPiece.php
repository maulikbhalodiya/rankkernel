<?php
/**
 * Dataset piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;

/**
 * Dataset as a Dataset node.
 *
 * Needed when the payload type is Dataset and a name resolves. Reads
 * headline, description, license, distributionUrl, and
 * distributionFormat from the payload fields. The license is kept
 * only when it passes URL validation, the distribution block holds a
 * single DataDownload entry and appears only with a content URL, and
 * the encoding format is omitted when empty.
 */
final class DatasetPiece implements PieceInterface {
    /**
     * Get piece id.
     */
    public function getId(): string {
        return 'dataset';
    }

    /**
     * Whether the piece is needed.
     *
     * @param Context $ctx Request context.
     */
    public function isNeeded( Context $ctx ): bool {
        if ('Dataset' !== SchemaHelpers::payloadType($ctx)) {
            return false;
        }

        return '' !== SchemaHelpers::headline($ctx, SchemaHelpers::fields($ctx));
    }

    /**
     * Build the Dataset node.
     *
     * @param Context $ctx Request context.
     * @return array<string, mixed>
     */
    public function build( Context $ctx ): array {
        if ('Dataset' !== SchemaHelpers::payloadType($ctx)) {
            return [];
        }

        $fields = SchemaHelpers::fields($ctx);
        $name   = SchemaHelpers::headline($ctx, $fields);

        if ('' === $name) {
            return [];
        }

        $permalink = $ctx->permalink();

        if ('' === $permalink) {
            return [];
        }

        $node = [
            '@type' => 'Dataset',
            '@id'   => $permalink . '#dataset',
            'name'  => $name,
        ];

        $description = SchemaHelpers::description($ctx, $fields);

        if ('' !== $description) {
            $node['description'] = $description;
        }

        $license = $this->licenseUrl($fields['license'] ?? '');

        if ('' !== $license) {
            $node['license'] = $license;
        }

        $distribution = $this->distribution($fields);

        if ([] !== $distribution) {
            $node['distribution'] = $distribution;
        }

        return $node;
    }

    /**
     * License URL, kept only when it passes URL validation.
     *
     * @param string $raw Raw license value.
     */
    private function licenseUrl( string $raw ): string {
        $clean = trim($raw);

        if ('' === $clean) {
            return '';
        }

        if (function_exists('esc_url_raw')) {
            return esc_url_raw($clean);
        }

        return $clean;
    }

    /**
     * Single DataDownload entry, empty without a content URL.
     *
     * @param array<string, string> $fields Manual overrides.
     * @return array<string, mixed>
     */
    private function distribution( array $fields ): array {
        $url = trim($fields['distributionUrl'] ?? '');

        if ('' === $url) {
            return [];
        }

        $entry = [
            '@type'      => 'DataDownload',
            'contentUrl' => $url,
        ];

        $format = trim($fields['distributionFormat'] ?? '');

        if ('' !== $format) {
            $entry['encodingFormat'] = $format;
        }

        return $entry;
    }
}
