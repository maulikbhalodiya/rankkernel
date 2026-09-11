<?php
/**
 * HowTo block, registration plus server render.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\blocks;

use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_url;
use function plugins_url;
use function sprintf;

/**
 * Registers the rankkernel/howto block and renders it on the server.
 *
 * Server rendering means zero frontend JS and zero frontend CSS ship
 * with the block. Steps render as a numbered list with title, text,
 * and optional image, so the visible order always matches the schema
 * order. The Step heading follows the title size unless the optional
 * stepTag override sets it explicitly, which keeps legacy blocks
 * rendering byte identical output while new blocks can diverge.
 */
final class HowtoBlock {
	/**
	 * Allowed title wrapper tags.
	 *
	 * @var string[]
	 */
	private const WRAPPERS = [ 'h2', 'h3', 'h4' ];

	/**
	 * Register the dynamic block type.
	 *
	 * The block category lives centrally in SchemaModule, so this class
	 * registers only the block type itself.
	 */
	public function register(): void {
		$this->registerBlock();
	}

	/**
	 * Plugin relative asset URL, empty when unavailable (tests, early boot).
	 */
	private static function assetUrl( string $path ): string {
		if ( ! function_exists( 'plugins_url' ) ) {
			return '';
		}

		$main = dirname( __DIR__, 4 ) . '/rankkernel.php';

		return (string) plugins_url( $path, $main );
	}

	/**
	 * Asset version from the single RANKKERNEL_VERSION constant, so
	 * bumping that one value in rankkernel.php busts the editor
	 * browser cache for both the script and the stylesheet.
	 */
	private static function assetVersion(): string {
		return \RankKernel\Plugin::version();
	}

	/**
	 * Register the dynamic block type from its block.json folder.
	 *
	 * The editor script and style register explicitly with full
	 * dependency lists instead of relying on metadata auto loading,
	 * so the editor globals they use always load first. All URLs flow
	 * through the single assetUrl helper, so tests and early boot see
	 * one consistent empty string fallback instead of mixed bases.
	 */
	public function registerBlock(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		if ( function_exists( 'wp_register_script' ) ) {
			$version = self::assetVersion();

			wp_register_script(
				'rankkernel-howto-editor',
				self::assetUrl( 'src/Modules/Schema/blocks/howto/howto-editor.js' ),
				[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ],
				$version,
				true
			);
		}

		if ( function_exists( 'wp_register_style' ) ) {
			$version = self::assetVersion();

			wp_register_style(
				'rankkernel-howto-editor',
				self::assetUrl( 'src/Modules/Schema/blocks/howto/editor.css' ),
				[],
				$version
			);
		}

		if ( function_exists( 'wp_set_script_translations' ) ) {
			try {
				wp_set_script_translations( 'rankkernel-howto-editor', 'rankkernel' );
			} catch ( \Throwable $e ) {
				// Test doubles can leak this name into later suites without a backend, so a failure here must never break registration. Production WP always provides it.
				unset( $e );
			}
		}

		register_block_type(
			__DIR__ . '/howto',
			[
				'editor_script'   => 'rankkernel-howto-editor',
				'editor_style'    => 'rankkernel-howto-editor',
				'render_callback' => [ $this, 'render' ],
			]
		);
	}

