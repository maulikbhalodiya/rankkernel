<?php
/**
 * Metadata editor metabox view.
 *
 * Presentation only. MetadataBox prepares every variable used below, and owns
 * capability checks, nonce verification, request handling, saving, reset
 * handling, token resolution and view state preparation.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var int    $postId                   Current post id.
 * @var string $titleTemplate            Configured title template, tokens kept.
 * @var string $resolvedTitleTemplate    Title template rendered into its inherited value.
 * @var string $descriptionTemplate      Configured description template, tokens kept.
 * @var string $resolvedDescriptionTemplate Description template rendered into its inherited value.
 * @var string $titleOverride            Stored title override, empty when the template applies.
 * @var string $descriptionOverride      Stored description override, empty when the template applies.
 * @var string $effectiveTitle           Title the frontend will print right now.
 * @var string $effectiveDescription     Description the frontend will print right now.
 * @var bool   $titleInherited           Whether the title still inherits from the template.
 * @var bool   $descriptionInherited     Whether the description still inherits from the template.
 * @var array<int, array{token: string, label: string, value: string}> $tokenRows Token quick insert rows.
 * @var int    $titleLimit               Title length budget.
 * @var int    $descriptionLimit         Description length budget.
 * @var string $canonical                Canonical URL override.
 * @var array<string, mixed> $robots   Robots directives.
 * @var array<string, mixed> $og       Open Graph subtree.
 * @var array<string, mixed> $twitter  Twitter subtree.
 * @var string $ogImageUrl               Open Graph image URL override.
 * @var int    $ogImageId                Open Graph image attachment id.
 * @var string $twitterImageUrl          Twitter image URL override.
 * @var int    $twitterImageId           Twitter image attachment id.
 * @var array<int, array{value: string, label: string}> $cardOptions Twitter card option rows.
 * @var array<int, array{value: string, label: string}> $maxImagePreviewOptions Max image preview option rows.
 * @var string $previewUrl               Permalink used by the SERP preview shell.
 * @var string $previewSiteName          Site name used by the SERP preview shell.
 * @var string $defaultOgImage           Default Open Graph image used by the social preview card.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$robotsNoindex      = empty( $robots['index'] );
$robotsNofollow     = empty( $robots['follow'] );
$maxSnippet         = isset( $robots['max_snippet'] ) && null !== $robots['max_snippet'] ? (string) $robots['max_snippet'] : '';
$maxVideoPreview    = isset( $robots['max_video_preview'] ) && null !== $robots['max_video_preview'] ? (string) $robots['max_video_preview'] : '';
$maxImagePreview    = isset( $robots['max_image_preview'] ) && null !== $robots['max_image_preview'] ? (string) $robots['max_image_preview'] : '';
$ogType             = isset( $og['type'] ) ? (string) $og['type'] : '';
$twitterCard        = isset( $twitter['card'] ) ? (string) $twitter['card'] : 'summary_large_image';
$twitterTitle       = isset( $twitter['title'] ) ? (string) $twitter['title'] : '';
$twitterDescription = isset( $twitter['description'] ) ? (string) $twitter['description'] : '';
$ogTitle            = isset( $og['title'] ) ? (string) $og['title'] : '';
$ogDescription      = isset( $og['description'] ) ? (string) $og['description'] : '';
$canonicalValue     = $canonical;

// Social unfurl card state. Social fields win, then the General effective
// values, then the inherited default Open Graph image.
$socialTitle       = '' !== trim( $ogTitle ) ? $ogTitle : $effectiveTitle;
$socialDescription = '' !== trim( $ogDescription ) ? $ogDescription : $effectiveDescription;
$socialImage       = '' !== trim( $ogImageUrl ) ? $ogImageUrl : $defaultOgImage;
$socialCard        = 'summary' === $twitterCard ? 'summary' : 'summary_large_image';
$socialCardLabel   = 'summary' === $socialCard ? __( 'Small image card', 'rankkernel' ) : __( 'Large image card', 'rankkernel' );
?>
<?php wp_nonce_field( 'rankkernel_meta_save', 'rankkernel_meta_nonce' ); ?>
<input type="hidden" name="rankkernel_meta_fields" value="1" />

<div class="rankkernel-meta-editor" data-rankkernel-meta-editor="1">
	<div class="rankkernel-meta-status" data-rankkernel-meta-status="1">
		<span class="rankkernel-meta-status-label" data-rankkernel-status-title="1"><?php echo esc_html( $titleInherited ? __( 'Title: inherited from the template', 'rankkernel' ) : __( 'Title: custom override active', 'rankkernel' ) ); ?></span>
		<span class="rankkernel-meta-status-label" data-rankkernel-status-description="1"><?php echo esc_html( $descriptionInherited ? __( 'Description: inherited from the template', 'rankkernel' ) : __( 'Description: custom override active', 'rankkernel' ) ); ?></span>
	</div>

	<div class="rankkernel-meta-tabs" role="tablist" data-rankkernel-tabs="1">
		<button type="button" role="tab" id="rankkernel-meta-tab-general" class="rankkernel-meta-tab" data-rk-tab="general" aria-selected="true" aria-controls="rankkernel-meta-panel-general"><?php echo esc_html__( 'General', 'rankkernel' ); ?></button>
		<button type="button" role="tab" id="rankkernel-meta-tab-social" class="rankkernel-meta-tab" data-rk-tab="social" aria-selected="false" aria-controls="rankkernel-meta-panel-social"><?php echo esc_html__( 'Social', 'rankkernel' ); ?></button>
		<button type="button" role="tab" id="rankkernel-meta-tab-advanced" class="rankkernel-meta-tab" data-rk-tab="advanced" aria-selected="false" aria-controls="rankkernel-meta-panel-advanced"><?php echo esc_html__( 'Advanced', 'rankkernel' ); ?></button>
	</div>

	<div class="rankkernel-meta-panel" id="rankkernel-meta-panel-general" data-rk-panel="general" role="tabpanel" aria-labelledby="rankkernel-meta-tab-general">
		<table class="form-table" role="presentation"><tbody>
			<tr>
				<th scope="row"><label for="rankkernel-meta-title"><?php echo esc_html__( 'SEO title', 'rankkernel' ); ?></label></th>
				<td>
					<input type="text" id="rankkernel-meta-title" class="large-text" name="rankkernel_meta_title" value="<?php echo esc_attr( $titleOverride ); ?>" placeholder="<?php echo esc_attr( $resolvedTitleTemplate ); ?>" maxlength="255" data-rankkernel-counter="title" />
					<p class="description">
						<?php echo esc_html__( 'Template:', 'rankkernel' ); ?> <code data-rankkernel-template-title="1"><?php echo esc_html( $titleTemplate ); ?></code>
						<span data-rankkernel-inherited-title="1"><?php echo esc_html( $titleInherited ? __( 'Inherited from the template', 'rankkernel' ) : __( 'Custom override active', 'rankkernel' ) ); ?></span>
					</p>
					<p class="description"><?php echo esc_html( sprintf( /* translators: %d: maximum length in characters. */ __( 'Recommended length: up to %d characters.', 'rankkernel' ), $titleLimit ) ); ?></p>
					<p class="rankkernel-meta-count" data-rk-count-for="title" data-rk-state="ok" role="status"></p>
					<label class="rankkernel-meta-reset"><input type="checkbox" id="rankkernel-meta-reset-title" name="rankkernel_meta_reset[title]" value="1" data-rk-reset="title" /> <?php echo esc_html__( 'Reset to template', 'rankkernel' ); ?></label>
					<p class="description"><?php echo esc_html__( 'Effective value:', 'rankkernel' ); ?> <span data-rankkernel-effective-title="1"><?php echo esc_html( $effectiveTitle ); ?></span></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rankkernel-meta-description"><?php echo esc_html__( 'Meta description', 'rankkernel' ); ?></label></th>
				<td>
					<textarea id="rankkernel-meta-description" class="large-text" rows="3" name="rankkernel_meta_description" placeholder="<?php echo esc_attr( $resolvedDescriptionTemplate ); ?>" maxlength="500" data-rankkernel-counter="description"><?php echo esc_textarea( $descriptionOverride ); ?></textarea>
					<p class="description">
						<?php echo esc_html__( 'Template:', 'rankkernel' ); ?> <code data-rankkernel-template-description="1"><?php echo esc_html( $descriptionTemplate ); ?></code>
						<span data-rankkernel-inherited-description="1"><?php echo esc_html( $descriptionInherited ? __( 'Inherited from the template', 'rankkernel' ) : __( 'Custom override active', 'rankkernel' ) ); ?></span>
					</p>
					<p class="description"><?php echo esc_html( sprintf( /* translators: %d: maximum length in characters. */ __( 'Recommended length: up to %d characters.', 'rankkernel' ), $descriptionLimit ) ); ?></p>
					<p class="rankkernel-meta-count" data-rk-count-for="description" data-rk-state="ok" role="status"></p>
					<label class="rankkernel-meta-reset"><input type="checkbox" id="rankkernel-meta-reset-description" name="rankkernel_meta_reset[description]" value="1" data-rk-reset="description" /> <?php echo esc_html__( 'Reset to template', 'rankkernel' ); ?></label>
					<p class="description"><?php echo esc_html__( 'Effective value:', 'rankkernel' ); ?> <span data-rankkernel-effective-description="1"><?php echo esc_html( $effectiveDescription ); ?></span></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Insert token', 'rankkernel' ); ?></th>
				<td>
					<div class="rankkernel-meta-tokens" data-rankkernel-tokens="1">
						<?php foreach ( $tokenRows as $tokenRow ) : ?>
							<button type="button" class="button rankkernel-meta-token" data-rk-token="<?php echo esc_attr( $tokenRow['token'] ); ?>" title="<?php echo esc_attr( $tokenRow['value'] ); ?>"><?php echo esc_html( $tokenRow['label'] ); ?></button>
						<?php endforeach; ?>
					</div>
					<p class="description"><?php echo esc_html__( 'Adds a token to the last focused field.', 'rankkernel' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Search preview', 'rankkernel' ); ?></th>
				<td>
					<div class="rankkernel-meta-preview" data-rankkernel-preview="1" data-rankkernel-preview-url="<?php echo esc_attr( $previewUrl ); ?>" data-rankkernel-preview-sitename="<?php echo esc_attr( $previewSiteName ); ?>">
						<div class="rankkernel-meta-preview-toggle">
							<button type="button" class="button" data-rk-preview="desktop" aria-pressed="true"><?php echo esc_html__( 'Desktop', 'rankkernel' ); ?></button>
							<button type="button" class="button" data-rk-preview="mobile" aria-pressed="false"><?php echo esc_html__( 'Mobile', 'rankkernel' ); ?></button>
						</div>
						<div class="rankkernel-meta-preview-frame" data-rk-preview-frame="desktop">
							<span class="rankkernel-meta-preview-site" id="rankkernel-meta-preview-site"><?php echo esc_html( $previewSiteName ); ?></span>
							<span class="rankkernel-meta-preview-url" id="rankkernel-meta-preview-url"><?php echo esc_html( $previewUrl ); ?></span>
							<span class="rankkernel-meta-preview-title" id="rankkernel-meta-preview-title"><?php echo esc_html( $effectiveTitle ); ?></span>
							<span class="rankkernel-meta-preview-description" id="rankkernel-meta-preview-description"><?php echo esc_html( $effectiveDescription ); ?></span>
						</div>
					</div>
				</td>
			</tr>
		</tbody></table>
	</div>

	<div class="rankkernel-meta-panel" id="rankkernel-meta-panel-social" data-rk-panel="social" role="tabpanel" aria-labelledby="rankkernel-meta-tab-social" hidden>
		<table class="form-table" role="presentation"><tbody>
			<tr>
				<th scope="row"><label for="rankkernel-meta-og-title"><?php echo esc_html__( 'Open Graph title', 'rankkernel' ); ?></label></th>
				<td><input type="text" id="rankkernel-meta-og-title" class="large-text" name="rankkernel_meta_og[title]" value="<?php echo esc_attr( $ogTitle ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rankkernel-meta-og-description"><?php echo esc_html__( 'Open Graph description', 'rankkernel' ); ?></label></th>
				<td><textarea id="rankkernel-meta-og-description" class="large-text" rows="2" name="rankkernel_meta_og[description]"><?php echo esc_textarea( $ogDescription ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="rankkernel-meta-og-type"><?php echo esc_html__( 'Open Graph type', 'rankkernel' ); ?></label></th>
				<td>
					<input type="text" id="rankkernel-meta-og-type" class="regular-text" name="rankkernel_meta_og[type]" value="<?php echo esc_attr( $ogType ); ?>" />
					<p class="description"><?php echo esc_html__( 'Leave blank to inherit (article for posts, website for pages).', 'rankkernel' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Open Graph image', 'rankkernel' ); ?></th>
				<td>
					<input type="hidden" id="rankkernel-meta-og-image" name="rankkernel_meta_og[image]" value="<?php echo esc_attr( $ogImageUrl ); ?>" data-rankkernel-image-url="og" />
					<input type="hidden" id="rankkernel-meta-og-image-id" name="rankkernel_meta_og[image_id]" value="<?php echo esc_attr( (string) $ogImageId ); ?>" data-rankkernel-image-id="og" />
					<button type="button" class="button" data-rankkernel-select-image="og" data-rankkernel-image-target="rankkernel-meta-og-image" data-rankkernel-image-id-target="rankkernel-meta-og-image-id"><?php echo esc_html__( 'Select image', 'rankkernel' ); ?></button>
					<button type="button" class="button-link" data-rankkernel-remove-image="og" data-rankkernel-image-target="rankkernel-meta-og-image" data-rankkernel-image-id-target="rankkernel-meta-og-image-id"><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></button>
					<p class="description" data-rankkernel-image-preview="og"><?php echo esc_html( $ogImageUrl ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rankkernel-meta-twitter-card"><?php echo esc_html__( 'Twitter card', 'rankkernel' ); ?></label></th>
				<td>
					<select id="rankkernel-meta-twitter-card" name="rankkernel_meta_twitter[card]">
						<?php foreach ( $cardOptions as $cardOption ) : ?>
							<option value="<?php echo esc_attr( $cardOption['value'] ); ?>"<?php echo selected( $twitterCard, $cardOption['value'], false ); ?>><?php echo esc_html( $cardOption['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rankkernel-meta-twitter-title"><?php echo esc_html__( 'Twitter title', 'rankkernel' ); ?></label></th>
				<td><input type="text" id="rankkernel-meta-twitter-title" class="large-text" name="rankkernel_meta_twitter[title]" value="<?php echo esc_attr( $twitterTitle ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rankkernel-meta-twitter-description"><?php echo esc_html__( 'Twitter description', 'rankkernel' ); ?></label></th>
				<td><textarea id="rankkernel-meta-twitter-description" class="large-text" rows="2" name="rankkernel_meta_twitter[description]"><?php echo esc_textarea( $twitterDescription ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Twitter image', 'rankkernel' ); ?></th>
				<td>
					<input type="hidden" id="rankkernel-meta-twitter-image" name="rankkernel_meta_twitter[image]" value="<?php echo esc_attr( $twitterImageUrl ); ?>" data-rankkernel-image-url="twitter" />
					<input type="hidden" id="rankkernel-meta-twitter-image-id" name="rankkernel_meta_twitter[image_id]" value="<?php echo esc_attr( (string) $twitterImageId ); ?>" data-rankkernel-image-id="twitter" />
					<button type="button" class="button" data-rankkernel-select-image="twitter" data-rankkernel-image-target="rankkernel-meta-twitter-image" data-rankkernel-image-id-target="rankkernel-meta-twitter-image-id"><?php echo esc_html__( 'Select image', 'rankkernel' ); ?></button>
					<button type="button" class="button-link" data-rankkernel-remove-image="twitter" data-rankkernel-image-target="rankkernel-meta-twitter-image" data-rankkernel-image-id-target="rankkernel-meta-twitter-image-id"><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></button>
					<p class="description" data-rankkernel-image-preview="twitter"><?php echo esc_html( $twitterImageUrl ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Social preview', 'rankkernel' ); ?></th>
				<td>
					<div class="rankkernel-meta-social" data-rk-social="1" data-rk-card="<?php echo esc_attr( $socialCard ); ?>" aria-label="<?php echo esc_attr( __( 'Social unfurl preview', 'rankkernel' ) ); ?>">
						<img class="rankkernel-meta-social-image" data-rk-social-image="1" src="<?php echo esc_url( $socialImage ); ?>" alt=""<?php echo '' === $socialImage ? ' hidden' : ''; ?> />
						<div class="rankkernel-meta-social-body">
							<span class="rankkernel-meta-social-site" data-rk-social-site="1"><?php echo esc_html( $previewSiteName ); ?></span>
							<span class="rankkernel-meta-social-title" data-rk-social-title="1"><?php echo esc_html( '' !== $socialTitle ? $socialTitle : __( 'Untitled', 'rankkernel' ) ); ?></span>
							<span class="rankkernel-meta-social-desc" data-rk-social-desc="1"><?php echo esc_html( $socialDescription ); ?></span>
							<span class="rankkernel-meta-social-card" data-rk-social-card="1"><?php echo esc_html( $socialCardLabel ); ?></span>
						</div>
					</div>
					<p class="description"><?php echo esc_html__( 'The card social networks will unfurl for this post.', 'rankkernel' ); ?></p>
				</td>
			</tr>
		</tbody></table>
	</div>

	<div class="rankkernel-meta-panel" id="rankkernel-meta-panel-advanced" data-rk-panel="advanced" role="tabpanel" aria-labelledby="rankkernel-meta-tab-advanced" hidden>
		<table class="form-table" role="presentation"><tbody>
			<tr>
				<th scope="row"><label for="rankkernel-meta-canonical"><?php echo esc_html__( 'Canonical URL', 'rankkernel' ); ?></label></th>
				<td>
					<input type="url" id="rankkernel-meta-canonical" class="large-text" name="rankkernel_meta_canonical" value="<?php echo esc_attr( $canonicalValue ); ?>" data-rankkernel-canonical="1" />
					<p class="description"><?php echo esc_html__( 'Leave blank to use the post permalink. A full URL such as https://example.com/page is required.', 'rankkernel' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Robots', 'rankkernel' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php echo esc_html__( 'Robots directives', 'rankkernel' ); ?></legend>
						<label for="rankkernel-meta-robots-noindex"><input type="checkbox" id="rankkernel-meta-robots-noindex" name="rankkernel_meta_robots[noindex]" value="1" <?php echo checked( $robotsNoindex, true, false ); ?> /> <?php echo esc_html__( 'noindex (hide from search results)', 'rankkernel' ); ?></label><br />
						<label for="rankkernel-meta-robots-nofollow"><input type="checkbox" id="rankkernel-meta-robots-nofollow" name="rankkernel_meta_robots[nofollow]" value="1" <?php echo checked( $robotsNofollow, true, false ); ?> /> <?php echo esc_html__( 'nofollow (do not follow links)', 'rankkernel' ); ?></label><br />
						<label for="rankkernel-meta-robots-noarchive"><input type="checkbox" id="rankkernel-meta-robots-noarchive" name="rankkernel_meta_robots[noarchive]" value="1" <?php echo checked( ! empty( $robots['noarchive'] ), true, false ); ?> /> <?php echo esc_html__( 'noarchive', 'rankkernel' ); ?></label><br />
						<label for="rankkernel-meta-robots-nosnippet"><input type="checkbox" id="rankkernel-meta-robots-nosnippet" name="rankkernel_meta_robots[nosnippet]" value="1" <?php echo checked( ! empty( $robots['nosnippet'] ), true, false ); ?> /> <?php echo esc_html__( 'nosnippet', 'rankkernel' ); ?></label><br />
						<label for="rankkernel-meta-robots-noimageindex"><input type="checkbox" id="rankkernel-meta-robots-noimageindex" name="rankkernel_meta_robots[noimageindex]" value="1" <?php echo checked( ! empty( $robots['noimageindex'] ), true, false ); ?> /> <?php echo esc_html__( 'noimageindex', 'rankkernel' ); ?></label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rankkernel-meta-robots-max-snippet"><?php echo esc_html__( 'Max snippet', 'rankkernel' ); ?></label></th>
				<td>
					<input type="number" id="rankkernel-meta-robots-max-snippet" class="small-text" name="rankkernel_meta_robots[max_snippet]" value="<?php echo esc_attr( $maxSnippet ); ?>" min="-1" step="1" />
					<p class="description"><?php echo esc_html__( 'Maximum characters search engines may show for the snippet. Leave blank to inherit.', 'rankkernel' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rankkernel-meta-robots-max-image-preview"><?php echo esc_html__( 'Max image preview', 'rankkernel' ); ?></label></th>
				<td>
					<select id="rankkernel-meta-robots-max-image-preview" name="rankkernel_meta_robots[max_image_preview]">
						<?php foreach ( $maxImagePreviewOptions as $previewOption ) : ?>
							<option value="<?php echo esc_attr( $previewOption['value'] ); ?>"<?php echo selected( $maxImagePreview, $previewOption['value'], false ); ?>><?php echo esc_html( $previewOption['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rankkernel-meta-robots-max-video-preview"><?php echo esc_html__( 'Max video preview', 'rankkernel' ); ?></label></th>
				<td>
					<input type="number" id="rankkernel-meta-robots-max-video-preview" class="small-text" name="rankkernel_meta_robots[max_video_preview]" value="<?php echo esc_attr( $maxVideoPreview ); ?>" min="-1" step="1" />
					<p class="description"><?php echo esc_html__( 'Maximum seconds search engines may show for a video preview. Leave blank to inherit.', 'rankkernel' ); ?></p>
				</td>
			</tr>
		</tbody></table>
	</div>
</div>
