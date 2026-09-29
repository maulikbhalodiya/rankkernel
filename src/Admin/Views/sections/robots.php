<?php
/**
 * Robots settings section.
 *
 * Presentation only. SettingsPage prepares every variable used below.
 *
 * The section is the shared card. The crawler policy cards keep their own
 * page scoped layout, because the shared layer has no card grid, while their
 * field label and helper text take the shared notations.
 *
 * The preview and edit strip is a real segmented control but stays on its own
 * class names. settings-admin.js matches .rk-robots-tabs .rk-tab to intercept
 * the click and swap the section, so renaming it to the shared rk-ui-tabs
 * control, which also renames its active modifier from is-active to
 * is-current, would silently break section loading inside the robots section.
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
<section id="rk-section-robots" class="rk-ui-card rk-settings-section" aria-labelledby="rk-section-robots-title">
	<div class="rk-ui-card-header">
		<h3 class="rk-ui-card-title" id="rk-section-robots-title"><?php echo esc_html__( 'Robots.txt', 'rankkernel' ); ?></h3>
	</div>
	<div class="rk-ui-card-body">
		<p class="rk-ui-sub"><?php echo esc_html__( 'robots.txt is served virtually from the WordPress filter. No file is ever written. The Sitemap line is managed by the Sitemaps module.', 'rankkernel' ); ?></p>

		<?php if ( [] !== $consistencyWarnings ) : ?>
			<div class="rk-banner rk-banner-warning">
				<p class="rk-banner-title"><span class="rk-icon" aria-hidden="true">warning</span><?php echo esc_html__( 'Consistency check', 'rankkernel' ); ?></p>
				<?php foreach ( $consistencyWarnings as $consistencyWarning ) : ?>
					<p><?php echo esc_html( $consistencyWarning ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<h4 class="rk-settings-subhead"><?php echo esc_html__( 'AI crawler policy', 'rankkernel' ); ?></h4>
		<p class="rk-ui-sub"><?php echo esc_html__( 'Training crawlers, AI search crawlers and user triggered fetchers are separate. Blocking a training crawler does not remove you from search. Blocking an AI search crawler removes you from that assistant results.', 'rankkernel' ); ?></p>

		<div class="rk-crawlers">
			<?php foreach ( $robotGroups as $robotGroup ) : ?>
				<div class="rk-crawler-group">
					<?php /* A group label inside the subhead, not a heading of its own, so it stays a span. */ ?>
					<span class="rk-crawler-group-title"><?php echo esc_html( $robotGroup['label'] ); ?></span>
					<div class="rk-crawler-cards">
						<?php foreach ( $robotGroup['crawlers'] as $robotCrawler ) : ?>
							<div class="rk-crawler-card">
								<label class="rk-ui-form-label" for="rk-robots-<?php echo esc_attr( $robotCrawler['slug'] ); ?>"><?php echo esc_html( $robotCrawler['label'] ); ?></label>
								<p class="rk-ui-hint"><?php echo esc_html( $robotCrawler['note'] ); ?></p>
								<div class="rk-ui-select-wrap">
									<select id="rk-robots-<?php echo esc_attr( $robotCrawler['slug'] ); ?>" name="rk_robots_policy[<?php echo esc_attr( $robotCrawler['slug'] ); ?>]" class="rk-ui-select">
										<option value="allow"<?php echo 'allow' === $robotCrawler['policy'] ? ' selected="selected"' : ''; ?>><?php echo esc_html__( 'Allow', 'rankkernel' ); ?></option>
										<option value="block"<?php echo 'block' === $robotCrawler['policy'] ? ' selected="selected"' : ''; ?>><?php echo esc_html__( 'Block', 'rankkernel' ); ?></option>
										<option value="custom"<?php echo 'custom' === $robotCrawler['policy'] ? ' selected="selected"' : ''; ?>><?php echo esc_html__( 'Custom', 'rankkernel' ); ?></option>
									</select>
									<span class="rk-icon rk-ui-select-chevron" aria-hidden="true">expand_more</span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<h4 class="rk-settings-subhead"><?php echo esc_html__( 'robots.txt', 'rankkernel' ); ?></h4>
		<?php /* Class names and the is-active modifier here are the loader's contract. See the file docblock. */ ?>
		<nav class="rk-robots-tabs" aria-label="<?php echo esc_attr__( 'Robots.txt tabs', 'rankkernel' ); ?>">
			<a class="rk-tab<?php echo 'preview' === $robotTab ? ' is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=rankkernel-general&section=robots&robots_tab=preview' ) ); ?>"<?php echo 'preview' === $robotTab ? ' aria-current="page"' : ''; ?>><?php echo esc_html__( 'Preview', 'rankkernel' ); ?></a>
			<a class="rk-tab<?php echo 'edit' === $robotTab ? ' is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=rankkernel-general&section=robots&robots_tab=edit' ) ); ?>"<?php echo 'edit' === $robotTab ? ' aria-current="page"' : ''; ?>><?php echo esc_html__( 'Edit', 'rankkernel' ); ?></a>
		</nav>

		<?php if ( 'edit' === $robotTab ) : ?>
			<p class="rk-ui-hint"><?php echo esc_html__( 'Edit the whole document. One directive per line. Allowed: User-agent, Allow, Disallow, Sitemap, Crawl-delay. Comments start with #.', 'rankkernel' ); ?></p>
			<textarea id="rk-robots-override" name="rk_robots_override" rows="16" cols="70" class="large-text code rk-code-editor"><?php echo esc_textarea( $robotEditValue ); ?></textarea>

			<?php
			/*
			 * The action row follows the editor and the banners follow the
			 * action row, which is the order the approved sheet lays them out
			 * in. Each banner keeps the conditional it had, so nothing new
			 * renders and nothing that used to render disappears.
			 */
			?>
			<p class="rk-form-actions">
				<button type="submit" class="button button-primary" name="rk_robots_save" value="1"><?php echo esc_html__( 'Save', 'rankkernel' ); ?></button>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rankkernel-general&section=robots&robots_tab=preview' ) ); ?>"><?php echo esc_html__( 'Cancel', 'rankkernel' ); ?></a>
				<button type="submit" class="button" name="rk_robots_reset" value="1" data-rk-confirm="<?php echo esc_attr( __( 'Reset robots.txt to the generated version? Your custom edits will be removed.', 'rankkernel' ) ); ?>"><?php echo esc_html__( 'Reset', 'rankkernel' ); ?></button>
			</p>

			<?php if ( [] !== $robotValidation['errors'] ) : ?>
				<?php /* Multi line by nature, so this keeps the banner notation rather than the one line notice. */ ?>
				<div class="rk-banner rk-banner-danger">
					<p class="rk-banner-title"><span class="rk-icon" aria-hidden="true">error</span><?php echo esc_html__( 'Validation', 'rankkernel' ); ?></p>
					<?php foreach ( $robotValidation['errors'] as $robotError ) : ?>
						<p><?php echo esc_html( $robotError ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( [] !== $robotValidation['warnings'] ) : ?>
				<div class="rk-banner rk-banner-warning">
					<p class="rk-banner-title"><span class="rk-icon" aria-hidden="true">warning</span><?php echo esc_html__( 'Validation', 'rankkernel' ); ?></p>
					<?php foreach ( $robotValidation['warnings'] as $robotWarning ) : ?>
						<p><?php echo esc_html( $robotWarning ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<pre class="rk-preview"><?php echo esc_html( $robotEffective ); ?></pre>
			<p class="rk-form-actions">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rankkernel-general&section=robots&robots_tab=edit' ) ); ?>"><?php echo esc_html__( 'Edit robots.txt', 'rankkernel' ); ?></a>
				<button type="submit" class="button" name="rk_robots_reset" value="1" data-rk-confirm="<?php echo esc_attr( __( 'Reset robots.txt to the generated version? Your custom edits will be removed.', 'rankkernel' ) ); ?>"><?php echo esc_html__( 'Reset', 'rankkernel' ); ?></button>
			</p>
		<?php endif; ?>
	</div>
</section>
