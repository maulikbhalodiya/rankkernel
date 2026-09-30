<?php
/**
 * General Settings view.
 *
 * Presentation only. SettingsPage prepares every variable used below, and owns
 * capability checks, nonce verification, request handling, validation and
 * redirects.
 *
 * The markup adopts the shared rk-ui component layer for the surfaces it can
 * carry (cards, page title, notices, buttons, select wrappers, pills, icons)
 * and keeps this screen's own classes under rk-settings-wrap for the two
 * column shell, the left rail, the section cards and the page scoped field
 * notation the approved sheet defines. The approved sheet draws 14px sentence
 * case labels and a 13px helper notation, where the shared layer offers a
 * 12px uppercase micro label, so the page stylesheet retargets those two
 * shared notations inside its own scope rather than restating them per field.
 *
 * Three hooks the section loader in assets/js/settings-admin.js needs are load
 * bearing and must not be renamed: the rk-settings shell it walks into, the
 * rk-settings-body column it swaps the fetched section into, and the
 * rk-settings-nav a.is-active link it reads to resolve the section to save.
 * The is-active modifier is the loader's own state class, so it stays on the
 * current rail link even though the shared tab strip calls the same idea
 * is-current.
 *
 * The rail stays a nav and is deliberately not the shared segmented rk-ui-tabs
 * control. The rail selects which section of one form is on screen, the tab
 * strip is a horizontal filter over a single view, and the loader reads the
 * active rail link rather than reading a tab, so the two are not
 * interchangeable.
 *
 * The notice row lives inside the page header card, which is where the
 * approved sheet draws the two state examples. At most one of the two flags
 * is set on a real request, so the grid stretches a lone notice to the full
 * measure rather than leaving a half width banner with an empty column next
 * to it.
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
?>
<div class="wrap rk-settings-wrap rk-ui">

	<?php
	/*
	 * WordPress injects third party plugin notices directly into .wrap before
	 * our rendered content, so we output a screen-reader heading here for the
	 * h1 it expects, then carry the visual title in the page header card.
	 */
	?>
	<h1 class="screen-reader-text"><?php echo esc_html__( 'RankKernel General Settings', 'rankkernel' ); ?></h1>

	<?php /* Section 1: page header card, with the notice row inside it. No actions block, because the save control belongs to the form below it. */ ?>
	<header class="rk-ui-card rk-settings-header">
		<h2 class="rk-ui-page-title"><?php echo esc_html__( 'RankKernel General Settings', 'rankkernel' ); ?></h2>
		<p class="rk-ui-sub"><?php echo esc_html__( 'Manage the templates, verification codes, social defaults and crawler rules RankKernel applies across this site.', 'rankkernel' ); ?></p>

		<?php
		/*
		 * Section 2: notice row. Both notices sit inside the page header the
		 * way the approved sheet draws them, and neither carries a dismiss
		 * button because this screen ships no JavaScript that wires one.
		 */
		?>
		<?php if ( $settingsUpdated || $settingsSaveFailed ) : ?>
			<div class="rk-settings-notices">
				<?php if ( $settingsUpdated ) : ?>
					<div class="rk-ui-notice rk-ui-notice-success" role="status">
						<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">check_circle</span>
						<p class="rk-ui-notice-text"><?php echo esc_html__( 'Settings saved.', 'rankkernel' ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( $settingsSaveFailed ) : ?>
					<div class="rk-ui-notice rk-ui-notice-error" role="alert">
						<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">error</span>
						<p class="rk-ui-notice-text"><?php echo esc_html__( 'Settings could not be saved. Please try again.', 'rankkernel' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</header>

	<form method="post" action="">
		<?php wp_nonce_field( 'rankkernel_settings' ); ?>

		<?php
		/*
		 * Section 3: the two column shell. The class on the nav is the hook
		 * the loader matches, and SettingsPageTest pins that class attribute
		 * exactly, so the rail keeps its own page scoped surface rather than
		 * adopting the shared card class. The rail is a sticky nav over
		 * sections of one form rather than a scrolling panel, so its surface
		 * is genuinely this screen's own.
		 */
		?>
		<div class="rk-settings">
			<nav class="rk-settings-nav" aria-label="<?php echo esc_attr( __( 'Settings sections', 'rankkernel' ) ); ?>">
				<p class="rk-settings-nav-title"><span class="rk-icon" aria-hidden="true">settings</span><?php echo esc_html__( 'Settings', 'rankkernel' ); ?></p>
				<ul>
					<?php foreach ( $settingsSections as $section ) : ?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rankkernel-general&section=' . $section['id'] ) ); ?>"<?php echo $section['id'] === $currentSection ? ' class="is-active" aria-current="page"' : ''; ?>><?php echo esc_html( $section['label'] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<?php
			/*
			 * The required section file loads here, and the save control
			 * follows it. The partial request path in SettingsPage renders the
			 * same pair, so the save control is styled as its own card from
			 * this stylesheet rather than being wrapped in extra markup that
			 * the loader swap would drop.
			 */
			?>
			<div class="rk-settings-body">

				<?php require __DIR__ . '/sections/' . $currentSection . '.php'; ?>

				<?php submit_button( __( 'Save Settings', 'rankkernel' ), 'primary', 'rankkernel_save' ); ?>
			</div>
		</div>
	</form>
</div>
