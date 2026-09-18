<?php
/**
 * General settings section.
 *
 * Presentation only. SettingsPage prepares every variable used below.
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
<section id="rk-section-general" class="rk-settings-section" aria-labelledby="rk-section-general-title">
					<h2 id="rk-section-general-title"><?php echo esc_html__( 'General', 'rankkernel' ); ?></h2>
					<h3><?php echo esc_html__( 'Title and description templates', 'rankkernel' ); ?></h3>
					<table class="form-table" role="presentation"><tbody>
						<tr>
							<th scope="row"><label for="rk-title-template"><?php echo esc_html__( 'Title template', 'rankkernel' ); ?></label></th>
							<td>
								<input type="text" id="rk-title-template" name="title_template" value="<?php echo esc_attr( $titleTemplate ); ?>" class="regular-text" />
								<p class="description"><?php echo esc_html__( 'Available tokens: %%title%%, %%sitename%%, %%sep%%, %%excerpt%%, %%date%%, %%author%%, %%category%%, %%page%%, %%currentdate%%', 'rankkernel' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="rk-desc-template"><?php echo esc_html__( 'Description template', 'rankkernel' ); ?></label></th>
							<td>
								<input type="text" id="rk-desc-template" name="description_template" value="<?php echo esc_attr( $descriptionTemplate ); ?>" class="regular-text" />
								<p class="description"><?php echo esc_html__( 'Available tokens: %%title%%, %%sitename%%, %%sep%%, %%excerpt%%, %%date%%, %%author%%, %%category%%, %%page%%, %%currentdate%%', 'rankkernel' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="rk-separator"><?php echo esc_html__( 'Separator', 'rankkernel' ); ?></label></th>
							<td>
								<input type="text" id="rk-separator" name="separator" value="<?php echo esc_attr( $titleSeparator ); ?>" class="regular-text" maxlength="10" />
							</td>
						</tr>
					</tbody></table>
				</section>
