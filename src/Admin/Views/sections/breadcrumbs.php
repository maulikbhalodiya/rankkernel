<?php
/**
 * Breadcrumbs settings section.
 *
 * Presentation only. SettingsPage prepares every variable used below.
 *
 * The section is a page scoped card. Rows that name one control keep a real
 * label pointing at that control. Each checkbox row follows the approved
 * sheet: the box, then a bold label that names the option, then the sentence
 * that explains it, which the box points at through aria-describedby. The row
 * title is no longer a separate group heading, because the sheet draws the
 * option name as the box's own label.
 *
 * The separator chooser keeps its real radios, fieldset and names. The sheet
 * draws the choices as pills, so the radio itself is visually replaced by the
 * pill surface while staying in the tab order, and the Custom field keeps the
 * id breadcrumbs-admin.js reads to show and hide it.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var bool   $settingsUpdated      Whether the settings saved notice renders.
 * @var array<int, array{id: string, label: string}> $settingsSections Settings left-nav sections.
 * @var string $currentSection       Active settings section id.
 * @var string $titleTemplate        Title template value.
 * @var string $descriptionTemplate  Description template value.
 * @var string $titleSeparator       Title separator value.
 * @var array<int, array{fieldId: string, key: string, label: string, value: string}> $webmasters Webmaster verification rows.
 * @var bool   $purgeChecked         Whether the data removal checkbox is checked.
 * @var string $breadcrumbSeparator  Stored breadcrumb separator.
 * @var string $homeLabel            Breadcrumb home label.
 * @var array<int, array{name: string, title: string, checked: bool, hint: string}> $appearanceToggles Breadcrumb appearance checkbox rows.
 * @var array<int, array{name: string, title: string, checked: bool, hint: string}> $behaviorToggles Breadcrumb trail behavior checkbox rows.
 * @var array<int, array{id: string, value: string, checked: bool}> $separatorChoices Separator preset radio rows.
 * @var bool   $isCustomSeparator    Whether the stored separator is not a preset.
 * @var array<int, array{rowType: string, title: string, hint?: string, fieldId?: string, field?: string, current?: string, options?: array<int, array{slug: string, label: string}>}> $taxonomyRows Taxonomy preference rows.
 * @var bool   $robotsEnabled        Whether the robots section renders.
 * @var array<int, array{label: string, crawlers: array<int, array{slug: string, label: string, note: string, policy: string}>}> $robotGroups Crawler policy groups.
 * @var string $robotTab             Active robots tab, preview or edit.
 * @var string $robotEffective       Effective robots.txt output.
 * @var string $robotEditValue       Value for the robots editor.
 * @var array{errors: string[], warnings: string[]} $robotValidation Robots validation result.
 * @var bool   $llmsEnabled          Whether llms.txt is on.
 * @var string $llmsSummary          llms.txt summary.
 * @var string $llmsContent          Curated llms.txt content.
 * @var bool   $llmsPhysical         Physical write toggle.
 * @var array{errors: string[], warnings: string[]} $llmsValidation llms validation result.
 * @var string $llmsPreview          llms.txt preview.
 * @var string $llmsNotice           llms physical write notice key.
 * @var bool   $htaccessSupported    Whether .htaccess editing is available.
 * @var bool   $htaccessWritable     Whether the file is writable.
 * @var string $htaccessContent      Current .htaccess content.
 * @var string $htaccessPath         Absolute .htaccess path.
 * @var string $htaccessNotice       .htaccess save notice key.
 */

