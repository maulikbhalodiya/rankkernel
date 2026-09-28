<?php
/**
 * 404 Monitor page view.
 *
 * Presentation only. NotFoundPage prepares every variable used below, and owns
 * capability checks, nonce verification, request handling, validation and
 * redirects. Nothing on this screen is invented: the summary figures, the
 * entry count, the capacity state and the list rows all come from the
 * controller, so a number that is absent here is absent on purpose.
 *
 * assets/js/monitor-admin.js is progressive enhancement for this view and is
 * unchanged. Its contract is therefore frozen: the root keeps the rk-monitor
 * class, the destructive links keep the rk-confirm class plus their
 * data-rk-confirm attribute, and the rk-bulk-form, rk-bulk-action,
 * rk-clear-form, rk-select-all, entry_ids[], rk-exclusions-body,
 * rk-exclusion-add, rk-exclusion-template and rk-exclusion-remove hooks all
 * stay exactly where the script looks for them.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var string $noticeSuccess       Success notice text, empty when none renders.
 * @var string $noticeWarning       Warning notice text, empty when none renders.
 * @var string $noticeError         Error notice text, empty when none renders.
 * @var string $carriedChain        Redirect chain path carried back from a redirect save.
 * @var bool   $carriedChainUnknown Whether the carried chain analysis was inconclusive.
 * @var string $carriedFinal        Recommended final destination carried back from a redirect save.
 * @var bool   $carriedMayLoop      Whether the carried loop check was inconclusive.
 * @var string $listSlug            Screen slug for the search form hidden field.
 * @var string $baseScreenUrl       Screen URL used by the clear and bulk forms.
 * @var string $searchFormUrl       Admin URL used by the list search form.
 * @var int    $summaryCount        Tracked address count.
 * @var int    $summaryMax          Configured maximum entries.
 * @var float  $summaryPercent      Current usage percent.
 * @var int    $summaryRetentionDays Retention window in days.
 * @var bool   $summaryHasRecent    Whether a most recent entry exists.
 * @var string $summaryRecentUri    Most recent entry URI.
 * @var string $summaryRecentSeen   Most recent entry last seen time.
 * @var string $nearState           Near limit state, one of normal, warn, high.
 * @var int    $nearMax             Configured maximum entries for the limit notice.
 * @var bool   $redirectsEnabled    Whether the Redirects module is enabled.
 * @var bool   $advancedFields      Whether advanced logging is enabled.
 * @var bool   $settingsAdvanced    Whether advanced logging is enabled.
 * @var bool   $settingsIgnoreQuery Whether query strings are ignored when logging.
 * @var int    $settingsRetention   Retention window in days.
 * @var int    $settingsMaxRows     Configured maximum entries.
 * @var int    $settingsFloodBudget New addresses allowed per flood window.
 * @var int    $settingsFloodWindow Length of the flood window in seconds.
 * @var array<string, string>            $settingsComparators Exclusion comparator labels.
 * @var array<int, array<string, mixed>> $exclusionRowItems   Prepared exclusion rows.
 * @var array<string, mixed>                $listFilters Current list filters.
 * @var array<int, array<string, string>>   $sortColumns Sortable column data.
 * @var array<int, array<string, mixed>>    $listRowItems Prepared log rows.
 * @var bool   $listIsEmpty         Whether the list has no rows.
 * @var bool   $listHasFilter       Whether a search filter is active.
 * @var int    $listTotal           Total matching entries.
 * @var array<string, mixed>                $pagination Pagination data.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap rk-monitor rk-ui">

	<?php /* Section 1: the page h1 WordPress expects, kept for screen readers only. */ ?>
	<h1 class="screen-reader-text"><?php echo esc_html__( '404 Monitor', 'rankkernel' ); ?></h1>

	<?php /* Section 2: notice row. No dismiss control renders, because no script on this screen wires one. */ ?>
	<?php if ( '' !== $noticeSuccess ) : ?>
		<div class="rk-ui-notice rk-ui-notice-success" role="status">
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">check_circle</span>
			<p class="rk-ui-notice-text"><?php echo esc_html( $noticeSuccess ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $noticeWarning ) : ?>
		<div class="rk-ui-notice rk-ui-notice-warning" role="status">
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">warning</span>
			<p class="rk-ui-notice-text"><?php echo esc_html( $noticeWarning ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $noticeError ) : ?>
		<div class="rk-ui-notice rk-ui-notice-error" role="alert">
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">error</span>
			<p class="rk-ui-notice-text"><?php echo esc_html( $noticeError ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $carriedChain ) : ?>
		<div class="rk-ui-notice rk-ui-notice-warning" role="status">
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">warning</span>
			<p class="rk-ui-notice-text">
				<?php
				echo esc_html( sprintf( /* translators: %s: redirect chain path */ __( 'Redirect chain detected: %s.', 'rankkernel' ), $carriedChain ) );
				if ( $carriedChainUnknown || '' === $carriedFinal ) {
					echo ' ';
					echo esc_html__( 'RankKernel could not determine the final destination, so please verify the chain manually. Saved as entered.', 'rankkernel' );
				} else {
					echo ' ';
					echo esc_html( sprintf( /* translators: %s: recommended final destination */ __( 'Consider pointing the source directly to %s.', 'rankkernel' ), $carriedFinal ) );
				}
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $carriedMayLoop ) : ?>
		<div class="rk-ui-notice rk-ui-notice-warning" role="status">
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">warning</span>
			<p class="rk-ui-notice-text"><?php echo esc_html__( 'The loop check could not fully verify this redirect, so a loop is still possible. Please verify it manually.', 'rankkernel' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $carriedChainUnknown && '' === $carriedChain ) : ?>
		<div class="rk-ui-notice rk-ui-notice-info" role="status">
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">info</span>
			<p class="rk-ui-notice-text"><?php echo esc_html__( 'Chain analysis could not determine the final destination because the next rule uses a pattern matcher. Saved as entered.', 'rankkernel' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( 'normal' !== $nearState ) : ?>
		<?php if ( 'high' === $nearState ) : ?>
			<div class="rk-ui-notice rk-ui-notice-warning" role="status">
				<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">warning</span>
				<p class="rk-ui-notice-text"><?php echo esc_html( sprintf( /* translators: %s: configured maximum entry count */ __( 'Your 404 log is nearly at its configured entry limit of %s entries. The oldest entries are removed automatically when the limit is reached. You can clear the log manually at any time.', 'rankkernel' ), number_format_i18n( $nearMax ) ) ); ?></p>
			</div>
		<?php else : ?>
			<div class="rk-ui-notice rk-ui-notice-info" role="status">
				<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">info</span>
				<p class="rk-ui-notice-text"><?php echo esc_html( sprintf( /* translators: %s: configured maximum entry count */ __( 'Your 404 log is approaching its configured entry limit of %s entries. The oldest entries are removed automatically when the limit is reached. You can clear the log manually at any time.', 'rankkernel' ), number_format_i18n( $nearMax ) ) ); ?></p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php
	/*
	 * The capacity state arrives from the controller as a three step enum. The
	 * pill spells the state out in words and the fill colour follows the same
	 * enum, so neither the badge nor the bar relies on colour alone.
	 */
	if ( 'high' === $nearState ) :
		$rkCapacityPill  = 'rk-ui-pill rk-ui-pill-danger';
		$rkCapacityLabel = __( 'Nearly at the entry limit', 'rankkernel' );
		$rkFillModifier  = 'rk-fill-high';
	elseif ( 'warn' === $nearState ) :
		$rkCapacityPill  = 'rk-ui-pill rk-ui-pill-warning';
		$rkCapacityLabel = __( 'Approaching the entry limit', 'rankkernel' );
		$rkFillModifier  = 'rk-fill-warn';
	else :
		$rkCapacityPill  = 'rk-ui-pill rk-ui-pill-success';
		$rkCapacityLabel = __( 'Within the entry limit', 'rankkernel' );
		$rkFillModifier  = 'rk-fill-normal';
	endif;
	?>

	<?php /* Section 3: page header card. The capacity pill is the only thing beside the title. */ ?>
	<header class="rk-ui-card rk-ui-page-header">
		<div class="rk-ui-page-header-text">
			<div class="rk-ui-page-header-title-row">
				<h2 class="rk-ui-page-title"><?php echo esc_html__( '404 Monitor', 'rankkernel' ); ?></h2>
				<span class="<?php echo esc_attr( $rkCapacityPill ); ?>"><?php echo esc_html( $rkCapacityLabel ); ?></span>
			</div>
			<p class="rk-ui-sub"><?php echo esc_html__( 'See which missing pages visitors hit, then turn the busy ones into redirects. Oldest entries prune automatically, and you can clear the log at any time.', 'rankkernel' ); ?></p>
		</div>
	</header>

	<?php /* Section 4: log status card. The figures come from the controller, never from a mockup. */ ?>
	<div class="rk-ui-card rk-summary">
		<div class="rk-ui-card-header">
			<div class="rk-ui-card-header-left">
				<h3 class="rk-ui-card-title"><?php echo esc_html__( 'Log Status', 'rankkernel' ); ?></h3>
			</div>
			<form method="post" action="<?php echo esc_url( $baseScreenUrl ); ?>" id="rk-clear-form" data-rk-confirm="<?php echo esc_attr__( 'Clear the whole 404 log? This cannot be undone.', 'rankkernel' ); ?>">
				<?php wp_nonce_field( 'rankkernel_404_clear' ); ?>
				<?php submit_button( __( 'Clear Log', 'rankkernel' ), 'secondary rk-compact-submit', 'rankkernel_404_clear', false ); ?>
			</form>
		</div>

		<div class="rk-summary-body">
			<div class="rk-stat-grid" role="region" aria-label="<?php echo esc_attr__( '404 log status figures', 'rankkernel' ); ?>">
				<div class="rk-stat">
					<span class="rk-stat-label"><?php echo esc_html__( 'Tracked addresses', 'rankkernel' ); ?></span>
					<span class="rk-stat-value"><?php echo esc_html( (string) number_format_i18n( $summaryCount ) ); ?></span>
				</div>

				<div class="rk-stat rk-stat-wide">
					<span class="rk-stat-label"><?php echo esc_html__( 'Usage', 'rankkernel' ); ?></span>
					<span class="rk-stat-value"><?php echo esc_html( sprintf( /* translators: %1$s: current entry count, %2$s: configured maximum */ __( '%1$s / %2$s', 'rankkernel' ), number_format_i18n( $summaryCount ), number_format_i18n( $summaryMax ) ) ); ?></span>
					<div class="rk-progress" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $summaryPercent ); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php echo esc_attr__( '404 Log Storage Usage', 'rankkernel' ); ?>" aria-valuetext="<?php echo esc_attr( sprintf( /* translators: %1$s: current entry count, %2$s: configured maximum */ __( '%1$s of %2$s entries used', 'rankkernel' ), number_format_i18n( $summaryCount ), number_format_i18n( $summaryMax ) ) ); ?>"><div class="rk-progress-fill <?php echo esc_attr( $rkFillModifier ); ?>" style="width:<?php echo esc_attr( (string) $summaryPercent ); ?>%"></div></div>
				</div>

				<div class="rk-stat">
					<span class="rk-stat-label"><?php echo esc_html__( 'Retention', 'rankkernel' ); ?></span>
					<span class="rk-stat-value"><?php echo esc_html( sprintf( /* translators: %d: retention period in days */ __( '%d days', 'rankkernel' ), $summaryRetentionDays ) ); ?></span>
					<span class="rk-stat-hint"><?php echo esc_html__( 'Entries older than this are removed automatically, oldest first.', 'rankkernel' ); ?></span>
				</div>

				<?php if ( $summaryHasRecent ) : ?>
					<div class="rk-stat">
						<span class="rk-stat-label"><?php echo esc_html__( 'Most recent', 'rankkernel' ); ?></span>
						<span class="rk-stat-value rk-stat-small"><?php echo '' === $summaryRecentSeen ? esc_html__( 'Unknown', 'rankkernel' ) : esc_html( $summaryRecentSeen ); ?></span>
						<span class="rk-stat-hint rk-stat-uri"><?php echo esc_html( $summaryRecentUri ); ?></span>
					</div>
				<?php endif; ?>
			</div>

			<p class="rk-sub rk-summary-note"><?php echo esc_html__( 'Manual clearing is separate from automatic pruning and removes entries in bounded batches.', 'rankkernel' ); ?></p>
		</div>
	</div>

	<?php /* Section 5: the searchable, sortable, paginated list. */ ?>
	<div class="rk-ui-card rk-log-card">

		<div class="rk-ui-card-header">
			<div class="rk-ui-card-header-left">
				<h3 class="rk-ui-card-title"><?php echo esc_html__( 'Tracked 404s', 'rankkernel' ); ?></h3>
				<span class="rk-entry-count"><?php echo esc_html( sprintf( /* translators: %d: number of tracked 404 entries */ __( '%d entries', 'rankkernel' ), $listTotal ) ); ?></span>
			</div>
		</div>

		<div class="rk-log-toolbar">
			<form method="get" action="<?php echo esc_url( $searchFormUrl ); ?>" class="rk-log-filters" role="search" aria-label="<?php echo esc_attr__( 'Search tracked 404 addresses', 'rankkernel' ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( $listSlug ); ?>" />
				<div class="rk-ui-search-wrap">
					<label for="rk-search-input" class="screen-reader-text"><?php echo esc_html__( 'Search addresses', 'rankkernel' ); ?></label>
					<span class="rk-icon rk-ui-search-icon" aria-hidden="true">search</span>
					<input
						type="search"
						id="rk-search-input"
						name="s"
						value="<?php echo esc_attr( (string) $listFilters['search'] ); ?>"
						placeholder="<?php echo esc_attr__( 'Search addresses', 'rankkernel' ); ?>"
						class="rk-ui-search-input"
					/>
				</div>
				<?php submit_button( __( 'Search', 'rankkernel' ), 'secondary rk-compact-submit', '', false ); ?>
			</form>
		</div>

		<?php if ( ! $redirectsEnabled ) : ?>
			<div class="rk-log-info">
				<div class="rk-ui-notice rk-ui-notice-info" role="status">
					<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">info</span>
					<p class="rk-ui-notice-text"><?php echo esc_html__( 'Redirect creation needs the Redirects module. Enable Redirects to turn a 404 entry into a redirect.', 'rankkernel' ); ?></p>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $listIsEmpty ) : ?>
			<div class="rk-empty">
				<?php if ( $listHasFilter ) : ?>
					<div class="rk-empty-icon" aria-hidden="true"><span class="rk-icon" aria-hidden="true">search_off</span></div>
					<p class="rk-empty-title"><?php echo esc_html__( 'No 404 entries match your search.', 'rankkernel' ); ?></p>
					<p class="rk-empty-body"><?php echo esc_html__( 'Try a different search or clear it to see every tracked address.', 'rankkernel' ); ?></p>
					<a class="rk-ui-btn rk-ui-btn-secondary" href="<?php echo esc_url( $baseScreenUrl ); ?>"><span class="rk-icon" aria-hidden="true">filter_alt_off</span><?php echo esc_html__( 'Clear search', 'rankkernel' ); ?></a>
				<?php else : ?>
					<div class="rk-empty-icon" aria-hidden="true"><span class="rk-icon" aria-hidden="true">inbox</span></div>
					<p class="rk-empty-title"><?php echo esc_html__( 'No 404 entries are being tracked yet.', 'rankkernel' ); ?></p>
					<p class="rk-empty-body"><?php echo esc_html__( 'When visitors hit a missing page, its address appears here with hit counts and first and last seen times.', 'rankkernel' ); ?></p>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( $baseScreenUrl ); ?>" id="rk-bulk-form" data-rk-confirm="<?php echo esc_attr__( 'Delete the selected entries? This cannot be undone.', 'rankkernel' ); ?>">
				<?php wp_nonce_field( 'rankkernel_404_bulk' ); ?>

				<div class="rk-log-actions">
					<div class="rk-bulk-controls">
						<label for="rk-bulk-action" class="screen-reader-text"><?php echo esc_html__( 'Select bulk action', 'rankkernel' ); ?></label>
						<div class="rk-ui-select-wrap">
							<select name="rk_bulk_action" id="rk-bulk-action" class="rk-ui-select">
								<option value=""><?php echo esc_html__( 'Bulk actions', 'rankkernel' ); ?></option>
								<option value="delete"><?php echo esc_html__( 'Delete', 'rankkernel' ); ?></option>
							</select>
							<span class="rk-icon rk-ui-select-chevron" aria-hidden="true">expand_more</span>
						</div>
						<?php submit_button( __( 'Apply', 'rankkernel' ), 'secondary rk-compact-submit', 'rankkernel_404_bulk', false ); ?>
					</div>

					<?php if ( $pagination['has'] ) : ?>
						<?php /* The footer below is the labelled navigation landmark, so this repeat stays an unlabelled group. */ ?>
						<div class="rk-pages-top">
							<span class="rk-paging-text"><?php echo esc_html( $pagination['label'] ); ?></span>
							<?php if ( '' !== $pagination['previousUrl'] ) : ?>
								<a class="rk-ui-page-link" href="<?php echo esc_url( $pagination['previousUrl'] ); ?>"><?php echo esc_html__( 'Previous', 'rankkernel' ); ?></a>
							<?php else : ?>
								<span class="rk-ui-page-link is-disabled" aria-disabled="true"><?php echo esc_html__( 'Previous', 'rankkernel' ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $pagination['nextUrl'] ) : ?>
								<a class="rk-ui-page-link" href="<?php echo esc_url( $pagination['nextUrl'] ); ?>"><?php echo esc_html__( 'Next', 'rankkernel' ); ?></a>
							<?php else : ?>
								<span class="rk-ui-page-link is-disabled" aria-disabled="true"><?php echo esc_html__( 'Next', 'rankkernel' ); ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="rk-ui-table-wrap">
					<table class="rk-ui-table rk-table">
						<thead>
							<tr>
								<th scope="col" class="rk-col-check">
									<label for="rk-select-all" class="screen-reader-text"><?php echo esc_html__( 'Select All', 'rankkernel' ); ?></label>
									<input type="checkbox" id="rk-select-all" />
								</th>
								<?php foreach ( $sortColumns as $sortColumn ) : ?>
									<th scope="col" class="<?php echo esc_attr( $sortColumn['class'] ); ?>">
										<a class="rk-sort-link" href="<?php echo esc_url( $sortColumn['url'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: column label */ __( 'Sort by %s', 'rankkernel' ), $sortColumn['label'] ) ); ?>">
											<span class="rk-sort-label"><?php echo esc_html( $sortColumn['label'] ); ?></span>
											<?php if ( '' !== $sortColumn['arrow'] ) : ?>
												<span class="rk-sort-arrow" aria-hidden="true"><?php echo esc_html( $sortColumn['arrow'] ); ?></span>
											<?php endif; ?>
										</a>
									</th>
								<?php endforeach; ?>
								<th scope="col" class="rk-col-actions"><?php echo esc_html__( 'Actions', 'rankkernel' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $listRowItems as $listRowItem ) : ?>
								<tr>
									<th scope="row" class="rk-col-check">
										<input type="checkbox" name="entry_ids[]" value="<?php echo esc_attr( (string) $listRowItem['id'] ); ?>" aria-label="<?php echo esc_attr( $listRowItem['selectLabel'] ); ?>" />
									</th>
									<td class="rk-col-uri"><strong class="rk-uri-value"><?php echo esc_html( $listRowItem['uri'] ); ?></strong>
										<details class="rk-details">
											<summary><span class="rk-icon" aria-hidden="true">expand_more</span><?php echo esc_html__( 'Details', 'rankkernel' ); ?></summary>
											<dl class="rk-detail-list">
												<dt><?php echo esc_html__( 'Full address', 'rankkernel' ); ?></dt><dd><?php echo esc_html( $listRowItem['uri'] ); ?></dd>
												<dt><?php echo esc_html__( 'Hits', 'rankkernel' ); ?></dt><dd><?php echo esc_html( (string) number_format_i18n( $listRowItem['hits'] ) ); ?></dd>
												<dt><?php echo esc_html__( 'First seen', 'rankkernel' ); ?></dt><dd><?php echo '' === $listRowItem['created'] ? esc_html__( 'Unknown', 'rankkernel' ) : esc_html( $listRowItem['created'] ); ?></dd>
												<dt><?php echo esc_html__( 'Last seen', 'rankkernel' ); ?></dt><dd><?php echo '' === $listRowItem['seen'] ? esc_html__( 'Unknown', 'rankkernel' ) : esc_html( $listRowItem['seen'] ); ?></dd>
												<?php if ( $advancedFields ) : ?>
													<dt><?php echo esc_html__( 'Referer', 'rankkernel' ); ?></dt><dd><?php echo '' === $listRowItem['referer'] ? esc_html__( 'None recorded', 'rankkernel' ) : esc_html( $listRowItem['referer'] ); ?></dd>
													<dt><?php echo esc_html__( 'User agent', 'rankkernel' ); ?></dt><dd><?php echo '' === $listRowItem['agent'] ? esc_html__( 'None recorded', 'rankkernel' ) : esc_html( $listRowItem['agent'] ); ?></dd>
												<?php endif; ?>
											</dl>
											<?php if ( ! $advancedFields ) : ?>
												<p class="rk-sub"><?php echo esc_html__( 'Referer and user agent logging is off. Turn on Advanced fields in Monitor Settings below to capture them.', 'rankkernel' ); ?></p>
											<?php endif; ?>
										</details>
									</td>
									<td class="rk-col-hits"><?php echo esc_html( (string) number_format_i18n( $listRowItem['hits'] ) ); ?></td>
									<td class="rk-col-created"><?php echo '' === $listRowItem['created'] ? esc_html__( 'Unknown', 'rankkernel' ) : esc_html( $listRowItem['created'] ); ?></td>
									<td class="rk-col-seen"><?php echo '' === $listRowItem['seen'] ? esc_html__( 'Unknown', 'rankkernel' ) : esc_html( $listRowItem['seen'] ); ?></td>
									<td class="rk-col-actions">
										<?php if ( $redirectsEnabled ) : ?>
											<a class="rk-ui-btn rk-ui-btn-secondary rk-row-action" href="<?php echo esc_url( $listRowItem['createRedirectUrl'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: missing page URL */ __( 'Create redirect for %s', 'rankkernel' ), $listRowItem['uri'] ) ); ?>"><span class="rk-icon" aria-hidden="true">alt_route</span><?php echo esc_html__( 'Create Redirect', 'rankkernel' ); ?></a>
										<?php endif; ?>
										<a class="rk-ui-btn rk-ui-btn-danger rk-row-action rk-confirm" href="<?php echo esc_url( $listRowItem['deleteUrl'] ); ?>" data-rk-confirm="<?php echo esc_attr__( 'Delete this entry? This cannot be undone.', 'rankkernel' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: missing page URL */ __( 'Delete 404 entry for %s', 'rankkernel' ), $listRowItem['uri'] ) ); ?>"><span class="rk-icon" aria-hidden="true">cancel</span><?php echo esc_html__( 'Delete', 'rankkernel' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="rk-log-footer">
					<span class="rk-showing"><?php echo esc_html( sprintf( /* translators: %d: total number of 404 entries */ __( '%d items', 'rankkernel' ), $listTotal ) ); ?></span>
					<?php if ( $pagination['has'] ) : ?>
						<nav class="rk-pages-bottom" aria-label="<?php echo esc_attr__( '404 log pages', 'rankkernel' ); ?>">
							<span class="rk-paging-text"><?php echo esc_html( $pagination['label'] ); ?></span>
							<?php if ( '' !== $pagination['previousUrl'] ) : ?>
								<a class="rk-ui-page-link" href="<?php echo esc_url( $pagination['previousUrl'] ); ?>"><?php echo esc_html__( 'Previous', 'rankkernel' ); ?></a>
							<?php else : ?>
								<span class="rk-ui-page-link is-disabled" aria-disabled="true"><?php echo esc_html__( 'Previous', 'rankkernel' ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $pagination['nextUrl'] ) : ?>
								<a class="rk-ui-page-link" href="<?php echo esc_url( $pagination['nextUrl'] ); ?>"><?php echo esc_html__( 'Next', 'rankkernel' ); ?></a>
							<?php else : ?>
								<span class="rk-ui-page-link is-disabled" aria-disabled="true"><?php echo esc_html__( 'Next', 'rankkernel' ); ?></span>
							<?php endif; ?>
						</nav>
					<?php endif; ?>
				</div>
			</form>
		<?php endif; ?>
	</div>

	<?php /* Section 6: monitor settings, collapsed. Every control posts the real settings form. */ ?>
	<details class="rk-ui-card rk-monitor-settings" id="rk-monitor-settings">
		<summary class="rk-monitor-settings-summary">
			<span class="rk-monitor-settings-heading">
				<span class="rk-monitor-settings-name"><span class="rk-icon" aria-hidden="true">settings</span><?php echo esc_html__( 'Monitor Settings', 'rankkernel' ); ?></span>
				<span class="rk-monitor-settings-hint"><?php echo esc_html__( 'Retention, entry limit, flood guard and address exclusions', 'rankkernel' ); ?></span>
			</span>
			<span class="rk-icon rk-monitor-settings-chevron" aria-hidden="true">expand_more</span>
		</summary>

		<form method="post" action="" class="rk-monitor-settings-form">
			<?php wp_nonce_field( 'rankkernel_404_settings' ); ?>

			<table class="form-table rk-form-table" role="presentation"><tbody>
				<tr><th scope="row"><?php echo esc_html__( 'Advanced fields', 'rankkernel' ); ?></th><td>
					<label><input type="checkbox" name="rk_advanced_fields" value="1" <?php echo checked( $settingsAdvanced, true, false ); ?> /> <?php echo esc_html__( 'Store the referer and user agent with each entry.', 'rankkernel' ); ?></label>
					<p class="description"><?php echo esc_html__( 'Optional and off by default. Values are truncated to 255 characters. IP addresses are never stored.', 'rankkernel' ); ?></p></td></tr>

				<tr><th scope="row"><label for="rk-retention"><?php echo esc_html__( 'Retention days', 'rankkernel' ); ?></label></th><td>
					<input type="number" id="rk-retention" name="rk_retention_days" value="<?php echo esc_attr( (string) $settingsRetention ); ?>" class="small-text" min="1" max="365" />
					<p class="description"><?php echo esc_html__( 'Entries older than this many days are removed automatically, from 1 to 365.', 'rankkernel' ); ?></p></td></tr>

				<tr><th scope="row"><label for="rk-max-rows"><?php echo esc_html__( 'Maximum entries', 'rankkernel' ); ?></label></th><td>
					<input type="number" id="rk-max-rows" name="rk_max_rows" value="<?php echo esc_attr( (string) $settingsMaxRows ); ?>" class="small-text" min="100" max="10000" />
					<p class="description"><?php echo esc_html__( 'Maximum entries kept in the log, from 100 to 10000. When the limit is reached, the oldest entries are removed first.', 'rankkernel' ); ?></p></td></tr>

				<tr><th scope="row"><label for="rk-flood-budget"><?php echo esc_html__( 'Flood budget', 'rankkernel' ); ?></label></th><td>
					<input type="number" id="rk-flood-budget" name="rk_flood_budget" value="<?php echo esc_attr( (string) $settingsFloodBudget ); ?>" class="small-text" min="1" max="1000" />
					<p class="description"><?php echo esc_html__( 'New addresses allowed per time window, from 1 to 1000. Repeat hits on known addresses always keep counting.', 'rankkernel' ); ?></p></td></tr>

				<tr><th scope="row"><label for="rk-flood-window"><?php echo esc_html__( 'Flood window', 'rankkernel' ); ?></label></th><td>
					<input type="number" id="rk-flood-window" name="rk_flood_window" value="<?php echo esc_attr( (string) $settingsFloodWindow ); ?>" class="small-text" min="60" max="3600" />
					<p class="description"><?php echo esc_html__( 'Length of the flood window in seconds, from 60 to 3600.', 'rankkernel' ); ?></p></td></tr>

				<tr><th scope="row"><?php echo esc_html__( 'Query strings', 'rankkernel' ); ?></th><td>
					<label><input type="checkbox" name="rk_ignore_query" value="1" <?php echo checked( $settingsIgnoreQuery, true, false ); ?> /> <?php echo esc_html__( 'Ignore the query string when logging.', 'rankkernel' ); ?></label>
					<p class="description"><?php echo esc_html__( 'On by default. Turn off to track each query string as a separate entry.', 'rankkernel' ); ?></p></td></tr>
			</tbody></table>

			<h4 class="rk-subheading"><?php echo esc_html__( 'Exclusions', 'rankkernel' ); ?></h4>
			<p class="rk-sub"><?php echo esc_html__( 'Skip logging for addresses that match a rule. Add as many rows as needed. Matching is case sensitive. Examples: Prefix /wp-admin/, Contains utm_, Exact /old-page.', 'rankkernel' ); ?></p>

			<div class="rk-ui-table-wrap rk-exclusions-wrap">
				<table class="rk-ui-table rk-exclusions">
					<thead>
						<tr>
							<th scope="col" class="rk-col-compare"><?php echo esc_html__( 'Compare', 'rankkernel' ); ?></th>
							<th scope="col" class="rk-col-value"><?php echo esc_html__( 'Value', 'rankkernel' ); ?></th>
							<th scope="col" class="rk-col-remove"><span class="screen-reader-text"><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></span></th>
						</tr>
					</thead>
					<tbody id="rk-exclusions-body">
						<?php foreach ( $exclusionRowItems as $exclusionRowItem ) : ?>
							<?php
							$rkExclRemoveLabel = '' !== $exclusionRowItem['value']
								? sprintf( /* translators: %s: exclusion value or pattern */ __( 'Remove exclusion rule for %s', 'rankkernel' ), $exclusionRowItem['value'] )
								: __( 'Remove exclusion rule', 'rankkernel' );
							?>
							<tr class="rk-exclusion-row"><td>
								<div class="rk-ui-select-wrap">
									<select name="rk_excl_comparator[]" aria-label="<?php echo esc_attr__( 'How to compare', 'rankkernel' ); ?>" class="rk-ui-select rk-exclusion-select">
										<?php foreach ( $settingsComparators as $comparatorOption => $comparatorLabel ) : ?>
											<option value="<?php echo esc_attr( $comparatorOption ); ?>"<?php echo selected( $exclusionRowItem['comparator'], $comparatorOption, false ); ?>><?php echo esc_html( $comparatorLabel ); ?></option>
										<?php endforeach; ?>
									</select>
									<span class="rk-icon rk-ui-select-chevron" aria-hidden="true">expand_more</span>
								</div></td>
								<td><input type="text" name="rk_excl_value[]" value="<?php echo esc_attr( $exclusionRowItem['value'] ); ?>" class="rk-exclusion-value" maxlength="500" aria-label="<?php echo esc_attr__( 'Exclusion value', 'rankkernel' ); ?>" /></td>
								<td><button type="button" class="rk-ui-btn rk-ui-btn-danger rk-exclusion-remove" aria-label="<?php echo esc_attr( $rkExclRemoveLabel ); ?>"><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></button></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<template id="rk-exclusion-template"><tr class="rk-exclusion-row"><td>
				<div class="rk-ui-select-wrap">
					<select name="rk_excl_comparator[]" aria-label="<?php echo esc_attr__( 'How to compare', 'rankkernel' ); ?>" class="rk-ui-select rk-exclusion-select">
						<?php foreach ( $settingsComparators as $comparatorOption => $comparatorLabel ) : ?>
							<option value="<?php echo esc_attr( $comparatorOption ); ?>"<?php echo selected( 'prefix', $comparatorOption, false ); ?>><?php echo esc_html( $comparatorLabel ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="rk-icon rk-ui-select-chevron" aria-hidden="true">expand_more</span>
				</div></td>
				<td><input type="text" name="rk_excl_value[]" value="" class="rk-exclusion-value" maxlength="500" aria-label="<?php echo esc_attr__( 'Exclusion value', 'rankkernel' ); ?>" /></td>
				<td><button type="button" class="rk-ui-btn rk-ui-btn-danger rk-exclusion-remove" aria-label="<?php echo esc_attr__( 'Remove exclusion rule', 'rankkernel' ); ?>"><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></button></td></tr></template>

			<div class="rk-exclusion-controls">
				<button type="button" class="rk-ui-btn rk-ui-btn-secondary" id="rk-exclusion-add"><span class="rk-icon" aria-hidden="true">add</span><?php echo esc_html__( 'Add Exclusion', 'rankkernel' ); ?></button>
				<span class="rk-sub"><?php echo esc_html__( 'Without JavaScript, clear a row value and save to remove its rule.', 'rankkernel' ); ?></span>
			</div>

			<div class="rk-monitor-settings-actions">
				<?php submit_button( __( 'Save Monitor Settings', 'rankkernel' ), 'primary rk-compact-submit', 'rankkernel_404_settings_save' ); ?>
			</div>
		</form>
	</details>
</div>
