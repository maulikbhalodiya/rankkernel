<?php
/**
 * General Settings view.
 *
 * Presentation only. SettingsPage prepares every variable used below, and owns
 * capability checks, nonce verification, request handling, validation and
 * redirects.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var bool   $settingsUpdated      Whether the settings saved notice renders.
 * @var array<int, array{id: string, label: string}> $settingsSections Settings left-nav sections.
 * @var array<int, array{id: string, label: string, enabled: bool}> $modules Module toggle rows.
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
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( $settingsUpdated ) :
	?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Settings saved.', 'rankkernel' ); ?></p></div>
	<?php
endif;
?>
<div class="wrap rk-settings-wrap">
	<h1><?php echo esc_html__( 'RankKernel General Settings', 'rankkernel' ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'rankkernel_settings' ); ?>

		<div class="rk-settings">
			<nav class="rk-settings-nav" aria-label="<?php echo esc_attr( __( 'Settings sections', 'rankkernel' ) ); ?>">
				<ul>
					<?php foreach ( $settingsSections as $section ) : ?>
						<li><a href="#rk-section-<?php echo esc_attr( $section['id'] ); ?>"><?php echo esc_html( $section['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<div class="rk-settings-body">
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

				<section id="rk-section-breadcrumbs" class="rk-settings-section" aria-labelledby="rk-section-breadcrumbs-title">
					<h2 id="rk-section-breadcrumbs-title"><?php echo esc_html__( 'Breadcrumbs', 'rankkernel' ); ?></h2>
					<p class="description"><?php echo esc_html__( 'Visible trail and breadcrumb schema share one trail. Place it with the block, shortcode, or template tag. Disabling the module in the module list disables breadcrumb integration.', 'rankkernel' ); ?></p>

					<p class="description"><?php echo esc_html__( 'Theme template:', 'rankkernel' ); ?> <code><?php echo esc_html( "if ( function_exists( 'rankkernel_breadcrumbs' ) ) { rankkernel_breadcrumbs(); }" ); ?></code><br /><?php echo esc_html__( 'Shortcode:', 'rankkernel' ); ?> <code><?php echo esc_html( '[rankkernel_breadcrumbs]' ); ?></code></p>

					<h3><?php echo esc_html__( 'Appearance', 'rankkernel' ); ?></h3>
					<p class="description"><?php echo esc_html__( 'How the trail looks.', 'rankkernel' ); ?></p>
					<table class="form-table" role="presentation"><tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Separator', 'rankkernel' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php echo esc_html__( 'Separator', 'rankkernel' ); ?></legend>
									<?php foreach ( $separatorChoices as $choice ) : ?>
										<label for="<?php echo esc_attr( $choice['id'] ); ?>"><input type="radio" id="<?php echo esc_attr( $choice['id'] ); ?>" name="rk_breadcrumbs_separator_choice" value="<?php echo esc_attr( $choice['value'] ); ?>" <?php echo checked( $choice['checked'], true, false ); ?> /> <span><?php echo esc_html( $choice['value'] ); ?></span></label><br />
									<?php endforeach; ?>
									<label for="rk-breadcrumbs-separator-choice-custom"><input type="radio" id="rk-breadcrumbs-separator-choice-custom" name="rk_breadcrumbs_separator_choice" value="custom" <?php echo checked( $isCustomSeparator, true, false ); ?> /> <?php echo esc_html__( 'Custom', 'rankkernel' ); ?></label>
									<div id="rk-breadcrumbs-separator-custom-wrap">
										<label for="rk-breadcrumbs-separator-custom"><?php echo esc_html__( 'Custom separator', 'rankkernel' ); ?></label> <input type="text" id="rk-breadcrumbs-separator-custom" name="rk_breadcrumbs_separator_custom" value="<?php echo esc_attr( $isCustomSeparator ? $breadcrumbSeparator : '' ); ?>" class="small-text" maxlength="10" />
									</div>
								</fieldset>
								<p class="description"><?php echo esc_html__( 'Character shown between crumbs.', 'rankkernel' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="rk-breadcrumbs-home-label"><?php echo esc_html__( 'Home label', 'rankkernel' ); ?></label></th>
							<td>
								<input type="text" id="rk-breadcrumbs-home-label" name="rk_breadcrumbs_home_label" value="<?php echo esc_attr( $homeLabel ); ?>" class="regular-text" />
							</td>
						</tr>
						<?php foreach ( $appearanceToggles as $toggle ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $toggle['title'] ); ?></th>
								<td>
									<label><input type="checkbox" name="<?php echo esc_attr( $toggle['name'] ); ?>" value="1" <?php echo checked( $toggle['checked'], true, false ); ?> /> <?php echo esc_html( $toggle['hint'] ); ?></label>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody></table>

					<h3><?php echo esc_html__( 'Trail behavior', 'rankkernel' ); ?></h3>
					<p class="description"><?php echo esc_html__( 'Which crumbs are included in the trail.', 'rankkernel' ); ?></p>
					<table class="form-table" role="presentation"><tbody>
						<?php foreach ( $behaviorToggles as $toggle ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $toggle['title'] ); ?></th>
								<td>
									<label><input type="checkbox" name="<?php echo esc_attr( $toggle['name'] ); ?>" value="1" <?php echo checked( $toggle['checked'], true, false ); ?> /> <?php echo esc_html( $toggle['hint'] ); ?></label>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody></table>

					<details id="rk-breadcrumbs-taxonomy-preferences">
						<summary><?php echo esc_html__( 'Taxonomy preferences', 'rankkernel' ); ?></summary>
						<p class="description"><?php echo esc_html__( 'Chooses which taxonomy supplies the term branch when a post type has several.', 'rankkernel' ); ?></p>
						<table class="form-table" role="presentation"><tbody>
							<?php foreach ( $taxonomyRows as $taxonomyRow ) : ?>
								<?php if ( 'info' === $taxonomyRow['rowType'] ) : ?>
									<tr>
										<th scope="row"><?php echo esc_html( $taxonomyRow['title'] ); ?></th>
										<td><p class="description"><?php echo esc_html( $taxonomyRow['hint'] ); ?></p></td>
									</tr>
								<?php else : ?>
									<tr>
										<th scope="row"><label for="<?php echo esc_attr( $taxonomyRow['fieldId'] ); ?>"><?php echo esc_html( $taxonomyRow['title'] ); ?></label></th>
										<td>
											<select id="<?php echo esc_attr( $taxonomyRow['fieldId'] ); ?>" name="<?php echo esc_attr( $taxonomyRow['field'] ); ?>">
												<option value=""<?php echo '' === $taxonomyRow['current'] ? ' selected="selected"' : ''; ?>><?php echo esc_html__( 'Default (first taxonomy with terms)', 'rankkernel' ); ?></option>
												<?php foreach ( $taxonomyRow['options'] as $taxonomyOption ) : ?>
													<option value="<?php echo esc_attr( $taxonomyOption['slug'] ); ?>"<?php echo $taxonomyRow['current'] === $taxonomyOption['slug'] ? ' selected="selected"' : ''; ?>><?php echo esc_html( $taxonomyOption['label'] ); ?></option>
												<?php endforeach; ?>
											</select>
											<p class="description"><?php echo esc_html__( 'Which taxonomy supplies the term branch on single views.', 'rankkernel' ); ?></p>
										</td>
									</tr>
								<?php endif; ?>
							<?php endforeach; ?>
						</tbody></table>
					</details>

					<p class="description"><?php echo esc_html__( 'Archive, search, and 404 labels follow the trail builder defaults. Custom formats are not configurable in this version.', 'rankkernel' ); ?></p>
				</section>

				<section id="rk-section-webmaster" class="rk-settings-section" aria-labelledby="rk-section-webmaster-title">
					<h2 id="rk-section-webmaster-title"><?php echo esc_html__( 'Webmaster Tools', 'rankkernel' ); ?></h2>
					<p class="description"><?php echo esc_html__( 'Paste the verification codes from each search engine.', 'rankkernel' ); ?></p>
					<table class="form-table" role="presentation"><tbody>
						<?php foreach ( $webmasters as $webmaster ) : ?>
							<tr>
								<th scope="row"><label for="<?php echo esc_attr( $webmaster['fieldId'] ); ?>"><?php echo esc_html( $webmaster['label'] ); ?></label></th>
								<td>
									<input type="text" id="<?php echo esc_attr( $webmaster['fieldId'] ); ?>" name="<?php echo esc_attr( $webmaster['key'] ); ?>" value="<?php echo esc_attr( $webmaster['value'] ); ?>" class="regular-text" />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody></table>
				</section>

				<section id="rk-section-modules" class="rk-settings-section" aria-labelledby="rk-section-modules-title">
					<h2 id="rk-section-modules-title"><?php echo esc_html__( 'Modules', 'rankkernel' ); ?></h2>
					<p class="description"><?php echo esc_html__( 'Turn optional features on or off. A disabled module adds no hooks and no runtime cost.', 'rankkernel' ); ?></p>
					<table class="form-table" role="presentation"><tbody>
						<?php foreach ( $modules as $module ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $module['label'] ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="rankkernel_modules[]" value="<?php echo esc_attr( $module['id'] ); ?>" <?php echo checked( $module['enabled'], true, false ); ?> /> <?php echo esc_html( $module['label'] ); ?>
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody></table>
				</section>

				<section id="rk-section-advanced" class="rk-settings-section" aria-labelledby="rk-section-advanced-title">
					<h2 id="rk-section-advanced-title"><?php echo esc_html__( 'Advanced', 'rankkernel' ); ?></h2>
					<h3><?php echo esc_html__( 'Uninstall', 'rankkernel' ); ?></h3>
					<table class="form-table" role="presentation"><tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Data removal', 'rankkernel' ); ?></th>
							<td>
								<label><input type="checkbox" name="purge_on_uninstall" value="1" <?php echo checked( $purgeChecked, true, false ); ?> /> <?php echo esc_html__( 'Delete all RankKernel data (options, metadata) when the plugin is deleted.', 'rankkernel' ); ?></label>
							</td>
						</tr>
					</tbody></table>
				</section>

				<?php submit_button( __( 'Save Settings', 'rankkernel' ), 'primary', 'rankkernel_save' ); ?>
			</div>
		</div>
	</form>
</div>
