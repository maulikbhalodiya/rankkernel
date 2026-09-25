<?php
/**
 * 404 Monitor page view.
 *
 * Presentation only. NotFoundPage prepares every variable used below, and owns
 * capability checks, nonce verification, request handling, validation and
 * redirects.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var string $noticeSuccess       Success notice text, empty when none renders.
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

if ( '' !== $noticeSuccess ) :
	?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $noticeSuccess ); ?></p></div>
	<?php
endif;

if ( '' !== $noticeError ) :
	?>
	<div class="notice notice-error"><p><?php echo esc_html( $noticeError ); ?></p></div>
	<?php
endif;

if ( '' !== $carriedChain ) :
	?>
	<div class="notice notice-warning is-dismissible"><p>
		<?php echo esc_html( sprintf( /* translators: %s: redirect chain path */ __( 'Redirect chain detected: %s.', 'rankkernel' ), $carriedChain ) ); ?>
		<?php
		if ( $carriedChainUnknown || '' === $carriedFinal ) {
			echo ' ';
			echo esc_html__( 'RankKernel could not determine the final destination, so please verify the chain manually. Saved as entered.', 'rankkernel' );
		} else {
			echo ' ';
			echo esc_html( sprintf( /* translators: %s: recommended final destination */ __( 'Consider pointing the source directly to %s.', 'rankkernel' ), $carriedFinal ) );
		}
		?>
	</p></div>
	<?php
endif;

if ( $carriedMayLoop ) :
	?>
	<div class="notice notice-warning is-dismissible"><p><?php echo esc_html__( 'The loop check could not fully verify this redirect, so a loop is still possible. Please verify it manually.', 'rankkernel' ); ?></p></div>
	<?php
endif;

if ( $carriedChainUnknown && '' === $carriedChain ) :
	?>
	<div class="notice notice-info is-dismissible"><p><?php echo esc_html__( 'Chain analysis could not determine the final destination because the next rule uses a pattern matcher. Saved as entered.', 'rankkernel' ); ?></p></div>
	<?php
