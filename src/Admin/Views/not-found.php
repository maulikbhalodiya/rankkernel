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
 * The composition is the approved 404 Monitor design: a stack of notices, a
 * page header card with the version chip, a Clear Log control and the settings
 * gear, a Log Status card with four stat tiles, the searchable sortable
 * paginated list with its bulk bar and footer, the two empty states, and the
 * collapsible Monitor Settings panel with the exclusions editor.
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
 * @var bool   $monitorActive       Whether the 404 Monitor module is enabled.
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

/*
 * Presentation derived values. Every one of these is arithmetic on controller
 * data, never a new figure: the rounded usage percent, the free slots left in
 * the configured entry limit, and a date formatter so the table and the detail
 * panel read like the design instead of raw SQL datetimes. The formatter falls
 * back to the raw string when it cannot parse one, and every caller keeps the
 * "Unknown" wording for an empty value.
 */
$rkUsagePercent   = (int) round( $summaryPercent );
$rkSlotsAvailable = max( 0, $summaryMax - $summaryCount );

$rkFormatDate = static function ( string $value ): string {
	if ( '' === $value ) {
		return '';
	}

	$rkParsed = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $value );

	return false !== $rkParsed ? $rkParsed->format( 'M j, Y' ) : $value;
};

$rkFormatDateTime = static function ( string $value ): string {
	if ( '' === $value ) {
		return '';
	}

	$rkParsed = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $value );

	return false !== $rkParsed ? $rkParsed->format( 'M j, Y H:i' ) : $value;
};

/*
 * The capacity state arrives from the controller as a three step enum. The
 * tile tag spells the state out in words, the fill colour follows the same
 * enum, and the thresholds legend below the bar states the bands the
 * controller uses (below 80, 80 to below 90, 90 and above), so neither the
 * badge nor the bar relies on colour alone.
 */
if ( 'high' === $nearState ) {
	$rkCapacityPill  = 'rk-ui-pill rk-ui-pill-danger';
	$rkCapacityLabel = __( 'Near limit (90% and above)', 'rankkernel' );
	$rkFillModifier  = 'rk-fill-high';
} elseif ( 'warn' === $nearState ) {
	$rkCapacityPill  = 'rk-ui-pill rk-ui-pill-warning';
	$rkCapacityLabel = __( 'Approaching limit (80 to 89%)', 'rankkernel' );
	$rkFillModifier  = 'rk-fill-warn';
} else {
	$rkCapacityPill  = 'rk-ui-pill rk-ui-pill-info';
	$rkCapacityLabel = __( 'Normal (0 to 79%)', 'rankkernel' );
	$rkFillModifier  = 'rk-fill-normal';
}

/*
 * The carried chain arrives as one " → " separated string. Splitting it back
 * apart lets the notice render each hop as its own chip, so the chain reads as
 * a path rather than a sentence. A single hop renders as a single chip.
 */
