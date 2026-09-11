<?php
/**
 * Podcast episode piece.
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
 * Episode as a PodcastEpisode node.
 *
 * Needed when the payload type is PodcastEpisode and a name resolves.
 * Reads headline, description, datePublished, duration, seriesName,
 * and contentUrl from the payload fields. The date falls back to the
 * post date, the duration follows the minutes or PT rule, the series
 * block is omitted when the series name is empty, and the media
 * block appears only with a content URL.
 */
final class PodcastEpisodePiece implements PieceInterface {
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
        return 'podcastepisode';
    }

    /**
     * Whether the piece is needed.
     *
     * @param Context $ctx Request context.
     */
    public function isNeeded( Context $ctx ): bool {
        if ('PodcastEpisode' !== SchemaHelpers::effectiveType($ctx, $this->settings)) {
            return false;
        }

        return '' !== SchemaHelpers::headline($ctx, SchemaHelpers::fields($ctx));
    }

    /**
     * Build the PodcastEpisode node.
     *
     * @param Context $ctx Request context.
     * @return array<string, mixed>
     */
    public function build( Context $ctx ): array {
        if ('PodcastEpisode' !== SchemaHelpers::effectiveType($ctx, $this->settings)) {
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
            '@type' => 'PodcastEpisode',
            '@id'   => $permalink . '#podcast',
            'name'  => $name,
        ];

        $description = SchemaHelpers::description($ctx, $fields);

        if ('' !== $description) {
            $node['description'] = $description;
        }

        $published = SchemaHelpers::normalizeDate($fields['datePublished'] ?? '');

        if ('' === $published) {
            $published = SchemaHelpers::postPublished($ctx);
        }

        if ('' !== $published) {
            $node['datePublished'] = $published;
        }

        $duration = SchemaHelpers::toDuration($fields['duration'] ?? '');

        if ('' !== $duration) {
            $node['duration'] = $duration;
        }

        $series = trim($fields['seriesName'] ?? '');

        if ('' !== $series) {
            $node['partOfSeries'] = [
                '@type' => 'PodcastSeries',
                'name'  => $series,
            ];
        }

        $media = trim($fields['contentUrl'] ?? '');

        if ('' !== $media) {
            $node['associatedMedia'] = [
                '@type'      => 'MediaObject',
                'contentUrl' => $media,
            ];
        }

        return $node;
    }
}
