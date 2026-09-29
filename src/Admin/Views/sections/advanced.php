<?php
/**
 * Advanced settings section.
 *
 * Presentation only. SettingsPage prepares every variable used below.
 *
 * The section is the shared card. The uninstall row names a group rather than
 * one control, so its title stays a span carrying a real id and the row
 * becomes a labelled group. The box keeps the real label it already had, the
 * sentence that names what deleting the plugin removes, so it does not become
 * a switch: a switch has no visible text of its own and would drop it.
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
<section id="rk-section-advanced" class="rk-ui-card rk-settings-section" aria-labelledby="rk-section-advanced-title">
	<div class="rk-ui-card-header">
		<h3 class="rk-ui-card-title" id="rk-section-advanced-title"><?php echo esc_html__( 'Advanced', 'rankkernel' ); ?></h3>
	</div>
	<div class="rk-ui-card-body">
		<h4 class="rk-settings-subhead"><?php echo esc_html__( 'Uninstall', 'rankkernel' ); ?></h4>
		<div class="rk-ui-form-row" role="group" aria-labelledby="rk-advanced-purge-label">
			<span class="rk-ui-form-label" id="rk-advanced-purge-label"><?php echo esc_html__( 'Data removal', 'rankkernel' ); ?></span>
			<label><input type="checkbox" name="purge_on_uninstall" value="1" <?php echo checked( $purgeChecked, true, false ); ?> /> <?php echo esc_html__( 'Delete all RankKernel data (options, metadata) when the plugin is deleted.', 'rankkernel' ); ?></label>
		</div>
	</div>
</section>
