<?php
/**
 * Sitemap settings page view.
 *
 * Presentation only. SitemapSettingsPage prepares every variable used below,
 * and owns capability checks, nonce verification, request handling,
 * validation and redirects. The markup adopts the shared rk-ui component
 * layer, so every class here comes from assets/css/rankkernel-ui.css or from
 * the page scoped assets/css/sitemap-admin.css sheet, and nothing on this
 * page redefines a shared component.
 *
 * The design is a spec sheet that stacks all four tab sections on one page.
 * Every panel renders server side; the controller marks the current one with
 * its hidden attribute, the JavaScript hides and shows panels in place, and
 * the tab links keep working as plain ?tab= page loads when JavaScript never
 * runs, so the address bar tab parameter remains the no-JS and deep-link
 * fallback.
 *
 * Two controls in the design have no backing feature in this product. The
 * "Submit to Google" button renders disabled beside the sitemap index URL
 * because it would otherwise be a control that does nothing, and the design's
 * "Discard Changes" text action is omitted for the same reason. Everything
 * else derives from real controller data. Checkboxes stay inside labels that
 * carry their visible sentence, so each one keeps a real accessible name and
 * the rows are labelled groups around them.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var bool   $settingsUpdated       Whether the settings saved notice renders.
 * @var bool   $settingsSaveFailed    Whether the save failed notice renders.
 * @var string $pluginVersion         Plugin version for the header chip.
 * @var array<int, array{id: string, url: string, current: bool, label: string, icon: string}> $tabItems Tab links.
 * @var bool   $showGeneral           Whether the General tab panel starts open.
 * @var bool   $showPostTypes         Whether the Post Types tab panel starts open.
 * @var bool   $showTaxonomies        Whether the Taxonomies tab panel starts open.
 * @var bool   $showAuthors           Whether the Authors tab panel starts open.
 * @var string $indexUrl              Sitemap index URL.
 * @var string $itemsPerPage          Links per sitemap value.
 * @var array<int, array<string, mixed>> $generalRows General tab rows.
 * @var array<int, array{key: string, label: string, enabled: bool, url: string, slug: string}> $postTypeRows Post type rows.
 * @var array<int, array{key: string, label: string, enabled: bool, url: string, slug: string}> $taxonomyRows Taxonomy rows.
 * @var array<int, array{name: string, title: string, checked: bool, hint: string}> $authorsRows Authors checkbox rows.
 * @var bool   $hasEditableRoles      Whether editable roles exist.
 * @var array<int, array{slug: string, name: string, excluded: bool}> $roleRows Editable role rows.
 * @var array{name: string, title: string, value: string, hint: string} $authorsExcludeUsers Exclude users row.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/*
 * Short notes under each General row title, the same labels the design
 * carries. They describe the row and are never a value or a status.
 */