endif;
?>
<div class="wrap rk-monitor">
	<h1 class="wp-heading-inline"><?php echo esc_html__( '404 Monitor', 'rankkernel' ); ?></h1>
	<p class="rk-sub"><?php echo esc_html__( 'See which missing pages visitors hit, then turn the busy ones into redirects. Oldest entries prune automatically, and you can clear the log at any time.', 'rankkernel' ); ?></p>

	<div class="rk-card rk-summary">
		<h2><?php echo esc_html__( 'Log Status', 'rankkernel' ); ?></h2>

		<div class="rk-stat-grid">
			<div class="rk-stat"><span class="rk-stat-label"><?php echo esc_html__( 'Tracked addresses', 'rankkernel' ); ?></span><span class="rk-stat-value"><?php echo esc_html( (string) number_format_i18n( $summaryCount ) ); ?></span></div>

			<div class="rk-stat rk-stat-wide"><span class="rk-stat-label"><?php echo esc_html__( 'Usage', 'rankkernel' ); ?></span><span class="rk-stat-value"><?php echo esc_html( sprintf( /* translators: %1$s: current entry count, %2$s: configured maximum */ __( '%1$s / %2$s', 'rankkernel' ), number_format_i18n( $summaryCount ), number_format_i18n( $summaryMax ) ) ); ?></span>
				<div class="rk-progress" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $summaryPercent ); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php echo esc_attr__( '404 Log Storage Usage', 'rankkernel' ); ?>" aria-valuetext="<?php echo esc_attr( sprintf( /* translators: %1$s: current entry count, %2$s: configured maximum */ __( '%1$s of %2$s entries used', 'rankkernel' ), number_format_i18n( $summaryCount ), number_format_i18n( $summaryMax ) ) ); ?>"><div class="rk-progress-fill" style="width:<?php echo esc_attr( (string) $summaryPercent ); ?>%"></div></div>
			</div>

			<div class="rk-stat"><span class="rk-stat-label"><?php echo esc_html__( 'Retention', 'rankkernel' ); ?></span><span class="rk-stat-value"><?php echo esc_html( sprintf( /* translators: %d: retention period in days */ __( '%d days', 'rankkernel' ), $summaryRetentionDays ) ); ?></span><span class="rk-stat-hint"><?php echo esc_html__( 'Entries older than this are removed automatically, oldest first.', 'rankkernel' ); ?></span></div>

			<?php if ( $summaryHasRecent ) : ?>
				<div class="rk-stat"><span class="rk-stat-label"><?php echo esc_html__( 'Most recent', 'rankkernel' ); ?></span><span class="rk-stat-value rk-stat-small"><?php echo '' === $summaryRecentSeen ? esc_html__( 'Unknown', 'rankkernel' ) : esc_html( $summaryRecentSeen ); ?></span><span class="rk-stat-hint"><?php echo esc_html( $summaryRecentUri ); ?></span></div>
			<?php endif; ?>
		</div>

		<form method="post" action="<?php echo esc_url( $baseScreenUrl ); ?>" id="rk-clear-form" data-rk-confirm="<?php echo esc_attr__( 'Clear the whole 404 log? This cannot be undone.', 'rankkernel' ); ?>">
			<?php wp_nonce_field( 'rankkernel_404_clear' ); ?>
			<?php submit_button( __( 'Clear Log', 'rankkernel' ), 'secondary', 'rankkernel_404_clear', false ); ?>
			<span class="rk-sub"><?php echo esc_html__( 'Manual clearing is separate from automatic pruning and removes entries in bounded batches.', 'rankkernel' ); ?></span>
		</form>
	</div>

	<?php if ( 'normal' !== $nearState ) : ?>
		<?php if ( 'high' === $nearState ) : ?>
			<div class="notice notice-warning"><p><?php echo esc_html( sprintf( /* translators: %s: configured maximum entry count */ __( 'Your 404 log is nearly at its configured entry limit of %s entries. The oldest entries are removed automatically when the limit is reached. You can clear the log manually at any time.', 'rankkernel' ), number_format_i18n( $nearMax ) ) ); ?></p></div>
		<?php else : ?>
			<div class="notice notice-info"><p><?php echo esc_html( sprintf( /* translators: %s: configured maximum entry count */ __( 'Your 404 log is approaching its configured entry limit of %s entries. The oldest entries are removed automatically when the limit is reached. You can clear the log manually at any time.', 'rankkernel' ), number_format_i18n( $nearMax ) ) ); ?></p></div>
		<?php endif; ?>
	<?php endif; ?>

	<h2><?php echo esc_html__( 'Tracked 404s', 'rankkernel' ); ?></h2>

	<form method="get" action="<?php echo esc_url( $searchFormUrl ); ?>" class="rk-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( $listSlug ); ?>" />
		<p class="search-box">
			<label for="rk-search-input" class="screen-reader-text"><?php echo esc_html__( 'Search addresses', 'rankkernel' ); ?></label>
			<input type="search" id="rk-search-input" name="s" value="<?php echo esc_attr( (string) $listFilters['search'] ); ?>" placeholder="<?php echo esc_attr__( 'Search addresses', 'rankkernel' ); ?>" />
			<?php submit_button( __( 'Search', 'rankkernel' ), '', '', false ); ?>
		</p><br class="clear" />
	</form>

	<?php if ( ! $redirectsEnabled ) : ?>
		<div class="notice notice-info inline"><p><?php echo esc_html__( 'Redirect creation needs the Redirects module. Enable Redirects to turn a 404 entry into a redirect.', 'rankkernel' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $listIsEmpty ) : ?>
		<div class="rk-empty">
			<?php if ( $listHasFilter ) : ?>
				<p><strong><?php echo esc_html__( 'No 404 entries match your search.', 'rankkernel' ); ?></strong></p>
				<p><?php echo esc_html__( 'Try a different search or clear it to see every tracked address.', 'rankkernel' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( $baseScreenUrl ); ?>"><?php echo esc_html__( 'Clear search', 'rankkernel' ); ?></a></p>
			<?php else : ?>
				<p><strong><?php echo esc_html__( 'No 404 entries are being tracked yet.', 'rankkernel' ); ?></strong></p>
				<p><?php echo esc_html__( 'When visitors hit a missing page, its address appears here with hit counts and first and last seen times.', 'rankkernel' ); ?></p>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( $baseScreenUrl ); ?>" id="rk-bulk-form" data-rk-confirm="<?php echo esc_attr__( 'Delete the selected entries? This cannot be undone.', 'rankkernel' ); ?>">
			<?php wp_nonce_field( 'rankkernel_404_bulk' ); ?>

			<div class="tablenav top"><div class="alignleft actions bulkactions">
				<label for="rk-bulk-action" class="screen-reader-text"><?php echo esc_html__( 'Select bulk action', 'rankkernel' ); ?></label>
				<select name="rk_bulk_action" id="rk-bulk-action">
					<option value=""><?php echo esc_html__( 'Bulk actions', 'rankkernel' ); ?></option>
					<option value="delete"><?php echo esc_html__( 'Delete', 'rankkernel' ); ?></option>
				</select>
				<?php submit_button( __( 'Apply', 'rankkernel' ), 'action', 'rankkernel_404_bulk', false ); ?>
			</div>
			<?php if ( $pagination['has'] ) : ?>
				<div class="tablenav-pages rk-pages-top">
					<span class="paging-text"><?php echo esc_html( $pagination['label'] ); ?></span>
					<?php if ( '' !== $pagination['previousUrl'] ) : ?>
						<a class="button" href="<?php echo esc_url( $pagination['previousUrl'] ); ?>"><?php echo esc_html__( 'Previous', 'rankkernel' ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $pagination['nextUrl'] ) : ?>
						<a class="button" href="<?php echo esc_url( $pagination['nextUrl'] ); ?>"><?php echo esc_html__( 'Next', 'rankkernel' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			</div>

			<table class="wp-list-table widefat fixed striped rk-table">
				<thead><tr>
					<td class="manage-column column-cb check-column">
						<label for="rk-select-all" class="screen-reader-text"><?php echo esc_html__( 'Select All', 'rankkernel' ); ?></label>
						<input type="checkbox" id="rk-select-all" />
					</td>
					<?php foreach ( $sortColumns as $sortColumn ) : ?>
						<th scope="col" class="<?php echo esc_attr( $sortColumn['class'] ); ?>"><a href="<?php echo esc_url( $sortColumn['url'] ); ?>"><span><?php echo esc_html( $sortColumn['label'] ); ?><?php echo esc_html( $sortColumn['arrow'] ); ?></span></a></th>
					<?php endforeach; ?>
					<th scope="col"><?php echo esc_html__( 'Actions', 'rankkernel' ); ?></th>
				</tr></thead><tbody>
					<?php foreach ( $listRowItems as $listRowItem ) : ?>
						<tr>
							<th scope="row" class="check-column"><input type="checkbox" name="entry_ids[]" value="<?php echo esc_attr( (string) $listRowItem['id'] ); ?>" aria-label="<?php echo esc_attr( $listRowItem['selectLabel'] ); ?>" /></th>
							<td class="rk-col-uri"><strong><?php echo esc_html( $listRowItem['uri'] ); ?></strong>
								<details class="rk-details"><summary><?php echo esc_html__( 'Details', 'rankkernel' ); ?></summary>
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
								</details></td>
							<td class="rk-col-hits"><?php echo esc_html( (string) number_format_i18n( $listRowItem['hits'] ) ); ?></td>
							<td class="rk-col-created"><?php echo '' === $listRowItem['created'] ? esc_html__( 'Unknown', 'rankkernel' ) : esc_html( $listRowItem['created'] ); ?></td>
							<td class="rk-col-seen"><?php echo '' === $listRowItem['seen'] ? esc_html__( 'Unknown', 'rankkernel' ) : esc_html( $listRowItem['seen'] ); ?></td>
							<td class="rk-col-actions">
								<?php if ( $redirectsEnabled ) : ?>
									<a class="button button-small" href="<?php echo esc_url( $listRowItem['createRedirectUrl'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: missing page URL */ __( 'Create redirect for %s', 'rankkernel' ), $listRowItem['uri'] ) ); ?>"><?php echo esc_html__( 'Create Redirect', 'rankkernel' ); ?></a>
								<?php endif; ?>
								<a class="button button-small rk-confirm" href="<?php echo esc_url( $listRowItem['deleteUrl'] ); ?>" data-rk-confirm="<?php echo esc_attr__( 'Delete this entry? This cannot be undone.', 'rankkernel' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: missing page URL */ __( 'Delete 404 entry for %s', 'rankkernel' ), $listRowItem['uri'] ) ); ?>"><?php echo esc_html__( 'Delete', 'rankkernel' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody></table>

			<div class="tablenav bottom"><div class="alignleft actions bulkactions">
				<span class="displaying-num"><?php echo esc_html( sprintf( /* translators: %d: total number of 404 entries */ __( '%d items', 'rankkernel' ), $listTotal ) ); ?></span>
			</div>
			<?php if ( $pagination['has'] ) : ?>
				<div class="tablenav-pages rk-pages-bottom">
					<span class="paging-text"><?php echo esc_html( $pagination['label'] ); ?></span>
					<?php if ( '' !== $pagination['previousUrl'] ) : ?>
						<a class="button" href="<?php echo esc_url( $pagination['previousUrl'] ); ?>"><?php echo esc_html__( 'Previous', 'rankkernel' ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $pagination['nextUrl'] ) : ?>
						<a class="button" href="<?php echo esc_url( $pagination['nextUrl'] ); ?>"><?php echo esc_html__( 'Next', 'rankkernel' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			</div>
		</form>
	<?php endif; ?>

	<details class="rk-card rk-settings" id="rk-monitor-settings">
		<summary class="rk-settings-summary"><?php echo esc_html__( 'Monitor Settings', 'rankkernel' ); ?></summary>

		<form method="post" action="">
			<?php wp_nonce_field( 'rankkernel_404_settings' ); ?>

			<table class="form-table" role="presentation"><tbody>
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

			<h3><?php echo esc_html__( 'Exclusions', 'rankkernel' ); ?></h3>
			<p class="rk-sub"><?php echo esc_html__( 'Skip logging for addresses that match a rule. Add as many rows as needed. Matching is case sensitive. Examples: Prefix /wp-admin/, Contains utm_, Exact /old-page.', 'rankkernel' ); ?></p>

			<table class="widefat striped rk-exclusions"><thead><tr>
				<th scope="col"><?php echo esc_html__( 'Compare', 'rankkernel' ); ?></th>
				<th scope="col"><?php echo esc_html__( 'Value', 'rankkernel' ); ?></th>
				<th scope="col"><span class="screen-reader-text"><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></span></th>
			</tr></thead><tbody id="rk-exclusions-body">
				<?php foreach ( $exclusionRowItems as $exclusionRowItem ) : ?>
					<?php
					$rkExclRemoveLabel = '' !== $exclusionRowItem['value']
						? sprintf( /* translators: %s: exclusion value or pattern */ __( 'Remove exclusion rule for %s', 'rankkernel' ), $exclusionRowItem['value'] )
						: __( 'Remove exclusion rule', 'rankkernel' );
					?>
					<tr class="rk-exclusion-row"><td>
						<select name="rk_excl_comparator[]" aria-label="<?php echo esc_attr__( 'How to compare', 'rankkernel' ); ?>">
							<?php foreach ( $settingsComparators as $comparatorOption => $comparatorLabel ) : ?>
								<option value="<?php echo esc_attr( $comparatorOption ); ?>"<?php echo selected( $exclusionRowItem['comparator'], $comparatorOption, false ); ?>><?php echo esc_html( $comparatorLabel ); ?></option>
							<?php endforeach; ?>
						</select></td>
						<td><input type="text" name="rk_excl_value[]" value="<?php echo esc_attr( $exclusionRowItem['value'] ); ?>" class="regular-text code" maxlength="500" aria-label="<?php echo esc_attr__( 'Exclusion value', 'rankkernel' ); ?>" /></td>
						<td><button type="button" class="button button-small rk-exclusion-remove" aria-label="<?php echo esc_attr( $rkExclRemoveLabel ); ?>"><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></button></td></tr>
				<?php endforeach; ?>
			</tbody></table>

			<template id="rk-exclusion-template"><tr class="rk-exclusion-row"><td>
				<select name="rk_excl_comparator[]" aria-label="<?php echo esc_attr__( 'How to compare', 'rankkernel' ); ?>">
					<?php foreach ( $settingsComparators as $comparatorOption => $comparatorLabel ) : ?>
						<option value="<?php echo esc_attr( $comparatorOption ); ?>"<?php echo selected( 'prefix', $comparatorOption, false ); ?>><?php echo esc_html( $comparatorLabel ); ?></option>
					<?php endforeach; ?>
				</select></td>
				<td><input type="text" name="rk_excl_value[]" value="" class="regular-text code" maxlength="500" aria-label="<?php echo esc_attr__( 'Exclusion value', 'rankkernel' ); ?>" /></td>
				<td><button type="button" class="button button-small rk-exclusion-remove" aria-label="<?php echo esc_attr__( 'Remove exclusion rule', 'rankkernel' ); ?>"><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></button></td></tr></template>

			<p><button type="button" class="button" id="rk-exclusion-add"><?php echo esc_html__( 'Add Exclusion', 'rankkernel' ); ?></button> <span class="rk-sub"><?php echo esc_html__( 'Without JavaScript, clear a row value and save to remove its rule.', 'rankkernel' ); ?></span></p>

			<?php submit_button( __( 'Save Monitor Settings', 'rankkernel' ), 'secondary', 'rankkernel_404_settings_save' ); ?>
		</form>
	</details>
</div>
