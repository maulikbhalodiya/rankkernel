<?php
/**
 * Instant Indexing view.
 *
 * Presentation only. InstantIndexingPage prepares every variable used below,
 * and owns capability checks, nonce verification, request handling and
 * redirects. The API key is deliberately absent: the view receives the
 * configured state and never the key, so it cannot render it by mistake.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var bool $settingsUpdated Whether the saved notice renders.
 * @var bool $keyConfigured   Whether a usable key is stored.
 * @var bool $autoSubmit      Whether automatic submission is on.
 * @var array<int, array{url: string, code: int, source: string, time: string, message: string}> $logRows Outcome log, newest first.
 * @var string $nonceSave       Nonce action for the auto submit toggle.
 * @var string $nonceRegenerate Nonce action for key regeneration.
 * @var string $nonceSubmit     Nonce action for the manual submit form.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Instant Indexing', 'rankkernel' ); ?></h1>
	<p class="description"><?php echo esc_html__( 'Notify participating search engines when a URL changes, using the IndexNow protocol.', 'rankkernel' ); ?></p>

	<?php if ( $settingsUpdated ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Settings saved.', 'rankkernel' ); ?></p></div>
	<?php endif; ?>

	<h2><?php echo esc_html__( 'Verification key', 'rankkernel' ); ?></h2>
	<p>
		<strong><?php echo esc_html( $keyConfigured ? __( 'Key configured', 'rankkernel' ) : __( 'No key configured', 'rankkernel' ) ); ?></strong>
	</p>
	<p class="description"><?php echo esc_html__( 'The key is generated on the server and is never shown here.', 'rankkernel' ); ?></p>
	<form method="post" action="">
		<?php wp_nonce_field( $nonceRegenerate ); ?>
		<input type="hidden" name="rankkernel_indexnow_action" value="regenerate" />
		<?php submit_button( __( 'Regenerate key', 'rankkernel' ), 'secondary', '', false ); ?>
	</form>

	<h2><?php echo esc_html__( 'Automatic submission', 'rankkernel' ); ?></h2>
	<form method="post" action="">
		<?php wp_nonce_field( $nonceSave ); ?>
		<input type="hidden" name="rankkernel_indexnow_action" value="save" />
		<label>
			<input type="checkbox" name="rankkernel_indexnow_auto_submit" value="1" <?php echo checked( $autoSubmit, true, false ); ?> />
			<?php echo esc_html__( 'Submit URLs automatically when a post or term changes.', 'rankkernel' ); ?>
		</label>
		<?php submit_button( __( 'Save settings', 'rankkernel' ), 'primary', '', false ); ?>
	</form>

	<h2><?php echo esc_html__( 'Submit a URL', 'rankkernel' ); ?></h2>
	<form method="post" action="">
		<?php wp_nonce_field( $nonceSubmit ); ?>
		<input type="hidden" name="rankkernel_indexnow_action" value="submit" />
		<label class="screen-reader-text" for="rankkernel-indexnow-url"><?php echo esc_html__( 'URL to submit', 'rankkernel' ); ?></label>
		<input type="url" class="regular-text" name="rankkernel_indexnow_url" id="rankkernel-indexnow-url" value="" />
		<?php submit_button( __( 'Submit', 'rankkernel' ), 'primary', '', false ); ?>
	</form>

	<h2><?php echo esc_html__( 'Recent submissions', 'rankkernel' ); ?></h2>
	<?php if ( [] === $logRows ) : ?>
		<p><?php echo esc_html__( 'Nothing has been submitted yet.', 'rankkernel' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'URL', 'rankkernel' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Status', 'rankkernel' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Source', 'rankkernel' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Time (UTC)', 'rankkernel' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Message', 'rankkernel' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logRows as $logRow ) : ?>
					<tr>
						<td><?php echo esc_url( $logRow['url'] ); ?></td>
						<td><?php echo esc_html( (string) $logRow['code'] ); ?></td>
						<td><?php echo esc_html( $logRow['source'] ); ?></td>
						<td><?php echo esc_html( $logRow['time'] ); ?></td>
						<td><?php echo esc_html( $logRow['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
