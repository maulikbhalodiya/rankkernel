<?php
/**
 * Breadcrumb template tags, thin wrappers over the shared builder.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Breadcrumbs;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Settings\SettingsStore;
use WP_Query;

/**
 * Sanitize the final breadcrumb HTML after the public filter.
 *
 * The renderer is safe before filtering, but a filter is a trust boundary:
 * its return value is HTML that WordPress will print. Keep only the
 * breadcrumb vocabulary, strip scripts/iframes/event handlers, and limit
 * the one CSS custom property the renderer needs. The style attribute is
 * checked again because KSES permits CSS declarations by default.
 *
 * @param string $html Filtered breadcrumb HTML.
 * @return string Sanitized HTML.
 */
function sanitize_breadcrumb_html( string $html ): string {
	$allowed = [
		'nav'     => [
			'class'      => true,
			'aria-label' => true,
			'style'      => true,
		],
		'ol'      => [
			'class'      => true,
			'aria-label' => true,
		],
		'li'      => [
			'class'        => true,
			'aria-current' => true,
		],
		'a'       => [
			'class'        => true,
			'href'         => true,
			'rel'          => true,
			'aria-label'   => true,
			'aria-current' => true,
		],
		'span'    => [
			'class'        => true,
			'aria-hidden'  => true,
			'aria-current' => true,
			'style'        => true,
		],
		'div'     => [ 'class' => true ],
		'p'       => [ 'class' => true ],
		'section' => [ 'class' => true ],
		'b'       => [],
		'strong'  => [],
		'em'      => [],
		'i'       => [],
		'br'      => [],
	];

	$safeStyle = '';

	if ( class_exists( '\WP_HTML_Tag_Processor' ) ) {
		$processor = new \WP_HTML_Tag_Processor( $html );

		while ( $processor->next_tag( [ 'tag_name' => 'nav' ] ) ) {
			$class = $processor->get_attribute( 'class' );

			if ( ! is_string( $class ) || 1 !== preg_match( '/(?:^|\s)rk-breadcrumbs(?:\s|$)/', $class ) ) {
				continue;
			}

			$style = $processor->get_attribute( 'style' );

			if ( is_string( $style ) && is_safe_breadcrumb_style( $style ) ) {
				$safeStyle = $style;
			}

			break;
		}
	}

	if ( class_exists( '\WP_HTML_Tag_Processor' ) ) {
		$processor = new \WP_HTML_Tag_Processor( $html );

		while ( $processor->next_tag() ) {
			foreach ( [ 'href', 'src' ] as $attribute ) {
				$url = $processor->get_attribute( $attribute );

				if ( is_string( $url ) && ! is_safe_breadcrumb_url( $url ) ) {
					$processor->remove_attribute( $attribute );
				}
			}
		}

		$html = $processor->get_updated_html();
	}

	if ( function_exists( 'wp_kses' ) ) {
		$html = wp_kses( $html, $allowed );
	}

	if ( class_exists( '\WP_HTML_Tag_Processor' ) ) {
		$processor = new \WP_HTML_Tag_Processor( $html );

		while ( $processor->next_tag() ) {
			$style = $processor->get_attribute( 'style' );

			if ( is_string( $style ) && ! is_safe_breadcrumb_style( $style ) ) {
				$processor->remove_attribute( 'style' );
			}
		}

		$html = $processor->get_updated_html();

		// KSES may remove custom properties because they are not in the
		// core safe_style_css list. Restore only the already validated root
		// breadcrumb style, never an arbitrary style from the filter.
		if ( '' !== $safeStyle ) {
			$processor = new \WP_HTML_Tag_Processor( $html );

			while ( $processor->next_tag( [ 'tag_name' => 'nav' ] ) ) {
				$class = $processor->get_attribute( 'class' );

				if ( is_string( $class ) && 1 === preg_match( '/(?:^|\s)rk-breadcrumbs(?:\s|$)/', $class ) ) {
					$processor->set_attribute( 'style', $safeStyle );
					break;
				}
			}

			$html = $processor->get_updated_html();
		}
	} else {
		// Older WordPress (before the Tag Processor) cannot verify the one
		// permitted property. Dropping style is safer than allowing
		// arbitrary CSS; the breadcrumb list remains intact.
		$html = (string) preg_replace_callback(
			'/\sstyle="([^"]*)"/i',
			static function ( array $matches ): string {
				$style = html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' );

				return is_safe_breadcrumb_style( $style ) ? $matches[0] : '';
			},
			$html
		);
	}

	return $html;
}

/**
 * Whether a breadcrumb link URL is safe enough for the allowlist.
 *
 * KSES strips a javascript: scheme but can leave the remainder as a
 * relative path, so check the original attribute before KSES runs.
 *
 * @param string $url Raw href or src value.
 * @return bool The result.
 */
function is_safe_breadcrumb_url( string $url ): bool {
	$url = trim( $url );

	if ( '' === $url ) {
		return true;
	}

	// Reject control characters, raw whitespace, and backslashes. Browsers
	// normalize backslashes to slashes, so a leading \\ can become a
	// protocol-relative URL even though it does not look like one.
	if ( 1 === preg_match( '/[\x00-\x20\x7F\\\\]/', $url ) ) {
		return false;
	}

	// Protocol-relative URLs are external even though they start with a slash.
	if ( str_starts_with( $url, '//' ) ) {
		return false;
	}

	if ( 1 === preg_match( '/^(?:javascript|data|vbscript|file):/i', $url ) ) {
		return false;
	}

	return 1 === preg_match( '#^(?:https?://|/|\#|\?|\.{1,2}/|[A-Za-z0-9._~-]+/)#i', $url );
}

/**
 * Whether a style declaration is exactly the breadcrumb separator property.
 *
 * @param string $style Style attribute value.
 * @return bool The result.
 */
function is_safe_breadcrumb_style( string $style ): bool {
	return 1 === preg_match( '/\A\s*--rk-breadcrumb-separator\s*:\s*"(?:[^"\\\\<;{}()]|\\\\.)*"\s*;?\s*\z/', $style );
}

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
		$filtered = apply_filters( 'rankkernel/breadcrumbs', $html, $resolved ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

		// A callback that returns something that is not a string cannot
		// be rendered, so the generated markup is kept. The other
		// breadcrumb filters guard their return type the same way.
		if ( is_string( $filtered ) ) {
			$html = sanitize_breadcrumb_html( $filtered );
		}
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
