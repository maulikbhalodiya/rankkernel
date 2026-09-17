<?php
/**
 * Shared admin screen guards for the editor metaboxes.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Reusable admin screen checks shared by the editor metaboxes.
 *
 * The MetadataBox and SchemaMetabox both register a Classic Editor metabox
 * that would duplicate controls the Gutenberg sidebar already renders. This
 * class owns that decision once so the two boxes can never drift apart.
 *
 * Every method fails safe to the Classic Editor behavior when WordPress
 * screen APIs are unavailable, which also keeps unit tests that stub or omit
 * get_current_screen working.
 */
final class ScreenGuard {
	/**
	 * Whether the current admin screen is the block editor.
	 *
	 * Guarded so unit tests that stub or omit get_current_screen keep
	 * working, and so a screen without is_block_editor() fails safe to the
	 * Classic Editor behavior instead of fataling.
	 *
	 * @return bool The result.
	 */
	public static function isBlockEditorScreen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! is_object( $screen ) || ! method_exists( $screen, 'is_block_editor' ) ) {
			return false;
		}

		return (bool) $screen->is_block_editor();
	}
}
