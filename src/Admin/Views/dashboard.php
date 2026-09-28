<?php
/**
 * Dashboard view.
 *
 * Presentation only. DashboardPage prepares every variable used below, and owns
 * capability checks, nonce verification, request handling and redirects.
 *
 * Layout follows the shared RankKernel design system so this screen reads the
 * same as Redirects and Instant Indexing: a page header card, a notice row, then
 * the module cards. Every colour, spacing, radius and shadow value resolves from
 * the --rk-* tokens, so no raw hex appears in this file.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var bool  $settingsUpdated Whether the module toggled notice renders.
 * @var array<int, array{id: string, label: string, description: string, enabled: bool, settingsUrl: string}> $cards Module cards.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap rk-dashboard rk-ui">

	<?php
	/*
	 * Both counts derive from the cards the controller already prepared, so the
	 * header can never disagree with the grid rendered underneath it. The
	 * registry is the single source of truth for the totals.
	 */
	$rk_total_modules  = count( $cards );
	$rk_active_modules = count(
		array_filter(
			$cards,
			static function ( array $rk_card ): bool {
				return (bool) $rk_card['enabled'];
			}
		)
	);
	?>

	<?php /* Section 1: page header card. */ ?>
	<header class="rk-ui-card rk-ui-page-header">
		<div class="rk-ui-page-header-text">
			<div class="rk-ui-page-header-title-row">
				<h2 class="rk-ui-page-title"><?php echo esc_html__( 'RankKernel', 'rankkernel' ); ?></h2>
				<span class="rk-ui-pill rk-ui-pill-info"><?php echo esc_html( sprintf( /* translators: %s: the plugin version number. */ __( 'Version %s', 'rankkernel' ), RANKKERNEL_VERSION ) ); ?></span>
				<span class="rk-ui-pill <?php echo 0 === $rk_active_modules ? 'rk-ui-pill-neutral' : 'rk-ui-pill-success'; ?>"><?php echo esc_html( sprintf( /* translators: 1: number of active modules, 2: total number of modules. */ __( '%1$d of %2$d modules active', 'rankkernel' ), $rk_active_modules, $rk_total_modules ) ); ?></span>
			</div>
			<p class="rk-ui-sub"><?php echo esc_html__( 'Turn features on or off. A disabled feature adds no hooks and no runtime cost.', 'rankkernel' ); ?></p>
		</div>
	</header>

	<?php /* Section 2: notice row. */ ?>
	<?php if ( $settingsUpdated ) : ?>
		<div class="rk-ui-notice rk-ui-notice-success" role="status">
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">check_circle</span>
			<p class="rk-ui-notice-text"><?php echo esc_html__( 'Module updated.', 'rankkernel' ); ?></p>
		</div>
	<?php endif; ?>

	<?php /* Section 3: module cards. */ ?>
	<div class="rk-cards">
		<?php foreach ( $cards as $card ) : ?>
			<div class="rk-ui-card rk-card">
				<div class="rk-ui-card-header">
					<h3 class="rk-ui-card-title"><?php echo esc_html( $card['label'] ); ?></h3>
					<span class="rk-ui-pill <?php echo $card['enabled'] ? 'rk-ui-pill-success' : 'rk-ui-pill-neutral'; ?>"><?php echo $card['enabled'] ? esc_html__( 'On', 'rankkernel' ) : esc_html__( 'Off', 'rankkernel' ); ?></span>
				</div>
				<?php if ( '' !== $card['description'] ) : ?>
					<p class="rk-card-desc"><?php echo esc_html( $card['description'] ); ?></p>
				<?php endif; ?>
				<div class="rk-card-actions">
					<?php if ( '' !== $card['settingsUrl'] ) : ?>
						<?php
						$settingsAriaLabel = sprintf(
							/* translators: %s: module name */
							__( 'Settings for %s', 'rankkernel' ),
							$card['label']
						);
						?>
						<a class="rk-ui-btn rk-ui-btn-secondary" href="<?php echo esc_url( $card['settingsUrl'] ); ?>" aria-label="<?php echo esc_attr( $settingsAriaLabel ); ?>"><span class="rk-icon" aria-hidden="true">settings</span><?php echo esc_html__( 'Settings', 'rankkernel' ); ?></a>
					<?php endif; ?>
					<form method="post" action="" class="rk-card-toggle">
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
						<button type="submit" class="rk-ui-btn <?php echo $card['enabled'] ? 'rk-ui-btn-secondary' : 'rk-ui-btn-primary'; ?>" aria-label="<?php echo esc_attr( $toggleAriaLabel ); ?>"><?php echo $card['enabled'] ? esc_html__( 'Turn off', 'rankkernel' ) : esc_html__( 'Turn on', 'rankkernel' ); ?></button>
					</form>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