$rkGeneralRowNotes = [
	'include_images'         => __( 'Image search indexing', 'rankkernel' ),
	'include_featured_image' => __( 'Header thumbnail indexing', 'rankkernel' ),
	'exclude_posts'          => __( 'Custom exclusions', 'rankkernel' ),
	'exclude_terms'          => __( 'Taxonomy blacklist', 'rankkernel' ),
	'include_empty_terms'    => __( 'Zero-post archives', 'rankkernel' ),
];
?>
<div class="wrap rk-sitemap-settings rk-ui">

	<?php
	/*
	 * WordPress injects third party plugin notices directly into .wrap before
	 * our rendered content, so we output a screen-reader heading here for the
	 * h1 it expects, then carry the visual title in the page header card.
	 */
	?>
	<h1 class="screen-reader-text"><?php echo esc_html__( 'Sitemap Settings', 'rankkernel' ); ?></h1>

	<?php
	/*
	 * Section 1: outcome notices, first, the order the design opens with. The
	 * notices live inside the page root so the shared notice component
	 * applies, and they carry no dismiss button.
	 */
	?>
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

	<?php
	/*
	 * Section 2: page header card. The icon tile, the version chip and the
	 * sitemap URL chip are static metadata. The Save Settings control is a
	 * real submit for the same form the active tab renders, wired across the
	 * DOM with its form attribute because the header sits above the form.
	 */
	?>
	<header class="rk-ui-card rk-ui-page-header rk-sitemap-header">
		<div class="rk-ui-page-header-text">
			<div class="rk-ui-page-header-title-row rk-sitemap-title-row">
				<span class="rk-sitemap-header-icon" aria-hidden="true"><span class="rk-icon" aria-hidden="true">alt_route</span></span>
				<h2 class="rk-ui-page-title"><?php echo esc_html__( 'Sitemap Settings', 'rankkernel' ); ?></h2>
				<span class="rk-sitemap-chip"><?php echo esc_html( 'v' . $pluginVersion ); ?></span>
				<a class="rk-sitemap-url-chip" href="<?php echo esc_url( $indexUrl ); ?>" title="<?php echo esc_attr( $indexUrl ); ?>">
					<span class="rk-sitemap-url-chip-text"><?php echo esc_html( $indexUrl ); ?></span>
					<span class="rk-icon" aria-hidden="true">link</span>
				</a>
			</div>
		</div>
		<div class="rk-ui-page-header-actions">
			<a class="rk-ui-btn rk-ui-btn-secondary" href="<?php echo esc_url( $indexUrl ); ?>"><span><?php echo esc_html__( 'View Sitemap', 'rankkernel' ); ?></span><span class="rk-icon" aria-hidden="true">arrow_right</span></a>
			<button type="submit" form="rk-sitemap-settings-form" name="rankkernel_sitemap_save" value="<?php echo esc_attr__( 'Save Sitemap Settings', 'rankkernel' ); ?>" class="rk-ui-btn rk-ui-btn-primary"><span class="rk-icon" aria-hidden="true">download</span><?php echo esc_html__( 'Save Settings', 'rankkernel' ); ?></button>
		</div>
	</header>

	<?php
	/*
	 * Section 3: tab bar. Tabs are links between separate pages, so they stay
	 * outside the form. The current flag selects the shared modifier and the
	 * matching aria-current, so exactly one link is marked as current.
	 */
	?>
	<nav class="rk-ui-tabs" aria-label="<?php echo esc_attr( __( 'Sitemap settings tabs', 'rankkernel' ) ); ?>">
		<?php foreach ( $tabItems as $tabItem ) : ?>
			<a href="<?php echo esc_url( $tabItem['url'] ); ?>" data-rk-tab="<?php echo esc_attr( $tabItem['id'] ); ?>" class="rk-ui-tab<?php echo ! empty( $tabItem['current'] ) ? ' is-current' : ''; ?>"<?php echo ! empty( $tabItem['current'] ) ? ' aria-current="page"' : ''; ?>><span class="rk-icon" aria-hidden="true"><?php echo esc_html( $tabItem['icon'] ); ?></span><?php echo esc_html( $tabItem['label'] ); ?></a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="" id="rk-sitemap-settings-form" class="rk-sitemap-form">
		<?php wp_nonce_field( 'rankkernel_sitemap_settings' ); ?>

		<?php
		/*
		 * Without JavaScript every panel must show, because the server only
		 * printed the current tab's panel without its hidden attribute clashing.
		 * The browser's normal [hidden] rule would otherwise hide the rest.
		 */
		?>
		<noscript><style>.rk-sitemap-panel[hidden]{display:block}</style></noscript>

		<div class="rk-ui-card rk-sitemap-panel" id="rk-sitemap-panel-general" data-rk-tab-panel="general"<?php echo $showGeneral ? '' : ' hidden'; ?>>
				<div class="rk-sitemap-panel-head">
					<div class="rk-sitemap-panel-head-text">
						<h3 class="rk-sitemap-panel-title"><?php echo esc_html__( 'General Settings', 'rankkernel' ); ?></h3>
						<p class="rk-sitemap-panel-desc"><?php echo esc_html__( 'Core XML generator parameters and global sitemap configuration.', 'rankkernel' ); ?></p>
					</div>
				</div>
				<div class="rk-sitemap-panel-body">
					<?php
					/*
					 * The sitemap index callout. The URL chip is the real link
					 * the page has always rendered for the index. The design's
					 * "Submit to Google" button has no backing feature here, so
					 * it renders disabled rather than pretending to submit.
					 */
					?>
					<div class="rk-sitemap-callout">
						<div class="rk-sitemap-callout-main">
							<div class="rk-sitemap-callout-line">
								<span class="rk-sitemap-callout-icon" aria-hidden="true"><span class="rk-icon" aria-hidden="true">link</span></span>
								<span class="rk-sitemap-callout-label"><?php echo esc_html__( 'Sitemap Index URL:', 'rankkernel' ); ?></span>
								<a class="rk-sitemap-callout-url" href="<?php echo esc_url( $indexUrl ); ?>"><?php echo esc_html( $indexUrl ); ?></a>
							</div>
							<p class="rk-sitemap-callout-note"><?php echo esc_html__( 'Your primary index sitemap. Submit this exact URL to Google Search Console and Bing Webmaster Tools.', 'rankkernel' ); ?></p>
						</div>
						<button type="button" class="rk-ui-btn rk-ui-btn-secondary rk-sitemap-callout-action" disabled aria-disabled="true" title="<?php echo esc_attr__( 'Not available in RankKernel.', 'rankkernel' ); ?>"><span><?php echo esc_html__( 'Submit to Google', 'rankkernel' ); ?></span><span class="rk-icon" aria-hidden="true">arrow_right</span></button>
					</div>

					<div class="rk-sitemap-rows">
						<div class="rk-sitemap-row">
							<div class="rk-sitemap-row-info">
								<label class="rk-sitemap-row-title" for="rk-items-per-page"><?php echo esc_html__( 'Links per sitemap', 'rankkernel' ); ?></label>
								<p class="rk-sitemap-row-note"><?php echo esc_html__( 'Pagination threshold', 'rankkernel' ); ?></p>
							</div>
							<div class="rk-sitemap-row-control">
								<div class="rk-sitemap-input-row">
									<input type="number" id="rk-items-per-page" name="items_per_page" value="<?php echo esc_attr( $itemsPerPage ); ?>" class="rk-sitemap-input rk-sitemap-input-small" min="1" max="50000" />
									<span class="rk-sitemap-input-suffix"><?php echo esc_html__( 'entries per file', 'rankkernel' ); ?></span>
								</div>
								<p class="rk-sitemap-row-hint"><?php echo esc_html__( 'Maximum number of links on each sitemap page. Default is 1,000. Maximum allowed by search protocol is 50,000.', 'rankkernel' ); ?></p>
							</div>
						</div>
						<?php foreach ( $generalRows as $generalRow ) : ?>
							<?php if ( 'checkbox' === $generalRow['kind'] ) : ?>
								<?php
								/*
								 * The row title names the group, so it carries a real
								 * id and the row becomes a labelled group rather than
								 * a bare div. The sentence beside the switch is the
								 * real label, and it names the box together with the
								 * row title, so the post type or setting stays part of
								 * the name and the visible words are still spoken.
								 */
								$rkGeneralTitleId = 'rk-sitemap-general-' . $generalRow['name'] . '-title';
								$rkGeneralHintId  = 'rk-sitemap-general-' . $generalRow['name'] . '-hint';
								?>
								<div class="rk-sitemap-row" role="group" aria-labelledby="<?php echo esc_attr( $rkGeneralTitleId ); ?>">
									<div class="rk-sitemap-row-info">
										<span class="rk-sitemap-row-title" id="<?php echo esc_attr( $rkGeneralTitleId ); ?>"><?php echo esc_html( $generalRow['title'] ); ?></span>
										<?php if ( isset( $rkGeneralRowNotes[ $generalRow['name'] ] ) ) : ?>
											<p class="rk-sitemap-row-note"><?php echo esc_html( $rkGeneralRowNotes[ $generalRow['name'] ] ); ?></p>
										<?php endif; ?>
									</div>
									<div class="rk-sitemap-row-control">
										<div class="rk-sitemap-toggle">
											<label class="rk-ui-switch">
												<input type="checkbox" name="<?php echo esc_attr( $generalRow['name'] ); ?>" value="1" <?php echo checked( $generalRow['checked'], true, false ); ?> aria-labelledby="<?php echo esc_attr( $rkGeneralTitleId . ' ' . $rkGeneralHintId ); ?>" />
												<span class="rk-ui-switch-track" aria-hidden="true"><span class="rk-ui-switch-knob"></span></span>
											</label>
											<span class="rk-sitemap-toggle-text" id="<?php echo esc_attr( $rkGeneralHintId ); ?>"><?php echo esc_html( $generalRow['hint'] ); ?></span>
										</div>
									</div>
								</div>
							<?php else : ?>
								<div class="rk-sitemap-row">
									<div class="rk-sitemap-row-info">
										<label class="rk-sitemap-row-title" for="rk-<?php echo esc_attr( $generalRow['name'] ); ?>"><?php echo esc_html( $generalRow['title'] ); ?></label>
										<?php if ( isset( $rkGeneralRowNotes[ $generalRow['name'] ] ) ) : ?>
											<p class="rk-sitemap-row-note"><?php echo esc_html( $rkGeneralRowNotes[ $generalRow['name'] ] ); ?></p>
										<?php endif; ?>
									</div>
									<div class="rk-sitemap-row-control">
										<input type="text" id="rk-<?php echo esc_attr( $generalRow['name'] ); ?>" name="<?php echo esc_attr( $generalRow['name'] ); ?>" value="<?php echo esc_attr( $generalRow['value'] ); ?>" class="rk-sitemap-input" />
										<p class="rk-sitemap-row-hint"><?php echo esc_html( $generalRow['hint'] ); ?></p>
									</div>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="rk-sitemap-panel-footer">
					<p class="rk-sitemap-footer-status"><span class="rk-sitemap-footer-dot" aria-hidden="true"></span><?php echo esc_html__( 'Sitemap cache clears automatically on save.', 'rankkernel' ); ?></p>
					<div class="rk-sitemap-footer-actions">
						<button type="submit" name="rankkernel_sitemap_save" value="<?php echo esc_attr__( 'Save Sitemap Settings', 'rankkernel' ); ?>" class="rk-ui-btn rk-ui-btn-primary"><span class="rk-icon" aria-hidden="true">download</span><?php echo esc_html__( 'Save Sitemap Settings', 'rankkernel' ); ?></button>
					</div>
				</div>
			</div>
		<?php
		$rkPostTypeCount      = count( $postTypeRows );
		$rkPostTypeCountLabel = 1 === $rkPostTypeCount
			? __( '1 post type registered', 'rankkernel' )
			: sprintf(
				/* translators: %d: number of registered public post types. */
				__( '%d post types registered', 'rankkernel' ),
				$rkPostTypeCount
			);
		?>
		<div class="rk-ui-card rk-sitemap-panel" id="rk-sitemap-panel-post-types" data-rk-tab-panel="post-types"<?php echo $showPostTypes ? '' : ' hidden'; ?>>
				<div class="rk-sitemap-panel-head">
					<div class="rk-sitemap-panel-head-text">
						<h3 class="rk-sitemap-panel-title"><?php echo esc_html__( 'Post Types', 'rankkernel' ); ?></h3>
						<p class="rk-sitemap-panel-desc"><?php echo esc_html__( 'Choose which post types are included in the XML sitemap. Each post type gets its own sitemap file.', 'rankkernel' ); ?></p>
					</div>
					<span class="rk-sitemap-meta-chip">
						<?php echo esc_html( $rkPostTypeCountLabel ); ?>
					</span>
				</div>
				<div class="rk-sitemap-panel-body">
					<div class="rk-sitemap-list">
						<?php foreach ( $postTypeRows as $postTypeRow ) : ?>
							<?php
							/*
							 * Each row names its own switch and keeps the visible
							 * sentence in the state pill, so the switch carries an
							 * explicit accessible name that includes the post type.
							 * The row title keeps the id the shared label wiring
							 * has always used.
							 */
							$rkPostTypeTitleId = 'rk-sitemap-post-type-' . $postTypeRow['key'] . '-title';
							$rkPostTypeLabelId = 'rk-sitemap-post-type-' . $postTypeRow['key'] . '-label';
							?>
							<div class="rk-sitemap-list-row<?php echo $postTypeRow['enabled'] ? '' : ' is-excluded'; ?>">
								<div class="rk-sitemap-list-main">
									<div class="rk-sitemap-list-name">
										<span class="rk-sitemap-list-title" id="<?php echo esc_attr( $rkPostTypeTitleId ); ?>"><?php echo esc_html( $postTypeRow['label'] ); ?></span>
										<span class="rk-sitemap-slug-chip"><?php echo esc_html( $postTypeRow['slug'] ); ?></span>
									</div>
									<p class="rk-sitemap-list-url">
										<span class="rk-sitemap-list-url-label"><?php echo esc_html__( 'Sitemap:', 'rankkernel' ); ?></span>
										<a href="<?php echo esc_url( $postTypeRow['url'] ); ?>"><?php echo esc_html( $postTypeRow['url'] ); ?><span class="rk-icon" aria-hidden="true">arrow_right</span></a>
									</p>
								</div>
								<div class="rk-sitemap-list-state">
									<?php if ( $postTypeRow['enabled'] ) : ?>
										<span class="rk-ui-pill rk-ui-pill-success"><?php echo esc_html__( 'Included in sitemap', 'rankkernel' ); ?></span>
									<?php else : ?>
										<span class="rk-ui-pill rk-ui-pill-neutral"><?php echo esc_html__( 'Excluded from sitemap', 'rankkernel' ); ?></span>
									<?php endif; ?>
									<label class="rk-ui-switch" id="<?php echo esc_attr( $rkPostTypeLabelId ); ?>">
										<input type="checkbox" name="<?php echo esc_attr( $postTypeRow['key'] ); ?>" value="1" <?php echo checked( $postTypeRow['enabled'], true, false ); ?> aria-label="<?php echo esc_attr( sprintf( /* translators: %s: post type name. */ __( 'Include %s in sitemap', 'rankkernel' ), $postTypeRow['label'] ) ); ?>" />
										<span class="rk-ui-switch-track" aria-hidden="true"><span class="rk-ui-switch-knob"></span></span>
									</label>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="rk-sitemap-panel-footer">
					<p class="rk-sitemap-footer-note"><span class="rk-icon" aria-hidden="true">info</span><?php echo esc_html__( 'Attachments are never included in XML sitemaps to prevent zero-value asset indexing.', 'rankkernel' ); ?></p>
					<div class="rk-sitemap-footer-actions">
						<button type="submit" name="rankkernel_sitemap_save" value="<?php echo esc_attr__( 'Save Post Types Settings', 'rankkernel' ); ?>" class="rk-ui-btn rk-ui-btn-primary"><span class="rk-icon" aria-hidden="true">download</span><?php echo esc_html__( 'Save Post Types Settings', 'rankkernel' ); ?></button>
					</div>
				</div>
			</div>
		<?php
		$rkTaxonomyCount      = count( $taxonomyRows );
		$rkTaxonomyCountLabel = 1 === $rkTaxonomyCount
			? __( '1 taxonomy registered', 'rankkernel' )
			: sprintf(
				/* translators: %d: number of registered public taxonomies. */
				__( '%d taxonomies registered', 'rankkernel' ),
				$rkTaxonomyCount
			);
		?>
		<div class="rk-ui-card rk-sitemap-panel" id="rk-sitemap-panel-taxonomies" data-rk-tab-panel="taxonomies"<?php echo $showTaxonomies ? '' : ' hidden'; ?>>
				<div class="rk-sitemap-panel-head">
					<div class="rk-sitemap-panel-head-text">
						<h3 class="rk-sitemap-panel-title"><?php echo esc_html__( 'Taxonomies', 'rankkernel' ); ?></h3>
						<p class="rk-sitemap-panel-desc"><?php echo esc_html__( 'Choose which taxonomies are included. Each taxonomy gets its own sitemap file.', 'rankkernel' ); ?></p>
					</div>
					<span class="rk-sitemap-meta-chip">
						<?php echo esc_html( $rkTaxonomyCountLabel ); ?>
					</span>
				</div>
				<div class="rk-sitemap-panel-body">
					<div class="rk-sitemap-list">
						<?php foreach ( $taxonomyRows as $taxonomyRow ) : ?>
							<?php
							/*
							 * The same reasoning as the post type rows above: the
							 * title id and the label id together name each switch
							 * after the taxonomy it belongs to.
							 */
							$rkTaxonomyTitleId = 'rk-sitemap-taxonomy-' . $taxonomyRow['key'] . '-title';
							$rkTaxonomyLabelId = 'rk-sitemap-taxonomy-' . $taxonomyRow['key'] . '-label';
							?>
							<div class="rk-sitemap-list-row<?php echo $taxonomyRow['enabled'] ? '' : ' is-excluded'; ?>">
								<div class="rk-sitemap-list-main">
									<div class="rk-sitemap-list-name">
										<span class="rk-sitemap-list-title" id="<?php echo esc_attr( $rkTaxonomyTitleId ); ?>"><?php echo esc_html( $taxonomyRow['label'] ); ?></span>
										<span class="rk-sitemap-slug-chip"><?php echo esc_html( $taxonomyRow['slug'] ); ?></span>
									</div>
									<p class="rk-sitemap-list-url">
										<span class="rk-sitemap-list-url-label"><?php echo esc_html__( 'Sitemap:', 'rankkernel' ); ?></span>
										<a href="<?php echo esc_url( $taxonomyRow['url'] ); ?>"><?php echo esc_html( $taxonomyRow['url'] ); ?><span class="rk-icon" aria-hidden="true">arrow_right</span></a>
									</p>
								</div>
								<div class="rk-sitemap-list-state">
									<?php if ( $taxonomyRow['enabled'] ) : ?>
										<span class="rk-ui-pill rk-ui-pill-success"><?php echo esc_html__( 'Included in sitemap', 'rankkernel' ); ?></span>
									<?php else : ?>
										<span class="rk-ui-pill rk-ui-pill-neutral"><?php echo esc_html__( 'Excluded from sitemap', 'rankkernel' ); ?></span>
									<?php endif; ?>
									<label class="rk-ui-switch" id="<?php echo esc_attr( $rkTaxonomyLabelId ); ?>">
										<input type="checkbox" name="<?php echo esc_attr( $taxonomyRow['key'] ); ?>" value="1" <?php echo checked( $taxonomyRow['enabled'], true, false ); ?> aria-label="<?php echo esc_attr( sprintf( /* translators: %s: taxonomy name. */ __( 'Include %s in sitemap', 'rankkernel' ), $taxonomyRow['label'] ) ); ?>" />
										<span class="rk-ui-switch-track" aria-hidden="true"><span class="rk-ui-switch-knob"></span></span>
									</label>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="rk-sitemap-panel-footer">
					<p class="rk-sitemap-footer-note"><span class="rk-icon" aria-hidden="true">info</span><?php echo esc_html__( 'Empty terms are listed only when the general include empty terms setting is on.', 'rankkernel' ); ?></p>
					<div class="rk-sitemap-footer-actions">
						<button type="submit" name="rankkernel_sitemap_save" value="<?php echo esc_attr__( 'Save Taxonomy Settings', 'rankkernel' ); ?>" class="rk-ui-btn rk-ui-btn-primary"><span class="rk-icon" aria-hidden="true">download</span><?php echo esc_html__( 'Save Taxonomy Settings', 'rankkernel' ); ?></button>
					</div>
				</div>
			</div>
		<div class="rk-ui-card rk-sitemap-panel" id="rk-sitemap-panel-authors" data-rk-tab-panel="authors"<?php echo $showAuthors ? '' : ' hidden'; ?>>
				<div class="rk-sitemap-panel-head">
					<div class="rk-sitemap-panel-head-text">
						<h3 class="rk-sitemap-panel-title"><?php echo esc_html__( 'Authors', 'rankkernel' ); ?></h3>
						<p class="rk-sitemap-panel-desc"><?php echo esc_html__( 'Control whether author archive pages appear in the sitemap and which users are excluded.', 'rankkernel' ); ?></p>
					</div>
				</div>
				<div class="rk-sitemap-panel-body">
					<div class="rk-sitemap-rows">
						<?php foreach ( $authorsRows as $authorsRow ) : ?>
							<?php
							/*
							 * As on the General tab: the title names the group
							 * and the sentence is already a real label on every
							 * row, so the box is named by the title and the
							 * sentence together and only the group needs the
							 * title id.
							 */
							$rkAuthorsTitleId = 'rk-sitemap-authors-' . $authorsRow['name'] . '-title';
							$rkAuthorsHintId  = 'rk-sitemap-authors-' . $authorsRow['name'] . '-hint';
							?>
							<div class="rk-sitemap-row" role="group" aria-labelledby="<?php echo esc_attr( $rkAuthorsTitleId ); ?>">
								<div class="rk-sitemap-row-info">
									<span class="rk-sitemap-row-title" id="<?php echo esc_attr( $rkAuthorsTitleId ); ?>"><?php echo esc_html( $authorsRow['title'] ); ?></span>
								</div>
								<div class="rk-sitemap-row-control">
									<div class="rk-sitemap-toggle">
										<label class="rk-ui-switch">
											<input type="checkbox" name="<?php echo esc_attr( $authorsRow['name'] ); ?>" value="1" <?php echo checked( $authorsRow['checked'], true, false ); ?> aria-labelledby="<?php echo esc_attr( $rkAuthorsTitleId . ' ' . $rkAuthorsHintId ); ?>" />
											<span class="rk-ui-switch-track" aria-hidden="true"><span class="rk-ui-switch-knob"></span></span>
										</label>
										<span class="rk-sitemap-toggle-text" id="<?php echo esc_attr( $rkAuthorsHintId ); ?>"><?php echo esc_html( $authorsRow['hint'] ); ?></span>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
						<?php
						/*
						 * The title names a set of role boxes rather than one
						 * control, so the row is a labelled group. Each box keeps
						 * its own real label, the role name, so no name is
						 * repeated and the group carries the heading.
						 */
						?>
						<div class="rk-sitemap-row" role="group" aria-labelledby="rk-sitemap-exclude-roles-label">
							<div class="rk-sitemap-row-info">
								<span class="rk-sitemap-row-title" id="rk-sitemap-exclude-roles-label"><?php echo esc_html__( 'Exclude roles', 'rankkernel' ); ?></span>
								<p class="rk-sitemap-row-note"><?php echo esc_html__( 'Role exclusions', 'rankkernel' ); ?></p>
							</div>
							<div class="rk-sitemap-row-control">
								<?php if ( ! $hasEditableRoles ) : ?>
									<p class="rk-sitemap-row-hint"><?php echo esc_html__( 'No editable roles found.', 'rankkernel' ); ?></p>
								<?php else : ?>
									<div class="rk-sitemap-role-grid">
										<?php foreach ( $roleRows as $roleRow ) : ?>
											<label class="rk-sitemap-role"><input type="checkbox" name="authors_exclude_roles[]" value="<?php echo esc_attr( $roleRow['slug'] ); ?>" <?php echo checked( $roleRow['excluded'], true, false ); ?> /> <span><?php echo esc_html( $roleRow['name'] ); ?></span></label>
										<?php endforeach; ?>
									</div>
									<p class="rk-sitemap-row-hint"><?php echo esc_html__( 'Authors with these user roles are filtered out from the sitemap files.', 'rankkernel' ); ?></p>
								<?php endif; ?>
							</div>
						</div>
						<div class="rk-sitemap-row">
							<div class="rk-sitemap-row-info">
								<label class="rk-sitemap-row-title" for="rk-<?php echo esc_attr( $authorsExcludeUsers['name'] ); ?>"><?php echo esc_html( $authorsExcludeUsers['title'] ); ?></label>
								<p class="rk-sitemap-row-note"><?php echo esc_html__( 'Custom ID blacklist', 'rankkernel' ); ?></p>
							</div>
							<div class="rk-sitemap-row-control">
								<input type="text" id="rk-<?php echo esc_attr( $authorsExcludeUsers['name'] ); ?>" name="<?php echo esc_attr( $authorsExcludeUsers['name'] ); ?>" value="<?php echo esc_attr( $authorsExcludeUsers['value'] ); ?>" class="rk-sitemap-input" />
								<p class="rk-sitemap-row-hint"><?php echo esc_html( $authorsExcludeUsers['hint'] ); ?></p>
							</div>
						</div>
					</div>
				</div>
				<div class="rk-sitemap-panel-footer rk-sitemap-panel-footer-end">
					<div class="rk-sitemap-footer-actions">
						<button type="submit" name="rankkernel_sitemap_save" value="<?php echo esc_attr__( 'Save Author Settings', 'rankkernel' ); ?>" class="rk-ui-btn rk-ui-btn-primary"><span class="rk-icon" aria-hidden="true">download</span><?php echo esc_html__( 'Save Author Settings', 'rankkernel' ); ?></button>
					</div>
				</div>
			</div>
	</form>

	<?php /* Section 4: footer line. Static product naming only, no claims. */ ?>
	<footer class="rk-sitemap-page-footer">
		<p class="rk-sitemap-page-footer-text"><?php echo esc_html__( 'RankKernel SEO Suite', 'rankkernel' ); ?> &bull; <?php echo esc_html__( 'XML Sitemap Module', 'rankkernel' ); ?></p>
	</footer>
</div>
