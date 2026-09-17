<?php
/**
 * Metadata editor metabox view.
 *
 * Presentation only. MetadataBox prepares every variable used below, and owns
 * capability checks, nonce verification, request handling, saving, reset
 * handling, token resolution and view state preparation.
 *
 * The four tabs mirror the Gutenberg sidebar information architecture:
 * General, Advanced, Schema, Social. Each field is one compact component
 * carrying its label, value, counter, inheritance state and reset together.
 * Previews always show the effective value: the override when set, the
 * server resolved template otherwise.
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
 * @var bool   $robotsIndex              Whether indexing is allowed.
 * @var bool   $robotsFollow             Whether following links is allowed.
 * @var bool   $canonicalCustom          Whether a canonical override is stored.
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
 * @var string $schemaPostType           Current post type slug for the schema automatic note.
 * @var bool   $schemaDisabled           Whether schema output is disabled for this post.
 * @var string $schemaSelected           Selected schema type, empty for automatic.
 * @var string $schemaResolved           Resolved automatic schema type for this post type.
 * @var string $schemaAutoLabel          Resolved label shown for the automatic option.
 * @var array<int, array{value: string, label: string}> $schemaTypeOptions Supported schema type options.
 * @var array<int, array{id: string, name: string, label: string, value: string, types: string, hidden: bool}> $schemaFieldRows Manual field override rows.
 * @var string $schemaCustomJson         Custom JSON textarea value.
 * @var string[] $schemaValidationMessages Validation warnings for the selected type.
 * @var string $schemaValidationLabel    Label used in the validation success message.
 * @var string $schemaRichResultsUrl     Rich Results Test URL.
 * @var string $schemaValidatorUrl       Schema Validator URL.
 * @var string $schemaExportUrl          Schema export URL.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

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

// Social unfurl card state. Social fields win, then the Twitter values,
// then the General effective values, then the inherited default image.
$socialTitle       = '' !== trim( $ogTitle ) ? $ogTitle : ( '' !== trim( $twitterTitle ) ? $twitterTitle : $effectiveTitle );
$socialDescription = '' !== trim( $ogDescription ) ? $ogDescription : ( '' !== trim( $twitterDescription ) ? $twitterDescription : $effectiveDescription );
$socialImage       = '' !== trim( $ogImageUrl ) ? $ogImageUrl : ( '' !== trim( $twitterImageUrl ) ? $twitterImageUrl : $defaultOgImage );
$socialCard        = 'summary' === $twitterCard ? 'summary' : 'summary_large_image';
$socialCardLabel   = 'summary' === $socialCard ? __( 'Small image card', 'rankkernel' ) : __( 'Large image card', 'rankkernel' );

if ( $schemaDisabled ) {
	$schemaStatusText = __( 'Disabled for this post. No structured data prints.', 'rankkernel' );
} elseif ( '' !== $schemaSelected ) {
	$schemaStatusText = sprintf(
		/* translators: %s: schema type label, e.g. Blog Posting. */
		__( 'Custom type: %s.', 'rankkernel' ),
		$schemaSelected
	);
} else {
	$schemaStatusText = sprintf(
		/* translators: %s: automatic option label, e.g. Automatic (Blog Posting). */
		__( 'Status: %s.', 'rankkernel' ),
		$schemaAutoLabel
	);
}
?>
<?php wp_nonce_field( 'rankkernel_meta_save', 'rankkernel_meta_nonce' ); ?>
<?php wp_nonce_field( 'rankkernel_schema_save', 'rankkernel_schema_nonce' ); ?>
<input type="hidden" name="rankkernel_meta_fields" value="1" />

