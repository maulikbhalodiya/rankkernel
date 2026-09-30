<?php
/**
 * Advanced settings section.
 *
 * Presentation only. SettingsPage prepares every variable used below.
 *
 * The section is a page scoped card. The uninstall row names its own box, so
 * the box is the label target and the sentence that says what deleting the
 * plugin removes is pointed at through aria-describedby. The box keeps the
 * real label it already had and does not become a switch: a switch has no
 * visible text of its own and would drop the sentence.
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
?>
<section id="rk-section-advanced" class="rk-ui-card rk-settings-section" aria-labelledby="rk-section-advanced-title">
	<div class="rk-settings-section-head rk-settings-section-head--snug">
		<h3 class="rk-settings-section-title" id="rk-section-advanced-title"><?php echo esc_html__( 'Advanced', 'rankkernel' ); ?></h3>
		<?php
		/*
		 * The approved sheet flags the whole panel as dangerous in its header,
		 * and the shared pill carries the word so the state never rests on the
		 * colour alone. Destructive is the one string this pass adds; every
		 * other label was already on the screen.
		 */
		?>
		<span class="rk-ui-pill rk-ui-pill-danger rk-settings-badge"><?php echo esc_html__( 'Destructive', 'rankkernel' ); ?></span>
	</div>
	<div class="rk-settings-section-body">
		<h4 class="rk-settings-subhead"><?php echo esc_html__( 'Uninstall', 'rankkernel' ); ?></h4>
		<div class="rk-settings-check">
			<input type="checkbox" id="rk_advanced_purge" name="purge_on_uninstall" value="1" <?php echo checked( $purgeChecked, true, false ); ?> aria-describedby="rk_advanced_purge-hint" />
			<div class="rk-settings-check-text">
				<label class="rk-settings-check-title" for="rk_advanced_purge"><?php echo esc_html__( 'Data removal', 'rankkernel' ); ?></label>
				<p class="rk-ui-hint rk-settings-hint-strong" id="rk_advanced_purge-hint"><?php echo esc_html__( 'Delete all RankKernel data (options, metadata) when the plugin is deleted.', 'rankkernel' ); ?></p>
			</div>
		</div>
	</div>
</section>
