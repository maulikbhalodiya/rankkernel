<?php
/**
 * Instant Indexing view.
 *
 * Presentation only. InstantIndexingPage prepares every variable used below,
 * and owns capability checks, nonce verification, request handling and
 * redirects. The API key is deliberately absent: the view receives the
 * configured state and never the key, so it cannot render it by mistake.
 * Every stat, tab count, pill and table row derives from the real log table
 * through the shared query layer, nothing on this screen is hard coded.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var bool $settingsUpdated Whether the saved notice renders.
 * @var array{type: string, message: string}|null $notice Submit outcome notice, null for none.
 * @var bool $keyConfigured   Whether a usable key is stored.
 * @var bool $autoSubmit      Whether automatic submission is on.
 * @var array{total: int, accepted: int, rejected: int, limited: int} $stats Stats strip numbers over the whole log table.
 * @var string $screenUrl     Screen URL without filter arguments.
 * @var InstantIndexingLogView $logView Log filters plus tabs plus pagination state.
 * @var array<int, array{key: string, label: string, url: string, count: int, current: bool}> $statusTabs Status tabs with real counts.
 * @var array<int, array{id: int, url: string, code: int, source: string, time: string, message: string, category: string, statusLabel: string, statusPill: string, sourceLabel: string, sourcePill: string}> $pageRows Enriched rows for the current page.
 * @var array{show: bool, prevUrl: string, nextUrl: string, pages: array<int, array{label: string, url: string, current: bool, gap: bool}>} $pagination Pagination links plus numbered pages.
 * @var array{from: int, to: int, total: int} $showing Visible range for the footer label.
 * @var bool $hasFilter       Whether any filter is active.
 * @var bool $listHasRows     Whether any log rows exist.
 * @var string $nonceSave       Nonce action for the auto submit toggle.
 * @var string $nonceRegenerate Nonce action for key regeneration.
 * @var string $nonceSubmit     Nonce action for the manual submit form.
 * @var string $nonceClear      Nonce action for the clear log form.
 * @var string $nonceVerify     Nonce action for the key file verification check.
 * @var string $nonceRetry      Nonce action for the single row retry form.
 * @var string $urlPlaceholder  Placeholder with three example URLs on this site.
 * @var string $noticeCode      Outcome code from the redirect, empty for none.
 * @var string $keyFileUrl      Public key file location, empty when no key is stored.
 * @var int $verifyCode         Status code from the last key file check, zero when none ran.
 */

declare(strict_types=1);