	/**
	 * Render the block.
	 *
	 * Every dynamic value is escaped, tag names come from an allowlist
	 * (h2, h3, h4, default h3) and step headings follow the title tag
	 * unless stepTag overrides them. The optional content and block
	 * params exist because WP core passes them to every render
	 * callback; this render ignores them and reads attributes only, so
	 * old stored blocks keep rendering. Rows with a blank title and
	 * blank text are skipped even when an image is present, which
	 * mirrors the HowtoPiece schema rule exactly, so the visible list
	 * and the HowTo nodes never diverge. Total time shows only when it
	 * matches the ISO 8601 duration shape, so invalid input stays out
	 * of the page the same way it stays out of the schema. Returns an
	 * empty string when no valid rows remain, so nothing renders.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content Block inner content, unused.
	 * @param mixed                $block Parsed block instance, unused.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WP core always passes content and block to render callbacks, so the signature keeps both names.
	public function render( array $attributes, string $content = '', mixed $block = null ): string {
		$title   = isset( $attributes['title'] ) ? trim( (string) $attributes['title'] ) : '';
		$wrapper = isset( $attributes['titleWrapper'] ) ? strtolower( trim( (string) $attributes['titleWrapper'] ) ) : 'h3';

		if ( ! in_array( $wrapper, self::WRAPPERS, true ) ) {
			$wrapper = 'h3';
		}

		$stepTag = isset( $attributes['stepTag'] ) ? strtolower( trim( (string) $attributes['stepTag'] ) ) : '';

		if ( ! in_array( $stepTag, self::WRAPPERS, true ) ) {
			$stepTag = '';
		}

		$stag = '' !== $stepTag ? $stepTag : $wrapper;

		$steps = $attributes['steps'] ?? [];

		if ( ! is_array( $steps ) ) {
			return '';
		}

		$rows  = '';
		$index = 0;

		foreach ( $steps as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$stepTitle = isset( $row['title'] ) ? trim( (string) $row['title'] ) : '';
			$text      = isset( $row['text'] ) ? trim( (string) $row['text'] ) : '';
			$image     = isset( $row['image'] ) ? self::safeImageUrl( (string) $row['image'] ) : '';
			$alt       = isset( $row['alt'] ) ? trim( (string) $row['alt'] ) : '';

			if ( '' === $stepTitle && '' === $text ) {
				continue;
			}

			++$index;

			$rows .= '<li class="rankkernel-howto-item">';

			if ( '' !== $stepTitle ) {
				$rows .= '<' . $stag . ' class="rankkernel-howto-step-title">'
					. '<span class="rankkernel-howto-number">' . $index . '. </span>'
					. esc_html( $stepTitle )
					. '</' . $stag . '>';
			}

			if ( '' !== $text ) {
				$rows .= '<div class="rankkernel-howto-step-text">' . wp_kses_post( $text ) . '</div>';
			}

			if ( '' !== $image ) {
				$rows .= '<img class="rankkernel-howto-step-image" src="' . esc_url( $image ) . '" alt="' . esc_attr( $alt ) . '" />';
			}

			$rows .= '</li>';
		}

		if ( '' === $rows ) {
			return '';
		}

		$out = '<div class="rankkernel-howto">';

		if ( '' !== $title ) {
			$out .= '<' . $wrapper . ' class="rankkernel-howto-title">'
				. esc_html( $title )
				. '</' . $wrapper . '>';
		}

		$description = isset( $attributes['description'] ) ? trim( (string) $attributes['description'] ) : '';

		if ( '' !== $description ) {
			$out .= '<p class="rankkernel-howto-description">' . esc_html( $description ) . '</p>';
		}

		$totalTime = isset( $attributes['totalTime'] ) ? trim( (string) $attributes['totalTime'] ) : '';

		if ( '' !== $totalTime && self::validDuration( $totalTime ) ) {
			// translators: %s is the total time value.
			$timeLine = sprintf( esc_html__( 'Total time: %s', 'rankkernel' ), esc_html( $totalTime ) );
			$out     .= '<p class="rankkernel-howto-total-time">' . $timeLine . '</p>';
		}

		$cost = isset( $attributes['estimatedCost'] ) ? trim( (string) $attributes['estimatedCost'] ) : '';

		if ( '' !== $cost ) {
			// translators: %s is the estimated cost value.
			$costLine = sprintf( esc_html__( 'Estimated cost: %s', 'rankkernel' ), esc_html( $cost ) );
			$out     .= '<p class="rankkernel-howto-estimated-cost">' . $costLine . '</p>';
		}

		$out .= self::stringList( $attributes, 'tools', 'rankkernel-howto-tools' );
		$out .= self::stringList( $attributes, 'materials', 'rankkernel-howto-materials' );

		$out .= '<ol class="rankkernel-howto-list" role="list" style="list-style-type:none;">' . $rows . '</ol>';
		$out .= '</div>';

		return $out;
	}

	/**
	 * Render one repeatable plain string list from block attributes.
	 *
	 * Empty values are dropped, so an untouched list adds no markup.
	 * The label literal stays inside this helper so the translation
	 * call always sees a single static string.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $key Attribute name, tools or materials.
	 * @param string               $cssClass List wrapper class.
	 */
	private static function stringList( array $attributes, string $key, string $cssClass ): string {
		$items = $attributes[ $key ] ?? [];

		if ( ! is_array( $items ) ) {
			return '';
		}

		$label = 'materials' === $key ? esc_html__( 'Materials', 'rankkernel' ) : esc_html__( 'Tools', 'rankkernel' );

		$rows = '';

		foreach ( $items as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}

			$value = trim( $item );

			if ( '' === $value ) {
				continue;
			}

			$rows .= '<li>' . esc_html( $value ) . '</li>';
		}

		if ( '' === $rows ) {
			return '';
		}

		return '<div class="' . $cssClass . '"><span class="' . $cssClass . '-label">'
			. $label
			. '</span><ul>' . $rows . '</ul></div>';
	}

	/**
	 * Image URL safe for output, empty when the scheme is unsafe.
	 *
	 * Trims the value, rejects script capable schemes before WP sees
	 * them, then runs the WP raw escaper when available so stored URLs
	 * keep their safe shape.
	 *
	 * @param string $url Raw image value.
	 */
	private static function safeImageUrl( string $url ): string {
		$clean = trim( $url );

		if ( '' === $clean ) {
			return '';
		}

		if ( 1 === preg_match( '#^\s*(?:javascript|data|vbscript)\s*:#i', $clean ) ) {
			return '';
		}

		if ( function_exists( 'esc_url_raw' ) ) {
			$clean = trim( (string) esc_url_raw( $clean ) );
		}

		if ( '' === $clean ) {
			return '';
		}

		if ( 1 === preg_match( '#^\s*(?:javascript|data|vbscript)\s*:#i', $clean ) ) {
			return '';
		}

		return $clean;
	}

	/**
	 * Whether a total time value matches the ISO 8601 duration shape.
	 *
	 * Accepts week, date, and time designs such as P1W, P1DT2H, PT30M,
	 * and PT1H. Anything else stays out of both the page and the
	 * schema, and the editor hint shows the expected shape.
	 *
	 * @param string $value Raw total time value.
	 */
	private static function validDuration( string $value ): bool {
		return 1 === preg_match( '/^P(?!$)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(?=\d)(\d+H)?(\d+M)?(\d+S)?)?$/', $value );
	}
}
