<?php
/**
 * Schema settings page view.
 *
 * Presentation only. SchemaSettingsPage prepares every variable used below,
 * and owns capability checks, nonce verification, request handling,
 * validation, and redirects.
 *
 * The page adopts the shared component layer through the rk-ui root class.
 * Page scoped rules live in assets/css/schema-settings-admin.css, and the
 * Schema accent uses the reserved secondary 2 token pair, never a new hue
 * and never the primary blue.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var bool     $settingsUpdated     Whether the settings saved notice renders.
 * @var bool     $settingsSaveFailed  Whether the save failed notice renders.
 * @var string   $represents          Selected site represents value.
 * @var string   $orgName             Organization name.
 * @var string   $orgLogo             Organization logo URL.
 * @var string[] $sameAsLines         Same As profile URLs.
 * @var bool     $websiteSearchAction Search Action checkbox state.
 * @var bool     $schemaBreadcrumbs   Breadcrumbs checkbox state.
 * @var bool     $schemaAuthor        Author checkbox state.
 * @var array<int, array{key: string, label: string, current: string}> $defaultRows Per post type default rows.
 * @var array<int, array{value: string, label: string}>                $schemaTypes Supported schema types.
 * @var string   $richResultsUrl      Rich Results Test URL.
 * @var string   $validatorUrl        Schema Validator URL.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap rk-schema-settings rk-ui">

	<?php
	/*
	 * Core prints no heading for a plugin screen, so the document keeps one h1
	 * here while the visible title is the h2 in the page header card below.
	 */
	?>
	<h1 class="screen-reader-text"><?php echo esc_html__( 'Schema Settings', 'rankkernel' ); ?></h1>

	<?php /* Section 1: notice row. No dismiss control, this screen wires none. */ ?>
	<?php if ( $settingsUpdated ) : ?>
		<div class="rk-ui-notice rk-ui-notice-success" role="status">
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">check_circle</span>
			<p class="rk-ui-notice-text"><?php echo esc_html__( 'Settings saved.', 'rankkernel' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $settingsSaveFailed ) : ?>
		<div class="rk-ui-notice rk-ui-notice-error" role="alert">
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">error</span>
			<p class="rk-ui-notice-text"><?php echo esc_html__( 'Settings could not be saved. Please try again.', 'rankkernel' ); ?></p>
		</div>
	<?php endif; ?>

	<?php /* Section 2: page header card carrying the Schema accent. */ ?>
	<header class="rk-ui-card rk-ui-page-header">
		<div class="rk-ui-page-header-text">
			<div class="rk-ui-page-header-title-row">
				<span class="rk-schema-mark"><span class="rk-icon" aria-hidden="true">code_blocks</span></span>
				<h2 class="rk-ui-page-title"><?php echo esc_html__( 'Schema Settings', 'rankkernel' ); ?></h2>
				<span class="rk-ui-pill rk-pill-schema"><?php echo esc_html__( 'Schema', 'rankkernel' ); ?></span>
			</div>
			<p class="rk-ui-sub"><?php echo esc_html__( 'Who this site represents, the default schema type per post type, and the tools that test the result.', 'rankkernel' ); ?></p>
		</div>
	</header>

	<?php
	/*
	 * One form covers every group, so the nonce, the field names and the save
	 * button stay exactly as the controller expects. The mockup shows a save
	 * control per section, which would need three forms and three nonces.
	 */
	?>
	<form method="post" action="">
		<?php wp_nonce_field( 'rankkernel_schema_settings' ); ?>

		<?php /* Section 3: identity card. */ ?>
		<div class="rk-ui-card rk-identity-card">
			<div class="rk-ui-card-header">
				<h3 class="rk-ui-card-title"><?php echo esc_html__( 'Identity', 'rankkernel' ); ?></h3>
			</div>

			<div class="rk-card-body">
				<div class="rk-ui-form-row">
					<label class="rk-ui-form-label" for="rk-site-represents"><?php echo esc_html__( 'Site Represents', 'rankkernel' ); ?></label>
					<div class="rk-ui-select-wrap">
						<select class="rk-ui-select" id="rk-site-represents" name="site_represents">
							<option value="organization"<?php echo selected( $represents, 'organization', false ); ?>><?php echo esc_html__( 'Organization', 'rankkernel' ); ?></option>
							<option value="person"<?php echo selected( $represents, 'person', false ); ?>><?php echo esc_html__( 'Person', 'rankkernel' ); ?></option>
						</select>
						<span class="rk-icon rk-ui-select-chevron" aria-hidden="true">expand_more</span>
					</div>
					<p class="rk-schema-hint"><?php echo esc_html__( 'Choose Organization for a business or group site, Person for a personal site.', 'rankkernel' ); ?></p>
				</div>

				<div class="rk-ui-form-row">
					<label class="rk-ui-form-label" for="rk-org-name"><?php echo esc_html__( 'Organization Name', 'rankkernel' ); ?></label>
					<input type="text" id="rk-org-name" name="org_name" value="<?php echo esc_attr( $orgName ); ?>" class="regular-text" />
					<p class="rk-schema-hint"><?php echo esc_html__( 'Shown as the site owner name in search results. Leave empty to use the site name.', 'rankkernel' ); ?></p>
				</div>

				<?php
				/*
				 * The row title names a group of two buttons, not one control,
				 * so it is a span wired to the group instead of a label with a
				 * for attribute. The ids inside the group are the ones the
				 * media picker script looks up, and it is left untouched.
				 */
				?>
				<div class="rk-ui-form-row" role="group" aria-labelledby="rk-org-logo-label">
					<span class="rk-ui-form-label" id="rk-org-logo-label"><?php echo esc_html__( 'Organization Logo', 'rankkernel' ); ?></span>
					<div id="rk-org-logo-wrap">
						<img id="rk-org-logo-preview" src="<?php echo esc_url( $orgLogo ); ?>" alt="" style="max-width:150px;height:auto;<?php echo '' === $orgLogo ? 'display:none;' : ''; ?>" />
						<input type="hidden" id="rk-org-logo" name="org_logo" value="<?php echo esc_attr( $orgLogo ); ?>" />
						<p class="rk-schema-logo-actions">
							<button type="button" class="button" id="rk-org-logo-select"><span class="rk-icon" aria-hidden="true">cloud_upload</span><?php echo esc_html__( 'Select image', 'rankkernel' ); ?></button>
							<button type="button" class="button" id="rk-org-logo-remove"<?php echo '' === $orgLogo ? ' style="display:none;"' : ''; ?>><span class="rk-icon" aria-hidden="true">cancel</span><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></button>
						</p>
					</div>
					<p class="rk-schema-hint"><?php echo esc_html__( 'Logo image shown with your site name in search results. Pick from the media library or upload a new image.', 'rankkernel' ); ?></p>
				</div>

				<div class="rk-ui-form-row">
					<label class="rk-ui-form-label" for="rk-org-sameas"><?php echo esc_html__( 'Same As', 'rankkernel' ); ?></label>
					<textarea id="rk-org-sameas" name="org_sameas" rows="4" cols="50"><?php echo esc_textarea( implode( "\n", $sameAsLines ) ); ?></textarea>
					<p class="rk-schema-hint"><?php echo esc_html__( 'One profile address per line, for example social profiles. Tells search engines which profiles are yours.', 'rankkernel' ); ?></p>
				</div>

				<?php
				/*
				 * A switch is only correct for a checkbox with no visible text
				 * of its own. Every checkbox on this screen sits inside a full
				 * sentence inside a real label, so all three stay normal
				 * checkboxes and the row titles above them stay spans.
				 */
				?>
				<div class="rk-ui-form-row">
					<span class="rk-ui-form-label"><?php echo esc_html__( 'Search Action', 'rankkernel' ); ?></span>
					<label class="rk-schema-check">
						<input type="checkbox" name="website_search_action" value="1" <?php echo checked( $websiteSearchAction, true, false ); ?> /> <?php echo esc_html__( 'Adds a search box under your home page in search results. Only useful if your site has search.', 'rankkernel' ); ?>
					</label>
				</div>
			</div>
		</div>

		<?php /* Section 4: defaults card. */ ?>
		<div class="rk-ui-card rk-defaults-card">
			<div class="rk-ui-card-header">
				<h3 class="rk-ui-card-title"><?php echo esc_html__( 'Defaults', 'rankkernel' ); ?></h3>
			</div>

			<div class="rk-card-body">
				<div class="rk-ui-notice rk-ui-notice-info" role="status">
					<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">info</span>
					<p class="rk-ui-notice-text"><?php echo esc_html__( 'Automatic means posts use BlogPosting, other types use Article.', 'rankkernel' ); ?></p>
				</div>

				<?php foreach ( $defaultRows as $row ) : ?>
					<div class="rk-ui-form-row">
						<label class="rk-ui-form-label" for="rk-<?php echo esc_attr( $row['key'] ); ?>"><?php echo esc_html( $row['label'] ); ?></label>
						<div class="rk-ui-select-wrap">
							<select class="rk-ui-select" id="rk-<?php echo esc_attr( $row['key'] ); ?>" name="<?php echo esc_attr( $row['key'] ); ?>">
								<option value=""<?php echo selected( $row['current'], '', false ); ?>><?php echo esc_html__( 'Automatic', 'rankkernel' ); ?></option>
								<?php foreach ( $schemaTypes as $schemaType ) : ?>
									<option value="<?php echo esc_attr( $schemaType['value'] ); ?>"<?php echo selected( $row['current'], $schemaType['value'], false ); ?>><?php echo esc_html( $schemaType['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="rk-icon rk-ui-select-chevron" aria-hidden="true">expand_more</span>
						</div>
						<p class="rk-schema-hint"><?php echo esc_html__( 'Default schema type for this post type.', 'rankkernel' ); ?></p>
					</div>
				<?php endforeach; ?>

				<div class="rk-ui-form-row">
					<span class="rk-ui-form-label"><?php echo esc_html__( 'Breadcrumbs', 'rankkernel' ); ?></span>
					<label class="rk-schema-check">
						<input type="checkbox" name="schema_breadcrumbs" value="1" <?php echo checked( $schemaBreadcrumbs, true, false ); ?> /> <?php echo esc_html__( 'Shows the page trail in search results. Turn off to hide it.', 'rankkernel' ); ?>
					</label>
				</div>

				<div class="rk-ui-form-row">
					<span class="rk-ui-form-label"><?php echo esc_html__( 'Author', 'rankkernel' ); ?></span>
					<label class="rk-schema-check">
						<input type="checkbox" name="schema_author" value="1" <?php echo checked( $schemaAuthor, true, false ); ?> /> <?php echo esc_html__( 'Shows the article author in search results. Turn off to hide it.', 'rankkernel' ); ?>
					</label>
				</div>
			</div>
		</div>

		<?php /* Section 5: testing tools card. */ ?>
		<div class="rk-ui-card rk-tools-card">
			<div class="rk-ui-card-header">
				<h3 class="rk-ui-card-title"><?php echo esc_html__( 'Tools', 'rankkernel' ); ?></h3>
			</div>

			<div class="rk-card-body">
				<div class="rk-schema-tools">
					<a class="rk-ui-btn rk-ui-btn-secondary" href="<?php echo esc_url( $richResultsUrl ); ?>" target="_blank" rel="noopener"><span class="rk-icon" aria-hidden="true">search</span><?php echo esc_html__( 'Rich Results Test', 'rankkernel' ); ?><span class="screen-reader-text"><?php echo esc_html__( '(opens in a new tab)', 'rankkernel' ); ?></span></a>
					<a class="rk-ui-btn rk-ui-btn-secondary" href="<?php echo esc_url( $validatorUrl ); ?>" target="_blank" rel="noopener"><span class="rk-icon" aria-hidden="true">code_blocks</span><?php echo esc_html__( 'Schema Validator', 'rankkernel' ); ?><span class="screen-reader-text"><?php echo esc_html__( '(opens in a new tab)', 'rankkernel' ); ?></span></a>
				</div>
				<p class="rk-schema-hint"><?php echo esc_html__( 'The Rich Results Test opens with your home URL prefilled.', 'rankkernel' ); ?></p>
			</div>
		</div>

		<?php /* Section 6: one save control for the single form. */ ?>
		<div class="rk-schema-form-foot">
			<?php submit_button( __( 'Save Schema Settings', 'rankkernel' ), 'primary', 'rankkernel_schema_save' ); ?>
		</div>
	</form>
</div>
