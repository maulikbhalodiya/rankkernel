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
	 * A refused capability or nonce stops the request with a 403 instead of
	 * returning silently, because core would otherwise show its own success
	 * notice while the handle was never written. A non string field is
	 * ignored, and a failed write stops with a visible error.
	 *
	 * @param int $userId User being edited.
	 */
	public function saveField( int $userId ): void {
		if ( ! current_user_can( 'edit_user', $userId ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to edit this user.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		if ( false === check_admin_referer( 'update-user_' . $userId ) ) {
			wp_die(
				esc_html__( 'Security check failed. Please refresh and try again.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		// The value is only read when it is a string, so an array shaped field
		// can never reach the sanitiser with the wrong type.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce verified above, the value is a string that is unslashed then sanitised below.
		$rawHandle = $_POST[ self::META_KEY ] ?? null;

		if ( ! is_string( $rawHandle ) ) {
			return;
		}

		$saved = update_user_meta( $userId, self::META_KEY, MetaPayload::sanitizeTwitterHandle( wp_unslash( $rawHandle ) ) );

		// A strict false means the write failed, so the editor sees an error
		// instead of core's success notice while the handle was lost.
		if ( false === $saved ) {
			wp_die(
				esc_html__( 'The X handle could not be saved. Please try again.', 'rankkernel' ),
				'',
				[ 'response' => 500 ]
			);
		}
	}
}
