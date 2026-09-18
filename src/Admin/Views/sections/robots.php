<?php
/**
 * Robots settings section.
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
<section id="rk-section-robots" class="rk-settings-section" aria-labelledby="rk-section-robots-title">
						<h2 id="rk-section-robots-title"><?php echo esc_html__( 'Robots.txt', 'rankkernel' ); ?></h2>
						<p class="description"><?php echo esc_html__( 'robots.txt is served virtually from the WordPress filter. No file is ever written. The Sitemap line is managed by the Sitemaps module.', 'rankkernel' ); ?></p>

						<h3><?php echo esc_html__( 'AI crawler policy', 'rankkernel' ); ?></h3>
						<p class="description"><?php echo esc_html__( 'Training crawlers, AI search crawlers and user triggered fetchers are separate. Blocking a training crawler does not remove you from search. Blocking an AI search crawler removes you from that assistant results.', 'rankkernel' ); ?></p>

						<div class="rk-crawlers">
							<?php foreach ( $robotGroups as $robotGroup ) : ?>
								<div class="rk-crawler-group">
									<h4 class="rk-crawler-group-title"><?php echo esc_html( $robotGroup['label'] ); ?></h4>
									<div class="rk-crawler-cards">
										<?php foreach ( $robotGroup['crawlers'] as $robotCrawler ) : ?>
											<div class="rk-crawler-card">
												<label for="rk-robots-<?php echo esc_attr( $robotCrawler['slug'] ); ?>"><?php echo esc_html( $robotCrawler['label'] ); ?></label>
												<p class="description"><?php echo esc_html( $robotCrawler['note'] ); ?></p>
												<select id="rk-robots-<?php echo esc_attr( $robotCrawler['slug'] ); ?>" name="rk_robots_policy[<?php echo esc_attr( $robotCrawler['slug'] ); ?>]">
													<option value="allow"<?php echo 'allow' === $robotCrawler['policy'] ? ' selected="selected"' : ''; ?>><?php echo esc_html__( 'Allow', 'rankkernel' ); ?></option>
													<option value="block"<?php echo 'block' === $robotCrawler['policy'] ? ' selected="selected"' : ''; ?>><?php echo esc_html__( 'Block', 'rankkernel' ); ?></option>
													<option value="custom"<?php echo 'custom' === $robotCrawler['policy'] ? ' selected="selected"' : ''; ?>><?php echo esc_html__( 'Custom', 'rankkernel' ); ?></option>
												</select>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<h3><?php echo esc_html__( 'robots.txt', 'rankkernel' ); ?></h3>
						<div class="rk-robots-tabs">
							<a class="rk-tab<?php echo 'preview' === $robotTab ? ' is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=rankkernel-general&section=robots&robots_tab=preview' ) ); ?>"><?php echo esc_html__( 'Preview', 'rankkernel' ); ?></a>
							<a class="rk-tab<?php echo 'edit' === $robotTab ? ' is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=rankkernel-general&section=robots&robots_tab=edit' ) ); ?>"><?php echo esc_html__( 'Edit', 'rankkernel' ); ?></a>
						</div>

						<?php if ( 'edit' === $robotTab ) : ?>
							<p class="description"><?php echo esc_html__( 'Edit the whole document. One directive per line. Allowed: User-agent, Allow, Disallow, Sitemap, Crawl-delay. Comments start with #.', 'rankkernel' ); ?></p>
							<textarea id="rk-robots-override" name="rk_robots_override" rows="16" cols="70" class="large-text code rk-code-editor"><?php echo esc_textarea( $robotEditValue ); ?></textarea>

							<?php if ( [] !== $robotValidation['errors'] ) : ?>
								<div class="notice notice-error inline">
									<?php foreach ( $robotValidation['errors'] as $robotError ) : ?>
										<p><?php echo esc_html( $robotError ); ?></p>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<?php if ( [] !== $robotValidation['warnings'] ) : ?>
								<div class="notice notice-warning inline">
									<?php foreach ( $robotValidation['warnings'] as $robotWarning ) : ?>
										<p><?php echo esc_html( $robotWarning ); ?></p>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<p class="rk-robots-actions">
								<button type="submit" class="button button-primary" name="rk_robots_save" value="1"><?php echo esc_html__( 'Save', 'rankkernel' ); ?></button>
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rankkernel-general&section=robots&robots_tab=preview' ) ); ?>"><?php echo esc_html__( 'Cancel', 'rankkernel' ); ?></a>
								<button type="submit" class="button" name="rk_robots_reset" value="1" data-rk-confirm="<?php echo esc_attr( __( 'Reset robots.txt to the generated version? Your custom edits will be removed.', 'rankkernel' ) ); ?>"><?php echo esc_html__( 'Reset', 'rankkernel' ); ?></button>
							</p>
						<?php else : ?>
							<pre class="rk-preview"><?php echo esc_html( $robotEffective ); ?></pre>
							<p class="rk-robots-actions">
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rankkernel-general&section=robots&robots_tab=edit' ) ); ?>"><?php echo esc_html__( 'Edit robots.txt', 'rankkernel' ); ?></a>
								<button type="submit" class="button" name="rk_robots_reset" value="1" data-rk-confirm="<?php echo esc_attr( __( 'Reset robots.txt to the generated version? Your custom edits will be removed.', 'rankkernel' ) ); ?>"><?php echo esc_html__( 'Reset', 'rankkernel' ); ?></button>
							</p>
						<?php endif; ?>
					</section>
