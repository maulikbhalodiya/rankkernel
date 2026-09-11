<?php
/**
 * FAQ block, registration plus server render.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\blocks;

/**
 * Registers the rankkernel/faq block and renders it on the server.
 *
 * Server rendering means zero frontend JS and zero frontend CSS ship
 * with the block. The tradeoff is visible content with no accordion
 * toggle, which keeps the markup screen reader friendly and lets the
 * FAQPage schema match exactly what visitors see.
 */
final class FaqBlock {
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
	 * Asset version from file modification time, so editor browsers
	 * always fetch the current file after an update without waiting
	 * for a plugin version bump. Falls back to the plugin version
	 * when the file is unreadable (tests, early boot).
	 */
	private static function assetVersion( string $path ): string {
		$file = dirname( __DIR__, 4 ) . '/' . ltrim( $path, '/' );

		if ( is_readable( $file ) ) {
			$mtime = filemtime( $file );

			if ( false !== $mtime ) {
				return (string) $mtime;
			}
		}

		return \RankKernel\Plugin::VERSION;
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
			$version = self::assetVersion( 'src/Modules/Schema/blocks/faq/faq-editor.js' );

			wp_register_script(
				'rankkernel-faq-editor',
				self::assetUrl( 'src/Modules/Schema/blocks/faq/faq-editor.js' ),
				[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ],
				$version,
				true
			);
		}

		if ( function_exists( 'wp_register_style' ) ) {
			$version = self::assetVersion( 'src/Modules/Schema/blocks/faq/editor.css' );

			wp_register_style(
				'rankkernel-faq-editor',
				self::assetUrl( 'src/Modules/Schema/blocks/faq/editor.css' ),
				[],
				$version
			);
		}

		if ( function_exists( 'wp_set_script_translations' ) ) {
			try {
				wp_set_script_translations( 'rankkernel-faq-editor', 'rankkernel' );
			} catch ( \Throwable $e ) {
				// Test doubles can leak this name into later suites without a backend, so a failure here must never break registration. Production WP always provides it.
				unset( $e );
			}
		}

		register_block_type(
			__DIR__ . '/faq',
			[
				'editor_script'   => 'rankkernel-faq-editor',
				'editor_style'    => 'rankkernel-faq-editor',
				'render_callback' => [ $this, 'render' ],
			]
		);
	}

	/**
	 * Render the block.
	 *
	 * Every dynamic value is escaped, tag names come from an allowlist
	 * (h2, h3, h4, default h3) and the list tag is ul or ol (default
	 * ul). Ordered lists number through a single span inside each
	 * question heading, so the number inherits the heading size,
	 * weight, and color, while the list itself carries no markers, so
	 * numbers never print twice. Unordered lists render plain bullets
	 * with no number spans. The optional content and block params exist because WP core
	 * passes them to every render callback; this render ignores them
	 * and reads attributes only, so old stored blocks keep rendering.
	 * Rows with an empty question are skipped even when an answer is
	 * present, which mirrors the FaqPiece schema rule exactly, so the
	 * visible list and the FAQPage nodes never diverge. Returns an
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

		$questionTag = isset( $attributes['questionTag'] ) ? strtolower( trim( (string) $attributes['questionTag'] ) ) : '';

		if ( ! in_array( $questionTag, self::WRAPPERS, true ) ) {
			$questionTag = '';
		}

		$qtag = '' !== $questionTag ? $questionTag : $wrapper;

		$list = isset( $attributes['listStyle'] ) ? strtolower( trim( (string) $attributes['listStyle'] ) ) : 'ul';

		if ( ! in_array( $list, [ 'ul', 'ol' ], true ) ) {
			$list = 'ul';
		}

		$ordered = ( 'ol' === $list );

		$questions = $attributes['questions'] ?? [];

		if ( ! is_array( $questions ) ) {
			$questions = [];
		}

		$rows  = '';
		$index = 0;

		foreach ( $questions as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$question = isset( $row['question'] ) ? trim( (string) $row['question'] ) : '';
			$answer   = isset( $row['answer'] ) ? trim( (string) $row['answer'] ) : '';

			if ( '' === $question ) {
				continue;
			}

			++$index;

			$rows .= '<li class="rankkernel-faq-item">';
			$rows .= '<' . $qtag . ' class="rankkernel-faq-question">'
				. ( $ordered ? '<span class="rankkernel-faq-number">' . $index . '. </span>' : '' )
				. esc_html( $question )
				. '</' . $qtag . '>';

			if ( '' !== $answer ) {
				$rows .= '<div class="rankkernel-faq-answer">' . wp_kses_post( $answer ) . '</div>';
			}

			$rows .= '</li>';
		}

		if ( '' === $rows ) {
			return '';
		}

		$out = '<div class="rankkernel-faq">';

		if ( '' !== $title ) {
			$out .= '<' . $wrapper . ' class="rankkernel-faq-title">'
				. esc_html( $title )
				. '</' . $wrapper . '>';
		}

		$listStyle = $ordered ? ' style="list-style-type:none;"' : ' style="list-style-type:disc;"';

		$out .= '<' . $list . ' class="rankkernel-faq-list" role="list"' . $listStyle . '>' . $rows . '</' . $list . '>';
		$out .= '</div>';

		return $out;
	}
}
