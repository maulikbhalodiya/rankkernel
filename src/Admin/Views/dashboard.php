<?php
/**
 * Dashboard view.
 *
 * Presentation only. DashboardPage prepares every variable used below, and owns
 * capability checks, nonce verification, request handling and redirects.
 *
 * The layout follows the approved Dashboard design: a header card, a three card
 * stat row, an attention bar, the module manager with an available list and a
 * planned list, and two side cards. Shared components (card, page header,
 * pills, buttons, tabs) come from rankkernel-ui.css under the rk-ui scope, and
 * every page specific rule lives under .rk-dashboard in dashboard-admin.css.
 * No raw hex appears in either file, every colour resolves from the --rk-*
 * tokens in rankkernel-admin.css, which loads first as a registered
 * dependency.
 *
 * Every number and state on this screen derives from the cards the controller
 * prepared, so the screen can never disagree with the stored module option:
 * - The stat row counts the cards, and `enabled` is the stored option state.
 * - A card is planned when its description carries the "Planned." marker the
 *   controller writes for the modules without an implementation. Available
 *   cards render under Active, planned cards under Coming Soon.
 * - The attention bar and its stat card count the available modules that are
 *   switched off. With none switched off the bar is absent and the card reads
 *   zero.
 * - The SEO Health rows read the same enabled flags.
 *
 * The Quick Actions card and the module search have no handler on this screen,
 * so both render static and disabled rather than dead on click. The module
 * toggles are real: every row posts the module id the form always posted.
 * Planned modules render a disabled switch and never post to the toggle.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var bool  $settingsUpdated Whether the module toggled notice renders.
 * @var array<int, array{id: string, label: string, description: string, enabled: bool, settingsUrl: string, planned: bool}> $cards Module cards.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/*
 * Derived state. The available and planned lists keep the registry order, so
 * the screen always reads the same order as the controller list.
 */
$rk_total_modules   = count( $cards );
$rk_active_modules  = 0;
$rk_available_cards = [];
$rk_planned_cards   = [];
$rk_attention_cards = [];
$rk_attention_names = [];

foreach ( $cards as $rk_card ) {
	if ( (bool) $rk_card['enabled'] ) {
		++$rk_active_modules;
	}

	if ( ! empty( $rk_card['planned'] ) || str_contains( (string) $rk_card['description'], 'Planned.' ) ) {
		$rk_planned_cards[] = $rk_card;

		continue;
	}

	$rk_available_cards[] = $rk_card;

	if ( ! (bool) $rk_card['enabled'] ) {
		$rk_attention_cards[] = $rk_card;
		$rk_attention_names[] = $rk_card['label'];
	}
}

$rk_available_count = count( $rk_available_cards );
$rk_planned_count   = count( $rk_planned_cards );
$rk_attention_count = count( $rk_attention_cards );

/* The four modules the side status card reports, in the design's order. */
$rk_health_ids   = [ 'sitemaps', 'robots', 'schema', '404' ];
$rk_health_cards = [];

foreach ( $cards as $rk_card ) {
	if ( in_array( $rk_card['id'], $rk_health_ids, true ) ) {
		$rk_health_cards[ $rk_card['id'] ] = $rk_card;
	}
}

$rk_sitemaps_card = $rk_health_cards['sitemaps'] ?? null;
$rk_sitemaps_on   = null !== $rk_sitemaps_card && (bool) $rk_sitemaps_card['enabled'];

/*
 * The icon per module id, picked from the shipped Material Symbols subset. The
 * metadata row keeps the design's letter tile instead. A module this map does
 * not know yet falls back to the generic controls glyph.
 */
$rk_module_icons = [
	'analysis'         => 'assessment',
	'sitemaps'         => 'alt_route',
	'schema'           => 'code_blocks',
	'breadcrumbs'      => 'arrow_right',
	'redirects'        => 'swap_vert',
	'404'              => 'search_off',
	'instant-indexing' => 'send',
	'robots'           => 'shield',
	'image-seo'        => 'upload',
	'gutenberg'        => 'add',
	'ai'               => 'auto_fix_high',
	'headless'         => 'link',
];

