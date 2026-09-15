<?php
/**
 * Visible breadcrumb renderer, accessible HTML only.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Breadcrumbs;

/**
 * Converts canonical Item lists into accessible HTML.
 *
 * Markup follows the core accessible pattern: a nav with a translatable
 * aria-label, an ordered list of items, linked ancestors as anchors, and
 * the current item as a span with aria-current page. The separator never
 * appears as an exposed text node; it travels as a CSS custom property
 * consumed by the scoped stylesheet through generated content, so
 * assistive technology never announces it.
 */
final class Renderer {
	/**
	 * Render items as accessible HTML.
	 *
	 * Args: before and after wrap the nav, wrap_before and wrap_after
	 * wrap those, separator travels as a CSS custom property, show_home
	 * drops the first item, show_current drops the last item. Labels
	 * escape with esc_html unless the item opts into allow_html, which
	 * filters through wp_kses_post. URLs escape with esc_url and
	 * attributes with esc_attr.
	 *
	 * @param Item[]               $items Trail items, ordered.
	 * @param array<string, mixed> $args  Display arguments.
	 * @return string Rendered HTML, empty without items.
	 */
	public function render( array $items, array $args = [] ): string {
		$items = array_values(
			array_filter(
				$items,
				static function ( mixed $item ): bool {
					return $item instanceof Item;
				}
			)
		);

		$showHome    = $this->argBool( $args, 'show_home', true );
		$showCurrent = $this->argBool( $args, 'show_current', true );

		if ( ! $showHome && [] !== $items ) {
			array_shift( $items );
		}

		if ( ! $showCurrent && [] !== $items ) {
			array_pop( $items );
		}

		if ( [] === $items ) {
			return '';
		}

		$before     = isset( $args['before'] ) && is_string( $args['before'] ) ? $args['before'] : '';
		$after      = isset( $args['after'] ) && is_string( $args['after'] ) ? $args['after'] : '';
		$wrapBefore = isset( $args['wrap_before'] ) && is_string( $args['wrap_before'] ) ? $args['wrap_before'] : '';
		$wrapAfter  = isset( $args['wrap_after'] ) && is_string( $args['wrap_after'] ) ? $args['wrap_after'] : '';
		$separator  = isset( $args['separator'] ) ? (string) $args['separator'] : '/';
		$ariaLabel  = isset( $args['aria_label'] ) && is_string( $args['aria_label'] ) && '' !== trim( $args['aria_label'] ) ? $args['aria_label'] : __( 'Breadcrumbs', 'rankkernel' );

		$count = count( $items );
		$lis   = '';

		foreach ( $items as $index => $item ) {
			$isCurrent = ( $index === $count - 1 );
			$lis      .= $this->renderItem( $item, $isCurrent );
		}

		$out  = $wrapBefore . $before;
		$out .= '<nav class="rk-breadcrumbs" aria-label="' . esc_attr( $ariaLabel ) . '" style="--rk-breadcrumb-separator:' . esc_attr( $separator ) . ';">';
		$out .= '<ol class="rk-breadcrumbs-list">' . $lis . '</ol>';
		$out .= '</nav>';
		$out .= $after . $wrapAfter;

		return $out;
	}

	/**
	 * Render one list item.
	 *
	 * Linked non current items become anchors. The current item and
	 * unlinked items become spans, with aria-current page on the
	 * current one only.
	 *
	 * @param Item $item      Trail item.
	 * @param bool $isCurrent Whether this is the last item.
	 * @return string List item HTML.
	 */
	private function renderItem( Item $item, bool $isCurrent ): string {
		$label = $item->allowHtml() ? wp_kses_post( $item->label() ) : esc_html( $item->label() );
		$url   = trim( $item->url() );

		if ( $isCurrent || '' === $url ) {
			if ( $isCurrent ) {
				return '<li class="rk-breadcrumbs-item rk-breadcrumbs-item-current"><span aria-current="page">' . $label . '</span></li>';
			}

			return '<li class="rk-breadcrumbs-item"><span>' . $label . '</span></li>';
		}

		return '<li class="rk-breadcrumbs-item"><a href="' . esc_url( $url ) . '">' . $label . '</a></li>';
	}

	/**
	 * Read a boolean argument with a fallback.
	 *
	 * @param array<string, mixed> $args     Display arguments.
	 * @param string               $key      Argument key.
	 * @param bool                 $fallback Fallback value.
	 * @return bool The result.
	 */
	private function argBool( array $args, string $key, bool $fallback ): bool {
		if ( ! array_key_exists( $key, $args ) ) {
			return $fallback;
		}

		$value = $args[ $key ];

		if ( is_bool( $value ) ) {
			return $value;
		}

		$normalized = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

		if ( null !== $normalized ) {
			return $normalized;
		}

		return $fallback;
	}
}
