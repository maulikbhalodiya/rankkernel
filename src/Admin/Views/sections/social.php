<?php
/**
 * Social settings section.
 *
 * Presentation only. SettingsPage prepares every variable used below.
 *
 * The section is a page scoped card. The default image row is a compound
 * control: a preview frame plus the picker buttons, so its title names the
 * group and reaches assistive technology through aria-labelledby rather than
 * pointing a label at the hidden field that stores the URL. The picker ids are
 * untouched, because settings-admin.js looks them up by id.
 *
 * The preview keeps an inline display toggle rather than a class because the
 * media picker in settings-admin.js reveals and hides it by writing
 * element.style.display directly. Its size now comes from the page stylesheet
 * so no raw inline presentation is left on the element.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var string $socialDefaultImage  Stored default social image URL.
 * @var int    $socialDefaultImageId Stored default social image attachment ID.
 * @var string $twitterSite         Stored X site handle.
 */

defined( 'ABSPATH' ) || exit;
?>
<section id="rk-section-social" class="rk-ui-card rk-settings-section" aria-labelledby="rk-section-social-title">
	<div class="rk-settings-section-head">
		<h3 class="rk-settings-section-title" id="rk-section-social-title"><?php echo esc_html__( 'Social', 'rankkernel' ); ?></h3>
	</div>
	<div class="rk-settings-section-body">
		<div class="rk-ui-form-row" role="group" aria-labelledby="rk-social-default-image-label">
			<span class="rk-ui-form-label" id="rk-social-default-image-label"><?php echo esc_html__( 'Default social image', 'rankkernel' ); ?></span>
			<div id="rk-social-default-image-wrap" class="rk-settings-media">
				<img class="rk-social-preview" id="rk-social-default-image-preview" src="<?php echo esc_url( $socialDefaultImage ); ?>" alt=""<?php echo '' === $socialDefaultImage ? ' style="display:none;"' : ''; ?> />
				<input type="hidden" id="rk-social-default-image" name="social_default_image" value="<?php echo esc_attr( $socialDefaultImage ); ?>" />
				<input type="hidden" id="rk-social-default-image-id" name="social_default_image_id" value="<?php echo esc_attr( (string) $socialDefaultImageId ); ?>" />
				<div class="rk-settings-media-actions">
					<button type="button" class="button button-secondary" id="rk-social-default-image-select"><?php echo esc_html__( 'Select image', 'rankkernel' ); ?></button>
					<?php
					/*
					 * Removing the stored image discards data, so the control
					 * carries the destructive colour on the same bordered
					 * secondary surface the approved sheet draws for it. Both
					 * picker ids are untouched.
					 */
					?>
					<button type="button" class="button button-secondary rk-settings-btn-remove" id="rk-social-default-image-remove"<?php echo '' === $socialDefaultImage ? ' style="display:none;"' : ''; ?>><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></button>
				</div>
			</div>
			<p class="rk-ui-hint"><?php echo esc_html__( 'Image used when a page has no featured or social image.', 'rankkernel' ); ?></p>
		</div>
		<div class="rk-ui-form-row">
			<?php
			/*
			 * The sheet draws the handle field with an at sign sitting inside
			 * the input and the character limit beside the label. The mark is
			 * decoration, so it is hidden from assistive technology and the
			 * hint below still says to leave it out. The label keeps pointing
			 * straight at the input, which keeps the id, the name and the
			 * maxlength exactly as they were.
			 */
			?>
			<div class="rk-settings-row-head rk-settings-row-head--measure">
				<label class="rk-ui-form-label" for="rk-twitter-site"><?php echo esc_html__( 'X site handle', 'rankkernel' ); ?></label>
				<span class="rk-settings-meta"><?php echo esc_html__( '15 characters max', 'rankkernel' ); ?></span>
			</div>
			<div class="rk-input-prefix rk-settings-input--wide">
				<span class="rk-input-prefix-mark" aria-hidden="true"><?php echo esc_html( '@' ); ?></span>
				<input type="text" id="rk-twitter-site" name="twitter_site" value="<?php echo esc_attr( $twitterSite ); ?>" maxlength="15" class="rk-settings-input rk-settings-input--prefixed" />
			</div>
			<p class="rk-ui-hint"><?php echo esc_html__( 'Enter the handle without the at sign. RankKernel adds it when rendering metadata.', 'rankkernel' ); ?></p>
		</div>
	</div>
</section>
