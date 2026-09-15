<?php
/**
 * Breadcrumb template tags, thin wrappers over the shared builder.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Breadcrumbs;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Get the visible breadcrumb trail HTML.
 *
 * Builds the request Context once, runs the canonical TrailBuilder,
 * filters the items through rankkernel/breadcrumbs/items with per item
 * allow_html semantics, renders with Renderer, then filters the final
 * HTML through rankkernel/breadcrumbs. Returns an empty string when
 * the context yields no trail or the query is unavailable.
 *
 * @param array<string, mixed> $args Display arguments.
 * @return string Rendered HTML, empty without items.
 */
function rankkernel_get_breadcrumbs( array $args = [] ): string {
	$settings = new BreadcrumbsSettings();
	$resolved = resolve_breadcrumb_args( $settings, $args );

	$query = current_breadcrumb_query();

	if ( null === $query ) {
		return '';
	}

	$ctx     = new Context( $query, new SettingsStore() );
	$builder = new TrailBuilder(
		$ctx,
		$settings,
		[
			'show_home'    => $resolved['show_home'],
			'show_current' => $resolved['show_current'],
		]
	);
	$items   = normalize_breadcrumb_items( filter_breadcrumb_items( $builder->build() ) );

	if ( [] === $items && ! $resolved['show_home'] && ! $resolved['show_current'] ) {
		return '';
	}

	enqueue_breadcrumb_style();

	// Visibility is already applied by the builder, before pagination, so the
	// renderer must not trim again or it would drop a Page N crumb.
	$renderArgs                     = $resolved;
	$renderArgs['apply_visibility'] = false;

	$html = ( new Renderer() )->render( $items, $renderArgs );

	if ( function_exists( 'apply_filters' ) ) {
		$html = (string) apply_filters( 'rankkernel/breadcrumbs', $html, $resolved ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.
	}

	return $html;
}

/**
 * Resolve display args from settings, then caller overrides.
 *
 * The resolved map passes through the rankkernel/breadcrumbs/args
 * filter and sanitizes again, so neither caller input nor filtered
 * values reach the renderer raw.
 *
 * @param BreadcrumbsSettings  $settings Breadcrumb settings.
 * @param array<string, mixed> $args     Caller overrides.
 * @return array<string, mixed> Resolved display args.
 */
function resolve_breadcrumb_args( BreadcrumbsSettings $settings, array $args ): array {
	$resolved = [
		'separator'    => (string) $settings->get( 'separator', '/' ),
		'before'       => '',
		'after'        => '',
		'wrap_before'  => '',
		'wrap_after'   => '',
		'show_home'    => (bool) $settings->get( 'show_home', true ),
		'show_current' => (bool) $settings->get( 'show_current', true ),
	];

	foreach ( $resolved as $key => $default ) {
		if ( array_key_exists( $key, $args ) ) {
			$resolved[ $key ] = $args[ $key ];
		}
	}

	if ( function_exists( 'apply_filters' ) ) {
		$filtered = apply_filters( 'rankkernel/breadcrumbs/args', $resolved ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

		if ( is_array( $filtered ) ) {
			$resolved = array_merge( $resolved, $filtered );
		}
	}

	return sanitize_breadcrumb_args( $resolved );
}

/**
 * Sanitize resolved display args.
 *
 * The separator and aria label strip tags; wrapper HTML filters
 * through wp_kses_post so themes can pass markup without scripts;
 * show flags coerce to booleans.
 *
 * @param array<string, mixed> $args Resolved args.
 * @return array<string, mixed> Sanitized args.
 */
function sanitize_breadcrumb_args( array $args ): array {
	$out = [
		'separator'    => '/',
		'before'       => '',
		'after'        => '',
		'wrap_before'  => '',
		'wrap_after'   => '',
		'show_home'    => true,
		'show_current' => true,
	];

	if ( isset( $args['separator'] ) ) {
		$separator = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( (string) $args['separator'] ) : trim( strip_tags( (string) $args['separator'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- fallback only when WordPress is not loaded.

		$out['separator'] = '' !== $separator ? $separator : '/';
	}

	foreach ( [ 'before', 'after', 'wrap_before', 'wrap_after' ] as $key ) {
		if ( ! isset( $args[ $key ] ) || ! is_string( $args[ $key ] ) ) {
			continue;
		}

		$out[ $key ] = function_exists( 'wp_kses_post' ) ? wp_kses_post( $args[ $key ] ) : strip_tags( $args[ $key ] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- fallback only when WordPress is not loaded.
	}

	foreach ( [ 'show_home', 'show_current' ] as $key ) {
		if ( ! array_key_exists( $key, $args ) ) {
			continue;
		}

		$value = $args[ $key ];

		if ( is_bool( $value ) ) {
			$out[ $key ] = $value;

			continue;
		}

		$normalized = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

		$out[ $key ] = null !== $normalized ? $normalized : (bool) $value;
	}

	if ( isset( $args['aria_label'] ) && is_string( $args['aria_label'] ) && '' !== trim( $args['aria_label'] ) ) {
		$out['aria_label'] = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $args['aria_label'] ) : trim( strip_tags( $args['aria_label'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- fallback only when WordPress is not loaded.
	}

	return $out;
}

/**
 * Filter canonical items with allow_html semantics.
 *
 * Items keep their Item type so the renderer can honor the opt in
 * HTML contract. Array entries shaped with label or name plus url or
 * item coerce to Item; anything else drops out.
 *
 * @param Item[] $items Canonical trail.
 * @return Item[] Filtered trail.
 */
function filter_breadcrumb_items( array $items ): array {
	if ( function_exists( 'apply_filters' ) ) {
		$filtered = apply_filters( 'rankkernel/breadcrumbs/items', $items ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

		if ( is_array( $filtered ) ) {
			$items = $filtered;
		}
	}

	return $items;
}

/**
 * Normalize filtered items back to Item objects.
 *
 * @param mixed[] $items Filtered entries.
 * @return Item[] Normalized trail.
 */
function normalize_breadcrumb_items( array $items ): array {
	$out = [];

	foreach ( $items as $entry ) {
		if ( $entry instanceof Item ) {
			$out[] = $entry;

			continue;
		}

		if ( ! is_array( $entry ) ) {
			continue;
		}

		$label = '';

		if ( isset( $entry['label'] ) && is_string( $entry['label'] ) ) {
			$label = $entry['label'];
		} elseif ( isset( $entry['name'] ) && is_string( $entry['name'] ) ) {
			$label = $entry['name'];
		}

		$url = '';

		if ( isset( $entry['url'] ) && is_string( $entry['url'] ) ) {
			$url = $entry['url'];
		} elseif ( isset( $entry['item'] ) && is_string( $entry['item'] ) ) {
			$url = $entry['item'];
		}

		if ( '' === $label && '' === $url ) {
			continue;
		}

		$out[] = new Item( $label, $url, ! empty( $entry['allow_html'] ) );
	}

	return $out;
}

/**
 * Current query for the breadcrumb context.
 *
 * @return WP_Query|null Query instance or null when unavailable.
 */
function current_breadcrumb_query(): ?WP_Query {
	global $wp_query;

	if ( isset( $wp_query ) && $wp_query instanceof WP_Query ) {
		return $wp_query;
	}

	return null;
}

/**
 * Enqueue the scoped breadcrumb stylesheet when available.
 */
function enqueue_breadcrumb_style(): void {
	if ( function_exists( 'wp_enqueue_style' ) ) {
		wp_enqueue_style( 'rankkernel-breadcrumbs' );
	}
}