<div class="rankkernel-meta-editor" data-rankkernel-meta-editor="1">
	<div class="rk-classic-tabs" role="tablist" aria-label="<?php echo esc_attr( __( 'SEO settings sections', 'rankkernel' ) ); ?>" data-rankkernel-tabs="1">
		<button type="button" role="tab" id="rankkernel-meta-tab-general" class="rk-classic-tab" data-rk-tab="general" aria-selected="true" aria-controls="rankkernel-meta-panel-general"><?php echo esc_html__( 'General', 'rankkernel' ); ?></button>
		<button type="button" role="tab" id="rankkernel-meta-tab-advanced" class="rk-classic-tab" data-rk-tab="advanced" aria-selected="false" aria-controls="rankkernel-meta-panel-advanced" tabindex="-1"><?php echo esc_html__( 'Advanced', 'rankkernel' ); ?></button>
		<button type="button" role="tab" id="rankkernel-meta-tab-schema" class="rk-classic-tab" data-rk-tab="schema" aria-selected="false" aria-controls="rankkernel-meta-panel-schema" tabindex="-1"><?php echo esc_html__( 'Schema', 'rankkernel' ); ?></button>
		<button type="button" role="tab" id="rankkernel-meta-tab-social" class="rk-classic-tab" data-rk-tab="social" aria-selected="false" aria-controls="rankkernel-meta-panel-social" tabindex="-1"><?php echo esc_html__( 'Social', 'rankkernel' ); ?></button>
	</div>

	<div class="rk-classic-panel" id="rankkernel-meta-panel-general" data-rk-panel="general" role="tabpanel" aria-labelledby="rankkernel-meta-tab-general" tabindex="0">
		<section class="rk-classic-card rk-field" aria-labelledby="rankkernel-meta-serp-heading">
			<div class="rk-classic-serp" data-rankkernel-preview="1" data-rankkernel-preview-url="<?php echo esc_attr( $previewUrl ); ?>" data-rankkernel-preview-sitename="<?php echo esc_attr( $previewSiteName ); ?>">
				<div class="rk-classic-serp-top">
					<h3 id="rankkernel-meta-serp-heading"><?php echo esc_html__( 'Search preview', 'rankkernel' ); ?></h3>
					<div class="rk-classic-toggle" role="group" aria-label="<?php echo esc_attr( __( 'Preview width', 'rankkernel' ) ); ?>">
						<button type="button" class="button button-small" data-rk-preview="desktop" aria-pressed="true"><?php echo esc_html__( 'Desktop', 'rankkernel' ); ?></button>
						<button type="button" class="button button-small" data-rk-preview="mobile" aria-pressed="false"><?php echo esc_html__( 'Mobile', 'rankkernel' ); ?></button>
					</div>
				</div>
				<div class="rk-serp-row">
					<span class="rk-serp-mark" aria-hidden="true"><?php echo esc_html( '' !== $previewSiteName ? mb_strtoupper( mb_substr( $previewSiteName, 0, 1 ) ) : 'R' ); ?></span>
					<div class="rk-serp-id">
						<p class="rk-classic-serp-site" id="rankkernel-meta-preview-site"><?php echo esc_html( $previewSiteName ); ?></p>
						<p class="rk-classic-serp-url" id="rankkernel-meta-preview-url"><?php echo esc_html( $previewUrl ); ?></p>
					</div>
				</div>
				<p class="rk-classic-serp-title" id="rankkernel-meta-preview-title"><?php echo esc_html( $effectiveTitle ); ?></p>
				<p class="rk-classic-serp-desc" id="rankkernel-meta-preview-description"><?php echo esc_html( $effectiveDescription ); ?></p>
				<p class="description"><?php echo esc_html__( 'Preview is approximate, not exact search rendering.', 'rankkernel' ); ?></p>
			</div>
		</section>

		<section class="rk-classic-card rk-field" aria-labelledby="rankkernel-meta-title-label">
			<div class="rk-classic-field-head">
				<label id="rankkernel-meta-title-label" for="rankkernel-meta-title"><?php echo esc_html__( 'SEO title', 'rankkernel' ); ?></label>
				<span class="rk-classic-badge" data-rankkernel-inherited-title="1" data-rk-state="<?php echo $titleInherited ? 'inherited' : 'custom'; ?>"><?php echo esc_html( $titleInherited ? __( 'Inherited', 'rankkernel' ) : __( 'Custom', 'rankkernel' ) ); ?></span>
			</div>
			<input type="text" id="rankkernel-meta-title" class="large-text" name="rankkernel_meta_title" value="<?php echo esc_attr( $titleOverride ); ?>" placeholder="<?php echo esc_attr( $resolvedTitleTemplate ); ?>" maxlength="255" data-rankkernel-counter="title" aria-describedby="rankkernel-meta-title-help" />
			<div class="rk-classic-meter">
				<p class="rankkernel-meta-count" data-rk-count-for="title" data-rk-state="ok" role="status"></p>
				<span class="rk-classic-bar" aria-hidden="true" data-rk-bar-for="title" data-rk-state="ok"><span></span></span>
			</div>
			<div class="rk-classic-field-foot">
				<span class="rk-classic-status" data-rk-status-for="title" data-rk-state="ok" aria-hidden="true"></span>
				<label class="rk-classic-reset" for="rankkernel-meta-reset-title"><input type="checkbox" id="rankkernel-meta-reset-title" name="rankkernel_meta_reset[title]" value="1" data-rk-reset="title" /> <?php echo esc_html__( 'Reset to template', 'rankkernel' ); ?></label>
			</div>
			<details class="rk-classic-tokens">
				<summary><?php echo esc_html__( 'Insert token', 'rankkernel' ); ?></summary>
				<div class="rk-classic-token-list" data-rankkernel-tokens="1">
					<?php foreach ( $tokenRows as $tokenRow ) : ?>
						<button type="button" class="button button-small" data-rk-token="<?php echo esc_attr( $tokenRow['token'] ); ?>" title="<?php echo esc_attr( $tokenRow['value'] ); ?>"><?php echo esc_html( $tokenRow['label'] ); ?></button>
					<?php endforeach; ?>
				</div>
			</details>
			<p class="description" id="rankkernel-meta-title-help"><?php echo esc_html__( 'Blank uses the template. Typing makes it custom.', 'rankkernel' ); ?></p>
		</section>

		<section class="rk-classic-card rk-field" aria-labelledby="rankkernel-meta-description-label">
			<div class="rk-classic-field-head">
				<label id="rankkernel-meta-description-label" for="rankkernel-meta-description"><?php echo esc_html__( 'Meta description', 'rankkernel' ); ?></label>
				<span class="rk-classic-badge" data-rankkernel-inherited-description="1" data-rk-state="<?php echo $descriptionInherited ? 'inherited' : 'custom'; ?>"><?php echo esc_html( $descriptionInherited ? __( 'Inherited', 'rankkernel' ) : __( 'Custom', 'rankkernel' ) ); ?></span>
			</div>
			<textarea id="rankkernel-meta-description" class="large-text" rows="3" name="rankkernel_meta_description" placeholder="<?php echo esc_attr( $resolvedDescriptionTemplate ); ?>" maxlength="500" data-rankkernel-counter="description" aria-describedby="rankkernel-meta-description-help"><?php echo esc_textarea( $descriptionOverride ); ?></textarea>
			<div class="rk-classic-meter">
				<p class="rankkernel-meta-count" data-rk-count-for="description" data-rk-state="ok" role="status"></p>
				<span class="rk-classic-bar" aria-hidden="true" data-rk-bar-for="description" data-rk-state="ok"><span></span></span>
			</div>
			<div class="rk-classic-field-foot">
				<span class="rk-classic-status" data-rk-status-for="description" data-rk-state="ok" aria-hidden="true"></span>
				<label class="rk-classic-reset" for="rankkernel-meta-reset-description"><input type="checkbox" id="rankkernel-meta-reset-description" name="rankkernel_meta_reset[description]" value="1" data-rk-reset="description" /> <?php echo esc_html__( 'Reset to template', 'rankkernel' ); ?></label>
			</div>
			<details class="rk-classic-tokens">
				<summary><?php echo esc_html__( 'Insert token', 'rankkernel' ); ?></summary>
				<div class="rk-classic-token-list" data-rankkernel-tokens="1">
					<?php foreach ( $tokenRows as $tokenRow ) : ?>
						<button type="button" class="button button-small" data-rk-token="<?php echo esc_attr( $tokenRow['token'] ); ?>" title="<?php echo esc_attr( $tokenRow['value'] ); ?>"><?php echo esc_html( $tokenRow['label'] ); ?></button>
					<?php endforeach; ?>
				</div>
			</details>
			<p class="description" id="rankkernel-meta-description-help"><?php echo esc_html__( 'Blank uses the template, then the excerpt.', 'rankkernel' ); ?></p>
		</section>

		<section class="rk-classic-card rk-classic-extension" aria-labelledby="rankkernel-meta-analysis-heading">
			<h3 id="rankkernel-meta-analysis-heading"><?php echo esc_html__( 'Content analysis', 'rankkernel' ); ?></h3>
			<p class="description"><?php echo esc_html__( 'Focus keyword and content analysis will appear here in a future release.', 'rankkernel' ); ?></p>
		</section>
	</div>

	<div class="rk-classic-panel" id="rankkernel-meta-panel-advanced" data-rk-panel="advanced" role="tabpanel" aria-labelledby="rankkernel-meta-tab-advanced" tabindex="0" hidden>
		<section class="rk-classic-card" aria-labelledby="rankkernel-meta-visibility-heading">
			<h3 id="rankkernel-meta-visibility-heading"><?php echo esc_html__( 'Search engine visibility', 'rankkernel' ); ?></h3>
			<div class="rk-classic-radio-group" role="radiogroup" aria-label="<?php echo esc_attr( __( 'Index this post', 'rankkernel' ) ); ?>">
				<label for="rankkernel-meta-robots-index"><input type="radio" id="rankkernel-meta-robots-index" name="rankkernel_meta_robots[noindex]" value="0"<?php echo checked( $robotsIndex, true, false ); ?> /> <?php echo esc_html__( 'Index', 'rankkernel' ); ?></label>
				<label for="rankkernel-meta-robots-noindex"><input type="radio" id="rankkernel-meta-robots-noindex" name="rankkernel_meta_robots[noindex]" value="1"<?php echo checked( $robotsIndex, false, false ); ?> /> <?php echo esc_html__( 'Noindex', 'rankkernel' ); ?></label>
			</div>
			<div class="rk-classic-radio-group" role="radiogroup" aria-label="<?php echo esc_attr( __( 'Follow links on this post', 'rankkernel' ) ); ?>">
				<label for="rankkernel-meta-robots-follow"><input type="radio" id="rankkernel-meta-robots-follow" name="rankkernel_meta_robots[nofollow]" value="0"<?php echo checked( $robotsFollow, true, false ); ?> /> <?php echo esc_html__( 'Follow', 'rankkernel' ); ?></label>
				<label for="rankkernel-meta-robots-nofollow"><input type="radio" id="rankkernel-meta-robots-nofollow" name="rankkernel_meta_robots[nofollow]" value="1"<?php echo checked( $robotsFollow, false, false ); ?> /> <?php echo esc_html__( 'Nofollow', 'rankkernel' ); ?></label>
			</div>
		</section>

		<details class="rk-classic-card">
			<summary><?php echo esc_html__( 'Additional robots settings', 'rankkernel' ); ?></summary>
			<fieldset class="rk-classic-robots-extra">
				<legend class="screen-reader-text"><?php echo esc_html__( 'Additional robots directives', 'rankkernel' ); ?></legend>
				<label for="rankkernel-meta-robots-noarchive"><input type="checkbox" id="rankkernel-meta-robots-noarchive" name="rankkernel_meta_robots[noarchive]" value="1"<?php echo checked( ! empty( $robots['noarchive'] ), true, false ); ?> /> <?php echo esc_html__( 'No archive', 'rankkernel' ); ?></label>
				<label for="rankkernel-meta-robots-nosnippet"><input type="checkbox" id="rankkernel-meta-robots-nosnippet" name="rankkernel_meta_robots[nosnippet]" value="1"<?php echo checked( ! empty( $robots['nosnippet'] ), true, false ); ?> /> <?php echo esc_html__( 'No snippet', 'rankkernel' ); ?></label>
				<label for="rankkernel-meta-robots-noimageindex"><input type="checkbox" id="rankkernel-meta-robots-noimageindex" name="rankkernel_meta_robots[noimageindex]" value="1"<?php echo checked( ! empty( $robots['noimageindex'] ), true, false ); ?> /> <?php echo esc_html__( 'No image index', 'rankkernel' ); ?></label>
			</fieldset>
			<p>
				<label for="rankkernel-meta-robots-max-snippet"><?php echo esc_html__( 'Max snippet', 'rankkernel' ); ?></label>
				<input type="number" id="rankkernel-meta-robots-max-snippet" class="small-text" name="rankkernel_meta_robots[max_snippet]" value="<?php echo esc_attr( $maxSnippet ); ?>" min="-1" step="1" />
				<span class="description"><?php echo esc_html__( 'Blank means unlimited.', 'rankkernel' ); ?></span>
			</p>
			<p>
				<label for="rankkernel-meta-robots-max-image-preview"><?php echo esc_html__( 'Max image preview', 'rankkernel' ); ?></label>
				<select id="rankkernel-meta-robots-max-image-preview" name="rankkernel_meta_robots[max_image_preview]">
					<?php foreach ( $maxImagePreviewOptions as $previewOption ) : ?>
						<option value="<?php echo esc_attr( $previewOption['value'] ); ?>"<?php echo selected( $maxImagePreview, $previewOption['value'], false ); ?>><?php echo esc_html( $previewOption['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="rankkernel-meta-robots-max-video-preview"><?php echo esc_html__( 'Max video preview', 'rankkernel' ); ?></label>
				<input type="number" id="rankkernel-meta-robots-max-video-preview" class="small-text" name="rankkernel_meta_robots[max_video_preview]" value="<?php echo esc_attr( $maxVideoPreview ); ?>" min="-1" step="1" />
				<span class="description"><?php echo esc_html__( 'Seconds. Blank means unlimited.', 'rankkernel' ); ?></span>
			</p>
		</details>

		<section class="rk-classic-card rk-field" aria-labelledby="rankkernel-meta-canonical-label">
			<div class="rk-classic-field-head">
				<label id="rankkernel-meta-canonical-label" for="rankkernel-meta-canonical"><?php echo esc_html__( 'Canonical URL', 'rankkernel' ); ?></label>
				<span class="rk-classic-badge" data-rk-canonical-state="1" data-rk-state="<?php echo $canonicalCustom ? 'custom' : 'default'; ?>"><?php echo esc_html( $canonicalCustom ? __( 'Custom', 'rankkernel' ) : __( 'Default', 'rankkernel' ) ); ?></span>
			</div>
			<input type="url" id="rankkernel-meta-canonical" class="large-text" name="rankkernel_meta_canonical" value="<?php echo esc_attr( $canonicalValue ); ?>" aria-describedby="rankkernel-meta-canonical-help" />
			<p class="rk-classic-error" data-rk-canonical-error="1" role="alert" hidden></p>
			<p class="description" id="rankkernel-meta-canonical-help"><?php echo esc_html__( 'Blank uses the generated canonical. A full URL is required.', 'rankkernel' ); ?></p>
		</section>
	</div>

	<div class="rk-classic-panel" id="rankkernel-meta-panel-schema" data-rk-panel="schema" role="tabpanel" aria-labelledby="rankkernel-meta-tab-schema" tabindex="0" hidden>
		<section class="rk-classic-card" aria-labelledby="rankkernel-meta-schema-heading">
			<h3 id="rankkernel-meta-schema-heading"><?php echo esc_html__( 'Schema', 'rankkernel' ); ?></h3>
			<p class="rk-classic-schema-status" data-rk-schema-status="1" data-rk-schema-auto="<?php echo esc_attr( $schemaAutoLabel ); ?>"><?php echo esc_html( $schemaStatusText ); ?></p>
			<p>
				<label for="rankkernel-meta-schema-type"><?php echo esc_html__( 'Schema type', 'rankkernel' ); ?></label>
				<select id="rankkernel-meta-schema-type" name="rankkernel_schema_type">
					<option value=""<?php echo selected( $schemaSelected, '', false ); ?>><?php echo esc_html( $schemaAutoLabel ); ?></option>
					<?php foreach ( $schemaTypeOptions as $schemaTypeOption ) : ?>
						<option value="<?php echo esc_attr( $schemaTypeOption['value'] ); ?>"<?php echo selected( $schemaSelected, $schemaTypeOption['value'], false ); ?>><?php echo esc_html( $schemaTypeOption['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php if ( '' !== $schemaPostType ) : ?>
				<p class="description"><?php echo esc_html__( 'Automatic uses the default type set for this post type in Schema settings.', 'rankkernel' ); ?></p>
			<?php endif; ?>
			<p><label for="rankkernel-meta-schema-disabled"><input type="checkbox" id="rankkernel-meta-schema-disabled" name="rankkernel_schema_disabled" value="1"<?php echo checked( $schemaDisabled, true, false ); ?> /> <?php echo esc_html__( 'Disable schema output for this post', 'rankkernel' ); ?></label></p>
		</section>

		<details class="rk-classic-card">
			<summary><?php echo esc_html__( 'Manual overrides', 'rankkernel' ); ?></summary>
			<p class="description"><?php echo esc_html__( 'Only needed when a value must differ from the post itself. Rows unrelated to the chosen type stay hidden.', 'rankkernel' ); ?></p>
			<?php foreach ( $schemaFieldRows as $schemaFieldRow ) : ?>
				<p class="rk-classic-schema-row" data-rankkernel-field-types="<?php echo esc_attr( $schemaFieldRow['types'] ); ?>"<?php echo $schemaFieldRow['hidden'] ? ' hidden' : ''; ?>>
					<label for="<?php echo esc_attr( $schemaFieldRow['id'] ); ?>"><?php echo esc_html( $schemaFieldRow['label'] ); ?></label>
					<input type="text" id="<?php echo esc_attr( $schemaFieldRow['id'] ); ?>" class="regular-text" name="<?php echo esc_attr( $schemaFieldRow['name'] ); ?>" value="<?php echo esc_attr( $schemaFieldRow['value'] ); ?>" />
				</p>
			<?php endforeach; ?>
		</details>

		<details class="rk-classic-card">
			<summary><?php echo esc_html__( 'Advanced', 'rankkernel' ); ?></summary>
			<p>
				<label for="rankkernel-meta-schema-custom"><?php echo esc_html__( 'Custom JSON', 'rankkernel' ); ?></label>
				<textarea id="rankkernel-meta-schema-custom" class="large-text code" rows="6" name="rankkernel_schema_custom"><?php echo esc_textarea( $schemaCustomJson ); ?></textarea>
				<span class="description"><?php echo esc_html__( 'Optional, for advanced use. A valid JSON object typed here is added to the schema output as is.', 'rankkernel' ); ?></span>
			</p>
			<h4><?php echo esc_html__( 'Validation', 'rankkernel' ); ?></h4>
			<?php if ( [] === $schemaValidationMessages ) : ?>
				<div class="notice notice-success inline"><p><?php echo esc_html( sprintf( /* translators: %s: schema type name */ __( 'All required fields for %s are present.', 'rankkernel' ), $schemaValidationLabel ) ); ?></p></div>
			<?php else : ?>
				<div class="notice notice-warning inline"><ul>
					<?php foreach ( $schemaValidationMessages as $schemaValidationMessage ) : ?>
						<li><?php echo esc_html( $schemaValidationMessage ); ?></li>
					<?php endforeach; ?>
				</ul></div>
			<?php endif; ?>
			<h4><?php echo esc_html__( 'Test this page', 'rankkernel' ); ?></h4>
			<p><a href="<?php echo esc_url( $schemaRichResultsUrl ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Rich Results Test', 'rankkernel' ); ?></a> | <a href="<?php echo esc_url( $schemaValidatorUrl ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Schema Validator', 'rankkernel' ); ?></a></p>
			<h4><?php echo esc_html__( 'Import and export', 'rankkernel' ); ?></h4>
			<p><a class="button" href="<?php echo esc_url( $schemaExportUrl ); ?>"><?php echo esc_html__( 'Export JSON', 'rankkernel' ); ?></a></p>
			<p><label for="rankkernel-meta-schema-import"><?php echo esc_html__( 'Import JSON', 'rankkernel' ); ?></label> <input type="file" id="rankkernel-meta-schema-import" name="rankkernel_schema_import" accept=".json,application/json" /></p>
			<p class="description"><?php echo esc_html__( 'Upload a file previously exported with the Export JSON button above.', 'rankkernel' ); ?></p>
		</details>
	</div>

	<div class="rk-classic-panel" id="rankkernel-meta-panel-social" data-rk-panel="social" role="tabpanel" aria-labelledby="rankkernel-meta-tab-social" tabindex="0" hidden>
		<details class="rk-classic-card" open>
			<summary><?php echo esc_html__( 'Open Graph', 'rankkernel' ); ?></summary>
			<p>
				<label for="rankkernel-meta-og-title"><?php echo esc_html__( 'Open Graph title', 'rankkernel' ); ?></label>
				<input type="text" id="rankkernel-meta-og-title" class="large-text" name="rankkernel_meta_og[title]" value="<?php echo esc_attr( $ogTitle ); ?>" placeholder="<?php echo esc_attr( $effectiveTitle ); ?>" />
				<button type="button" class="button button-small" data-rk-reset="ogTitle" aria-label="<?php echo esc_attr( __( 'Reset Open Graph title', 'rankkernel' ) ); ?>"><?php echo esc_html__( 'Reset', 'rankkernel' ); ?></button>
			</p>
			<p>
				<label for="rankkernel-meta-og-description"><?php echo esc_html__( 'Open Graph description', 'rankkernel' ); ?></label>
				<textarea id="rankkernel-meta-og-description" class="large-text" rows="2" name="rankkernel_meta_og[description]" placeholder="<?php echo esc_attr( $effectiveDescription ); ?>"><?php echo esc_textarea( $ogDescription ); ?></textarea>
				<button type="button" class="button button-small" data-rk-reset="ogDescription" aria-label="<?php echo esc_attr( __( 'Reset Open Graph description', 'rankkernel' ) ); ?>"><?php echo esc_html__( 'Reset', 'rankkernel' ); ?></button>
			</p>
			<div class="rk-classic-image-row" data-rk-image-row="og">
				<span class="rk-classic-field-label" id="rankkernel-meta-og-image-label"><?php echo esc_html__( 'Open Graph image', 'rankkernel' ); ?></span>
				<img id="rankkernel-meta-og-thumb" class="rk-classic-thumb" src="<?php echo esc_url( $ogImageUrl ); ?>" alt=""<?php echo '' === $ogImageUrl ? ' hidden' : ''; ?> />
				<input type="hidden" id="rankkernel-meta-og-image" name="rankkernel_meta_og[image]" value="<?php echo esc_attr( $ogImageUrl ); ?>" />
				<input type="hidden" id="rankkernel-meta-og-image-id" name="rankkernel_meta_og[image_id]" value="<?php echo esc_attr( (string) $ogImageId ); ?>" />
				<div class="rk-classic-row-actions" role="group" aria-labelledby="rankkernel-meta-og-image-label">
					<button type="button" class="button" data-rankkernel-select-image="og"><?php echo esc_html( '' !== $ogImageUrl ? __( 'Change image', 'rankkernel' ) : __( 'Select image', 'rankkernel' ) ); ?></button>
					<button type="button" class="button-link" data-rankkernel-remove-image="og"<?php echo '' === $ogImageUrl ? ' hidden' : ''; ?>><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></button>
				</div>
			</div>
			<p>
				<label for="rankkernel-meta-og-type"><?php echo esc_html__( 'Open Graph type', 'rankkernel' ); ?></label>
				<input type="text" id="rankkernel-meta-og-type" class="regular-text" name="rankkernel_meta_og[type]" value="<?php echo esc_attr( $ogType ); ?>" />
				<span class="description"><?php echo esc_html__( 'Blank inherits (article for posts, website for pages).', 'rankkernel' ); ?></span>
			</p>
		</details>

		<details class="rk-classic-card">
			<summary><?php echo esc_html__( 'Twitter', 'rankkernel' ); ?></summary>
			<p>
				<label for="rankkernel-meta-twitter-card"><?php echo esc_html__( 'Twitter card', 'rankkernel' ); ?></label>
				<select id="rankkernel-meta-twitter-card" name="rankkernel_meta_twitter[card]">
					<?php foreach ( $cardOptions as $cardOption ) : ?>
						<option value="<?php echo esc_attr( $cardOption['value'] ); ?>"<?php echo selected( $twitterCard, $cardOption['value'], false ); ?>><?php echo esc_html( $cardOption['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="rankkernel-meta-twitter-title"><?php echo esc_html__( 'Twitter title', 'rankkernel' ); ?></label>
				<input type="text" id="rankkernel-meta-twitter-title" class="large-text" name="rankkernel_meta_twitter[title]" value="<?php echo esc_attr( $twitterTitle ); ?>" placeholder="<?php echo esc_attr( $effectiveTitle ); ?>" />
				<button type="button" class="button button-small" data-rk-reset="twTitle" aria-label="<?php echo esc_attr( __( 'Reset Twitter title', 'rankkernel' ) ); ?>"><?php echo esc_html__( 'Reset', 'rankkernel' ); ?></button>
			</p>
			<p>
				<label for="rankkernel-meta-twitter-description"><?php echo esc_html__( 'Twitter description', 'rankkernel' ); ?></label>
				<textarea id="rankkernel-meta-twitter-description" class="large-text" rows="2" name="rankkernel_meta_twitter[description]" placeholder="<?php echo esc_attr( $effectiveDescription ); ?>"><?php echo esc_textarea( $twitterDescription ); ?></textarea>
				<button type="button" class="button button-small" data-rk-reset="twDescription" aria-label="<?php echo esc_attr( __( 'Reset Twitter description', 'rankkernel' ) ); ?>"><?php echo esc_html__( 'Reset', 'rankkernel' ); ?></button>
			</p>
			<div class="rk-classic-image-row" data-rk-image-row="twitter">
				<span class="rk-classic-field-label" id="rankkernel-meta-twitter-image-label"><?php echo esc_html__( 'Twitter image', 'rankkernel' ); ?></span>
				<img id="rankkernel-meta-twitter-thumb" class="rk-classic-thumb" src="<?php echo esc_url( $twitterImageUrl ); ?>" alt=""<?php echo '' === $twitterImageUrl ? ' hidden' : ''; ?> />
				<input type="hidden" id="rankkernel-meta-twitter-image" name="rankkernel_meta_twitter[image]" value="<?php echo esc_attr( $twitterImageUrl ); ?>" />
				<input type="hidden" id="rankkernel-meta-twitter-image-id" name="rankkernel_meta_twitter[image_id]" value="<?php echo esc_attr( (string) $twitterImageId ); ?>" />
				<div class="rk-classic-row-actions" role="group" aria-labelledby="rankkernel-meta-twitter-image-label">
					<button type="button" class="button" data-rankkernel-select-image="twitter"><?php echo esc_html( '' !== $twitterImageUrl ? __( 'Change image', 'rankkernel' ) : __( 'Select image', 'rankkernel' ) ); ?></button>
					<button type="button" class="button-link" data-rankkernel-remove-image="twitter"<?php echo '' === $twitterImageUrl ? ' hidden' : ''; ?>><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></button>
				</div>
			</div>
		</details>

		<section class="rk-classic-card" aria-labelledby="rankkernel-meta-social-heading">
			<h3 id="rankkernel-meta-social-heading"><?php echo esc_html__( 'Social preview', 'rankkernel' ); ?></h3>
			<div class="rankkernel-meta-social" data-rk-social="1" data-rk-card="<?php echo esc_attr( $socialCard ); ?>" aria-label="<?php echo esc_attr( __( 'Social unfurl preview', 'rankkernel' ) ); ?>">
				<img class="rankkernel-meta-social-image" data-rk-social-image="1" src="<?php echo esc_url( $socialImage ); ?>" alt=""<?php echo '' === $socialImage ? ' hidden' : ''; ?> />
				<div class="rankkernel-meta-social-body">
					<span class="rankkernel-meta-social-site" data-rk-social-site="1"><?php echo esc_html( $previewSiteName ); ?></span>
					<span class="rankkernel-meta-social-title" data-rk-social-title="1"><?php echo esc_html( '' !== $socialTitle ? $socialTitle : __( 'Untitled', 'rankkernel' ) ); ?></span>
					<span class="rankkernel-meta-social-desc" data-rk-social-desc="1"><?php echo esc_html( $socialDescription ); ?></span>
					<span class="rankkernel-meta-social-card" data-rk-social-card="1"><?php echo esc_html( $socialCardLabel ); ?></span>
				</div>
			</div>
			<p class="description"><?php echo esc_html__( 'Blank social fields fall back to the General values.', 'rankkernel' ); ?></p>
		</section>
	</div>
</div>