defined( 'ABSPATH' ) || exit;
?>
<section id="rk-section-breadcrumbs" class="rk-ui-card rk-settings-section" aria-labelledby="rk-section-breadcrumbs-title">
	<div class="rk-settings-section-head rk-settings-section-head--snug">
		<h3 class="rk-settings-section-title" id="rk-section-breadcrumbs-title"><?php echo esc_html__( 'Breadcrumbs', 'rankkernel' ); ?></h3>
	</div>
	<div class="rk-settings-section-body">
		<p class="rk-settings-lead"><?php echo esc_html__( 'Visible trail and breadcrumb schema share one trail. Place it with the block, shortcode, or template tag. Disabling the module in the module list disables breadcrumb integration.', 'rankkernel' ); ?></p>

		<?php
		/*
		 * The two placement idioms sit in one monospace block rather than an
		 * inline sentence, because the approved design shows them as lines of
		 * code a site owner copies. Both labels are existing strings.
		 */
		?>
		<div class="rk-code-block">
			<p class="rk-code-line"><span class="rk-code-label"><?php echo esc_html__( 'Theme template:', 'rankkernel' ); ?></span> <code><?php echo esc_html( "if ( function_exists( 'rankkernel_breadcrumbs' ) ) { rankkernel_breadcrumbs(); }" ); ?></code></p>
			<p class="rk-code-line"><span class="rk-code-label"><?php echo esc_html__( 'Shortcode:', 'rankkernel' ); ?></span> <code><?php echo esc_html( '[rankkernel_breadcrumbs]' ); ?></code></p>
		</div>

		<div class="rk-settings-block">
			<h4 class="rk-settings-subhead"><?php echo esc_html__( 'Appearance', 'rankkernel' ); ?></h4>
			<p class="rk-ui-sub"><?php echo esc_html__( 'How the trail looks.', 'rankkernel' ); ?></p>

			<?php
			/*
			 * The separator row names a set of radios rather than one field, so
			 * the title is a span naming the group. The native fieldset and its
			 * legend are left exactly as they were, because they group the
			 * radios for the form itself and are not a design decision. Each
			 * choice label carries the pill treatment the page sheet defines,
			 * so the row wraps inline and needs no line break between the
			 * choices.
			 */
			?>
			<div class="rk-ui-form-row" role="group" aria-labelledby="rk-breadcrumbs-separator-label">
				<span class="rk-ui-form-label" id="rk-breadcrumbs-separator-label"><?php echo esc_html__( 'Separator', 'rankkernel' ); ?></span>
				<div class="rk-settings-separator">
					<fieldset>
						<legend class="screen-reader-text"><?php echo esc_html__( 'Separator', 'rankkernel' ); ?></legend>
						<?php foreach ( $separatorChoices as $choice ) : ?>
							<label class="rk-settings-pill" for="<?php echo esc_attr( $choice['id'] ); ?>"><input type="radio" id="<?php echo esc_attr( $choice['id'] ); ?>" name="rk_breadcrumbs_separator_choice" value="<?php echo esc_attr( $choice['value'] ); ?>" <?php echo checked( $choice['checked'], true, false ); ?> /> <span class="rk-settings-pill-text"><?php echo esc_html( $choice['value'] ); ?></span></label>
						<?php endforeach; ?>
						<label class="rk-settings-pill rk-settings-pill--custom" for="rk-breadcrumbs-separator-choice-custom"><input type="radio" id="rk-breadcrumbs-separator-choice-custom" name="rk_breadcrumbs_separator_choice" value="custom" <?php echo checked( $isCustomSeparator, true, false ); ?> /> <span class="rk-settings-pill-text"><?php echo esc_html__( 'Custom', 'rankkernel' ); ?></span></label>
						<div id="rk-breadcrumbs-separator-custom-wrap" class="rk-settings-custom-sep">
							<label for="rk-breadcrumbs-separator-custom"><?php echo esc_html__( 'Custom separator', 'rankkernel' ); ?></label>
							<input type="text" id="rk-breadcrumbs-separator-custom" name="rk_breadcrumbs_separator_custom" value="<?php echo esc_attr( $isCustomSeparator ? $breadcrumbSeparator : '' ); ?>" class="rk-settings-input rk-settings-input--narrow" maxlength="10" placeholder="<?php echo esc_attr( __( 'e.g. »', 'rankkernel' ) ); ?>" />
						</div>
					</fieldset>
				</div>
				<p class="rk-ui-hint"><?php echo esc_html__( 'Character shown between crumbs.', 'rankkernel' ); ?></p>
			</div>

			<div class="rk-ui-form-row">
				<label class="rk-ui-form-label" for="rk-breadcrumbs-home-label"><?php echo esc_html__( 'Home label', 'rankkernel' ); ?></label>
				<input type="text" id="rk-breadcrumbs-home-label" name="rk_breadcrumbs_home_label" value="<?php echo esc_attr( $homeLabel ); ?>" class="rk-settings-input rk-settings-input--medium" />
			</div>
		</div>

		<?php
		/*
		 * Each box here carries a visible sentence of its own, so each row
		 * keeps the box, the bold option label and the sentence the sheet
		 * draws. The box points at its sentence through aria-describedby.
		 */
		?>
		<div class="rk-settings-block rk-settings-block--ruled">
			<h4 class="rk-settings-subhead"><?php echo esc_html__( 'Trail behavior', 'rankkernel' ); ?></h4>
			<p class="rk-ui-sub"><?php echo esc_html__( 'Which crumbs are included in the trail.', 'rankkernel' ); ?></p>

			<div class="rk-settings-checks">
				<?php foreach ( $appearanceToggles as $toggle ) : ?>
					<?php $rk_toggle_hint_id = $toggle['name'] . '-hint'; ?>
					<div class="rk-settings-check">
						<input type="checkbox" id="<?php echo esc_attr( $toggle['name'] ); ?>" name="<?php echo esc_attr( $toggle['name'] ); ?>" value="1" <?php echo checked( $toggle['checked'], true, false ); ?> aria-describedby="<?php echo esc_attr( $rk_toggle_hint_id ); ?>" />
						<div class="rk-settings-check-text">
							<label class="rk-settings-check-title" for="<?php echo esc_attr( $toggle['name'] ); ?>"><?php echo esc_html( $toggle['title'] ); ?></label>
							<p class="rk-ui-hint" id="<?php echo esc_attr( $rk_toggle_hint_id ); ?>"><?php echo esc_html( $toggle['hint'] ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
				<?php foreach ( $behaviorToggles as $toggle ) : ?>
					<?php $rk_toggle_hint_id = $toggle['name'] . '-hint'; ?>
					<div class="rk-settings-check">
						<input type="checkbox" id="<?php echo esc_attr( $toggle['name'] ); ?>" name="<?php echo esc_attr( $toggle['name'] ); ?>" value="1" <?php echo checked( $toggle['checked'], true, false ); ?> aria-describedby="<?php echo esc_attr( $rk_toggle_hint_id ); ?>" />
						<div class="rk-settings-check-text">
							<label class="rk-settings-check-title" for="<?php echo esc_attr( $toggle['name'] ); ?>"><?php echo esc_html( $toggle['title'] ); ?></label>
							<p class="rk-ui-hint" id="<?php echo esc_attr( $rk_toggle_hint_id ); ?>"><?php echo esc_html( $toggle['hint'] ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<details id="rk-breadcrumbs-taxonomy-preferences" class="rk-settings-disclosure">
			<summary><span class="rk-settings-disclosure-title"><?php echo esc_html__( 'Taxonomy preferences', 'rankkernel' ); ?></span><span class="rk-icon" aria-hidden="true">expand_more</span></summary>
			<p class="rk-ui-hint"><?php echo esc_html__( 'Chooses which taxonomy supplies the term branch when a post type has several.', 'rankkernel' ); ?></p>
			<?php foreach ( $taxonomyRows as $taxonomyRow ) : ?>
				<?php if ( 'info' === $taxonomyRow['rowType'] ) : ?>
					<?php
					/*
					 * An info row has no control at all, so its title is a plain
					 * span with no group and no invented for attribute.
					 */
					?>
					<div class="rk-settings-tax-row">
						<span class="rk-ui-form-label"><?php echo esc_html( $taxonomyRow['title'] ); ?></span>
						<p class="rk-ui-hint"><?php echo esc_html( $taxonomyRow['hint'] ); ?></p>
					</div>
				<?php else : ?>
					<?php
					/*
					 * A taxonomy row has one control, so its title is a real
					 * label. The select is the shared control, which draws its
					 * own chevron, so the same notation the other screens use
					 * carries over here, held to the sheet's measure.
					 */
					?>
					<div class="rk-settings-tax-row">
						<label class="rk-ui-form-label" for="<?php echo esc_attr( $taxonomyRow['fieldId'] ); ?>"><?php echo esc_html( $taxonomyRow['title'] ); ?></label>
						<div class="rk-ui-select-wrap rk-settings-select-wrap">
							<select id="<?php echo esc_attr( $taxonomyRow['fieldId'] ); ?>" name="<?php echo esc_attr( $taxonomyRow['field'] ); ?>" class="rk-ui-select rk-settings-select">
								<option value=""<?php echo '' === $taxonomyRow['current'] ? ' selected="selected"' : ''; ?>><?php echo esc_html__( 'Default (first taxonomy with terms)', 'rankkernel' ); ?></option>
								<?php foreach ( $taxonomyRow['options'] as $taxonomyOption ) : ?>
									<option value="<?php echo esc_attr( $taxonomyOption['slug'] ); ?>"<?php echo $taxonomyRow['current'] === $taxonomyOption['slug'] ? ' selected="selected"' : ''; ?>><?php echo esc_html( $taxonomyOption['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="rk-icon rk-ui-select-chevron" aria-hidden="true">expand_more</span>
						</div>
						<p class="rk-ui-hint"><?php echo esc_html__( 'Which taxonomy supplies the term branch on single views.', 'rankkernel' ); ?></p>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</details>

		<p class="rk-ui-hint"><?php echo esc_html__( 'Archive, search, and 404 labels follow the trail builder defaults. Custom formats are not configurable in this version.', 'rankkernel' ); ?></p>
	</div>
</section>
