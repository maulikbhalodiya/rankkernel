<?php
/**
 * Breadcrumb template tags, global wrappers over the shared builder.
 *
 * Loaded by BreadcrumbsModule::boot only when the module is enabled,
 * so these globals exist solely behind the module gate. Each function
 * guards with function_exists.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! function_exists( 'rankkernel_breadcrumbs' ) ) {
	/**
	 * Echo the visible breadcrumb trail.
	 *
	 * Contract: echoes the HTML string returned by
	 * rankkernel_get_breadcrumbs for the same args. Args override the
	 * stored settings: separator, before, after, wrap_before,
	 * wrap_after, show_home, show_current. Caller args never bypass
	 * sanitization.
	 *
	 * @param array<string, mixed> $args Display arguments.
	 */
	function rankkernel_breadcrumbs( array $args = [] ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the renderer and the final HTML filter contract.
		echo rankkernel_get_breadcrumbs( $args );
	}
}

if ( ! function_exists( 'rankkernel_get_breadcrumbs' ) ) {
	/**
	 * Get the visible breadcrumb trail HTML.
	 *
	 * Contract: returns the accessible nav HTML for the current
	 * request, or an empty string when the context yields no trail.
	 * The resolved args pass through the
	 * rankkernel/breadcrumbs/args filter, items through
	 * rankkernel/breadcrumbs/items with per item allow_html semantics,
	 * and the final HTML through rankkernel/breadcrumbs.
	 *
	 * @param array<string, mixed> $args Display arguments.
	 * @return string Rendered HTML, empty without items.
	 */
	function rankkernel_get_breadcrumbs( array $args = [] ): string {
		return \RankKernel\Modules\Breadcrumbs\rankkernel_get_breadcrumbs( $args );
	}
}