use RankKernel\Admin\InstantIndexingLogView;
use RankKernel\Admin\InstantIndexingOutcomes;
use RankKernel\Admin\InstantIndexingPage;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap rk-instant-indexing-wrap">

	<?php
	/*
	 * WordPress injects third-party plugin notices directly into .wrap before
	 * our rendered content. We output a screen-reader heading here so WP has
	 * the h1 it expects, then open .rk-instant-indexing below the native
	 * notice area.
	 */
	?>
	<h1 class="screen-reader-text"><?php echo esc_html__( 'Instant Indexing', 'rankkernel' ); ?></h1>

	<div class="rk-instant-indexing rk-ui">

		<?php /* Section 1: page header card. */ ?>
		<header class="rk-ui-card rk-ui-page-header">
			<div class="rk-ui-page-header-text">
				<div class="rk-ui-page-header-title-row">
					<h2 class="rk-ui-page-title"><?php echo esc_html__( 'Instant Indexing', 'rankkernel' ); ?></h2>
					<?php if ( $autoSubmit ) : ?>
						<span class="rk-auto-pill rk-auto-pill-on"><span class="rk-auto-pill-dot" aria-hidden="true"></span><?php echo esc_html__( 'Automatic submission on', 'rankkernel' ); ?></span>
					<?php else : ?>
						<span class="rk-auto-pill rk-auto-pill-off"><span class="rk-auto-pill-dot" aria-hidden="true"></span><?php echo esc_html__( 'Automatic submission off', 'rankkernel' ); ?></span>
					<?php endif; ?>
				</div>
				<p class="rk-ui-sub"><?php echo esc_html__( 'Notify participating search engines when a URL changes, using the IndexNow protocol.', 'rankkernel' ); ?></p>
			</div>
			<div class="rk-ui-page-header-actions">
				<button type="button" class="rk-ui-btn rk-ui-btn-primary" id="rk-submit-toggle" aria-expanded="false" aria-controls="rk-submit-panel"><span class="rk-icon" aria-hidden="true">send</span><?php echo esc_html__( 'Submit a URL', 'rankkernel' ); ?></button>
				<button type="button" class="rk-ui-btn rk-ui-btn-secondary" id="rk-settings-toggle" aria-expanded="false" aria-controls="rk-settings-panel"><span class="rk-icon" aria-hidden="true">settings</span><?php echo esc_html__( 'Settings', 'rankkernel' ); ?></button>
				<button type="button" class="rk-ui-btn rk-ui-btn-icon" id="rk-help-toggle" aria-expanded="false" aria-controls="rk-help-panel" aria-label="<?php echo esc_attr__( 'How Instant Indexing works', 'rankkernel' ); ?>" title="<?php echo esc_attr__( 'How Instant Indexing works', 'rankkernel' ); ?>"><span class="rk-icon" aria-hidden="true">help</span></button>
			</div>
		</header>

		<?php /* Section 2: notice row. */ ?>
		<?php if ( $settingsUpdated ) : ?>
			<div class="rk-ui-notice rk-ui-notice-success" role="status">
				<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">check_circle</span>
				<p class="rk-ui-notice-text"><?php echo esc_html__( 'Settings saved.', 'rankkernel' ); ?></p>
				<button type="button" class="rk-ui-notice-dismiss" aria-label="<?php echo esc_attr( __( 'Dismiss notice', 'rankkernel' ) ); ?>"><span class="rk-icon" aria-hidden="true">close</span></button>
			</div>
		<?php endif; ?>
		<?php if ( null !== $notice ) : ?>
			<div class="rk-ui-notice rk-ui-notice-<?php echo esc_attr( $notice['type'] ); ?>" role="alert">
				<span class="rk-icon rk-ui-notice-icon" aria-hidden="true"><?php echo 'info' === $notice['type'] ? 'info' : 'error'; ?></span>
				<p class="rk-ui-notice-text"><?php echo esc_html( $notice['message'] ); ?></p>
				<button type="button" class="rk-ui-notice-dismiss" aria-label="<?php echo esc_attr( __( 'Dismiss notice', 'rankkernel' ) ); ?>"><span class="rk-icon" aria-hidden="true">close</span></button>
			</div>
		<?php endif; ?>

		<?php /* Section 3: stats strip, every number derived from the real log rows. */ ?>
		<div class="rk-stats" role="region" aria-label="<?php echo esc_attr( __( 'Submission statistics', 'rankkernel' ) ); ?>">
			<div class="rk-ui-card rk-stat">
				<div class="rk-stat-label"><?php echo esc_html__( 'Total submissions', 'rankkernel' ); ?></div>
				<div class="rk-stat-value"><?php echo esc_html( (string) $stats['total'] ); ?></div>
				<div class="rk-stat-caption"><?php echo esc_html__( 'since the log was last cleared', 'rankkernel' ); ?></div>
			</div>
			<div class="rk-ui-card rk-stat">
				<div class="rk-stat-label"><?php echo esc_html__( 'Accepted', 'rankkernel' ); ?></div>
				<div class="rk-stat-value rk-stat-value-positive"><?php echo esc_html( (string) $stats['accepted'] ); ?></div>
				<div class="rk-stat-caption"><?php echo esc_html__( '200 and 202 responses', 'rankkernel' ); ?></div>
			</div>
			<div class="rk-ui-card rk-stat">
				<div class="rk-stat-label"><?php echo esc_html__( 'Rejected', 'rankkernel' ); ?></div>
				<div class="rk-stat-value rk-stat-value-negative"><?php echo esc_html( (string) $stats['rejected'] ); ?></div>
				<div class="rk-stat-caption"><?php echo esc_html__( '400, 403, 405, 422 or refused', 'rankkernel' ); ?></div>
			</div>
			<div class="rk-ui-card rk-stat">
				<div class="rk-stat-label"><?php echo esc_html__( 'Rate limited', 'rankkernel' ); ?></div>
				<div class="rk-stat-value rk-stat-value-warning"><?php echo esc_html( (string) $stats['limited'] ); ?></div>
				<div class="rk-stat-caption"><?php echo esc_html__( '429 temporary status', 'rankkernel' ); ?></div>
			</div>
		</div>

		<?php /* Section 4: shared panel area above the log. One panel shows at a time, all hidden on load. */ ?>
		<div class="rk-panels">
		<div class="rk-ui-card rk-submit-card rk-panel" id="rk-submit-panel" hidden>
			<div class="rk-submit-head">
				<div class="rk-submit-title-wrap">
					<h2 class="rk-ui-card-title"><?php echo esc_html__( 'Submit URLs', 'rankkernel' ); ?></h2>
					<span class="rk-submit-hint"><?php echo esc_html__( 'One per line', 'rankkernel' ); ?></span>
				</div>
				<div class="rk-collapse-row">
					<a href="#rk-submit-panel" class="rk-collapse-hide" id="rk-submit-hide" aria-label="<?php echo esc_attr( __( 'Hide submit URLs', 'rankkernel' ) ); ?>"><?php echo esc_html__( 'Hide', 'rankkernel' ); ?></a>
				</div>
			</div>
			<form method="post" action="" class="rk-submit-form">
				<?php wp_nonce_field( $nonceSubmit ); ?>
				<input type="hidden" name="rankkernel_indexnow_action" value="submit" />
				<div class="rk-ui-form-row">
					<label class="rk-ui-form-label" for="rankkernel-indexnow-urls"><?php echo esc_html__( 'URLs', 'rankkernel' ); ?></label>
					<textarea class="rk-urls-input" id="rankkernel-indexnow-urls" name="rankkernel_indexnow_urls" rows="5" placeholder="<?php echo esc_attr( $urlPlaceholder ); ?>"></textarea>
				</div>
				<div id="rankkernel-indexnow-urls-status" class="rk-validation" role="status"></div>
				<div class="rk-submit-foot">
					<p class="rk-form-hint"><?php echo esc_html__( 'Must be URLs on this site. Deleted pages can be submitted too. Up to 10000 URLs are sent per request.', 'rankkernel' ); ?></p>
					<?php submit_button( __( 'Submit URLs', 'rankkernel' ), 'primary', '', false, [ 'id' => 'rankkernel-indexnow-submit' ] ); ?>
				</div>
			</form>
		</div>

		<?php /* Settings panel inside the shared area. */ ?>
		<div class="rk-ui-card rk-settings-card rk-panel" id="rk-settings-panel" hidden>
			<div class="rk-settings-head">
				<div>
					<h2 class="rk-ui-card-title"><?php echo esc_html__( 'Settings and key', 'rankkernel' ); ?></h2>
					<p class="rk-settings-hint"><?php echo esc_html__( 'Open to manage the key and automatic submission.', 'rankkernel' ); ?></p>
				</div>
				<div class="rk-collapse-row">
					<a href="#rk-settings-panel" class="rk-collapse-hide" id="rk-settings-hide" aria-label="<?php echo esc_attr( __( 'Hide settings and key', 'rankkernel' ) ); ?>"><?php echo esc_html__( 'Hide', 'rankkernel' ); ?></a>
				</div>
			</div>

			<div class="rk-settings-group">
				<div class="rk-settings-group-head">
					<div class="rk-settings-group-title">
						<span class="rk-icon" aria-hidden="true">shield</span>
						<span class="rk-settings-group-name"><?php echo esc_html__( 'Verification key', 'rankkernel' ); ?></span>
					</div>
					<?php if ( $keyConfigured ) : ?>
						<span class="rk-ui-pill rk-ui-pill-success"><?php echo esc_html__( 'Key configured', 'rankkernel' ); ?></span>
					<?php else : ?>
						<span class="rk-ui-pill rk-ui-pill-warning"><?php echo esc_html__( 'No key configured', 'rankkernel' ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( $keyConfigured ) : ?>
					<p class="rk-settings-text"><?php echo esc_html__( 'The key is generated on the server and is never shown here, so it cannot leak into your browser.', 'rankkernel' ); ?></p>
					<p class="rk-settings-text">
						<?php echo esc_html__( 'Key file:', 'rankkernel' ); ?>
						<a class="rk-key-url" href="<?php echo esc_url( $keyFileUrl ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( __( 'Open the key file in a new tab', 'rankkernel' ) ); ?>"><?php echo esc_html( $keyFileUrl ); ?></a>
					</p>
					<p class="rk-settings-text"><?php echo esc_html__( 'Opening that URL should show only the key as plain text. A 404 or a login redirect means the file is not publicly reachable and verification will fail.', 'rankkernel' ); ?></p>
					<div class="rk-key-actions">
						<form method="post" action="" class="rk-verify-form">
							<?php wp_nonce_field( $nonceVerify ); ?>
							<input type="hidden" name="rankkernel_indexnow_action" value="verify" />
							<?php submit_button( __( 'Verify key file', 'rankkernel' ), 'secondary', '', false ); ?>
						</form>
						<form method="post" action="" class="rk-key-form">
							<?php wp_nonce_field( $nonceRegenerate ); ?>
							<input type="hidden" name="rankkernel_indexnow_action" value="regenerate" />
							<?php submit_button( __( 'Regenerate key', 'rankkernel' ), 'secondary', '', false ); ?>
						</form>
					</div>
					<?php if ( 'verified' === $noticeCode ) : ?>
						<p class="rk-verify-result is-ok" role="status"><?php echo esc_html( sprintf( /* translators: %d: HTTP status code from the key file check. */ __( 'Key file verified. Returned %d and contained the expected key.', 'rankkernel' ), $verifyCode ) ); ?></p>
					<?php elseif ( 'verify_failed' === $noticeCode ) : ?>
						<p class="rk-verify-result is-error" role="alert"><?php echo esc_html( sprintf( /* translators: %d: HTTP status code from the key file check. */ __( 'Key file check failed. Returned %d. The file did not contain the expected key.', 'rankkernel' ), $verifyCode ) ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<p class="rk-settings-text"><?php echo esc_html__( 'A key is generated automatically the first time the module is enabled.', 'rankkernel' ); ?></p>
					<form method="post" action="" class="rk-key-form">
						<?php wp_nonce_field( $nonceRegenerate ); ?>
						<input type="hidden" name="rankkernel_indexnow_action" value="regenerate" />
						<?php submit_button( __( 'Generate key', 'rankkernel' ), 'primary', '', false ); ?>
					</form>
				<?php endif; ?>
				<p class="rk-settings-note"><span class="rk-icon" aria-hidden="true">lock</span><?php echo esc_html__( 'The verification file is served virtually from your site root. No file is written to disk.', 'rankkernel' ); ?></p>
			</div>

			<hr class="rk-settings-divider" />

			<div class="rk-settings-group">
				<form method="post" action="" class="rk-auto-form">
					<?php wp_nonce_field( $nonceSave ); ?>
					<input type="hidden" name="rankkernel_indexnow_action" value="save" />
					<div class="rk-auto-row">
						<div class="rk-auto-text">
							<div class="rk-auto-title"><?php echo esc_html__( 'Submit URLs automatically when a post or term changes.', 'rankkernel' ); ?></div>
							<p class="rk-settings-text"><?php echo esc_html__( 'Runs when a post is published, updated or trashed. Autosaves and revisions are skipped.', 'rankkernel' ); ?></p>
							<p class="rk-settings-note"><?php echo esc_html__( 'Off by default. Nothing is sent until you turn this on.', 'rankkernel' ); ?></p>
						</div>
						<label class="rk-ui-switch">
							<input type="checkbox" name="rankkernel_indexnow_auto_submit" value="1" <?php echo checked( $autoSubmit, true, false ); ?> aria-label="<?php echo esc_attr( __( 'Submit URLs automatically when a post or term changes.', 'rankkernel' ) ); ?>" />
							<span class="rk-ui-switch-track" aria-hidden="true"><span class="rk-ui-switch-knob"></span></span>
						</label>
					</div>
					<div class="rk-settings-actions">
						<a class="rk-ui-btn rk-ui-btn-secondary" href="<?php echo esc_url( $screenUrl ); ?>"><?php echo esc_html__( 'Cancel', 'rankkernel' ); ?></a>
						<?php submit_button( __( 'Save settings', 'rankkernel' ), 'primary', '', false ); ?>
					</div>
				</form>
			</div>
		</div>

		<?php /* Help panel inside the shared area. */ ?>
		<div class="rk-ui-card rk-help-card rk-panel" id="rk-help-panel" hidden>
			<div class="rk-settings-head">
				<div>
					<h2 class="rk-ui-card-title"><?php echo esc_html__( 'How Instant Indexing works', 'rankkernel' ); ?></h2>
					<p class="rk-settings-hint"><?php echo esc_html__( 'The IndexNow flow from setup to submission, in short steps.', 'rankkernel' ); ?></p>
				</div>
				<div class="rk-collapse-row">
					<a href="#rk-help-panel" class="rk-collapse-hide" id="rk-help-hide" aria-label="<?php echo esc_attr( __( 'Hide the walkthrough', 'rankkernel' ) ); ?>"><?php echo esc_html__( 'Hide', 'rankkernel' ); ?></a>
				</div>
			</div>

			<ol class="rk-help-list">
				<li><?php echo esc_html__( 'The module is on, and this page is where its settings live. Automatic submission is still off by default, so turn it on in the Settings panel if you want the plugin to notify search engines by itself.', 'rankkernel' ); ?></li>
				<li><?php echo esc_html__( 'A verification key was created on your server when the module was enabled. It is stored on the server and the key value is never sent to your browser.', 'rankkernel' ); ?></li>
				<li><?php echo esc_html__( 'Search engines confirm the key by fetching a small public text file at the key file URL shown in Settings. This plugin serves that file virtually, so no file is written to your site root or your disk.', 'rankkernel' ); ?></li>
				<li><?php echo esc_html__( 'When a post, page or term is published, updated or trashed, the plugin tells the search engines the URL changed. Autosaves and revisions are skipped, and the same URL is not sent more than once every 10 minutes.', 'rankkernel' ); ?></li>
				<li><?php echo esc_html__( 'The engines answer with a status. 200 means accepted. 202 means accepted and the key is still pending verification, which is normal for a new key. 4xx means rejected, and the engine is likely to reject the same URL again unless the page changes, though you can still retry it. 429 means too many requests, so try again later.', 'rankkernel' ); ?></li>
				<li><?php echo esc_html__( 'You can also submit URLs by hand from the Submit a URL panel when you change something outside the normal publishing flow.', 'rankkernel' ); ?></li>
				<li><?php echo esc_html__( 'A submission means the engine was notified, not that the page was indexed. Sitemaps still cover your whole site.', 'rankkernel' ); ?></li>
				<li><?php echo esc_html__( 'Nothing else is contacted. There is no telemetry.', 'rankkernel' ); ?></li>
			</ol>
		</div>
		</div>

		<?php /* Section 5: submission history card. */ ?>
		<div class="rk-ui-card rk-log-card">
			<div class="rk-ui-card-header">
				<div class="rk-ui-card-header-left">
					<h2 class="rk-ui-card-title"><?php echo esc_html__( 'Submission history', 'rankkernel' ); ?></h2>
					<span class="rk-entry-count"><?php echo esc_html( sprintf( /* translators: %d: number of log entries */ __( '%d entries', 'rankkernel' ), $stats['total'] ) ); ?></span>
				</div>
				<?php if ( $listHasRows ) : ?>
					<form method="post" action="" class="rk-clear-form">
						<?php wp_nonce_field( $nonceClear ); ?>
						<input type="hidden" name="rankkernel_indexnow_action" value="clear" />
						<span class="rk-clear-wrap">
							<?php submit_button( __( 'Clear log', 'rankkernel' ), 'secondary', 'rankkernel_indexnow_clear', false ); ?>
						</span>
					</form>
				<?php endif; ?>
			</div>

			<?php if ( ! $listHasRows ) : ?>
				<div class="rk-empty">
					<div class="rk-empty-icon" aria-hidden="true"><span class="rk-icon" aria-hidden="true">inbox</span></div>
					<p class="rk-empty-title"><?php echo esc_html__( 'Nothing has been submitted yet.', 'rankkernel' ); ?></p>
					<p class="rk-empty-body"><?php echo esc_html__( 'Published and updated URLs will appear here once automatic submission is on.', 'rankkernel' ); ?></p>
					<button type="button" class="rk-ui-btn rk-ui-btn-primary" data-rk-open-panel="rk-submit-panel"><span class="rk-icon" aria-hidden="true">send</span><?php echo esc_html__( 'Submit a URL', 'rankkernel' ); ?></button>
				</div>
				<?php
				/*
				 * Example preview for an empty log. Illustrative rows only,
				 * never counted in stats, tabs or pagination totals.
				 */
				?>
				<div class="rk-preview" aria-label="<?php echo esc_attr( __( 'Example preview', 'rankkernel' ) ); ?>">
					<p class="rk-preview-label"><?php echo esc_html__( 'Example preview. These rows are illustrative and are not real submissions.', 'rankkernel' ); ?></p>
					<div class="rk-ui-table-wrap">
						<table class="rk-ui-table">
							<thead>
								<tr>
									<th scope="col" class="rk-col-url"><?php echo esc_html__( 'URL', 'rankkernel' ); ?></th>
									<th scope="col" class="rk-col-status"><?php echo esc_html__( 'Status', 'rankkernel' ); ?></th>
									<th scope="col" class="rk-col-source"><?php echo esc_html__( 'Source', 'rankkernel' ); ?></th>
									<th scope="col" class="rk-col-time"><?php echo esc_html__( 'Time (UTC)', 'rankkernel' ); ?></th>
									<th scope="col" class="rk-col-message"><?php echo esc_html__( 'Message', 'rankkernel' ); ?></th>
									<th scope="col" class="rk-col-actions"><?php echo esc_html__( 'Actions', 'rankkernel' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								/*
								 * Preview rows are illustrative and have no stored
								 * id, so every Actions cell stays empty. The real
								 * table below renders the per row retry form, and
								 * the matching header keeps the two tables aligned.
								 */
								?>
								<tr>
									<td class="rk-col-url"><?php echo esc_html( 'https://example.com/blog/instant-indexing-overview' ); ?></td>
									<td class="rk-col-status"><span class="rk-ui-pill rk-ui-pill-success"><?php echo esc_html__( 'Accepted', 'rankkernel' ); ?></span></td>
									<td class="rk-col-source"><span class="rk-ui-pill rk-ui-pill-neutral"><?php echo esc_html__( 'Auto', 'rankkernel' ); ?></span></td>
									<td class="rk-col-time"><?php echo esc_html( '2026-09-24 06:58' ); ?></td>
									<td class="rk-col-message"><?php echo esc_html__( 'Accepted.', 'rankkernel' ); ?></td>
									<td class="rk-col-actions"></td>
								</tr>
								<tr>
									<td class="rk-col-url"><?php echo esc_html( 'https://example.com/products/wireless-keyboard' ); ?></td>
									<td class="rk-col-status"><span class="rk-ui-pill rk-ui-pill-info"><?php echo esc_html__( 'Key pending', 'rankkernel' ); ?></span></td>
									<td class="rk-col-source"><span class="rk-ui-pill rk-pill-source-manual"><?php echo esc_html__( 'Manual', 'rankkernel' ); ?></span></td>
									<td class="rk-col-time"><?php echo esc_html( '2026-09-24 06:42' ); ?></td>
									<td class="rk-col-message"><?php echo esc_html__( 'Accepted, the key is pending verification.', 'rankkernel' ); ?></td>
									<td class="rk-col-actions"></td>
								</tr>
								<tr>
									<td class="rk-col-url"><?php echo esc_html( 'https://example.com/about-us' ); ?></td>
									<td class="rk-col-status"><span class="rk-ui-pill rk-ui-pill-warning"><?php echo esc_html__( 'Rate limited', 'rankkernel' ); ?></span></td>
									<td class="rk-col-source"><span class="rk-ui-pill rk-pill-source-manual"><?php echo esc_html__( 'Manual', 'rankkernel' ); ?></span></td>
									<td class="rk-col-time"><?php echo esc_html( '2026-09-24 04:30' ); ?></td>
									<td class="rk-col-message"><?php echo esc_html__( 'Temporary failure, retry later.', 'rankkernel' ); ?></td>
									<td class="rk-col-actions"></td>
								</tr>
								<tr>
									<td class="rk-col-url"><?php echo esc_html( 'https://example.com/staging/draft-preview' ); ?></td>
									<td class="rk-col-status"><span class="rk-ui-pill rk-ui-pill-danger"><?php echo esc_html__( 'Rejected', 'rankkernel' ); ?></span></td>
									<td class="rk-col-source"><span class="rk-ui-pill rk-ui-pill-neutral"><?php echo esc_html__( 'Auto', 'rankkernel' ); ?></span></td>
									<td class="rk-col-time"><?php echo esc_html( '2026-09-23 22:11' ); ?></td>
									<td class="rk-col-message"><?php echo esc_html__( 'Rejected permanently, retrying will not help.', 'rankkernel' ); ?></td>
									<td class="rk-col-actions"></td>
								</tr>
								<tr>
									<td class="rk-col-url"><?php echo esc_html( 'https://example.com/changelog/version-2' ); ?></td>
									<td class="rk-col-status"><span class="rk-ui-pill rk-ui-pill-warning"><?php echo esc_html__( 'Retry later', 'rankkernel' ); ?></span></td>
									<td class="rk-col-source"><span class="rk-ui-pill rk-ui-pill-neutral"><?php echo esc_html__( 'Auto', 'rankkernel' ); ?></span></td>
									<td class="rk-col-time"><?php echo esc_html( '2026-09-23 18:04' ); ?></td>
									<td class="rk-col-message"><?php echo esc_html__( 'Temporary failure, retry later.', 'rankkernel' ); ?></td>
									<td class="rk-col-actions"></td>
								</tr>
								<tr>
									<td class="rk-col-url"><?php echo esc_html( 'https://example.com/pricing' ); ?></td>
									<td class="rk-col-status"><span class="rk-ui-pill rk-ui-pill-success"><?php echo esc_html__( 'Accepted', 'rankkernel' ); ?></span></td>
									<td class="rk-col-source"><span class="rk-ui-pill rk-pill-source-manual"><?php echo esc_html__( 'Manual', 'rankkernel' ); ?></span></td>
									<td class="rk-col-time"><?php echo esc_html( '2026-09-23 09:15' ); ?></td>
									<td class="rk-col-message"><?php echo esc_html__( 'Accepted.', 'rankkernel' ); ?></td>
									<td class="rk-col-actions"></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			<?php else : ?>
				<div class="rk-log-toolbar">
					<form method="get" action="<?php echo esc_url( $logView->filtersActionUrl() ); ?>" class="rk-log-filters" role="search" aria-label="<?php echo esc_attr( __( 'Filter submissions', 'rankkernel' ) ); ?>">
						<input type="hidden" name="page" value="<?php echo esc_attr( InstantIndexingPage::SLUG ); ?>" />
						<input type="hidden" name="rk_status" value="<?php echo esc_attr( $logView->status() ); ?>" />
						<div class="rk-ui-search-wrap">
							<label for="rk-log-search" class="screen-reader-text"><?php echo esc_html__( 'Search URL or message', 'rankkernel' ); ?></label>
							<span class="rk-icon rk-ui-search-icon" aria-hidden="true">search</span>
							<input
								type="search"
								id="rk-log-search"
								name="s"
								value="<?php echo esc_attr( $logView->search() ); ?>"
								placeholder="<?php echo esc_attr( __( 'Search URL or message', 'rankkernel' ) ); ?>"
								class="rk-ui-search-input"
							/>
						</div>
						<div class="rk-source-wrap">
							<label for="rk-log-source" class="rk-source-label"><?php echo esc_html__( 'Source:', 'rankkernel' ); ?></label>
							<div class="rk-ui-select-wrap">
								<select name="rk_source" id="rk-log-source" class="rk-ui-select">
									<option value="all"<?php echo InstantIndexingLogView::SOURCE_ALL === $logView->source() ? ' selected' : ''; ?>><?php echo esc_html__( 'All sources', 'rankkernel' ); ?></option>
									<option value="auto"<?php echo 'auto' === $logView->source() ? ' selected' : ''; ?>><?php echo esc_html__( 'Auto', 'rankkernel' ); ?></option>
									<option value="manual"<?php echo 'manual' === $logView->source() ? ' selected' : ''; ?>><?php echo esc_html__( 'Manual', 'rankkernel' ); ?></option>
								</select>
								<span class="rk-icon rk-ui-select-chevron" aria-hidden="true">expand_more</span>
							</div>
							<?php submit_button( __( 'Filter', 'rankkernel' ), 'secondary rk-filter-submit', 'rk_filter', false ); ?>
						</div>
					</form>
				</div>

				<nav class="rk-ui-tabs" aria-label="<?php echo esc_attr( __( 'Filter submissions by status', 'rankkernel' ) ); ?>">
					<?php foreach ( $statusTabs as $statusTab ) : ?>
						<a
							href="<?php echo esc_url( $statusTab['url'] ); ?>"
							class="rk-ui-tab<?php echo $statusTab['current'] ? ' is-current' : ''; ?>"
							<?php echo $statusTab['current'] ? ' aria-current="page"' : ''; ?>
						><?php echo esc_html( $statusTab['label'] ); ?> <span class="rk-ui-count"><?php echo esc_html( (string) $statusTab['count'] ); ?></span></a>
					<?php endforeach; ?>
				</nav>

				<?php if ( [] === $pageRows ) : ?>
					<div class="rk-empty rk-empty-filtered">
						<div class="rk-empty-icon" aria-hidden="true"><span class="rk-icon" aria-hidden="true">search</span></div>
						<p class="rk-empty-title"><?php echo esc_html__( 'No submissions match your filters.', 'rankkernel' ); ?></p>
						<p class="rk-empty-body"><?php echo esc_html__( 'Try a different search term or clear the filters.', 'rankkernel' ); ?></p>
						<a class="rk-ui-btn rk-ui-btn-secondary" href="<?php echo esc_url( $logView->clearUrl() ); ?>"><span class="rk-icon" aria-hidden="true">filter_alt_off</span><?php echo esc_html__( 'Clear filters', 'rankkernel' ); ?></a>
					</div>
				<?php else : ?>
					<div class="rk-ui-table-wrap">
						<table class="rk-ui-table">
							<thead>
								<tr>
									<th scope="col" class="rk-col-url"><?php echo esc_html__( 'URL', 'rankkernel' ); ?></th>
									<th scope="col" class="rk-col-status"><?php echo esc_html__( 'Status', 'rankkernel' ); ?></th>
									<th scope="col" class="rk-col-source"><?php echo esc_html__( 'Source', 'rankkernel' ); ?></th>
									<th scope="col" class="rk-col-time"><?php echo esc_html__( 'Time (UTC)', 'rankkernel' ); ?></th>
									<th scope="col" class="rk-col-message"><?php echo esc_html__( 'Message', 'rankkernel' ); ?></th>
									<th scope="col" class="rk-col-actions"><?php echo esc_html__( 'Actions', 'rankkernel' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $pageRows as $pageRow ) : ?>
									<?php
									/*
									 * The issue defines the retry action for any
									 * failure, including a permanent 4xx, and leaves
									 * the judgment to the admin, so a retry control
									 * renders for every row that did not already
									 * succeed. This is the negation of the success
									 * set rather than an enumeration of the failure
									 * set, so a future failure category stays
									 * retryable by default. Accepted and pending rows
									 * already succeeded, so they carry no control.
									 * The form posts the row id alone, never the URL.
									 */
									$retryable = ! in_array(
										$pageRow['category'],
										[ InstantIndexingOutcomes::CATEGORY_ACCEPTED, InstantIndexingOutcomes::CATEGORY_PENDING ],
										true
									);
									?>
									<tr>
										<td class="rk-col-url"><?php echo esc_html( $pageRow['url'] ); ?></td>
										<td class="rk-col-status"><span class="<?php echo esc_attr( $pageRow['statusPill'] ); ?>"><?php echo esc_html( $pageRow['statusLabel'] ); ?></span></td>
										<td class="rk-col-source"><span class="<?php echo esc_attr( $pageRow['sourcePill'] ); ?>"><?php echo esc_html( $pageRow['sourceLabel'] ); ?></span></td>
										<td class="rk-col-time"><?php echo esc_html( $pageRow['time'] ); ?></td>
										<td class="rk-col-message"><?php echo esc_html( $pageRow['message'] ); ?></td>
										<td class="rk-col-actions">
											<?php if ( $retryable ) : ?>
												<form method="post" action="" class="rk-retry-form">
													<?php wp_nonce_field( $nonceRetry ); ?>
													<input type="hidden" name="rankkernel_indexnow_action" value="retry" />
													<input type="hidden" name="rankkernel_indexnow_id" value="<?php echo esc_attr( (string) $pageRow['id'] ); ?>" />
													<?php submit_button( __( 'Retry', 'rankkernel' ), 'secondary rk-retry-submit', '', false ); ?>
												</form>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<div class="rk-log-footer">
						<span class="rk-showing"><?php echo esc_html( sprintf( /* translators: 1: first visible entry, 2: last visible entry, 3: total entries */ __( 'Showing %1$d to %2$d of %3$d entries', 'rankkernel' ), $showing['from'], $showing['to'], $showing['total'] ) ); ?></span>
						<?php if ( $pagination['show'] ) : ?>
							<div class="rk-ui-page-nums" role="navigation" aria-label="<?php echo esc_attr( __( 'Submission log pages', 'rankkernel' ) ); ?>">
								<?php if ( '' !== $pagination['prevUrl'] ) : ?>
									<a class="rk-ui-page-link" href="<?php echo esc_url( $pagination['prevUrl'] ); ?>"><?php echo esc_html__( 'Previous', 'rankkernel' ); ?></a>
								<?php else : ?>
									<span class="rk-ui-page-link is-disabled" aria-disabled="true"><?php echo esc_html__( 'Previous', 'rankkernel' ); ?></span>
								<?php endif; ?>
								<?php foreach ( $pagination['pages'] as $pageEntry ) : ?>
									<?php if ( $pageEntry['gap'] ) : ?>
										<span class="rk-ui-page-gap" aria-hidden="true"><?php echo esc_html( $pageEntry['label'] ); ?></span>
									<?php elseif ( $pageEntry['current'] ) : ?>
										<span class="rk-ui-page-link is-current" aria-current="page"><?php echo esc_html( $pageEntry['label'] ); ?></span>
									<?php else : ?>
										<a class="rk-ui-page-link" href="<?php echo esc_url( $pageEntry['url'] ); ?>"><?php echo esc_html( $pageEntry['label'] ); ?></a>
									<?php endif; ?>
								<?php endforeach; ?>
								<?php if ( '' !== $pagination['nextUrl'] ) : ?>
									<a class="rk-ui-page-link" href="<?php echo esc_url( $pagination['nextUrl'] ); ?>"><?php echo esc_html__( 'Next', 'rankkernel' ); ?></a>
								<?php else : ?>
									<span class="rk-ui-page-link is-disabled" aria-disabled="true"><?php echo esc_html__( 'Next', 'rankkernel' ); ?></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<?php if ( $hasFilter ) : ?>
							<a href="<?php echo esc_url( $logView->clearUrl() ); ?>" class="rk-filter-clear"><?php echo esc_html__( 'Clear filters', 'rankkernel' ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>

	</div>
</div>
