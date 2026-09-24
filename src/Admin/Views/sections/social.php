<?php
/**
 * Social settings section.
 *
 * Presentation only. SettingsPage prepares every variable used below.
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
<section id="rk-section-social" class="rk-settings-section" aria-labelledby="rk-section-social-title">
	<h2 id="rk-section-social-title"><?php echo esc_html__( 'Social', 'rankkernel' ); ?></h2>
	<table class="form-table" role="presentation"><tbody>
		<tr>
			<th scope="row"><label for="rk-social-default-image"><?php echo esc_html__( 'Default social image', 'rankkernel' ); ?></label></th>
			<td>
				<div id="rk-social-default-image-wrap">
					<img id="rk-social-default-image-preview" src="<?php echo esc_url( $socialDefaultImage ); ?>" alt="" style="max-width:150px;height:auto;<?php echo '' === $socialDefaultImage ? 'display:none;' : ''; ?>" />
					<input type="hidden" id="rk-social-default-image" name="social_default_image" value="<?php echo esc_attr( $socialDefaultImage ); ?>" />
					<input type="hidden" id="rk-social-default-image-id" name="social_default_image_id" value="<?php echo esc_attr( (string) $socialDefaultImageId ); ?>" />
					<p>
						<button type="button" class="button" id="rk-social-default-image-select"><?php echo esc_html__( 'Select image', 'rankkernel' ); ?></button>
						<button type="button" class="button" id="rk-social-default-image-remove"<?php echo '' === $socialDefaultImage ? ' style="display:none;"' : ''; ?>><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></button>
					</p>
				</div>
				<p class="description"><?php echo esc_html__( 'Image used when a page has no featured or social image.', 'rankkernel' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="rk-twitter-site"><?php echo esc_html__( 'X site handle', 'rankkernel' ); ?></label></th>
			<td>
				<input type="text" id="rk-twitter-site" name="twitter_site" value="<?php echo esc_attr( $twitterSite ); ?>" class="regular-text" maxlength="15" />
				<p class="description"><?php echo esc_html__( 'Enter the handle without the at sign. RankKernel adds it when rendering metadata.', 'rankkernel' ); ?></p>
			</td>
		</tr>
	</tbody></table>
</section>
