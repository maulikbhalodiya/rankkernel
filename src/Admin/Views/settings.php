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
 * @var bool   $settingsSaveFailed   Whether the settings save failed notice renders.
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

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( $settingsUpdated ) :
	?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Settings saved.', 'rankkernel' ); ?></p></div>
	<?php
endif;

if ( $settingsSaveFailed ) :
	?>
	<div class="notice notice-error"><p><?php echo esc_html__( 'Settings could not be saved. Please try again.', 'rankkernel' ); ?></p></div>
	<?php
endif;
?>
<div class="wrap rk-settings-wrap">
	<h1><?php echo esc_html__( 'RankKernel General Settings', 'rankkernel' ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'rankkernel_settings' ); ?>

		<div class="rk-settings">
			<nav class="rk-settings-nav" aria-label="<?php echo esc_attr( __( 'Settings sections', 'rankkernel' ) ); ?>">
				<p class="rk-settings-nav-title"><?php echo esc_html__( 'Settings', 'rankkernel' ); ?></p>
				<ul>
					<?php foreach ( $settingsSections as $section ) : ?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rankkernel-general&section=' . $section['id'] ) ); ?>"<?php echo $section['id'] === $currentSection ? ' class="is-active" aria-current="page"' : ''; ?>><?php echo esc_html( $section['label'] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<div class="rk-settings-body">

				<?php require __DIR__ . '/sections/' . $currentSection . '.php'; ?>

				<?php submit_button( __( 'Save Settings', 'rankkernel' ), 'primary', 'rankkernel_save' ); ?>
			</div>
		</div>
	</form>
</div>
