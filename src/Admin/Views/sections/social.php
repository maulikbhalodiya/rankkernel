<?php
/**
 * Social settings section.
 *
 * Presentation only. SettingsPage prepares every variable used below.
 *
 * The section is the shared card. The default image row keeps its original
 * association: the row label points at the hidden field that actually stores
 * the URL, because that is the control the value belongs to. The picker
 * buttons are a pair of ordinary buttons next to it, not the field itself.
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
	<div class="rk-ui-card-header">
		<h3 class="rk-ui-card-title" id="rk-section-social-title"><?php echo esc_html__( 'Social', 'rankkernel' ); ?></h3>
	</div>
	<div class="rk-ui-card-body">
		<div class="rk-ui-form-row">
			<label class="rk-ui-form-label" for="rk-social-default-image"><?php echo esc_html__( 'Default social image', 'rankkernel' ); ?></label>
			<div id="rk-social-default-image-wrap">
				<img class="rk-social-preview" id="rk-social-default-image-preview" src="<?php echo esc_url( $socialDefaultImage ); ?>" alt=""<?php echo '' === $socialDefaultImage ? ' style="display:none;"' : ''; ?> />
				<input type="hidden" id="rk-social-default-image" name="social_default_image" value="<?php echo esc_attr( $socialDefaultImage ); ?>" />
				<input type="hidden" id="rk-social-default-image-id" name="social_default_image_id" value="<?php echo esc_attr( (string) $socialDefaultImageId ); ?>" />
				<p>
					<button type="button" class="button" id="rk-social-default-image-select"><?php echo esc_html__( 'Select image', 'rankkernel' ); ?></button>
					<button type="button" class="button" id="rk-social-default-image-remove"<?php echo '' === $socialDefaultImage ? ' style="display:none;"' : ''; ?>><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></button>
				</p>
			</div>
			<p class="rk-ui-hint"><?php echo esc_html__( 'Image used when a page has no featured or social image.', 'rankkernel' ); ?></p>
		</div>
		<div class="rk-ui-form-row">
			<label class="rk-ui-form-label" for="rk-twitter-site"><?php echo esc_html__( 'X site handle', 'rankkernel' ); ?></label>
			<input type="text" id="rk-twitter-site" name="twitter_site" value="<?php echo esc_attr( $twitterSite ); ?>" class="regular-text" maxlength="15" />
			<p class="rk-ui-hint"><?php echo esc_html__( 'Enter the handle without the at sign. RankKernel adds it when rendering metadata.', 'rankkernel' ); ?></p>
		</div>
	</div>
</section>
