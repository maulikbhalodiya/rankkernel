<?php
/**
 * Per user social profile field.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

use RankKernel\Modules\Metadata\MetaPayload;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves the per author X handle.
 */
final class UserProfileField {
	/**
	 * User meta key consumed by the metadata renderer.
	 */
	public const META_KEY = 'rankkernel_twitter_handle';

	/**
	 * Register profile field hooks.
	 */
	public function register(): void {
		add_action( 'show_user_profile', [ $this, 'renderField' ], 10, 1 );
		add_action( 'edit_user_profile', [ $this, 'renderField' ], 10, 1 );
		add_action( 'personal_options_update', [ $this, 'saveField' ], 10, 1 );
		add_action( 'edit_user_profile_update', [ $this, 'saveField' ], 10, 1 );
	}

	/**
	 * Render the X handle field for a user profile.
	 *
	 * @param object $user User being edited.
	 */
	public function renderField( object $user ): void {
		$userId = isset( $user->ID ) ? absint( $user->ID ) : 0;

		if ( $userId <= 0 ) {
			return;
		}

		$storedHandle = get_user_meta( $userId, self::META_KEY, true );
		$handle       = MetaPayload::sanitizeTwitterHandle( $storedHandle );
		?>
		<h2><?php echo esc_html__( 'RankKernel Social', 'rankkernel' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="rankkernel-twitter-handle"><?php echo esc_html__( 'X handle', 'rankkernel' ); ?></label></th>
					<td>
						<input type="text" id="rankkernel-twitter-handle" name="rankkernel_twitter_handle" value="<?php echo esc_attr( $handle ); ?>" class="regular-text" maxlength="15" />
						<p class="description"><?php echo esc_html__( 'Enter the handle without the at sign. RankKernel adds it when rendering metadata.', 'rankkernel' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Save the X handle after the core profile form is verified.
	 *
	 * @param int $userId User being edited.
	 */
	public function saveField( int $userId ): void {
		$verified = check_admin_referer( 'update-user_' . $userId );

		if ( false === $verified || ! current_user_can( 'edit_user', $userId ) || ! isset( $_POST[ self::META_KEY ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rawHandle = wp_unslash( $_POST[ self::META_KEY ] );

		update_user_meta( $userId, self::META_KEY, MetaPayload::sanitizeTwitterHandle( $rawHandle ) );
	}
}