$rkChainLinks = '' !== $carriedChain ? explode( ' → ', $carriedChain ) : [];
?>
<div class="wrap rk-monitor rk-ui">

	<?php /* Section 1: the page h1 WordPress expects, kept for screen readers only. */ ?>
	<h1 class="screen-reader-text"><?php echo esc_html__( '404 Monitor', 'rankkernel' ); ?></h1>

	<?php /* Section 2: notice row. Every notice is server rendered at page load, so the static callouts carry no live region role. The save outcomes keep role status and role alert. */ ?>
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
		<div class="rk-ui-notice rk-ui-notice-warning">
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">warning</span>
			<p class="rk-ui-notice-text rk-chain-text">
				<span><?php echo esc_html__( 'Redirect chain detected:', 'rankkernel' ); ?></span>
				<?php foreach ( $rkChainLinks as $rkChainIndex => $rkChainLink ) : ?>
					<?php if ( $rkChainIndex > 0 ) : ?>
						<span class="rk-chain-arrow" aria-hidden="true">→</span>
					<?php endif; ?>
					<span class="rk-chain-chip"><?php echo esc_html( $rkChainLink ); ?></span>
				<?php endforeach; ?>
				<span>.</span>
				<?php if ( $carriedChainUnknown || '' === $carriedFinal ) : ?>
					<span><?php echo esc_html__( 'RankKernel could not determine the final destination, so please verify the chain manually. Saved as entered.', 'rankkernel' ); ?></span>
				<?php else : ?>
					<span><?php echo esc_html__( 'Consider pointing the source directly to', 'rankkernel' ); ?></span>
					<span class="rk-chain-chip"><?php echo esc_html( $carriedFinal ); ?></span>
					<span>.</span>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $carriedMayLoop ) : ?>
		<div class="rk-ui-notice rk-ui-notice-warning">
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">warning</span>
			<p class="rk-ui-notice-text"><?php echo esc_html__( 'The loop check could not fully verify this redirect, so a loop is still possible. Please verify it manually.', 'rankkernel' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $carriedChainUnknown && '' === $carriedChain ) : ?>
		<div class="rk-ui-notice rk-ui-notice-info">
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">info</span>
			<p class="rk-ui-notice-text"><?php echo esc_html__( 'Chain analysis could not determine the final destination because the next rule uses a pattern matcher. Saved as entered.', 'rankkernel' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( 'normal' !== $nearState ) : ?>
		<?php if ( 'high' === $nearState ) : ?>
			<div class="rk-ui-notice rk-ui-notice-warning">
				<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">warning</span>
				<p class="rk-ui-notice-text"><?php echo esc_html( sprintf( /* translators: %s: configured maximum entry count */ __( 'Your 404 log is nearly at its configured entry limit of %s entries. The oldest entries are removed automatically when the limit is reached. You can clear the log manually at any time.', 'rankkernel' ), number_format_i18n( $nearMax ) ) ); ?></p>
				<?php
				/*
				 * Static label, never a figure. It marks the same persistent
				 * condition the notice explains, and it is set in the design,
				 * so it is rendered as fixed chrome rather than data.
				 */
				?>
				<span class="rk-notice-tag"><?php echo esc_html__( 'PERSISTENT LIMIT ALERT', 'rankkernel' ); ?></span>
			</div>
		<?php else : ?>
			<div class="rk-ui-notice rk-ui-notice-info">
				<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">info</span>
				<p class="rk-ui-notice-text"><?php echo esc_html( sprintf( /* translators: %s: configured maximum entry count */ __( 'Your 404 log is approaching its configured entry limit of %s entries. The oldest entries are removed automatically when the limit is reached. You can clear the log manually at any time.', 'rankkernel' ), number_format_i18n( $nearMax ) ) ); ?></p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php /* Section 3: page header card. Title plus the real plugin version, the Clear Log control and the settings gear that toggles the settings panel. */ ?>
	<header class="rk-ui-card rk-ui-page-header">
		<div class="rk-ui-page-header-text">
			<div class="rk-ui-page-header-title-row">
				<h2 class="rk-ui-page-title"><?php echo esc_html__( '404 Monitor', 'rankkernel' ); ?></h2>
				<span class="rk-version-chip">v<?php echo esc_html( \RankKernel\Plugin::version() ); ?></span>
			</div>
			<p class="rk-ui-sub"><?php echo esc_html__( 'See which missing pages visitors hit, then turn the busy ones into redirects. Oldest entries prune automatically.', 'rankkernel' ); ?></p>
		</div>
		<div class="rk-ui-page-header-actions">
			<?php
			/*
			 * Clearing the log destroys every entry, so the control carries
			 * the page destructive treatment: the shared danger colour on a
			 * bordered button, which is the destructive shape this design
			 * uses in the page header. The marker name the controller reads
			 * plus the form id the script confirms are unchanged.
			 */
			?>
			<form method="post" action="<?php echo esc_url( $baseScreenUrl ); ?>" id="rk-clear-form" class="rk-clear-form" data-rk-confirm="<?php echo esc_attr__( 'Clear the whole 404 log? This cannot be undone.', 'rankkernel' ); ?>">
				<?php wp_nonce_field( 'rankkernel_404_clear' ); ?>
				<button type="submit" name="rankkernel_404_clear" id="rankkernel_404_clear" value="1" class="rk-ui-btn rk-clear-btn"><span class="rk-icon" aria-hidden="true">cancel</span><?php echo esc_html__( 'Clear Log', 'rankkernel' ); ?></button>
			</form>
			<button type="button" class="rk-ui-btn rk-ui-btn-icon" id="rk-monitor-settings-toggle" aria-expanded="false" aria-controls="rk-monitor-settings" aria-label="<?php echo esc_attr__( 'Monitor Settings', 'rankkernel' ); ?>" title="<?php echo esc_attr__( 'Monitor Settings', 'rankkernel' ); ?>"><span class="rk-icon" aria-hidden="true">settings</span></button>
		</div>
	</header>

	<?php /* Monitor settings panel, collapsed. Every control posts the real settings form. Sits above the log status card. */ ?>
	<noscript><style>.rk-monitor .rk-monitor-settings[hidden]{display:block}</style></noscript>
	<div class="rk-ui-card rk-monitor-settings" id="rk-monitor-settings" hidden>
		<?php
		/*
		 * The disclosure summary carries the card heading, so the heading is
		 * a real h3 inside the summary rather than a styled span. A heading
		 * is allowed inside a summary, and the summary is not allowed to
		 * hold block content, so the name and the hint are two spans inside
		 * the heading rather than a wrapper around it. The Hide/Show wording
		 * is a pair of spans swapped by the hidden state in CSS. The gear
		 * button plus the banner link above toggle this panel through
		 * assets/js/monitor-settings.js; every form hook is unchanged.
		 */
		?>
		<div class="rk-monitor-settings-summary">
			<h3 class="rk-monitor-settings-heading">
				<span class="rk-monitor-settings-name"><span class="rk-icon" aria-hidden="true">settings</span><?php echo esc_html__( 'Monitor Settings', 'rankkernel' ); ?></span>
				<span class="rk-monitor-settings-hint"><?php echo esc_html__( 'Stored in WordPress. Retention, entry limit, flood guard and address exclusions.', 'rankkernel' ); ?></span>
			</h3>
			<span class="rk-monitor-settings-toggle"><span class="rk-monitor-settings-show"><?php echo esc_html__( 'Show', 'rankkernel' ); ?></span><span class="rk-monitor-settings-hide"><?php echo esc_html__( 'Hide', 'rankkernel' ); ?></span></span>
		</div>

		<form method="post" action="" class="rk-monitor-settings-form">
			<?php wp_nonce_field( 'rankkernel_404_settings' ); ?>

			<div class="rk-settings-grid">
				<div class="rk-settings-col">
					<?php /* Row A: advanced fields, the switch the design draws next to the explanation. */ ?>
					<div class="rk-setting-row">
						<div class="rk-setting-text">
							<label class="rk-setting-label" for="rk-advanced-fields"><?php echo esc_html__( 'Advanced fields', 'rankkernel' ); ?></label>
							<span class="rk-setting-desc"><?php echo esc_html__( 'Store referer and user agent with each entry.', 'rankkernel' ); ?></span>
							<p class="rk-ui-hint"><?php echo esc_html__( 'Optional and off by default. Values are truncated to 255 characters. IP addresses are never stored.', 'rankkernel' ); ?></p>
						</div>
						<span class="rk-ui-switch rk-setting-switch">
							<input type="checkbox" id="rk-advanced-fields" name="rk_advanced_fields" value="1" <?php echo checked( $settingsAdvanced, true, false ); ?> />
							<span class="rk-ui-switch-track" aria-hidden="true"><span class="rk-ui-switch-knob"></span></span>
						</span>
					</div>

					<?php /* Row B: retention window. */ ?>
					<div class="rk-setting-row">
						<div class="rk-setting-text">
							<label class="rk-setting-label" for="rk-retention"><?php echo esc_html__( 'Retention days', 'rankkernel' ); ?></label>
							<span class="rk-setting-desc"><?php echo esc_html__( 'Log rotation threshold in days', 'rankkernel' ); ?></span>
							<p class="rk-ui-hint"><?php echo esc_html__( 'Entries older than this many days are removed automatically, from 1 to 365.', 'rankkernel' ); ?></p>
						</div>
						<span class="rk-setting-control">
							<input type="number" id="rk-retention" name="rk_retention_days" value="<?php echo esc_attr( (string) $settingsRetention ); ?>" class="rk-setting-input" min="1" max="365" />
							<span class="rk-setting-unit"><?php echo esc_html__( 'days', 'rankkernel' ); ?></span>
						</span>
					</div>

					<?php /* Row C: entry limit. */ ?>
					<div class="rk-setting-row">
						<div class="rk-setting-text">
							<label class="rk-setting-label" for="rk-max-rows"><?php echo esc_html__( 'Maximum entries', 'rankkernel' ); ?></label>
							<span class="rk-setting-desc"><?php echo esc_html__( 'Table limit guard', 'rankkernel' ); ?></span>
							<p class="rk-ui-hint"><?php echo esc_html__( 'Maximum entries kept in the log, from 100 to 10000. When the limit is reached, the oldest entries are removed first.', 'rankkernel' ); ?></p>
						</div>
						<span class="rk-setting-control">
							<input type="number" id="rk-max-rows" name="rk_max_rows" value="<?php echo esc_attr( (string) $settingsMaxRows ); ?>" class="rk-setting-input" min="100" max="10000" />
							<span class="rk-setting-unit"><?php echo esc_html__( 'entries', 'rankkernel' ); ?></span>
						</span>
					</div>
				</div>

				<div class="rk-settings-col">
					<?php /* Row D: flood budget. */ ?>
					<div class="rk-setting-row">
						<div class="rk-setting-text">
							<label class="rk-setting-label" for="rk-flood-budget"><?php echo esc_html__( 'Flood budget', 'rankkernel' ); ?></label>
							<span class="rk-setting-desc"><?php echo esc_html__( 'Rate limiting protection against bot scans', 'rankkernel' ); ?></span>
							<p class="rk-ui-hint"><?php echo esc_html__( 'New addresses allowed per time window, from 1 to 1000. Repeat hits on known addresses always keep counting.', 'rankkernel' ); ?></p>
						</div>
						<span class="rk-setting-control">
							<input type="number" id="rk-flood-budget" name="rk_flood_budget" value="<?php echo esc_attr( (string) $settingsFloodBudget ); ?>" class="rk-setting-input" min="1" max="1000" />
							<span class="rk-setting-unit"><?php echo esc_html__( 'addrs', 'rankkernel' ); ?></span>
						</span>
					</div>

					<?php /* Row E: flood window. */ ?>
					<div class="rk-setting-row">
						<div class="rk-setting-text">
							<label class="rk-setting-label" for="rk-flood-window"><?php echo esc_html__( 'Flood window', 'rankkernel' ); ?></label>
							<span class="rk-setting-desc"><?php echo esc_html__( 'Rolling window duration', 'rankkernel' ); ?></span>
							<p class="rk-ui-hint"><?php echo esc_html__( 'Length of the flood window in seconds, from 60 to 3600.', 'rankkernel' ); ?></p>
						</div>
						<span class="rk-setting-control">
							<input type="number" id="rk-flood-window" name="rk_flood_window" value="<?php echo esc_attr( (string) $settingsFloodWindow ); ?>" class="rk-setting-input" min="60" max="3600" />
							<span class="rk-setting-unit"><?php echo esc_html__( 'sec', 'rankkernel' ); ?></span>
						</span>
					</div>

					<?php /* Row F: query strings. */ ?>
					<div class="rk-setting-row">
						<div class="rk-setting-text">
							<label class="rk-setting-label" for="rk-ignore-query"><?php echo esc_html__( 'Query strings', 'rankkernel' ); ?></label>
							<span class="rk-setting-desc"><?php echo esc_html__( 'Ignore query strings when logging.', 'rankkernel' ); ?></span>
							<p class="rk-ui-hint"><?php echo esc_html__( 'On by default. Turn off to track each query string as a separate entry.', 'rankkernel' ); ?></p>
						</div>
						<span class="rk-ui-switch rk-setting-switch">
							<input type="checkbox" id="rk-ignore-query" name="rk_ignore_query" value="1" <?php echo checked( $settingsIgnoreQuery, true, false ); ?> />
							<span class="rk-ui-switch-track" aria-hidden="true"><span class="rk-ui-switch-knob"></span></span>
						</span>
					</div>
				</div>
			</div>

			<hr class="rk-settings-divider" />

			<?php
			/*
			 * The heading above is also the name of the table below, so table
			 * navigation announces what the table holds. It is the id the
			 * table points at, so the visible words stay the single source.
			 */
			?>
			<div class="rk-exclusions-head">
				<h4 class="rk-subheading" id="rk-exclusions-heading"><?php echo esc_html__( 'URL Exclusions', 'rankkernel' ); ?></h4>
				<p class="rk-ui-hint rk-ui-hint-strong"><?php echo esc_html__( 'Skip logging for addresses that match a rule. Matching is case sensitive. Examples: Prefix /wp-admin/, Contains utm_, Exact /old-page.', 'rankkernel' ); ?></p>
			</div>

			<div class="rk-ui-table-wrap rk-exclusions-wrap">
				<table class="rk-ui-table rk-exclusions" aria-labelledby="rk-exclusions-heading">
					<thead>
						<tr>
							<th scope="col" class="rk-col-compare"><?php echo esc_html__( 'Compare', 'rankkernel' ); ?></th>
							<th scope="col" class="rk-col-value"><?php echo esc_html__( 'Value', 'rankkernel' ); ?></th>
							<th scope="col" class="rk-col-remove"><?php echo esc_html__( 'Action', 'rankkernel' ); ?></th>
						</tr>
					</thead>
					<tbody id="rk-exclusions-body">
						<?php
						/*
						 * Every row of this table carried the same two names, so
						 * a screen reader heard "How to compare" and "Exclusion
						 * value" once per row with nothing to say which rule it
						 * was on. The position in the list is what the server
						 * rendered rows can offer that is unique, so each pair
						 * of controls is named after its rule number. The Remove
						 * button in the same row already names itself after the
						 * value it removes, which is the pattern followed here.
						 */
						foreach ( $exclusionRowItems as $rkExclRowIndex => $exclusionRowItem ) :
							$rkExclRuleNumber = $rkExclRowIndex + 1;

							$rkExclRemoveLabel = '' !== $exclusionRowItem['value']
								? sprintf( /* translators: %s: exclusion value or pattern */ __( 'Remove exclusion rule for %s', 'rankkernel' ), $exclusionRowItem['value'] )
								: __( 'Remove exclusion rule', 'rankkernel' );
							?>
							<tr class="rk-exclusion-row"><td>
								<div class="rk-ui-select-wrap">
									<select name="rk_excl_comparator[]" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: position of the exclusion rule in the list */ __( 'How to compare for exclusion rule %d', 'rankkernel' ), $rkExclRuleNumber ) ); ?>" class="rk-ui-select rk-exclusion-select">
										<?php foreach ( $settingsComparators as $comparatorOption => $comparatorLabel ) : ?>
											<option value="<?php echo esc_attr( $comparatorOption ); ?>"<?php echo selected( $exclusionRowItem['comparator'], $comparatorOption, false ); ?>><?php echo esc_html( $comparatorLabel ); ?></option>
										<?php endforeach; ?>
									</select>
									<span class="rk-icon rk-ui-select-chevron" aria-hidden="true">expand_more</span>
								</div></td>
								<td><input type="text" name="rk_excl_value[]" value="<?php echo esc_attr( $exclusionRowItem['value'] ); ?>" class="rk-exclusion-value" maxlength="500" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: position of the exclusion rule in the list */ __( 'Exclusion value for exclusion rule %d', 'rankkernel' ), $rkExclRuleNumber ) ); ?>" /></td>
								<td><button type="button" class="rk-ui-btn rk-ui-btn-danger rk-exclusion-remove" aria-label="<?php echo esc_attr( $rkExclRemoveLabel ); ?>"><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></button></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php
			/*
			 * The row the script clones. It carries no id of its own, because
			 * every clone would copy that id, so the controls are named from
			 * the copy itself rather than from a per row id. A clone has no
			 * number yet, so it says it is a new rule, and the number arrives
			 * with the page after the save.
			 */
			?>
			<template id="rk-exclusion-template"><tr class="rk-exclusion-row"><td>
				<div class="rk-ui-select-wrap">
					<select name="rk_excl_comparator[]" aria-label="<?php echo esc_attr__( 'How to compare for a new exclusion rule', 'rankkernel' ); ?>" class="rk-ui-select rk-exclusion-select">
						<?php foreach ( $settingsComparators as $comparatorOption => $comparatorLabel ) : ?>
							<option value="<?php echo esc_attr( $comparatorOption ); ?>"<?php echo selected( 'prefix', $comparatorOption, false ); ?>><?php echo esc_html( $comparatorLabel ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="rk-icon rk-ui-select-chevron" aria-hidden="true">expand_more</span>
				</div></td>
				<td><input type="text" name="rk_excl_value[]" value="" class="rk-exclusion-value" maxlength="500" aria-label="<?php echo esc_attr__( 'Exclusion value for a new exclusion rule', 'rankkernel' ); ?>" /></td>
				<td><button type="button" class="rk-ui-btn rk-ui-btn-danger rk-exclusion-remove" aria-label="<?php echo esc_attr__( 'Remove exclusion rule', 'rankkernel' ); ?>"><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></button></td></tr></template>

			<div class="rk-exclusion-controls">
				<div class="rk-exclusion-add-wrap">
					<button type="button" class="rk-ui-btn rk-ui-btn-secondary" id="rk-exclusion-add"><span class="rk-icon" aria-hidden="true">add</span><?php echo esc_html__( 'Add Exclusion', 'rankkernel' ); ?></button>
					<span class="rk-ui-hint"><?php echo esc_html__( 'Without JavaScript, clear a row value and save to remove its rule.', 'rankkernel' ); ?></span>
				</div>
				<?php submit_button( __( 'Save Monitor Settings', 'rankkernel' ), 'primary', 'rankkernel_404_settings_save' ); ?>
			</div>
		</form>
	</div>

	<?php /* Section 4: log status card. The figures come from the controller, never from a mockup. */ ?>
	<section class="rk-ui-card rk-log-status">
		<div class="rk-log-status-head">
			<div class="rk-log-status-head-left">
				<h3 class="rk-ui-card-title"><?php echo esc_html__( 'Log Status', 'rankkernel' ); ?></h3>
				<?php if ( $monitorActive ) : ?>
					<span class="rk-status-chip rk-status-chip-on"><span class="rk-status-dot" aria-hidden="true"></span><?php echo esc_html__( 'Monitoring active', 'rankkernel' ); ?></span>
				<?php else : ?>
					<span class="rk-status-chip rk-status-chip-off"><span class="rk-status-dot" aria-hidden="true"></span><?php echo esc_html__( 'Monitoring inactive', 'rankkernel' ); ?></span>
				<?php endif; ?>
			</div>
			<span class="rk-log-status-meta"><?php echo esc_html__( 'Real-time HTTP 404 listener', 'rankkernel' ); ?></span>
		</div>

		<div class="rk-stat-grid" role="region" aria-label="<?php echo esc_attr__( '404 log status figures', 'rankkernel' ); ?>">
			<?php /* Tile 1: tracked addresses. The only figure is the real tracked count. */ ?>
			<div class="rk-stat rk-stat-tracked">
				<div class="rk-stat-top">
					<span class="rk-stat-label"><?php echo esc_html__( 'Tracked', 'rankkernel' ); ?></span>
					<span class="rk-stat-icon" aria-hidden="true"><span class="rk-icon">link</span></span>
				</div>
				<div class="rk-stat-value"><?php echo esc_html( (string) number_format_i18n( $summaryCount ) ); ?></div>
				<div class="rk-stat-caption"><?php echo esc_html__( 'tracked addresses', 'rankkernel' ); ?></div>
			</div>

			<?php /* Tile 2: capacity against the configured maximum, with the real slots left. */ ?>
			<div class="rk-stat rk-stat-capacity">
				<div class="rk-stat-top">
					<span class="rk-stat-label"><?php echo esc_html__( 'Capacity', 'rankkernel' ); ?></span>
					<span class="<?php echo esc_attr( $rkCapacityPill ); ?>"><?php echo esc_html( $rkCapacityLabel ); ?></span>
				</div>
				<div class="rk-progress" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $summaryPercent ); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php echo esc_attr__( '404 Log Storage Usage', 'rankkernel' ); ?>" aria-valuetext="<?php echo esc_attr( sprintf( /* translators: %1$s: current entry count, %2$s: configured maximum */ __( '%1$s of %2$s entries used', 'rankkernel' ), number_format_i18n( $summaryCount ), number_format_i18n( $summaryMax ) ) ); ?>"><div class="rk-progress-fill <?php echo esc_attr( $rkFillModifier ); ?>" style="width:<?php echo esc_attr( (string) $summaryPercent ); ?>%"></div></div>
				<div class="rk-capacity-row">
					<span class="rk-capacity-value"><?php echo esc_html( sprintf( /* translators: %1$s: current entry count, %2$s: configured maximum, %3$s: usage percent */ __( '%1$s / %2$s entries (%3$s%%)', 'rankkernel' ), number_format_i18n( $summaryCount ), number_format_i18n( $summaryMax ), number_format_i18n( $rkUsagePercent ) ) ); ?></span>
					<span class="rk-capacity-slots"><?php echo esc_html( sprintf( /* translators: %s: number of free entry slots left in the log */ __( '%s slots available', 'rankkernel' ), number_format_i18n( $rkSlotsAvailable ) ) ); ?></span>
				</div>
				<?php
				/*
				 * Static legend for the band thresholds the controller uses.
				 * It describes fixed product policy (below 80, 80 to below
				 * 90, 90 and above), not data from this site, so it is not a
				 * fabricated figure.
				 */
				?>
				<p class="rk-stat-note"><span class="rk-icon" aria-hidden="true">info</span><span><?php echo esc_html__( 'Thresholds: blue 0 to 79%, amber 80 to 89%, red 90% and above', 'rankkernel' ); ?></span></p>
			</div>

			<?php /* Tile 3: retention window from the settings store. */ ?>
			<div class="rk-stat rk-stat-retention">
				<div class="rk-stat-top">
					<span class="rk-stat-label"><?php echo esc_html__( 'Retention', 'rankkernel' ); ?></span>
					<span class="rk-stat-icon" aria-hidden="true"><span class="rk-icon">tune</span></span>
				</div>
				<div class="rk-stat-value"><?php echo esc_html( (string) $summaryRetentionDays ); ?></div>
				<div class="rk-stat-caption"><?php echo esc_html__( 'days retention', 'rankkernel' ); ?></div>
				<p class="rk-stat-note"><?php echo esc_html( sprintf( /* translators: %s: retention period in days */ __( 'Entries older than %s days removed automatically.', 'rankkernel' ), number_format_i18n( $summaryRetentionDays ) ) ); ?></p>
			</div>

			<?php /* Tile 4: the most recent entry, rendered only when the controller has one. */ ?>
			<?php if ( $summaryHasRecent ) : ?>
				<div class="rk-stat rk-stat-recent">
					<div class="rk-stat-top">
						<span class="rk-stat-label"><?php echo esc_html__( 'Most recent', 'rankkernel' ); ?></span>
						<span class="rk-stat-icon rk-stat-icon-recent" aria-hidden="true"><span class="rk-icon">download</span></span>
					</div>
					<div class="rk-stat-value rk-stat-value-recent"><?php echo '' === $summaryRecentSeen ? esc_html__( 'Unknown', 'rankkernel' ) : esc_html( $summaryRecentSeen ); ?></div>
					<div class="rk-stat-caption"><?php echo esc_html__( 'most recent 404', 'rankkernel' ); ?></div>
					<div class="rk-stat-uri" title="<?php echo esc_attr( $summaryRecentUri ); ?>"><?php echo esc_html( $summaryRecentUri ); ?></div>
				</div>
			<?php endif; ?>
		</div>

		<?php
		/*
		 * The clearing contract, kept from the screen this redesign
		 * replaces: manual clearing and automatic pruning are separate
		 * mechanisms, and clearing runs in bounded batches. The design has no
		 * line here, so it is set as quiet helper text under the tiles.
		 */
		?>
		<p class="rk-ui-hint rk-summary-note"><?php echo esc_html__( 'Manual clearing is separate from automatic pruning and removes entries in bounded batches.', 'rankkernel' ); ?></p>
	</section>

	<?php /* Section 5: the searchable, sortable, paginated list. */ ?>
	<section class="rk-tracked" aria-label="<?php echo esc_attr__( 'Tracked 404 Errors', 'rankkernel' ); ?>">
		<div class="rk-tracked-head">
			<div class="rk-tracked-title-row">
				<?php
				/*
				 * The heading is also the name of the log table below, so
				 * table navigation announces what the table holds rather than
				 * only its shape. The id on the heading is what the table
				 * points at, so the visible words stay the single source.
				 */
				?>
				<h3 class="rk-tracked-title" id="rk-log-heading"><?php echo esc_html__( 'Tracked 404s', 'rankkernel' ); ?></h3>
				<span class="rk-entry-count"><?php echo esc_html( sprintf( /* translators: %d: number of tracked 404 entries */ __( '%d entries', 'rankkernel' ), $listTotal ) ); ?></span>
			</div>
			<form method="get" action="<?php echo esc_url( $searchFormUrl ); ?>" class="rk-tracked-search" role="search" aria-label="<?php echo esc_attr__( 'Search tracked 404 addresses', 'rankkernel' ); ?>">
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
				<?php submit_button( __( 'Search', 'rankkernel' ), 'secondary rk-search-submit', '', false ); ?>
			</form>
		</div>

		<?php if ( ! $redirectsEnabled ) : ?>
			<div class="rk-ui-notice rk-ui-notice-info rk-tracked-banner">
				<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">info</span>
				<p class="rk-ui-notice-text"><?php echo esc_html__( 'Redirect creation needs the Redirects module. Enable Redirects to turn a 404 entry into a redirect.', 'rankkernel' ); ?></p>
				<a class="rk-banner-link" id="rk-monitor-settings-link" href="#rk-monitor-settings"><?php echo esc_html__( 'Module Settings', 'rankkernel' ); ?><span aria-hidden="true"> →</span></a>
			</div>
		<?php endif; ?>

		<?php if ( $listIsEmpty ) : ?>
			<?php if ( $listHasFilter ) : ?>
				<?php /* Search miss state. The clear control returns to the unfiltered screen. */ ?>
				<div class="rk-empty-state">
					<div class="rk-empty-icon" aria-hidden="true"><span class="rk-icon" aria-hidden="true">search_off</span></div>
					<p class="rk-empty-title"><?php echo esc_html__( 'No 404 entries match your search.', 'rankkernel' ); ?></p>
					<p class="rk-empty-body"><?php echo esc_html__( 'Try a different term or clear the search to see all tracked addresses.', 'rankkernel' ); ?></p>
					<a class="rk-ui-btn rk-ui-btn-secondary" href="<?php echo esc_url( $baseScreenUrl ); ?>"><span class="rk-icon" aria-hidden="true">filter_alt_off</span><?php echo esc_html__( 'Clear search', 'rankkernel' ); ?></a>
				</div>
			<?php else : ?>
				<?php /* Initial empty log state, the watermark variant from the design. */ ?>
				<div class="rk-empty-state rk-empty-state-initial">
					<div class="rk-empty-watermark" aria-hidden="true">404</div>
					<p class="rk-empty-title"><?php echo esc_html__( 'No 404 entries are being tracked yet.', 'rankkernel' ); ?></p>
					<p class="rk-empty-body"><?php echo esc_html__( 'When visitors hit a missing page, the URL appears here with hit counts and first and last seen times.', 'rankkernel' ); ?></p>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( $baseScreenUrl ); ?>" id="rk-bulk-form" class="rk-bulk-form" data-rk-confirm="<?php echo esc_attr__( 'Delete the selected entries? This cannot be undone.', 'rankkernel' ); ?>">
				<?php wp_nonce_field( 'rankkernel_404_bulk' ); ?>

				<div class="rk-bulk-bar">
					<div class="rk-bulk-controls">
						<label for="rk-bulk-action" class="screen-reader-text"><?php echo esc_html__( 'Select bulk action', 'rankkernel' ); ?></label>
						<div class="rk-ui-select-wrap">
							<select name="rk_bulk_action" id="rk-bulk-action" class="rk-ui-select">
								<option value=""><?php echo esc_html__( 'Bulk actions', 'rankkernel' ); ?></option>
								<option value="delete"><?php echo esc_html__( 'Delete', 'rankkernel' ); ?></option>
							</select>
							<span class="rk-icon rk-ui-select-chevron" aria-hidden="true">expand_more</span>
						</div>
						<?php submit_button( __( 'Apply', 'rankkernel' ), 'secondary rk-apply-submit', 'rankkernel_404_bulk', false ); ?>
					</div>

					<?php if ( $pagination['has'] ) : ?>
						<?php
						/*
						 * The footer below is the labelled navigation landmark, so
						 * this repeat stays an unlabelled group.
						 *
						 * A disabled page link is a link that cannot be followed.
						 * aria-disabled on a plain span was inert, because the
						 * span was never in the focus order and a generic
						 * element does not carry the state, so nothing
						 * announced it. These four controls are now anchors
						 * with the link role and no href, kept focusable with
						 * tabindex so the unavailable state is announced, and
						 * with no destination so activating one does nothing.
						 * The is-disabled class still carries the visible
						 * treatment on all four.
						 */
						?>
						<div class="rk-pages-top">
							<span class="rk-paging-text"><?php echo esc_html( $pagination['label'] ); ?></span>
							<?php if ( '' !== $pagination['previousUrl'] ) : ?>
								<a class="rk-ui-page-link" href="<?php echo esc_url( $pagination['previousUrl'] ); ?>"><?php echo esc_html__( 'Previous', 'rankkernel' ); ?></a>
							<?php else : ?>
								<a class="rk-ui-page-link is-disabled" role="link" aria-disabled="true" tabindex="0"><?php echo esc_html__( 'Previous', 'rankkernel' ); ?></a>
							<?php endif; ?>
							<?php if ( '' !== $pagination['nextUrl'] ) : ?>
								<a class="rk-ui-page-link" href="<?php echo esc_url( $pagination['nextUrl'] ); ?>"><?php echo esc_html__( 'Next', 'rankkernel' ); ?></a>
							<?php else : ?>
								<a class="rk-ui-page-link is-disabled" role="link" aria-disabled="true" tabindex="0"><?php echo esc_html__( 'Next', 'rankkernel' ); ?></a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="rk-ui-table-wrap rk-table-card">
					<?php
					/*
					 * The table is named by the section heading above, so table
					 * navigation announces what the table holds. The column
					 * headers below then say which one is sorted and in which
					 * direction through aria-sort on the header cell, and the
					 * sort link repeats that state in its own accessible name,
					 * because the arrow glyph that shows it on screen is
					 * decorative and hidden from assistive technology.
					 *
					 * The controller sends the state as the arrow character only,
					 * so the direction is read back from that character. The
					 * controller itself is unchanged. An unsorted column shows a
					 * quiet sort glyph, which is decorative chrome and carries no
					 * state.
					 */
					?>
					<table class="rk-ui-table rk-table" aria-labelledby="rk-log-heading">
						<thead>
							<tr>
								<th scope="col" class="rk-col-check">
									<label for="rk-select-all" class="screen-reader-text"><?php echo esc_html__( 'Select All', 'rankkernel' ); ?></label>
									<input type="checkbox" id="rk-select-all" />
								</th>
								<?php foreach ( $sortColumns as $sortColumn ) : ?>
									<?php
									$rkSortIsAscending = ' ↑' === $sortColumn['arrow'];

									if ( $rkSortIsAscending ) {
										$rkSortState = 'ascending';
										$rkSortName  = sprintf( /* translators: %s: column label */ __( 'Sort by %s, currently sorted ascending', 'rankkernel' ), $sortColumn['label'] );
									} elseif ( ' ↓' === $sortColumn['arrow'] ) {
										$rkSortState = 'descending';
										$rkSortName  = sprintf( /* translators: %s: column label */ __( 'Sort by %s, currently sorted descending', 'rankkernel' ), $sortColumn['label'] );
									} else {
										$rkSortState = 'none';
										$rkSortName  = sprintf( /* translators: %s: column label */ __( 'Sort by %s, not currently sorted', 'rankkernel' ), $sortColumn['label'] );
									}
									?>
									<th scope="col" class="<?php echo esc_attr( $sortColumn['class'] ); ?>" aria-sort="<?php echo esc_attr( $rkSortState ); ?>">
										<a class="rk-sort-link" href="<?php echo esc_url( $sortColumn['url'] ); ?>" aria-label="<?php echo esc_attr( $rkSortName ); ?>">
											<span class="rk-sort-label"><?php echo esc_html( $sortColumn['label'] ); ?></span>
											<?php if ( '' !== $sortColumn['arrow'] ) : ?>
												<span class="rk-sort-arrow" aria-hidden="true"><?php echo esc_html( $sortColumn['arrow'] ); ?></span>
											<?php else : ?>
												<span class="rk-sort-glyph" aria-hidden="true"><span class="rk-icon">swap_vert</span></span>
											<?php endif; ?>
										</a>
									</th>
								<?php endforeach; ?>
								<th scope="col" class="rk-col-actions"><?php echo esc_html__( 'Actions', 'rankkernel' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $listRowItems as $listRowItem ) : ?>
								<?php
								$rkRowUri     = (string) ( $listRowItem['uri'] ?? '' );
								$rkRowHits    = max( 0, (int) ( $listRowItem['hits'] ?? 0 ) );
								$rkRowCreated = (string) ( $listRowItem['created'] ?? '' );
								$rkRowSeen    = (string) ( $listRowItem['seen'] ?? '' );
								$rkRowReferer = (string) ( $listRowItem['referer'] ?? '' );
								$rkRowAgent   = (string) ( $listRowItem['agent'] ?? '' );

								$rkRowCreatedLabel = $rkFormatDate( $rkRowCreated );
								$rkRowSeenLabel    = $rkFormatDate( $rkRowSeen );

								$rkRowCreatedFull = $rkFormatDateTime( $rkRowCreated );
								$rkRowSeenFull    = $rkFormatDateTime( $rkRowSeen );
								?>
								<tr>
									<th scope="row" class="rk-col-check">
										<input type="checkbox" name="entry_ids[]" value="<?php echo esc_attr( (string) $listRowItem['id'] ); ?>" aria-label="<?php echo esc_attr( $listRowItem['selectLabel'] ); ?>" />
									</th>
									<td class="rk-col-uri">
										<div class="rk-uri-row">
											<span class="rk-uri-value"><?php echo esc_html( $rkRowUri ); ?></span>
											<?php if ( $rkRowHits >= 50 ) : ?>
												<?php
												/*
												 * The badge marks the heavily hit rows the
												 * design highlights. The number inside it is
												 * the real hit count, never a band label.
												 */
												?>
												<span class="rk-hit-badge"><?php echo esc_html( sprintf( /* translators: %s: number of hits for this 404 entry */ __( '%s hits', 'rankkernel' ), number_format_i18n( $rkRowHits ) ) ); ?></span>
											<?php endif; ?>
										</div>
										<?php
										/*
										 * The disclosure button itself is the word Details,
										 * and twenty rows of them meant twenty identical
										 * buttons. The address in the same row is the one
										 * thing that tells them apart, so it is added to
										 * the accessible name through a screen reader
										 * only span. The visible button is unchanged.
										 */
										?>
										<details class="rk-details">
											<summary><span class="rk-icon" aria-hidden="true">expand_more</span><?php echo esc_html__( 'Details', 'rankkernel' ); ?><span class="screen-reader-text">: <?php echo esc_html( $rkRowUri ); ?></span></summary>
											<div class="rk-detail-panel">
												<div class="rk-detail-head">
													<div class="rk-detail-head-left">
														<span class="rk-detail-title"><?php echo esc_html__( 'Detailed Log Payload', 'rankkernel' ); ?></span>
														<?php if ( $advancedFields ) : ?>
															<span class="rk-detail-state"><?php echo esc_html__( 'Advanced fields enabled, full trace captured', 'rankkernel' ); ?></span>
														<?php endif; ?>
													</div>
													<?php if ( $advancedFields ) : ?>
														<span class="rk-detail-meta"><?php echo esc_html__( 'IP masking applied (zero storage policy)', 'rankkernel' ); ?></span>
													<?php endif; ?>
												</div>
												<?php
												/*
												 * A two column definition list. The wrapper div
												 * groups each term with its value so the grid can
												 * place them, and the dt stays a bare dt because
												 * the wording is the stable contract.
												 */
												?>
												<dl class="rk-detail-list">
													<div class="rk-detail-item"><dt><?php echo esc_html__( 'Full address', 'rankkernel' ); ?></dt><dd><?php echo esc_html( $rkRowUri ); ?></dd></div>
													<div class="rk-detail-item"><dt><?php echo esc_html__( 'Total hits', 'rankkernel' ); ?></dt><dd><?php echo esc_html( sprintf( /* translators: %s: number of hits for this 404 entry */ __( '%s total occurrences', 'rankkernel' ), number_format_i18n( $rkRowHits ) ) ); ?></dd></div>
													<div class="rk-detail-item"><dt><?php echo esc_html__( 'First seen', 'rankkernel' ); ?></dt><dd><?php echo '' === $rkRowCreatedFull ? esc_html__( 'Unknown', 'rankkernel' ) : esc_html( $rkRowCreatedFull ); ?></dd></div>
													<div class="rk-detail-item"><dt><?php echo esc_html__( 'Last seen', 'rankkernel' ); ?></dt><dd><?php echo '' === $rkRowSeenFull ? esc_html__( 'Unknown', 'rankkernel' ) : esc_html( $rkRowSeenFull ); ?></dd></div>
													<?php if ( $advancedFields ) : ?>
														<div class="rk-detail-item"><dt><?php echo esc_html__( 'Referer', 'rankkernel' ); ?></dt><dd><?php echo '' === $rkRowReferer ? esc_html__( 'None recorded', 'rankkernel' ) : esc_html( $rkRowReferer ); ?></dd></div>
														<div class="rk-detail-item"><dt><?php echo esc_html__( 'User agent', 'rankkernel' ); ?></dt><dd><?php echo '' === $rkRowAgent ? esc_html__( 'None recorded', 'rankkernel' ) : esc_html( $rkRowAgent ); ?></dd></div>
													<?php endif; ?>
												</dl>
												<?php if ( ! $advancedFields ) : ?>
													<p class="rk-ui-hint"><?php echo esc_html__( 'Referer and user agent logging is off. Turn on Advanced fields in Monitor Settings below to capture them.', 'rankkernel' ); ?></p>
												<?php endif; ?>
												<div class="rk-detail-actions">
													<?php if ( $redirectsEnabled ) : ?>
														<a class="rk-ui-btn rk-ui-btn-primary rk-detail-action" href="<?php echo esc_url( $listRowItem['createRedirectUrl'] ); ?>"><span class="rk-icon" aria-hidden="true">add</span><?php echo esc_html__( 'Create Redirect from this URL', 'rankkernel' ); ?><span class="screen-reader-text">: <?php echo esc_html( $rkRowUri ); ?></span></a>
													<?php endif; ?>
													<a class="rk-ui-btn rk-ui-btn-danger rk-confirm rk-detail-delete" href="<?php echo esc_url( $listRowItem['deleteUrl'] ); ?>" data-rk-confirm="<?php echo esc_attr__( 'Delete this entry? This cannot be undone.', 'rankkernel' ); ?>"><?php echo esc_html__( 'Delete entry', 'rankkernel' ); ?><span class="screen-reader-text">: <?php echo esc_html( $rkRowUri ); ?></span></a>
												</div>
											</div>
										</details>
									</td>
									<td class="rk-col-hits"><?php echo esc_html( (string) number_format_i18n( $rkRowHits ) ); ?></td>
									<td class="rk-col-created"><?php echo '' === $rkRowCreatedLabel ? esc_html__( 'Unknown', 'rankkernel' ) : esc_html( $rkRowCreatedLabel ); ?></td>
									<td class="rk-col-seen"><?php echo '' === $rkRowSeenLabel ? esc_html__( 'Unknown', 'rankkernel' ) : esc_html( $rkRowSeenLabel ); ?></td>
									<td class="rk-col-actions">
										<?php if ( $redirectsEnabled ) : ?>
											<a class="rk-ui-btn rk-ui-btn-primary rk-row-action" href="<?php echo esc_url( $listRowItem['createRedirectUrl'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: missing page URL */ __( 'Create redirect for %s', 'rankkernel' ), $rkRowUri ) ); ?>"><?php echo esc_html__( 'Create Redirect', 'rankkernel' ); ?></a>
										<?php endif; ?>
										<a class="rk-ui-btn rk-ui-btn-danger rk-row-action rk-row-delete rk-confirm" href="<?php echo esc_url( $listRowItem['deleteUrl'] ); ?>" data-rk-confirm="<?php echo esc_attr__( 'Delete this entry? This cannot be undone.', 'rankkernel' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: missing page URL */ __( 'Delete 404 entry for %s', 'rankkernel' ), $rkRowUri ) ); ?>"><?php echo esc_html__( 'Delete', 'rankkernel' ); ?></a>
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
								<a class="rk-ui-page-link is-disabled" role="link" aria-disabled="true" tabindex="0"><?php echo esc_html__( 'Previous', 'rankkernel' ); ?></a>
							<?php endif; ?>
							<?php if ( '' !== $pagination['nextUrl'] ) : ?>
								<a class="rk-ui-page-link" href="<?php echo esc_url( $pagination['nextUrl'] ); ?>"><?php echo esc_html__( 'Next', 'rankkernel' ); ?></a>
							<?php else : ?>
								<a class="rk-ui-page-link is-disabled" role="link" aria-disabled="true" tabindex="0"><?php echo esc_html__( 'Next', 'rankkernel' ); ?></a>
							<?php endif; ?>
						</nav>
					<?php endif; ?>
				</div>
			</form>
		<?php endif; ?>
	</section>


	<?php /* Section 7: footer meta caption. The version is the real plugin version. */ ?>
	<footer class="rk-monitor-footer"><?php echo esc_html( sprintf( /* translators: %s: plugin version */ __( 'RankKernel SEO Suite · 404 Monitor · v%s · Zero runtime overhead background logging', 'rankkernel' ), \RankKernel\Plugin::version() ) ); ?></footer>
</div>
