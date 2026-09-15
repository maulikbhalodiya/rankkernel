<?php
/**
 * Breadcrumb item value object.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Breadcrumbs;

/**
 * One immutable breadcrumb trail entry.
 *
 * The label is plain text by default. Rendering escapes it unless
 * allow_html was explicitly opted in. The schema_excluded flag marks
 * visible only crumbs (pagination) that must never reach JSON-LD.
 */
final class Item {
	/**
	 * Constructor.
	 *
	 * @param string $label          Crumb label, plain text unless allow_html is true.
	 * @param string $url            Crumb URL, empty for unlinked crumbs.
	 * @param bool   $allowHtml      Whether the label may carry HTML.
	 * @param bool   $schemaExcluded Whether the crumb stays out of schema output.
	 */
	public function __construct(
		private readonly string $label,
		private readonly string $url = '',
		private readonly bool $allowHtml = false,
		private readonly bool $schemaExcluded = false
	) {
	}

	/**
	 * Get the crumb label.
	 *
	 * @return string The result.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Get the crumb URL, empty when unlinked.
	 *
	 * @return string The result.
	 */
	public function url(): string {
		return $this->url;
	}

	/**
	 * Whether the label may carry HTML.
	 *
	 * @return bool The result.
	 */
	public function allowHtml(): bool {
		return $this->allowHtml;
	}

	/**
	 * Whether the crumb is excluded from schema output.
	 *
	 * @return bool The result.
	 */
	public function schemaExcluded(): bool {
		return $this->schemaExcluded;
	}
}
