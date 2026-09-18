<?php
/**
 * Htaccess settings section.
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
<section id="rk-section-htaccess" class="rk-settings-section" aria-labelledby="rk-section-htaccess-title">
					<h2 id="rk-section-htaccess-title"><?php echo esc_html__( '.htaccess', 'rankkernel' ); ?></h2>

					<?php if ( 'saved' === $htaccessNotice ) : ?>
						<div class="rk-banner rk-banner-info"><p class="rk-banner-title"><?php echo esc_html__( 'Saved. A backup was written next to the file.', 'rankkernel' ); ?></p></div>
					<?php elseif ( 'unsupported' === $htaccessNotice ) : ?>
						<div class="rk-banner rk-banner-warning"><p class="rk-banner-title"><?php echo esc_html__( 'Not saved.', 'rankkernel' ); ?></p><p><?php echo esc_html__( 'This server does not read .htaccess.', 'rankkernel' ); ?></p></div>
					<?php elseif ( 'failed' === $htaccessNotice ) : ?>
						<div class="rk-banner rk-banner-danger"><p class="rk-banner-title"><?php echo esc_html__( 'Not saved.', 'rankkernel' ); ?></p><p><?php echo esc_html__( 'The file could not be written.', 'rankkernel' ); ?></p></div>
					<?php endif; ?>

					<div class="rk-banner rk-banner-danger">
						<p class="rk-banner-title"><?php echo esc_html__( 'Danger', 'rankkernel' ); ?></p>
						<p><?php echo esc_html__( 'A mistake here can take the whole site down, including wp-admin, with no way back into the dashboard. Have file or server access ready before you save. RankKernel writes a timestamped backup next to the file first.', 'rankkernel' ); ?></p>
					</div>

					<?php if ( ! $htaccessSupported ) : ?>
						<div class="rk-banner rk-banner-warning">
							<p class="rk-banner-title"><?php echo esc_html__( 'Not available on this server', 'rankkernel' ); ?></p>
							<p><?php echo esc_html__( 'Only Apache and LiteSpeed read .htaccess. This server does not look like either, so the editor is disabled here.', 'rankkernel' ); ?></p>
						</div>
					<?php elseif ( ! $htaccessWritable ) : ?>
						<div class="rk-banner rk-banner-warning">
							<p class="rk-banner-title"><?php echo esc_html__( 'File not writable', 'rankkernel' ); ?></p>
							<p><?php echo esc_html__( 'WordPress cannot write the .htaccess file. Ask your host, or edit it over SFTP.', 'rankkernel' ); ?></p>
						</div>
					<?php else : ?>
						<p class="description"><?php echo esc_html( $htaccessPath ); ?></p>
						<textarea id="rk-htaccess-content" name="rk_htaccess_content" rows="18" cols="80" class="large-text code rk-code-editor"><?php echo esc_textarea( $htaccessContent ); ?></textarea>
						<p class="rk-robots-actions">
							<button type="submit" class="button button-primary" name="rk_htaccess_save" value="1" data-rk-confirm="<?php echo esc_attr( __( 'Save .htaccess? A mistake can take the whole site down. A backup is written first.', 'rankkernel' ) ); ?>"><?php echo esc_html__( 'Save .htaccess', 'rankkernel' ); ?></button>
						</p>
					<?php endif; ?>
				</section>
