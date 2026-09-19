<?php
/**
 * Llms settings section.
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
 * @var array<int, string> $consistencyWarnings Robots and llms conflict warnings.
 * @var bool   $htaccessSupported    Whether .htaccess editing is available.
 * @var bool   $htaccessWritable     Whether the file is writable.
 * @var string $htaccessContent      Current .htaccess content.
 * @var string $htaccessPath         Absolute .htaccess path.
 * @var string $htaccessNotice       .htaccess save notice key.
 */

defined( 'ABSPATH' ) || exit;
?>
<section id="rk-section-llms" class="rk-settings-section" aria-labelledby="rk-section-llms-title">
						<h2 id="rk-section-llms-title"><?php echo esc_html__( 'llms.txt', 'rankkernel' ); ?></h2>
						<p class="description"><?php echo esc_html__( 'A curated index for AI tools, served virtually as Markdown with an X-Robots-Tag noindex header. Google Search ignores llms.txt, so this is optional.', 'rankkernel' ); ?></p>

						<?php if ( [] !== $consistencyWarnings ) : ?>
							<div class="rk-banner rk-banner-warning">
								<p class="rk-banner-title"><?php echo esc_html__( 'Consistency check', 'rankkernel' ); ?></p>
								<?php foreach ( $consistencyWarnings as $consistencyWarning ) : ?>
									<p><?php echo esc_html( $consistencyWarning ); ?></p>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<div class="rk-banner rk-banner-info">
							<p class="rk-banner-title"><?php echo esc_html__( 'How to make llms.txt', 'rankkernel' ); ?></p>
							<p><?php echo esc_html__( 'Write a one line summary, then a few sections as Markdown. Each item is a link in the form - [Title](https://example.com/page): one line of context. Keep it short and put your most important pages first, not every post.', 'rankkernel' ); ?></p>
							<p><?php echo esc_html__( 'llms.txt is a community proposal, not a standard. See', 'rankkernel' ); ?> <a href="https://llmstxt.org/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'the official llms.txt site', 'rankkernel' ); ?></a>.</p>
						</div>

						<?php if ( 'written' === $llmsNotice ) : ?>
							<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'A physical llms.txt was written.', 'rankkernel' ); ?></p></div>
						<?php elseif ( 'exists' === $llmsNotice ) : ?>
							<div class="notice notice-warning is-dismissible"><p><?php echo esc_html__( 'A physical llms.txt already exists, so RankKernel did not overwrite it.', 'rankkernel' ); ?></p></div>
						<?php elseif ( 'failed' === $llmsNotice ) : ?>
							<div class="notice notice-error"><p><?php echo esc_html__( 'The physical llms.txt could not be written.', 'rankkernel' ); ?></p></div>
						<?php endif; ?>

						<table class="form-table" role="presentation"><tbody>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Enable', 'rankkernel' ); ?></th>
								<td>
									<label><input type="checkbox" name="rk_llms_enabled" value="1" <?php echo checked( true, $llmsEnabled, false ); ?> /> <?php echo esc_html__( 'Serve the virtual llms.txt route', 'rankkernel' ); ?></label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="rk-llms-summary"><?php echo esc_html__( 'Summary', 'rankkernel' ); ?></label></th>
								<td>
									<textarea id="rk-llms-summary" name="rk_llms_summary" rows="2" cols="60" class="large-text"><?php echo esc_textarea( $llmsSummary ); ?></textarea>
									<p class="description"><?php echo esc_html__( 'Rendered as a blockquote under the site name.', 'rankkernel' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="rk-llms-content"><?php echo esc_html__( 'Sections', 'rankkernel' ); ?></label></th>
								<td>
									<textarea id="rk-llms-content" name="rk_llms_content" rows="10" cols="60" class="large-text code"><?php echo esc_textarea( $llmsContent ); ?></textarea>
									<p class="description"><?php echo esc_html__( 'Use Markdown headings and link lists, for example a ## Company heading, then a line such as - [About](https://example.com/about): one line of context.', 'rankkernel' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Physical file', 'rankkernel' ); ?></th>
								<td>
									<label><input type="checkbox" name="rk_llms_physical" value="1" <?php echo checked( true, $llmsPhysical, false ); ?> /> <?php echo esc_html__( 'Allow writing a physical llms.txt', 'rankkernel' ); ?></label>
									<p class="description"><?php echo esc_html__( 'The virtual route stays the default. Writing never overwrites an existing file.', 'rankkernel' ); ?></p>
									<p class="rk-robots-actions">
										<button type="submit" class="button button-primary" name="rk_llms_save" value="1"><?php echo esc_html__( 'Save', 'rankkernel' ); ?></button>
										<button type="submit" class="button" name="rk_llms_write" value="1"><?php echo esc_html__( 'Write physical llms.txt', 'rankkernel' ); ?></button>
										<button type="submit" class="button" name="rk_llms_reset" value="1" data-rk-confirm="<?php echo esc_attr( __( 'Reset llms.txt to defaults? Your summary and sections will be cleared.', 'rankkernel' ) ); ?>"><?php echo esc_html__( 'Reset', 'rankkernel' ); ?></button>
									</p>
								</td>
							</tr>
						</tbody></table>

						<?php if ( [] !== $llmsValidation['errors'] ) : ?>
							<div class="notice notice-error inline">
								<?php foreach ( $llmsValidation['errors'] as $llmsError ) : ?>
									<p><?php echo esc_html( $llmsError ); ?></p>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<?php if ( [] !== $llmsValidation['warnings'] ) : ?>
							<div class="notice notice-warning inline">
								<?php foreach ( $llmsValidation['warnings'] as $llmsWarning ) : ?>
									<p><?php echo esc_html( $llmsWarning ); ?></p>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<h3><?php echo esc_html__( 'Preview', 'rankkernel' ); ?></h3>
						<pre class="code" style="padding:12px;background:#fff;border:1px solid #c3c4c7;max-height:360px;overflow:auto;"><?php echo esc_html( $llmsPreview ); ?></pre>
					</section>
