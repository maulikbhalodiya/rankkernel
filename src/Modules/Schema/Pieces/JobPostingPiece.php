<?php
/**
 * Job posting piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;

/**
 * Job posting as a JobPosting node.
 *
 * Needed when the payload type is JobPosting and a title resolves. Reads
 * headline as the title, description, company, jobLocation, salary,
 * priceCurrency, datePosted, and validThrough from the payload fields.
 * The hiring organization points at the site organization, or at a named
 * organization when the company field is set. The salary maps to a
 * MonetaryAmount with currency and value, the posted date falls back to
 * the post date, and invalid dates are dropped.
 */
final class JobPostingPiece implements PieceInterface {
    /**
     * Get piece id.
     */
    public function getId(): string {
        return 'jobposting';
    }

    /**
     * Whether the piece is needed.
     *
     * @param Context $ctx Request context.
     */
    public function isNeeded( Context $ctx ): bool {
        if ('JobPosting' !== SchemaHelpers::payloadType($ctx)) {
            return false;
        }

        return '' !== SchemaHelpers::headline($ctx, SchemaHelpers::fields($ctx));
    }

    /**
     * Build the JobPosting node.
     *
     * @param Context $ctx Request context.
     * @return array<string, mixed>
     */
    public function build( Context $ctx ): array {
        if ('JobPosting' !== SchemaHelpers::payloadType($ctx)) {
            return [];
        }

        $fields = SchemaHelpers::fields($ctx);
        $title  = SchemaHelpers::headline($ctx, $fields);

        if ('' === $title) {
            return [];
        }

        $permalink = $ctx->permalink();

        if ('' === $permalink) {
            return [];
        }

        $node = [
            '@type' => 'JobPosting',
            '@id'   => $permalink . '#jobposting',
            'title' => $title,
        ];

        $description = SchemaHelpers::description($ctx, $fields);

        if ('' !== $description) {
            $node['description'] = $description;
        }

        $company = trim($fields['company'] ?? '');

        if ('' !== $company) {
            $node['hiringOrganization'] = [
                '@type' => 'Organization',
                'name'  => $company,
            ];
        } else {
            $node['hiringOrganization'] = [
                '@id' => SchemaHelpers::orgId(),
            ];
        }

        $location = trim($fields['jobLocation'] ?? '');

        if ('' !== $location) {
            $node['jobLocation'] = [
                '@type' => 'Place',
                'name'  => $location,
            ];
        }

        $salary = SchemaHelpers::priceString($fields['salary'] ?? '');

        if ('' !== $salary) {
            $amount = [
                '@type' => 'MonetaryAmount',
                'value' => $salary,
            ];

            $currency = SchemaHelpers::currency($fields['priceCurrency'] ?? '');

            if ('' !== $currency) {
                $amount['currency'] = $currency;
            }

            $node['baseSalary'] = $amount;
        }

        $posted = SchemaHelpers::normalizeDate($fields['datePosted'] ?? '');

        if ('' === $posted) {
            $posted = SchemaHelpers::postPublished($ctx);
        }

        if ('' !== $posted) {
            $node['datePosted'] = $posted;
        }

        $valid = SchemaHelpers::normalizeDate($fields['validThrough'] ?? '');

        if ('' !== $valid) {
            $node['validThrough'] = $valid;
        }

        return $node;
    }
}
