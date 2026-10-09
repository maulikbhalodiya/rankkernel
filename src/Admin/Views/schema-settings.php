<?php
/**
 * Schema settings page view.
 *
 * Presentation only. SchemaSettingsPage prepares every variable used below,
 * and owns capability checks, nonce verification, request handling,
 * validation, and redirects.
 *
 * The page adopts the shared component layer through the rk-ui root class,
 * so every component (cards, header, buttons, notices in all four semantic
 * colours, pills, tables, selects, switches and the focus ring) comes from
 * rankkernel-ui.css. Page scoped rules live in
 * assets/css/schema-settings-admin.css. The page accent is the primary blue,
 * the global palette the approved design uses: the Schema chip, the header
 * icon tile and every section glyph take the primary token pair, and the
 * reserved violet secondary 2 pair stays available for focus states only,
 * which is the only place the design paints it.
 *
 * The design is a two column composition: a sticky sidebar carrying the
 * section nav and a schema status summary, then five stacked cards. The page
 * keeps one form and one nonce for every group, exactly as before, so the
 * single Save Settings control sits in the header the way the design shows
 * it, and the per section save bars the mockup draws are deliberately not
 * rendered because they would need one form and one nonce per section.
 *
 * Two blocks exist for composition only and never claim live data. The
 * JSON-LD preview renders a clearly labelled example graph, because the real
 * graph is generated when a page is requested and is not available on this
 * screen, and the profile URL field keeps the real textarea as its control,
 * with the chips and add row as a JavaScript enhancement on top of it.
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
 * @var array<string, mixed> $previewGraph Live preview graph document.
 * @var string   $previewJson         Pretty-printed preview JSON, empty when no graph.
 */

declare(strict_types=1);

use RankKernel\Plugin;

defined( 'ABSPATH' ) || exit;

