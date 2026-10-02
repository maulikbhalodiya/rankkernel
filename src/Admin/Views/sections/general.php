<?php
/**
 * General settings section.
 *
 * Presentation only. SettingsPage prepares every variable used below.
 *
 * The section is a page scoped card: an 18px section title over a hairline
 * rule, then the rows. Every row here has exactly one control, except the
 * separator row, which names a radio group and takes the group treatment, the
 * same one the breadcrumbs separator chooser uses. The page stylesheet
 * retargets the shared label notation to the 14px sentence case the approved
 * sheet draws on this screen.
 *
 * Each template row prints its translated token sentence unchanged and adds a
 * chip row under it. The chips come from a hardcoded identifier list, because
 * the tokens are placeholders a site owner copies and never translated text,
 * so the sentence stays whole for translators.
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
 * @var bool   $isCustomTitleSeparator Whether the stored general separator is not a preset.
 * @var array<int, array{id: string, value: string, checked: bool}> $titleSeparatorChoices General separator preset radio rows.
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

/* The tokens the template fields accept, as identifiers rather than prose. */
$templateTokens = [ '%%title%%', '%%sitename%%', '%%sep%%', '%%excerpt%%', '%%date%%', '%%author%%', '%%category%%', '%%page%%', '%%currentdate%%' ];
?>
<section id="rk-section-general" class="rk-ui-card rk-settings-section" aria-labelledby="rk-section-general-title">
	<div class="rk-settings-section-head">
		<h3 class="rk-settings-section-title" id="rk-section-general-title"><?php echo esc_html__( 'General', 'rankkernel' ); ?></h3>
	</div>
	<div class="rk-settings-section-body">
		<h4 class="rk-settings-subhead"><?php echo esc_html__( 'Title and description templates', 'rankkernel' ); ?></h4>
		<div class="rk-ui-form-row">
			<label class="rk-ui-form-label" for="rk-title-template"><?php echo esc_html__( 'Title template', 'rankkernel' ); ?></label>
			<input type="text" id="rk-title-template" name="title_template" value="<?php echo esc_attr( $titleTemplate ); ?>" class="rk-settings-input rk-settings-input--mono" aria-describedby="rk-title-template-hint" />
			<p id="rk-title-template-hint" class="rk-ui-hint"><?php echo esc_html__( 'Available tokens: %%title%%, %%sitename%%, %%sep%%, %%excerpt%%, %%date%%, %%author%%, %%category%%, %%page%%, %%currentdate%%', 'rankkernel' ); ?></p>
			<div class="rk-token-chips">
				<?php foreach ( $templateTokens as $templateToken ) : ?>
					<code class="rk-token-chip"><?php echo esc_html( $templateToken ); ?></code>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="rk-ui-form-row">
			<label class="rk-ui-form-label" for="rk-desc-template"><?php echo esc_html__( 'Description template', 'rankkernel' ); ?></label>
			<input type="text" id="rk-desc-template" name="description_template" value="<?php echo esc_attr( $descriptionTemplate ); ?>" class="rk-settings-input rk-settings-input--mono" aria-describedby="rk-desc-template-hint" />
			<p id="rk-desc-template-hint" class="rk-ui-hint"><?php echo esc_html__( 'Available tokens: %%title%%, %%sitename%%, %%sep%%, %%excerpt%%, %%date%%, %%author%%, %%category%%, %%page%%, %%currentdate%%', 'rankkernel' ); ?></p>
			<div class="rk-token-chips">
				<?php foreach ( $templateTokens as $templateToken ) : ?>
					<code class="rk-token-chip"><?php echo esc_html( $templateToken ); ?></code>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="rk-ui-form-row" role="group" aria-labelledby="rk-general-separator-label">
			<?php
			/*
			 * The sheet puts the character limit on the same line as the row
			 * title. The row names a radio group rather than one control, so
			 * the title is a span and the fieldset below groups the radios for
			 * the form itself.
			 */
			?>
			<div class="rk-settings-row-head">
				<span class="rk-ui-form-label" id="rk-general-separator-label"><?php echo esc_html__( 'Separator', 'rankkernel' ); ?></span>
				<span class="rk-settings-meta"><?php echo esc_html__( '10 characters max', 'rankkernel' ); ?></span>
			</div>
			<div class="rk-settings-separator">
				<fieldset>
					<legend class="screen-reader-text"><?php echo esc_html__( 'Separator', 'rankkernel' ); ?></legend>
					<div class="rk-settings-separator-row">
						<?php foreach ( $titleSeparatorChoices as $titleSeparatorChoice ) : ?>
							<label class="rk-settings-pill" for="<?php echo esc_attr( $titleSeparatorChoice['id'] ); ?>"><input type="radio" id="<?php echo esc_attr( $titleSeparatorChoice['id'] ); ?>" name="rk_general_separator_choice" value="<?php echo esc_attr( $titleSeparatorChoice['value'] ); ?>" <?php echo checked( $titleSeparatorChoice['checked'], true, false ); ?> /> <span class="rk-settings-pill-text"><?php echo esc_html( $titleSeparatorChoice['value'] ); ?></span></label>
						<?php endforeach; ?>
						<label class="rk-settings-pill rk-settings-pill--custom" for="rk-separator-choice-custom"><input type="radio" id="rk-separator-choice-custom" name="rk_general_separator_choice" value="custom" <?php echo checked( $isCustomTitleSeparator, true, false ); ?> aria-controls="rk-general-separator-custom-wrap" /> <span class="rk-settings-pill-text"><?php echo esc_html__( 'Custom', 'rankkernel' ); ?></span></label>
					</div>
					<div id="rk-general-separator-custom-wrap" class="rk-settings-custom-sep">
						<label for="rk-general-separator-custom"><?php echo esc_html__( 'Custom separator', 'rankkernel' ); ?></label>
						<input type="text" id="rk-general-separator-custom" name="rk_general_separator_custom" value="<?php echo esc_attr( $isCustomTitleSeparator ? $titleSeparator : '' ); ?>" class="rk-settings-input rk-settings-input--narrow" maxlength="10" placeholder="<?php echo esc_attr( __( 'e.g. »', 'rankkernel' ) ); ?>" />
					</div>
				</fieldset>
			</div>
		</div>
	</div>
</section>
