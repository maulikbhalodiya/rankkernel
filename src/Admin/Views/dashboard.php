<?php
/**
 * Dashboard view.
 *
 * Presentation only. DashboardPage prepares every variable used below, and owns
 * capability checks, nonce verification, request handling and redirects.
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
<div class="wrap rk-dashboard">
	<h1><?php echo esc_html__( 'RankKernel', 'rankkernel' ); ?></h1>
	<p class="description"><?php echo esc_html__( 'Turn features on or off. A disabled feature adds no hooks and no runtime cost.', 'rankkernel' ); ?></p>

	<?php if ( $settingsUpdated ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Module updated.', 'rankkernel' ); ?></p></div>
	<?php endif; ?>

	<div class="rk-cards">
		<?php foreach ( $cards as $card ) : ?>
			<div class="rk-card<?php echo $card['enabled'] ? ' is-enabled' : ''; ?>">
				<div class="rk-card-head">
					<h2 class="rk-card-title"><?php echo esc_html( $card['label'] ); ?></h2>
					<span class="rk-card-state"><?php echo $card['enabled'] ? esc_html__( 'On', 'rankkernel' ) : esc_html__( 'Off', 'rankkernel' ); ?></span>
				</div>
				<p class="rk-card-desc"><?php echo esc_html( $card['description'] ); ?></p>
				<div class="rk-card-actions">
					<?php if ( '' !== $card['settingsUrl'] ) : ?>
						<?php
						$settingsAriaLabel = sprintf(
							/* translators: %s: module name */
							__( 'Settings for %s', 'rankkernel' ),
							$card['label']
						);
						?>
						<a class="button button-secondary" href="<?php echo esc_url( $card['settingsUrl'] ); ?>" aria-label="<?php echo esc_attr( $settingsAriaLabel ); ?>"><?php echo esc_html__( 'Settings', 'rankkernel' ); ?></a>
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
						<button type="submit" class="button<?php echo $card['enabled'] ? '' : ' button-primary'; ?>" aria-label="<?php echo esc_attr( $toggleAriaLabel ); ?>"><?php echo $card['enabled'] ? esc_html__( 'Turn off', 'rankkernel' ) : esc_html__( 'Turn on', 'rankkernel' ); ?></button>
					</form>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