$rkPluginVersion = Plugin::version();
?>
<div class="wrap rk-schema-settings rk-ui">

	<?php
	/*
	 * Core prints no heading for a plugin screen, so the document keeps one h1
	 * here while the visible title is the h2 in the page header card below.
	 */
	?>
	<h1 class="screen-reader-text"><?php echo esc_html__( 'Schema Settings', 'rankkernel' ); ?></h1>

	<?php
	/*
	 * Section 1: notices, first, the order the design opens with. The success
	 * and error notices are outcomes of the real save path. The information
	 * notice is product copy, so it states what the module does rather than a
	 * runtime status, and the Schema module is the only thing that can turn
	 * the graph on or off.
	 */
	?>
	<div class="rk-schema-notices">
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

		<div class="rk-ui-notice rk-ui-notice-info">
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">info</span>
			<p class="rk-ui-notice-text"><?php echo esc_html__( 'Schema output is one unified JSON-LD graph per page, assembled from the entities configured below.', 'rankkernel' ); ?></p>
		</div>
	</div>

	<?php
	/*
	 * One form covers every group, so the nonce, the field names and the save
	 * control stay exactly as the controller expects. The header sits inside
	 * the form, which keeps the Save Settings control a plain submit button
	 * and the page working without JavaScript.
	 */
	?>
	<form method="post" action="" class="rk-schema-form">
		<?php wp_nonce_field( 'rankkernel_schema_settings' ); ?>

		<?php
		/*
		 * Section 2: page header card. The icon tile, the Schema badge and
		 * the version chip are presentation. The version is the real plugin
		 * version, not the design mockup number. Rich Results Test and Schema
		 * Validator are the real controller URLs, and the save control is the
		 * single submit for the form that wraps this header.
		 */
		?>
		<header class="rk-ui-card rk-ui-page-header rk-schema-header">
			<div class="rk-ui-page-header-text">
				<div class="rk-ui-page-header-title-row rk-schema-title-row">
					<span class="rk-schema-header-icon" aria-hidden="true"><span class="rk-icon" aria-hidden="true">code_blocks</span></span>
					<h2 class="rk-ui-page-title"><?php echo esc_html__( 'Schema Settings', 'rankkernel' ); ?></h2>
					<span class="rk-schema-badge"><?php echo esc_html__( 'Schema', 'rankkernel' ); ?></span>
					<span class="rk-schema-version-chip"><?php echo esc_html( 'v' . $rkPluginVersion ); ?></span>
				</div>
				<p class="rk-ui-sub"><?php echo esc_html__( 'Configure structured data graph output for search engines.', 'rankkernel' ); ?></p>
			</div>
			<div class="rk-ui-page-header-actions rk-schema-header-actions">
				<a class="rk-ui-btn rk-ui-btn-secondary rk-schema-header-link" href="<?php echo esc_url( $richResultsUrl ); ?>" target="_blank" rel="noopener"><span class="rk-icon" aria-hidden="true">search</span><?php echo esc_html__( 'Rich Results Test', 'rankkernel' ); ?><span class="rk-icon rk-schema-arrow" aria-hidden="true">arrow_right</span><span class="screen-reader-text"><?php echo esc_html__( '(opens in a new tab)', 'rankkernel' ); ?></span></a>
				<a class="rk-ui-btn rk-ui-btn-secondary rk-schema-header-link" href="<?php echo esc_url( $validatorUrl ); ?>" target="_blank" rel="noopener"><span class="rk-icon" aria-hidden="true">check_circle</span><?php echo esc_html__( 'Schema Validator', 'rankkernel' ); ?><span class="rk-icon rk-schema-arrow" aria-hidden="true">arrow_right</span><span class="screen-reader-text"><?php echo esc_html__( '(opens in a new tab)', 'rankkernel' ); ?></span></a>
				<button type="submit" name="rankkernel_schema_save" value="<?php echo esc_attr( __( 'Save Schema Settings', 'rankkernel' ) ); ?>" class="rk-ui-btn rk-ui-btn-primary"><span class="rk-icon" aria-hidden="true">download</span><?php echo esc_html__( 'Save Settings', 'rankkernel' ); ?></button>
			</div>
		</header>

		<?php
		/*
		 * Section 3: the two column body. The sidebar links to the five card
		 * ids and summarises the real settings the cards below carry. Every
		 * status on the panel is derived from a field on this page or from a
		 * fixed behaviour of the generator, never from a runtime probe this
		 * screen does not run.
		 */
		?>
		<div class="rk-schema-layout">

			<aside class="rk-schema-sidebar">
				<nav class="rk-ui-card rk-schema-nav" aria-label="<?php echo esc_attr( __( 'Schema settings sections', 'rankkernel' ) ); ?>">
					<div class="rk-schema-nav-head"><?php echo esc_html__( 'Settings', 'rankkernel' ); ?></div>
					<ul class="rk-schema-nav-list">
						<li>
							<a class="rk-schema-nav-item is-current" href="#section-identity">
								<span class="rk-icon" aria-hidden="true">shield</span>
								<span><?php echo esc_html__( 'Identity', 'rankkernel' ); ?></span>
							</a>
						</li>
						<li>
							<a class="rk-schema-nav-item" href="#section-defaults">
								<span class="rk-icon" aria-hidden="true">assessment</span>
								<span><?php echo esc_html__( 'Schema Defaults', 'rankkernel' ); ?></span>
							</a>
						</li>
						<li>
							<a class="rk-schema-nav-item" href="#section-output">
								<span class="rk-icon" aria-hidden="true">tune</span>
								<span><?php echo esc_html__( 'Output Options', 'rankkernel' ); ?></span>
							</a>
						</li>
						<li>
							<a class="rk-schema-nav-item" href="#section-preview">
								<span class="rk-icon" aria-hidden="true">code_blocks</span>
								<span><?php echo esc_html__( 'JSON-LD Preview', 'rankkernel' ); ?></span>
								<span class="rk-schema-nav-dot" aria-hidden="true"></span>
							</a>
						</li>
						<li>
							<a class="rk-schema-nav-item" href="#section-testing">
								<span class="rk-icon" aria-hidden="true">search</span>
								<span><?php echo esc_html__( 'Testing Tools', 'rankkernel' ); ?></span>
							</a>
						</li>
					</ul>

					<div class="rk-schema-status">
						<div class="rk-schema-status-head">
							<span class="rk-schema-status-title"><?php echo esc_html__( 'Schema status', 'rankkernel' ); ?></span>
							<span class="rk-schema-status-meta"><?php echo esc_html__( 'JSON graph', 'rankkernel' ); ?></span>
						</div>
						<ul class="rk-schema-status-list">
							<li class="rk-schema-status-row">
								<span class="rk-schema-status-entity"><span class="rk-schema-status-dot is-on" aria-hidden="true"></span><?php echo esc_html__( 'WebSite entity', 'rankkernel' ); ?></span>
								<span class="rk-schema-status-value"><?php echo esc_html__( 'Always included', 'rankkernel' ); ?></span>
							</li>
							<li class="rk-schema-status-row">
								<span class="rk-schema-status-entity"><span class="rk-schema-status-dot is-on" aria-hidden="true"></span><?php echo 'person' === $represents ? esc_html__( 'Person entity', 'rankkernel' ) : esc_html__( 'Organization entity', 'rankkernel' ); ?></span>
								<span class="rk-schema-status-value"><?php echo esc_html__( 'Selected', 'rankkernel' ); ?></span>
							</li>
							<li class="rk-schema-status-row">
								<span class="rk-schema-status-entity"><span class="rk-schema-status-dot<?php echo $schemaBreadcrumbs ? ' is-on' : ''; ?>" aria-hidden="true"></span><?php echo esc_html__( 'Breadcrumbs', 'rankkernel' ); ?></span>
								<span class="rk-schema-status-value"><?php echo $schemaBreadcrumbs ? esc_html__( 'On', 'rankkernel' ) : esc_html__( 'Off', 'rankkernel' ); ?></span>
							</li>
							<li class="rk-schema-status-row">
								<span class="rk-schema-status-entity"><span class="rk-schema-status-dot<?php echo $schemaAuthor ? ' is-on' : ''; ?>" aria-hidden="true"></span><?php echo esc_html__( 'Author entity', 'rankkernel' ); ?></span>
								<span class="rk-schema-status-value"><?php echo $schemaAuthor ? esc_html__( 'On', 'rankkernel' ) : esc_html__( 'Off', 'rankkernel' ); ?></span>
							</li>
						</ul>
						<div class="rk-schema-status-foot">
							<span class="rk-schema-status-entity"><span class="rk-schema-status-dot is-on" aria-hidden="true"></span><strong><?php echo esc_html__( 'JSON-LD', 'rankkernel' ); ?></strong></span>
							<span class="rk-ui-pill rk-ui-pill-success"><?php echo esc_html__( 'One per page', 'rankkernel' ); ?></span>
						</div>
					</div>
				</nav>

				<div class="rk-ui-card rk-schema-hintbox">
					<span class="rk-icon" aria-hidden="true">auto_fix_high</span>
					<p><?php echo esc_html__( 'RankKernel compiles all entities into a single unified JSON-LD graph to eliminate markup bloat.', 'rankkernel' ); ?></p>
				</div>
			</aside>

			<main class="rk-schema-main">
				<noscript><style>.rk-schema-section[hidden]{display:block}</style></noscript>

				<?php /* Section 4: identity card. */ ?>
				<section class="rk-ui-card rk-schema-section" id="section-identity">
					<div class="rk-ui-card-header rk-schema-section-head">
						<div class="rk-schema-section-head-text">
							<h3 class="rk-ui-card-title"><?php echo esc_html__( 'Identity', 'rankkernel' ); ?></h3>
							<p class="rk-ui-sub"><?php echo esc_html__( 'Define who owns this site for the WebSite and Organization schema entities.', 'rankkernel' ); ?></p>
						</div>
						<span class="rk-schema-section-glyph" aria-hidden="true"><span class="rk-icon" aria-hidden="true">shield</span></span>
					</div>

					<div class="rk-ui-card-body rk-schema-rows">
						<?php
						/*
						 * Represents. The design draws two radio cards, so the
						 * control is a real radio group. The group container
						 * keeps the id and the described association the old
						 * select held, so the accessible description travels
						 * with the control it explains.
						 */
						?>
						<div class="rk-schema-row">
							<div class="rk-schema-row-lead">
								<span class="rk-ui-form-label" id="rk-site-represents-label"><?php echo esc_html__( 'Represents', 'rankkernel' ); ?></span>
								<p class="rk-ui-hint" id="rk-site-represents-desc"><?php echo esc_html__( 'Affects which entity type is used in the WebSite schema.', 'rankkernel' ); ?></p>
							</div>
							<div class="rk-schema-row-control">
								<div class="rk-schema-rep-group" id="rk-site-represents" role="radiogroup" aria-labelledby="rk-site-represents-label" aria-describedby="rk-site-represents-desc">
									<label class="rk-schema-rep-card<?php echo 'organization' === $represents ? ' is-selected' : ''; ?>">
										<input class="rk-schema-rep-input" type="radio" name="site_represents" value="organization" <?php echo checked( $represents, 'organization', false ); ?> />
										<span class="rk-schema-rep-text">
											<span class="rk-schema-rep-title"><?php echo esc_html__( 'Organization', 'rankkernel' ); ?></span>
											<span class="rk-schema-rep-sub"><?php echo esc_html__( 'Business or group', 'rankkernel' ); ?></span>
										</span>
										<span class="rk-schema-rep-dot" aria-hidden="true"></span>
									</label>
									<label class="rk-schema-rep-card<?php echo 'person' === $represents ? ' is-selected' : ''; ?>">
										<input class="rk-schema-rep-input" type="radio" name="site_represents" value="person" <?php echo checked( $represents, 'person', false ); ?> />
										<span class="rk-schema-rep-text">
											<span class="rk-schema-rep-title"><?php echo esc_html__( 'Person', 'rankkernel' ); ?></span>
											<span class="rk-schema-rep-sub"><?php echo esc_html__( 'Personal site', 'rankkernel' ); ?></span>
										</span>
										<span class="rk-schema-rep-dot" aria-hidden="true"></span>
									</label>
								</div>
							</div>
						</div>

						<?php
						/*
						 * Name. The field name, the id and the described
						 * association are unchanged, only the label and the
						 * description match the design wording.
						 */
						?>
						<div class="rk-schema-row">
							<div class="rk-schema-row-lead">
								<label class="rk-ui-form-label" for="rk-org-name"><?php echo esc_html__( 'Name', 'rankkernel' ); ?></label>
								<p class="rk-ui-hint" id="rk-org-name-desc"><?php echo esc_html__( 'The official entity name output in schema. Leave empty to use the site name.', 'rankkernel' ); ?></p>
							</div>
							<div class="rk-schema-row-control">
								<input type="text" id="rk-org-name" name="org_name" value="<?php echo esc_attr( $orgName ); ?>" class="rk-schema-input" aria-describedby="rk-org-name-desc" />
							</div>
						</div>

						<?php
						/*
						 * Logo. The row title names a group of two buttons, so
						 * it is a span wired to the group. The ids inside the
						 * group are the ones the media picker script looks up,
						 * and it is left untouched. Removing the logo discards
						 * the stored image, so that control keeps the shared
						 * destructive button rather than a second neutral one.
						 */
						?>
						<div class="rk-schema-row" role="group" aria-labelledby="rk-org-logo-label">
							<div class="rk-schema-row-lead">
								<span class="rk-ui-form-label" id="rk-org-logo-label"><?php echo esc_html__( 'Logo', 'rankkernel' ); ?></span>
								<p class="rk-ui-hint" id="rk-org-logo-desc"><?php echo esc_html__( 'PNG recommended. Pick from the media library or upload a new image.', 'rankkernel' ); ?></p>
							</div>
							<div class="rk-schema-row-control">
								<div class="rk-schema-logo-picker">
									<div class="rk-schema-logo-frame">
										<span class="rk-schema-logo-placeholder" aria-hidden="true"><span class="rk-icon" aria-hidden="true">upload</span></span>
										<img id="rk-org-logo-preview" class="rk-schema-logo-preview" src="<?php echo esc_url( $orgLogo ); ?>" alt=""<?php echo '' === $orgLogo ? ' style="display:none;"' : ''; ?> />
									</div>
									<div class="rk-schema-logo-text">
										<p class="rk-schema-logo-actions">
											<button type="button" class="rk-ui-btn rk-ui-btn-secondary" id="rk-org-logo-select" aria-describedby="rk-org-logo-desc"><span class="rk-icon" aria-hidden="true">cloud_upload</span><?php echo esc_html__( 'Select image', 'rankkernel' ); ?></button>
											<button type="button" class="rk-ui-btn rk-ui-btn-danger" id="rk-org-logo-remove"<?php echo '' === $orgLogo ? ' style="display:none;"' : ''; ?> aria-describedby="rk-org-logo-desc"><span class="rk-icon" aria-hidden="true">cancel</span><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></button>
										</p>
										<p class="rk-ui-hint"><?php echo esc_html__( 'Shown in Google Knowledge Graph and Search results.', 'rankkernel' ); ?></p>
									</div>
									<input type="hidden" id="rk-org-logo" name="org_logo" value="<?php echo esc_attr( $orgLogo ); ?>" />
								</div>
							</div>
						</div>

						<?php
						/*
						 * Social profiles. The real control is the textarea the
						 * controller has always collected, so the page works
						 * without JavaScript. The chips and the add row above
						 * it are an enhancement: the script reveals them, keeps
						 * the textarea value in step and submits the very same
						 * newline separated field.
						 */
						?>
						<div class="rk-schema-row">
							<div class="rk-schema-row-lead">
								<span class="rk-ui-form-label" id="rk-org-sameas-label"><?php echo esc_html__( 'Social profiles', 'rankkernel' ); ?></span>
								<p class="rk-ui-hint" id="rk-org-sameas-desc"><?php echo esc_html__( 'Links your site to these profiles in structured data, one URL per line.', 'rankkernel' ); ?></p>
							</div>
							<div class="rk-schema-row-control">
								<div class="rk-schema-sameas" id="rk-schema-sameas-app" data-remove-label="<?php echo esc_attr( __( 'Remove profile URL', 'rankkernel' ) ); ?>" data-invalid-label="<?php echo esc_attr( __( 'Enter a full profile URL starting with http:// or https://.', 'rankkernel' ) ); ?>">
									<ul class="rk-schema-profile-chips" id="rk-schema-sameas-chips"></ul>
									<div class="rk-schema-sameas-add">
										<span class="rk-icon" aria-hidden="true">link</span>
										<label class="screen-reader-text" for="rk-schema-sameas-new"><?php echo esc_html__( 'Add a profile URL', 'rankkernel' ); ?></label>
										<input type="url" id="rk-schema-sameas-new" class="rk-schema-sameas-input" placeholder="<?php echo esc_attr( __( 'Add profile URL (e.g. https://instagram.com/example)', 'rankkernel' ) ); ?>" />
										<button type="button" class="rk-ui-btn rk-ui-btn-secondary" id="rk-schema-sameas-add"><?php echo esc_html__( 'Add', 'rankkernel' ); ?></button>
									</div>
								</div>
								<textarea id="rk-org-sameas" name="org_sameas" class="rk-schema-sameas-field" rows="4" cols="50" aria-labelledby="rk-org-sameas-label" aria-describedby="rk-org-sameas-desc"><?php echo esc_textarea( implode( "\n", $sameAsLines ) ); ?></textarea>
							</div>
						</div>

						<?php
						/*
						 * Search box. A switch is correct here because the
						 * checkbox has no visible text of its own, the sentence
						 * beside it is a separate label. The field name and
						 * value are unchanged.
						 */
						?>
						<div class="rk-schema-row">
							<div class="rk-schema-row-lead">
								<span class="rk-ui-form-label" id="rk-search-action-label"><?php echo esc_html__( 'Search box', 'rankkernel' ); ?></span>
								<p class="rk-ui-hint"><?php echo esc_html__( 'Sitelinks SearchBox integration.', 'rankkernel' ); ?></p>
							</div>
							<div class="rk-schema-row-control rk-schema-switch-row">
								<label class="rk-ui-switch">
									<input type="checkbox" name="website_search_action" value="1" <?php echo checked( $websiteSearchAction, true, false ); ?> aria-label="<?php echo esc_attr( __( 'Enable Google Sitelinks Search Box for this site.', 'rankkernel' ) ); ?>" />
									<span class="rk-ui-switch-track" aria-hidden="true"><span class="rk-ui-switch-knob"></span></span>
								</label>
								<span class="rk-schema-switch-text"><?php echo esc_html__( 'Enable Google Sitelinks Search Box for this site.', 'rankkernel' ); ?></span>
							</div>
						</div>
					</div>
				</section>

				<?php /* Section 5: schema defaults card. */ ?>
				<section class="rk-ui-card rk-schema-section" id="section-defaults" hidden>
					<div class="rk-ui-card-header rk-schema-section-head">
						<div class="rk-schema-section-head-text">
							<h3 class="rk-ui-card-title"><?php echo esc_html__( 'Schema Defaults', 'rankkernel' ); ?></h3>
							<p class="rk-ui-sub"><?php echo esc_html__( 'The default schema type applied to each post type when no post override exists.', 'rankkernel' ); ?></p>
						</div>
						<span class="rk-schema-section-glyph" aria-hidden="true"><span class="rk-icon" aria-hidden="true">assessment</span></span>
					</div>

					<div class="rk-ui-card-body rk-schema-defaults-body">
						<?php
						/*
						 * A static callout rather than a status message, so it
						 * deliberately carries no live region role. One rule
						 * covers every static callout in the product: no role,
						 * and the info glyph the shared notice variants map it
						 * to.
						 */
						?>
						<div class="rk-ui-notice rk-ui-notice-info">
							<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">info</span>
							<p class="rk-ui-notice-text"><?php echo esc_html__( 'Automatic falls back to BlogPosting for posts and Article for all other types.', 'rankkernel' ); ?></p>
						</div>

						<?php
						/*
						 * Rows per real public post type. The select keeps the
						 * stored field name, the Automatic option and every
						 * supported type, and the described association each
						 * control carried before.
						 */
						?>
						<div class="rk-ui-table-wrap">
							<table class="rk-ui-table rk-schema-defaults-table">
								<thead>
									<tr>
										<th scope="col" class="rk-schema-col-type"><?php echo esc_html__( 'Post type', 'rankkernel' ); ?></th>
										<th scope="col" class="rk-schema-col-schema"><?php echo esc_html__( 'Schema type', 'rankkernel' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $defaultRows as $row ) : ?>
										<?php
										/*
										 * The slug is the stored key without its
										 * prefix, and the Overridden pill states
										 * the one real fact the row carries: a
										 * value is stored for this post type.
										 */
										$rkSlug = str_starts_with( $row['key'], 'schema_default_' ) ? substr( $row['key'], strlen( 'schema_default_' ) ) : $row['key'];
										?>
										<tr>
											<td class="rk-schema-col-type">
												<span class="rk-schema-post-type">
													<span class="rk-schema-post-name"><?php echo esc_html( $row['label'] ); ?></span>
													<span class="rk-schema-slug-chip"><?php echo esc_html( $rkSlug ); ?></span>
													<?php if ( '' !== $row['current'] ) : ?>
														<span class="rk-ui-pill rk-ui-pill-info"><?php echo esc_html__( 'Overridden', 'rankkernel' ); ?></span>
													<?php endif; ?>
												</span>
											</td>
											<td class="rk-schema-col-schema">
												<div class="rk-ui-select-wrap rk-schema-select-wrap">
													<select class="rk-ui-select rk-schema-select" id="rk-<?php echo esc_attr( $row['key'] ); ?>" name="<?php echo esc_attr( $row['key'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: post type label */ __( 'Default schema type for %s', 'rankkernel' ), $row['label'] ) ); ?>" aria-describedby="rk-<?php echo esc_attr( $row['key'] ); ?>-desc">
														<option value=""<?php echo selected( $row['current'], '', false ); ?>><?php echo esc_html__( 'Automatic', 'rankkernel' ); ?></option>
														<?php foreach ( $schemaTypes as $schemaType ) : ?>
															<option value="<?php echo esc_attr( $schemaType['value'] ); ?>"<?php echo selected( $row['current'], $schemaType['value'], false ); ?>><?php echo esc_html( $schemaType['label'] ); ?></option>
														<?php endforeach; ?>
													</select>
													<span class="rk-icon rk-ui-select-chevron" aria-hidden="true">expand_more</span>
												</div>
												<p class="rk-ui-hint rk-schema-select-desc" id="rk-<?php echo esc_attr( $row['key'] ); ?>-desc"><?php echo esc_html__( 'Default schema type for this post type.', 'rankkernel' ); ?></p>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</section>

				<?php /* Section 6: output options card. */ ?>
				<section class="rk-ui-card rk-schema-section" id="section-output" hidden>
					<div class="rk-ui-card-header rk-schema-section-head">
						<div class="rk-schema-section-head-text">
							<h3 class="rk-ui-card-title"><?php echo esc_html__( 'Output Options', 'rankkernel' ); ?></h3>
							<p class="rk-ui-sub"><?php echo esc_html__( 'Control which optional schema pieces are included in the JSON-LD graph.', 'rankkernel' ); ?></p>
						</div>
						<span class="rk-schema-section-glyph" aria-hidden="true"><span class="rk-icon" aria-hidden="true">tune</span></span>
					</div>

					<div class="rk-ui-card-body rk-schema-rows">
						<?php /* BreadcrumbList output toggle, field name and value unchanged. */ ?>
						<div class="rk-schema-toggle-row">
							<div class="rk-schema-toggle-text">
								<span class="rk-schema-toggle-icon" aria-hidden="true"><span class="rk-icon" aria-hidden="true">alt_route</span></span>
								<span class="rk-schema-toggle-copy">
									<span class="rk-schema-toggle-title"><?php echo esc_html__( 'BreadcrumbList', 'rankkernel' ); ?></span>
									<span class="rk-schema-toggle-desc"><?php echo esc_html__( 'Shows the page trail in Google search results as breadcrumb links.', 'rankkernel' ); ?></span>
									<span class="rk-schema-toggle-note"><?php echo esc_html__( 'Turn this off to stop outputting the BreadcrumbList entity.', 'rankkernel' ); ?></span>
								</span>
							</div>
							<label class="rk-ui-switch">
								<input type="checkbox" name="schema_breadcrumbs" value="1" <?php echo checked( $schemaBreadcrumbs, true, false ); ?> aria-label="<?php echo esc_attr( __( 'Output the BreadcrumbList schema entity.', 'rankkernel' ) ); ?>" />
								<span class="rk-ui-switch-track" aria-hidden="true"><span class="rk-ui-switch-knob"></span></span>
							</label>
						</div>

						<?php /* Author entity output toggle, field name and value unchanged. */ ?>
						<div class="rk-schema-toggle-row">
							<div class="rk-schema-toggle-text">
								<span class="rk-schema-toggle-icon" aria-hidden="true"><span class="rk-icon" aria-hidden="true">add</span></span>
								<span class="rk-schema-toggle-copy">
									<span class="rk-schema-toggle-title"><?php echo esc_html__( 'Author Entity', 'rankkernel' ); ?></span>
									<span class="rk-schema-toggle-desc"><?php echo esc_html__( 'Adds the post author as a Person entity in the schema graph.', 'rankkernel' ); ?></span>
									<span class="rk-schema-toggle-note"><?php echo esc_html__( 'Only applies to posts that have an author.', 'rankkernel' ); ?></span>
								</span>
							</div>
							<label class="rk-ui-switch">
								<input type="checkbox" name="schema_author" value="1" <?php echo checked( $schemaAuthor, true, false ); ?> aria-label="<?php echo esc_attr( __( 'Output the post author as a Person entity.', 'rankkernel' ) ); ?>" />
								<span class="rk-ui-switch-track" aria-hidden="true"><span class="rk-ui-switch-knob"></span></span>
							</label>
						</div>
					</div>
				</section>

				<?php
				/*
				 * Section 7: JSON-LD preview. The graph is generated on a page
				 * request, not on this screen, so the code block renders a
				 * clearly labelled example instead of a fabricated fragment,
				 * and the buttons that would need new clipboard or download
				 * handlers stay disabled. Open in Validator is the real
				 * controller URL.
				 */
				?>
				<section class="rk-ui-card rk-schema-section" id="section-preview" hidden>
					<div class="rk-ui-card-header rk-schema-section-head">
						<div class="rk-schema-section-head-text">
							<div class="rk-schema-preview-title-row">
								<h3 class="rk-ui-card-title"><?php echo esc_html__( 'JSON-LD Preview', 'rankkernel' ); ?></h3>
								<span class="rk-schema-preview-chip"><span class="rk-schema-preview-dot" aria-hidden="true"></span><?php echo esc_html__( 'Example', 'rankkernel' ); ?></span>
							</div>
							<p class="rk-ui-sub"><?php echo esc_html__( 'A sample of the graph shape the plugin prints in the page head.', 'rankkernel' ); ?></p>
						</div>
						<div class="rk-schema-preview-actions">
							<button type="button" class="rk-ui-btn rk-ui-btn-secondary rk-ui-btn-disabled" disabled aria-disabled="true"><span class="rk-icon" aria-hidden="true">code_blocks</span><?php echo esc_html__( 'Copy JSON', 'rankkernel' ); ?></button>
							<button type="button" class="rk-ui-btn rk-ui-btn-secondary rk-ui-btn-disabled" disabled aria-disabled="true"><span class="rk-icon" aria-hidden="true">download</span><?php echo esc_html__( 'Export JSON', 'rankkernel' ); ?></button>
							<a class="rk-ui-btn rk-schema-validator-link" href="<?php echo esc_url( $validatorUrl ); ?>" target="_blank" rel="noopener"><span class="rk-icon" aria-hidden="true">check_circle</span><?php echo esc_html__( 'Open in Validator', 'rankkernel' ); ?><span class="screen-reader-text"><?php echo esc_html__( '(opens in a new tab)', 'rankkernel' ); ?></span></a>
						</div>
					</div>

					<div class="rk-ui-card-body rk-schema-preview-body">
						<div class="rk-ui-notice rk-ui-notice-info">
							<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">info</span>
							<p class="rk-ui-notice-text"><?php echo esc_html__( 'The graph for your site is assembled from these settings when a page is requested, and an individual post can override the schema type.', 'rankkernel' ); ?></p>
						</div>

						<?php if ( '' !== $previewJson ) : ?>
						<p class="rk-ui-hint rk-schema-preview-example"><?php echo esc_html__( 'Live preview. This graph is generated from your settings by the same builder the frontend uses.', 'rankkernel' ); ?></p>

						<div class="rk-schema-codeblock">
							<pre class="rk-schema-code"><code><?php echo esc_html( $previewJson ); ?></code></pre>
						</div>

						<p class="rk-schema-preview-meta">
							<span><?php echo esc_html( sprintf( /* translators: %d: number of schema nodes in the preview. */ __( 'Live graph: %d nodes', 'rankkernel' ), count( is_array( $previewGraph['@graph'] ?? null ) ? $previewGraph['@graph'] : array() ) ) ); ?></span>
						</p>
						<?php else : ?>
						<p class="rk-ui-hint rk-schema-preview-example"><?php echo esc_html__( 'No schema nodes are configured yet. The preview appears once your settings produce a graph.', 'rankkernel' ); ?></p>
						<?php endif; ?>
					</div>
				</section>

				<?php /* Section 8: testing tools card. */ ?>
				<section class="rk-ui-card rk-schema-section" id="section-testing" hidden>
					<div class="rk-ui-card-header rk-schema-section-head">
						<div class="rk-schema-section-head-text">
							<h3 class="rk-ui-card-title"><?php echo esc_html__( 'Testing Tools', 'rankkernel' ); ?></h3>
							<p class="rk-ui-sub"><?php echo esc_html__( 'Validate your structured data against official search engine testing tools.', 'rankkernel' ); ?></p>
						</div>
						<span class="rk-schema-section-glyph" aria-hidden="true"><span class="rk-icon" aria-hidden="true">search</span></span>
					</div>

					<div class="rk-ui-card-body">
						<div class="rk-schema-tools">
							<div class="rk-schema-tool-card">
								<div class="rk-schema-tool-head">
									<span class="rk-schema-tool-icon" aria-hidden="true"><span class="rk-icon" aria-hidden="true">search</span></span>
									<h4 class="rk-schema-tool-title"><?php echo esc_html__( 'Rich Results Test', 'rankkernel' ); ?></h4>
								</div>
								<p class="rk-schema-tool-desc"><?php echo esc_html__( 'Opens with your home URL. Test which rich results your schema enables in Google search.', 'rankkernel' ); ?></p>
								<p class="rk-schema-tool-action">
									<a class="rk-ui-btn rk-ui-btn-secondary" href="<?php echo esc_url( $richResultsUrl ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Open Tool', 'rankkernel' ); ?><span class="rk-icon rk-schema-arrow" aria-hidden="true">arrow_right</span><span class="screen-reader-text"><?php echo esc_html__( '(opens in a new tab)', 'rankkernel' ); ?></span></a>
								</p>
							</div>
							<div class="rk-schema-tool-card">
								<div class="rk-schema-tool-head">
									<span class="rk-schema-tool-icon" aria-hidden="true"><span class="rk-icon" aria-hidden="true">check_circle</span></span>
									<h4 class="rk-schema-tool-title"><?php echo esc_html__( 'Schema Validator', 'rankkernel' ); ?></h4>
								</div>
								<p class="rk-schema-tool-desc"><?php echo esc_html__( 'Paste your JSON-LD output to validate it against schema.org specifications and guidelines.', 'rankkernel' ); ?></p>
								<p class="rk-schema-tool-action">
									<a class="rk-ui-btn rk-ui-btn-secondary" href="<?php echo esc_url( $validatorUrl ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Open Tool', 'rankkernel' ); ?><span class="rk-icon rk-schema-arrow" aria-hidden="true">arrow_right</span><span class="screen-reader-text"><?php echo esc_html__( '(opens in a new tab)', 'rankkernel' ); ?></span></a>
								</p>
							</div>
						</div>
					</div>
				</section>

			</main>
		</div>
	</form>

	<?php /* Section 9: footer line, static product naming only, no claims. */ ?>
	<p class="rk-schema-footer"><?php echo esc_html__( 'RankKernel SEO Suite', 'rankkernel' ); ?> &bull; <?php echo esc_html__( 'Schema and Structured Data', 'rankkernel' ); ?> &bull; <?php echo esc_html( 'v' . $rkPluginVersion ); ?></p>
</div>