$rk_attention_label = 1 === $rk_attention_count
	? __( '1 module needs attention', 'rankkernel' )
	: sprintf(
		/* translators: %d: number of available modules that are switched off. */
		__( '%d modules need attention', 'rankkernel' ),
		$rk_attention_count
	);

$rk_attention_body = sprintf(
	/* translators: %s: comma separated list of module names. */
	__( 'Switched off: %s. Turn modules on or off from the Modules list.', 'rankkernel' ),
	implode( ', ', $rk_attention_names )
);

/* The header's View Site control is dropped when no home URL is available. */
$rk_home_url = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
?>
<div class="wrap rk-dashboard rk-ui">

	<?php /* WordPress prints no h1 for a plugin admin screen, so supply one for assistive tech. */ ?>
	<h1 class="screen-reader-text"><?php echo esc_html__( 'RankKernel', 'rankkernel' ); ?></h1>

	<div class="rk-dashboard-inner">

		<?php /* Section 1: page header card. */ ?>
		<header class="rk-ui-card rk-ui-page-header">
			<div class="rk-ui-page-header-text">
				<div class="rk-ui-page-header-title-row">
					<span class="rk-dashboard-logo" aria-hidden="true"><span class="rk-icon" aria-hidden="true">search</span></span>
					<h2 class="rk-ui-page-title"><?php echo esc_html__( 'RankKernel', 'rankkernel' ); ?></h2>
					<span class="rk-ui-pill rk-ui-pill-info"><?php echo esc_html( sprintf( /* translators: %s: the plugin version number. */ __( 'v%s', 'rankkernel' ), RANKKERNEL_VERSION ) ); ?></span>
				</div>
				<p class="rk-ui-sub"><?php echo esc_html__( 'SEO plugin for WordPress. Modules you disable cost nothing.', 'rankkernel' ); ?></p>
			</div>
			<?php if ( '' !== $rk_home_url ) : ?>
				<div class="rk-ui-page-header-actions">
					<a class="rk-ui-btn rk-ui-btn-secondary" href="<?php echo esc_url( $rk_home_url ); ?>"><span class="rk-icon" aria-hidden="true">link</span><?php echo esc_html__( 'View Site', 'rankkernel' ); ?></a>
				</div>
			<?php endif; ?>
		</header>

		<?php /* Section 2: module toggled notice, the only notice this screen raises. */ ?>
		<?php if ( $settingsUpdated ) : ?>
			<div class="rk-ui-notice rk-ui-notice-success" role="status">
				<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">check_circle</span>
				<p class="rk-ui-notice-text"><?php echo esc_html__( 'Module updated.', 'rankkernel' ); ?></p>
			</div>
		<?php endif; ?>

		<?php /* Section 3: stat row, every number derived from the prepared cards. */ ?>
		<section class="rk-dashboard-stats" aria-label="<?php echo esc_attr( __( 'Module status', 'rankkernel' ) ); ?>">
			<div class="rk-ui-card rk-dashboard-stat">
				<div class="rk-dashboard-stat-text">
					<div class="rk-dashboard-stat-value"><?php echo esc_html( (string) $rk_active_modules ); ?></div>
					<div class="rk-dashboard-stat-label"><?php echo esc_html( sprintf( /* translators: %d: total number of registered modules. */ __( 'of %d modules active', 'rankkernel' ), $rk_total_modules ) ); ?></div>
				</div>
				<div class="rk-dashboard-stat-icon rk-dashboard-stat-icon-info" aria-hidden="true"><span class="rk-icon" aria-hidden="true">tune</span></div>
			</div>

			<div class="rk-ui-card rk-dashboard-stat">
				<div class="rk-dashboard-stat-text">
					<div class="rk-dashboard-stat-value-row">
						<span class="rk-dashboard-stat-value rk-dashboard-stat-value-text"><?php echo $rk_sitemaps_on ? esc_html__( 'Live', 'rankkernel' ) : esc_html__( 'Off', 'rankkernel' ); ?></span>
						<span class="rk-ui-pill <?php echo $rk_sitemaps_on ? 'rk-ui-pill-success' : 'rk-ui-pill-neutral'; ?>">
							<span class="rk-dashboard-status-dot" aria-hidden="true"></span>
							<?php echo $rk_sitemaps_on ? esc_html__( 'Healthy', 'rankkernel' ) : esc_html__( 'Inactive', 'rankkernel' ); ?>
						</span>
					</div>
					<div class="rk-dashboard-stat-caption"><?php echo $rk_sitemaps_on ? esc_html__( 'sitemap_index.xml serving', 'rankkernel' ) : esc_html__( 'XML sitemaps are switched off', 'rankkernel' ); ?></div>
				</div>
				<div class="rk-dashboard-stat-icon <?php echo $rk_sitemaps_on ? 'rk-dashboard-stat-icon-positive' : 'rk-dashboard-stat-icon-neutral'; ?>" aria-hidden="true"><span class="rk-icon" aria-hidden="true">alt_route</span></div>
			</div>

			<div class="rk-ui-card rk-dashboard-stat">
				<div class="rk-dashboard-stat-text">
					<div class="rk-dashboard-stat-value-row">
						<span class="rk-dashboard-stat-value<?php echo $rk_attention_count > 0 ? ' rk-dashboard-stat-value-warning' : ''; ?>"><?php echo esc_html( (string) $rk_attention_count ); ?></span>
						<?php if ( $rk_attention_count > 0 ) : ?>
							<span class="rk-dashboard-attention-tag"><?php echo esc_html__( 'Attention', 'rankkernel' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="rk-dashboard-stat-label"><?php echo esc_html__( 'available modules switched off', 'rankkernel' ); ?></div>
					<a class="rk-dashboard-stat-link" href="#rk-modules"><?php echo esc_html__( 'View Modules', 'rankkernel' ); ?><span class="rk-icon" aria-hidden="true">arrow_right</span></a>
				</div>
				<div class="rk-dashboard-stat-icon <?php echo $rk_attention_count > 0 ? 'rk-dashboard-stat-icon-warning' : 'rk-dashboard-stat-icon-positive'; ?>" aria-hidden="true"><span class="rk-icon" aria-hidden="true"><?php echo $rk_attention_count > 0 ? 'warning' : 'check_circle'; ?></span></div>
			</div>
		</section>

		<?php /* Section 4: attention bar. It renders only while an available module is switched off, so it never shows an empty alert. */ ?>
		<?php if ( $rk_attention_count > 0 ) : ?>
			<details class="rk-dashboard-alert" open>
				<summary class="rk-dashboard-alert-summary">
					<span class="rk-dashboard-alert-lead">
						<span class="rk-icon rk-dashboard-alert-icon" aria-hidden="true">error</span>
						<strong class="rk-dashboard-alert-count"><?php echo esc_html( $rk_attention_label ); ?></strong>
						<span class="rk-dashboard-alert-names">&bull; <?php echo esc_html( implode( ', ', $rk_attention_names ) ); ?></span>
					</span>
					<span class="rk-dashboard-alert-toggle">
						<span class="rk-dashboard-alert-toggle-text"><?php echo esc_html__( 'Details', 'rankkernel' ); ?></span>
						<span class="rk-icon" aria-hidden="true">expand_more</span>
					</span>
				</summary>
				<div class="rk-dashboard-alert-body">
					<p class="rk-dashboard-alert-text"><?php echo esc_html( $rk_attention_body ); ?></p>
					<a class="rk-dashboard-alert-link" href="#rk-modules"><?php echo esc_html__( 'Go to Modules', 'rankkernel' ); ?><span class="rk-icon" aria-hidden="true">arrow_right</span></a>
				</div>
			</details>
		<?php endif; ?>

		<?php /* Section 5: two column main area, modules on the left, side cards on the right. */ ?>
		<main class="rk-dashboard-main">

			<section class="rk-ui-card rk-dashboard-modules" id="rk-modules" aria-labelledby="rk-modules-title">
				<div class="rk-ui-card-header rk-dashboard-modules-header">
					<div class="rk-ui-card-header-left">
						<h2 class="rk-ui-card-title" id="rk-modules-title"><?php echo esc_html__( 'Modules', 'rankkernel' ); ?></h2>
						<span class="rk-dashboard-count-pill"><?php echo esc_html( sprintf( /* translators: 1: number of available modules, 2: number of planned modules. */ __( '%1$d active / %2$d planned', 'rankkernel' ), $rk_available_count, $rk_planned_count ) ); ?></span>
					</div>
					<div class="rk-dashboard-search">
						<label class="screen-reader-text" for="rk-modules-search"><?php echo esc_html__( 'Search modules', 'rankkernel' ); ?></label>
						<span class="rk-icon rk-dashboard-search-icon" aria-hidden="true">search</span>
						<input type="search" id="rk-modules-search" class="rk-dashboard-search-input" placeholder="<?php echo esc_attr( __( 'Search modules...', 'rankkernel' ) ); ?>" title="<?php echo esc_attr( __( 'Module search is not available in this version.', 'rankkernel' ) ); ?>" disabled />
					</div>
				</div>

				<nav class="rk-ui-tabs rk-dashboard-tabs" aria-label="<?php echo esc_attr( __( 'Module lists', 'rankkernel' ) ); ?>">
					<a class="rk-ui-tab is-current" href="#rk-modules-active"><?php echo esc_html__( 'Active', 'rankkernel' ); ?> <span class="rk-ui-count"><?php echo esc_html( (string) $rk_available_count ); ?></span></a>
					<a class="rk-ui-tab" href="#rk-modules-planned"><?php echo esc_html__( 'Planned', 'rankkernel' ); ?> <span class="rk-ui-count"><?php echo esc_html( (string) $rk_planned_count ); ?></span></a>
				</nav>

				<div class="rk-dashboard-modules-body">

					<?php /* Available modules. Every row keeps its working toggle form. */ ?>
					<div class="rk-dashboard-module-group" id="rk-modules-active">
						<div class="rk-dashboard-group-head">
							<span class="rk-dashboard-group-dot rk-dashboard-group-dot-active" aria-hidden="true"></span>
							<h3 class="rk-dashboard-group-title rk-dashboard-group-title-active"><?php echo esc_html__( 'Active', 'rankkernel' ); ?></h3>
						</div>
						<div class="rk-dashboard-module-list">
							<?php foreach ( $rk_available_cards as $card ) : ?>
								<div class="rk-dashboard-module">
									<div class="rk-dashboard-module-main">
										<?php if ( 'metadata' === $card['id'] ) : ?>
											<span class="rk-dashboard-module-tile rk-dashboard-module-letter" aria-hidden="true">T</span>
										<?php else : ?>
											<span class="rk-dashboard-module-tile<?php echo 'schema' === $card['id'] ? ' rk-dashboard-module-tile-schema' : ''; ?>" aria-hidden="true"><span class="rk-icon" aria-hidden="true"><?php echo esc_html( $rk_module_icons[ $card['id'] ] ?? 'tune' ); ?></span></span>
										<?php endif; ?>
										<div class="rk-dashboard-module-text">
											<div class="rk-dashboard-module-title"><?php echo esc_html( $card['label'] ); ?></div>
											<?php if ( '' !== $card['description'] ) : ?>
												<div class="rk-dashboard-module-desc"><?php echo esc_html( $card['description'] ); ?></div>
											<?php endif; ?>
										</div>
									</div>
									<div class="rk-dashboard-module-actions">
										<?php if ( '' !== $card['settingsUrl'] ) : ?>
											<?php
											$settingsAriaLabel = sprintf(
												/* translators: %s: module name */
												__( 'Settings for %s', 'rankkernel' ),
												$card['label']
											);
											?>
											<a class="rk-dashboard-module-link" href="<?php echo esc_url( $card['settingsUrl'] ); ?>" aria-label="<?php echo esc_attr( $settingsAriaLabel ); ?>"><?php echo esc_html__( 'Settings', 'rankkernel' ); ?></a>
										<?php endif; ?>
										<form method="post" action="" class="rk-dashboard-module-toggle">
											<?php wp_nonce_field( 'rankkernel_module_toggle' ); ?>
											<input type="hidden" name="rankkernel_module_toggle" value="<?php echo esc_attr( $card['id'] ); ?>" />
											<?php
											$toggleAriaLabel = $card['enabled']
												? sprintf(
													/* translators: %s: module name */
													__( 'Turn off %s module', 'rankkernel' ),
													$card['label']
												)
												: sprintf(
													/* translators: %s: module name */
													__( 'Turn on %s module', 'rankkernel' ),
													$card['label']
												);
											?>
											<button type="submit" class="rk-dashboard-switch<?php echo $card['enabled'] ? ' is-on' : ''; ?>" role="switch" aria-checked="<?php echo $card['enabled'] ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $toggleAriaLabel ); ?>"><span class="rk-dashboard-switch-knob" aria-hidden="true"></span></button>
										</form>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<?php /* Planned modules. They are not implemented, so no toggle form is rendered and nothing posts to the REST toggle. */ ?>
					<div class="rk-dashboard-module-group" id="rk-modules-planned">
						<div class="rk-dashboard-group-head">
							<span class="rk-dashboard-group-dot" aria-hidden="true"></span>
							<h3 class="rk-dashboard-group-title"><?php echo esc_html__( 'Coming Soon', 'rankkernel' ); ?></h3>
						</div>
						<div class="rk-dashboard-module-list rk-dashboard-module-list-planned">
							<?php foreach ( $rk_planned_cards as $card ) : ?>
								<div class="rk-dashboard-module rk-dashboard-module-planned">
									<div class="rk-dashboard-module-main">
										<span class="rk-dashboard-module-tile" aria-hidden="true"><span class="rk-icon" aria-hidden="true"><?php echo esc_html( $rk_module_icons[ $card['id'] ] ?? 'tune' ); ?></span></span>
										<div class="rk-dashboard-module-text">
											<div class="rk-dashboard-module-title"><?php echo esc_html( $card['label'] ); ?></div>
											<?php if ( '' !== $card['description'] ) : ?>
												<div class="rk-dashboard-module-desc"><?php echo esc_html( $card['description'] ); ?></div>
											<?php endif; ?>
										</div>
									</div>
									<div class="rk-dashboard-module-actions">
										<span class="rk-dashboard-module-planned-tag"><?php echo esc_html__( 'Planned', 'rankkernel' ); ?></span>
										<?php
										$plannedAriaLabel = sprintf(
											/* translators: %s: module name */
											__( '%s module is planned and cannot be enabled yet', 'rankkernel' ),
											$card['label']
										);
										?>
										<button type="button" class="rk-dashboard-switch" role="switch" aria-checked="false" aria-disabled="true" disabled aria-label="<?php echo esc_attr( $plannedAriaLabel ); ?>"><span class="rk-dashboard-switch-knob" aria-hidden="true"></span></button>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

				</div>
			</section>

			<aside class="rk-dashboard-side">

				<?php /* Quick Actions. Every action the design shows is present so the composition matches, and each is disabled because this screen has no handler for it yet. See docs/DESIGN-WIRING-BACKLOG.md for the logic each one would reuse. */ ?>
				<div class="rk-ui-card rk-dashboard-side-card">
					<div class="rk-dashboard-card-head">
						<h2 class="rk-dashboard-card-title"><?php echo esc_html__( 'Quick Actions', 'rankkernel' ); ?></h2>
						<span class="rk-dashboard-card-tag"><?php echo esc_html__( 'Tools', 'rankkernel' ); ?></span>
					</div>
					<div class="rk-dashboard-tool-list">
						<button type="button" class="rk-dashboard-tool" disabled>
							<span class="rk-dashboard-tool-main"><span class="rk-icon rk-dashboard-tool-icon rk-dashboard-tool-icon-primary" aria-hidden="true">swap_vert</span><span><?php echo esc_html__( 'Flush Sitemap Cache', 'rankkernel' ); ?></span></span>
							<span class="rk-dashboard-tool-tag"><?php echo esc_html__( 'POST', 'rankkernel' ); ?></span>
						</button>
						<button type="button" class="rk-dashboard-tool" disabled>
							<span class="rk-dashboard-tool-main"><span class="rk-icon rk-dashboard-tool-icon rk-dashboard-tool-icon-positive" aria-hidden="true">download</span><span><?php echo esc_html__( 'Export Redirects CSV', 'rankkernel' ); ?></span></span>
							<span class="rk-dashboard-tool-tag"><?php echo esc_html__( '.csv', 'rankkernel' ); ?></span>
						</button>
						<button type="button" class="rk-dashboard-tool" disabled>
							<span class="rk-dashboard-tool-main"><span class="rk-icon rk-dashboard-tool-icon rk-dashboard-tool-icon-muted" aria-hidden="true">link</span><span><?php echo esc_html__( 'View robots.txt', 'rankkernel' ); ?></span></span>
							<span class="rk-icon rk-dashboard-tool-launch" aria-hidden="true">arrow_right</span>
						</button>
						<button type="button" class="rk-dashboard-tool rk-dashboard-tool-danger" disabled>
							<span class="rk-dashboard-tool-main"><span class="rk-icon rk-dashboard-tool-icon rk-dashboard-tool-icon-danger" aria-hidden="true">cancel</span><span><?php echo esc_html__( 'Clear 404 Log', 'rankkernel' ); ?></span></span>
						</button>
					</div>
				</div>

				<?php /* SEO Health. Each row reads the enabled flag of one module, so no status here is invented. */ ?>
				<div class="rk-ui-card rk-dashboard-side-card">
					<div class="rk-dashboard-card-head">
						<h2 class="rk-dashboard-card-title"><?php echo esc_html__( 'SEO Health', 'rankkernel' ); ?></h2>
						<span class="rk-dashboard-live"><span class="rk-dashboard-live-dot" aria-hidden="true"></span><?php echo esc_html__( 'Live Status', 'rankkernel' ); ?></span>
					</div>
					<ul class="rk-dashboard-health-list">
						<?php foreach ( $rk_health_ids as $rk_health_id ) : ?>
							<?php
							$rk_health_card = $rk_health_cards[ $rk_health_id ] ?? null;

							if ( null === $rk_health_card ) {
								continue;
							}
							?>
							<li class="rk-dashboard-health-item<?php echo $rk_health_card['enabled'] ? '' : ' is-off'; ?>">
								<span class="rk-icon rk-dashboard-health-icon" aria-hidden="true"><?php echo $rk_health_card['enabled'] ? 'check_circle' : 'warning'; ?></span>
								<span class="rk-dashboard-health-text"><strong><?php echo esc_html( $rk_health_card['label'] ); ?>:</strong> <?php echo $rk_health_card['enabled'] ? esc_html__( 'Active', 'rankkernel' ) : esc_html__( 'Off', 'rankkernel' ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

			</aside>

		</main>

	</div>
</div>
