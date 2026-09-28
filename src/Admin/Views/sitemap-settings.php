<?php
/**
 * Sitemap settings page view.
 *
 * Presentation only. SitemapSettingsPage prepares every variable used below,
 * and owns capability checks, nonce verification, request handling,
 * validation and redirects. The markup adopts the shared rk-ui component
 * layer, so every class here comes from assets/css/rankkernel-ui.css and
 * nothing on this page redefines a component.
 *
 * Every checkbox on this screen sits inside a label that carries visible
 * sentence text, so each one stays a normal checkbox in a real label rather
 * than becoming a switch. A switch has no visible text of its own, so using
 * one here would drop the sentence that names what the box turns on.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var bool   $settingsUpdated       Whether the settings saved notice renders.
 * @var bool   $settingsSaveFailed    Whether the save failed notice renders.
 * @var array<int, array{url: string, class: string, current: bool, label: string}> $tabItems Tab links. The class key holds a core nav-tab string and is deliberately not emitted.
 * @var bool   $showGeneral           Whether the General tab section renders.
 * @var bool   $showPostTypes         Whether the Post Types tab section renders.
 * @var bool   $showTaxonomies        Whether the Taxonomies tab section renders.
 * @var bool   $showAuthors           Whether the Authors tab section renders.
 * @var string $indexUrl              Sitemap index URL.
 * @var string $itemsPerPage          Links per sitemap value.
 * @var array<int, array<string, mixed>> $generalRows General tab rows.
 * @var array<int, array{key: string, label: string, enabled: bool, url: string}> $postTypeRows Post type rows.
 * @var array<int, array{key: string, label: string, enabled: bool, url: string}> $taxonomyRows Taxonomy rows.
 * @var array<int, array{name: string, title: string, checked: bool, hint: string}> $authorsRows Authors checkbox rows.
 * @var bool   $hasEditableRoles      Whether editable roles exist.
 * @var array<int, array{slug: string, name: string, excluded: bool}> $roleRows Editable role rows.
 * @var array{name: string, title: string, value: string, hint: string} $authorsExcludeUsers Exclude users row.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
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

	<?php /* Section 1: page header card. No actions block, because this screen has no header control the controller supplies. */ ?>
	<header class="rk-ui-card rk-ui-page-header">
		<div class="rk-ui-page-header-text">
			<div class="rk-ui-page-header-title-row">
				<h2 class="rk-ui-page-title"><?php echo esc_html__( 'Sitemap Settings', 'rankkernel' ); ?></h2>
			</div>
			<p class="rk-ui-sub"><?php echo esc_html__( 'Choose which post types, taxonomies and author archives are listed in your XML sitemap.', 'rankkernel' ); ?></p>
		</div>
	</header>

	<?php
	/*
	 * Section 2: notice row. It sits directly under the page header, which is
	 * the order the Dashboard and the reference page both use, so the four
	 * screens open the same way. The notices live inside the page root so the
	 * shared notice component applies, and they carry no dismiss button
	 * because this screen ships no JavaScript to wire one.
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
	 * Tabs are links between separate pages, so they stay outside the form.
	 * The controller still builds a core nav-tab class per tab, so the shared
	 * modifier comes from the current flag and the core class is dropped.
	 */
	?>
	<nav class="rk-ui-tabs" aria-label="<?php echo esc_attr( __( 'Sitemap settings tabs', 'rankkernel' ) ); ?>">
		<?php foreach ( $tabItems as $tabItem ) : ?>
			<a href="<?php echo esc_url( $tabItem['url'] ); ?>" class="rk-ui-tab<?php echo ! empty( $tabItem['current'] ) ? ' is-current' : ''; ?>"<?php echo ! empty( $tabItem['current'] ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $tabItem['label'] ); ?></a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="">
		<?php wp_nonce_field( 'rankkernel_sitemap_settings' ); ?>

		<?php if ( $showGeneral ) : ?>
			<div class="rk-ui-card">
				<div class="rk-ui-card-header">
					<h3 class="rk-ui-card-title"><?php echo esc_html__( 'General', 'rankkernel' ); ?></h3>
				</div>
				<div class="rk-ui-card-body">
					<?php
					/*
					 * A static callout rather than a status message, so it
					 * deliberately carries no live region role. The two real
					 * outcome notices above keep role status and role alert.
					 * One rule covers every static callout in the product: no
					 * role, and the info glyph that the shared notice variants
					 * map it to. The Schema defaults card and the 404 redirect
					 * hint use the identical markup.
					 */
					?>
					<div class="rk-ui-notice rk-ui-notice-info">
						<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">info</span>
						<p class="rk-ui-notice-text">
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: sitemap index URL link */
									__( 'Your sitemap index can be found here: %s', 'rankkernel' ),
									'<a href="' . esc_url( $indexUrl ) . '">' . esc_html( $indexUrl ) . '</a>'
								),
								[
									'a' => [
										'href' => [],
									],
								]
							);
							?>
						</p>
					</div>

					<div class="rk-ui-form-row">
						<label class="rk-ui-form-label" for="rk-items-per-page"><?php echo esc_html__( 'Links Per Sitemap', 'rankkernel' ); ?></label>
						<input type="number" id="rk-items-per-page" name="items_per_page" value="<?php echo esc_attr( $itemsPerPage ); ?>" class="small-text" min="1" max="50000" />
						<p class="rk-ui-hint"><?php echo esc_html__( 'Max number of links on each sitemap page.', 'rankkernel' ); ?></p>
					</div>
					<?php foreach ( $generalRows as $generalRow ) : ?>
						<?php if ( 'checkbox' === $generalRow['kind'] ) : ?>
							<?php
							/*
							 * The row title names the group, so it carries a real
							 * id and the row becomes a labelled group rather than
							 * a bare div. The sentence beside the box is the real
							 * label and is already different on every row, so the
							 * box keeps that name untouched and the title reaches
							 * assistive technology as the name of the group.
							 */
							$rkGeneralTitleId = 'rk-sitemap-general-' . $generalRow['name'] . '-title';
							?>
							<div class="rk-ui-form-row" role="group" aria-labelledby="<?php echo esc_attr( $rkGeneralTitleId ); ?>">
								<span class="rk-ui-form-label" id="<?php echo esc_attr( $rkGeneralTitleId ); ?>"><?php echo esc_html( $generalRow['title'] ); ?></span>
								<label><input type="checkbox" name="<?php echo esc_attr( $generalRow['name'] ); ?>" value="1" <?php echo checked( $generalRow['checked'], true, false ); ?> /> <?php echo esc_html( $generalRow['hint'] ); ?></label>
							</div>
						<?php else : ?>
							<div class="rk-ui-form-row">
								<label class="rk-ui-form-label" for="rk-<?php echo esc_attr( $generalRow['name'] ); ?>"><?php echo esc_html( $generalRow['title'] ); ?></label>
								<textarea id="rk-<?php echo esc_attr( $generalRow['name'] ); ?>" name="<?php echo esc_attr( $generalRow['name'] ); ?>" rows="2" cols="40"><?php echo esc_textarea( $generalRow['value'] ); ?></textarea>
								<p class="rk-ui-hint"><?php echo esc_html( $generalRow['hint'] ); ?></p>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php elseif ( $showPostTypes ) : ?>
			<div class="rk-ui-card">
				<div class="rk-ui-card-header">
					<h3 class="rk-ui-card-title"><?php echo esc_html__( 'Post Types', 'rankkernel' ); ?></h3>
				</div>
				<div class="rk-ui-card-body">
					<p class="rk-ui-sub"><?php echo esc_html__( 'Each post type you include gets its own sitemap file.', 'rankkernel' ); ?></p>
					<?php foreach ( $postTypeRows as $postTypeRow ) : ?>
						<?php
						/*
						 * Every row on this tab repeats the same sentence in its
						 * label, so a box named by that sentence alone answers to
						 * the same accessible name as every other box on the
						 * screen. The row title carries a real id, the row is a
						 * labelled group, and the box takes its name from the
						 * title and from its own visible label together, so the
						 * post type is part of the name and the visible words
						 * are still spoken. Nothing on screen changes.
						 */
						$rkPostTypeTitleId = 'rk-sitemap-post-type-' . $postTypeRow['key'] . '-title';
						$rkPostTypeLabelId = 'rk-sitemap-post-type-' . $postTypeRow['key'] . '-label';
						?>
						<div class="rk-ui-form-row" role="group" aria-labelledby="<?php echo esc_attr( $rkPostTypeTitleId ); ?>">
							<span class="rk-ui-form-label" id="<?php echo esc_attr( $rkPostTypeTitleId ); ?>"><?php echo esc_html( $postTypeRow['label'] ); ?></span>
							<label id="<?php echo esc_attr( $rkPostTypeLabelId ); ?>"><input type="checkbox" name="<?php echo esc_attr( $postTypeRow['key'] ); ?>" value="1" <?php echo checked( $postTypeRow['enabled'], true, false ); ?> aria-labelledby="<?php echo esc_attr( $rkPostTypeTitleId . ' ' . $rkPostTypeLabelId ); ?>" /> <?php echo esc_html__( 'Include in Sitemap', 'rankkernel' ); ?></label>
							<p class="rk-ui-hint"><?php echo esc_html__( 'Include archive pages for posts of this type in the XML sitemap.', 'rankkernel' ); ?></p>
							<p class="rk-ui-hint">
								<?php
								echo wp_kses(
									sprintf(
										/* translators: %s: sitemap URL link */
										__( 'Sitemap URL: %s', 'rankkernel' ),
										'<a href="' . esc_url( $postTypeRow['url'] ) . '">' . esc_html( $postTypeRow['url'] ) . '</a>'
									),
									[
										'a' => [
											'href' => [],
										],
									]
								);
								?>
							</p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php elseif ( $showTaxonomies ) : ?>
			<div class="rk-ui-card">
				<div class="rk-ui-card-header">
					<h3 class="rk-ui-card-title"><?php echo esc_html__( 'Taxonomies', 'rankkernel' ); ?></h3>
				</div>
				<div class="rk-ui-card-body">
					<p class="rk-ui-sub"><?php echo esc_html__( 'Each taxonomy you include gets its own sitemap file.', 'rankkernel' ); ?></p>
					<?php foreach ( $taxonomyRows as $taxonomyRow ) : ?>
						<?php
						/*
						 * The same reasoning as the post type rows above: the
						 * label sentence repeats on every row, so the title id
						 * and the label id together name each box after the
						 * taxonomy it belongs to.
						 */
						$rkTaxonomyTitleId = 'rk-sitemap-taxonomy-' . $taxonomyRow['key'] . '-title';
						$rkTaxonomyLabelId = 'rk-sitemap-taxonomy-' . $taxonomyRow['key'] . '-label';
						?>
						<div class="rk-ui-form-row" role="group" aria-labelledby="<?php echo esc_attr( $rkTaxonomyTitleId ); ?>">
							<span class="rk-ui-form-label" id="<?php echo esc_attr( $rkTaxonomyTitleId ); ?>"><?php echo esc_html( $taxonomyRow['label'] ); ?></span>
							<label id="<?php echo esc_attr( $rkTaxonomyLabelId ); ?>"><input type="checkbox" name="<?php echo esc_attr( $taxonomyRow['key'] ); ?>" value="1" <?php echo checked( $taxonomyRow['enabled'], true, false ); ?> aria-labelledby="<?php echo esc_attr( $rkTaxonomyTitleId . ' ' . $rkTaxonomyLabelId ); ?>" /> <?php echo esc_html__( 'Include in Sitemap', 'rankkernel' ); ?></label>
							<p class="rk-ui-hint"><?php echo esc_html__( 'Include archive pages for terms of this taxonomy in the XML sitemap.', 'rankkernel' ); ?></p>
							<p class="rk-ui-hint">
								<?php
								echo wp_kses(
									sprintf(
										/* translators: %s: sitemap URL link */
										__( 'Sitemap URL: %s', 'rankkernel' ),
										'<a href="' . esc_url( $taxonomyRow['url'] ) . '">' . esc_html( $taxonomyRow['url'] ) . '</a>'
									),
									[
										'a' => [
											'href' => [],
										],
									]
								);
								?>
							</p>
						</div>
					<?php endforeach; ?>
					<p class="rk-ui-hint"><?php echo esc_html__( 'Empty terms are listed only when the general include empty terms setting is on.', 'rankkernel' ); ?></p>
				</div>
			</div>
		<?php elseif ( $showAuthors ) : ?>
			<div class="rk-ui-card">
				<div class="rk-ui-card-header">
					<h3 class="rk-ui-card-title"><?php echo esc_html__( 'Authors', 'rankkernel' ); ?></h3>
				</div>
				<div class="rk-ui-card-body">
					<p class="rk-ui-sub"><?php echo esc_html__( 'Decide whether author archives are listed, and which users are left out.', 'rankkernel' ); ?></p>
					<?php foreach ( $authorsRows as $authorsRow ) : ?>
						<?php
						/*
						 * As on the General tab: the title names the group and
						 * the sentence is already a different real label on
						 * every row, so only the group needs the id.
						 */
						$rkAuthorsTitleId = 'rk-sitemap-authors-' . $authorsRow['name'] . '-title';
						?>
						<div class="rk-ui-form-row" role="group" aria-labelledby="<?php echo esc_attr( $rkAuthorsTitleId ); ?>">
							<span class="rk-ui-form-label" id="<?php echo esc_attr( $rkAuthorsTitleId ); ?>"><?php echo esc_html( $authorsRow['title'] ); ?></span>
							<label><input type="checkbox" name="<?php echo esc_attr( $authorsRow['name'] ); ?>" value="1" <?php echo checked( $authorsRow['checked'], true, false ); ?> /> <?php echo esc_html( $authorsRow['hint'] ); ?></label>
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
					<div class="rk-ui-form-row" role="group" aria-labelledby="rk-sitemap-exclude-roles-label">
						<span class="rk-ui-form-label" id="rk-sitemap-exclude-roles-label"><?php echo esc_html__( 'Exclude roles', 'rankkernel' ); ?></span>
						<?php if ( ! $hasEditableRoles ) : ?>
							<p class="rk-ui-hint"><?php echo esc_html__( 'No editable roles found.', 'rankkernel' ); ?></p>
						<?php else : ?>
							<?php foreach ( $roleRows as $roleRow ) : ?>
								<label><input type="checkbox" name="authors_exclude_roles[]" value="<?php echo esc_attr( $roleRow['slug'] ); ?>" <?php echo checked( $roleRow['excluded'], true, false ); ?> /> <?php echo esc_html( $roleRow['name'] ); ?></label><br />
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
					<div class="rk-ui-form-row">
						<label class="rk-ui-form-label" for="rk-<?php echo esc_attr( $authorsExcludeUsers['name'] ); ?>"><?php echo esc_html( $authorsExcludeUsers['title'] ); ?></label>
						<textarea id="rk-<?php echo esc_attr( $authorsExcludeUsers['name'] ); ?>" name="<?php echo esc_attr( $authorsExcludeUsers['name'] ); ?>" rows="2" cols="40"><?php echo esc_textarea( $authorsExcludeUsers['value'] ); ?></textarea>
						<p class="rk-ui-hint"><?php echo esc_html( $authorsExcludeUsers['hint'] ); ?></p>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php submit_button( __( 'Save Sitemap Settings', 'rankkernel' ), 'primary', 'rankkernel_sitemap_save' ); ?>
	</form>
</div>
