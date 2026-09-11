<?php
/**
 * Course piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;
use RankKernel\Settings\SettingsStore;

/**
 * Course as a Course node.
 *
 * Needed when the payload type is Course and a name resolves. Reads
 * headline and description from the payload fields. The provider ref
 * points at the site organization.
 */
final class CoursePiece implements PieceInterface {
    /**
     * Settings store.
     */
    private readonly SettingsStore $settings;

    /**
     * Constructor.
     *
     * @param SettingsStore|null $settings Optional settings store.
     */
    public function __construct( ?SettingsStore $settings = null ) {
        $this->settings = $settings ?? new SettingsStore();
    }

    /**
     * Get piece id.
     */
    public function getId(): string {
        return 'course';
    }

    /**
     * Whether the piece is needed.
     *
     * @param Context $ctx Request context.
     */
    public function isNeeded( Context $ctx ): bool {
        if ('Course' !== SchemaHelpers::effectiveType($ctx, $this->settings)) {
            return false;
        }

        return '' !== SchemaHelpers::headline($ctx, SchemaHelpers::fields($ctx));
    }

    /**
     * Build the Course node.
     *
     * @param Context $ctx Request context.
     * @return array<string, mixed>
     */
    public function build( Context $ctx ): array {
        if ('Course' !== SchemaHelpers::effectiveType($ctx, $this->settings)) {
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
            '@type'    => 'Course',
            '@id'      => $permalink . '#course',
            'name'     => $name,
            'provider' => [
                '@id' => SchemaHelpers::publisherId($this->settings),
            ],
        ];

        $description = SchemaHelpers::description($ctx, $fields);

        if ('' !== $description) {
            $node['description'] = $description;
        }

        return $node;
    }
}
