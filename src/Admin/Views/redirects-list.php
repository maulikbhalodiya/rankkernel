<?php
/**
 * Redirects list section partial.
 *
 * Renders the tabs, filter bar, and table (or empty states) for the redirect
 * list. This partial is used both by the full-page render() and by the
 * AJAX handleAjaxList() handler so the list logic lives in exactly one place.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var array<int, array{url: string, current: bool, label: string, count: string}> $statusViews Status view links.
 * @var string $filterSearch   Current search term.
 * @var string $filterStatus   Current status filter.
 * @var string $filterMatch    Current match-type filter.
 * @var string $filterCode     Current code filter.
 * @var bool   $hasFilter      Whether any filter is active.
 * @var string $clearFiltersUrl URL that clears all filters.
 * @var string $addFirstUrl    URL that opens the editor for a first redirect.
 * @var string $bulkFormAction URL for the bulk-action POST form.
 * @var string $filtersActionUrl URL for the filter GET form.
 * @var string $screenSlug     Admin page slug.
 * @var bool   $listHasRows    Whether any rows exist.
 * @var array<int, array<string, mixed>> $listRows Prepared redirect rows.
 * @var array<int, array{value: string, label: string, hint: string}> $matchOptions Match type options.
 * @var array<int, array{value: string, label: string, hint: string}> $codeOptions  Code options.
 * @var array<int, array{label: string, url: string, current: bool, arrow: string, column: string}> $sortableHeaders Sortable headers.
 * @var array{show: bool, prevUrl: string, nextUrl: string, pages: array<int, array{label: string, url: string, current: bool}>} $pagination Pagination links plus numbered pages.
 * @var string $paginationText Pagination label text.
 * @var string $showingLabel   Visible range label text.
 * @var string $totalLabel     Total rows label text.
 * @var int    $perPage        Current rows per page.
 * @var array<int, int> $perPageOptions Rows per page choices.
 * @var string $nonceBulkAction Nonce action for the bulk form.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/*
 * Badge maps for the table loop.
 */
$rkListCodeBadgeMap = [
	'301' => 'rk-badge rk-badge-301',
	'302' => 'rk-badge rk-badge-302',
	'307' => 'rk-badge rk-badge-307',
	'410' => 'rk-badge rk-badge-410',
	'451' => 'rk-badge rk-badge-451',
];

$rkListMatchBadgeMap = [
	'exact'  => 'rk-badge rk-badge-exact',
	'prefix' => 'rk-badge rk-badge-prefix',
	'regex'  => 'rk-badge rk-badge-regex',
];
?>
<div class="rk-table-section" id="rk-list-section">

	<?php /* Status tabs */ ?>
	<nav
		class="rk-tabs"
		aria-label="<?php echo esc_attr__( 'Filter redirects by status', 'rankkernel' ); ?>"
	>
		<?php foreach ( $statusViews as $statusView ) : ?>
			<a
				href="<?php echo esc_url( $statusView['url'] ); ?>"
				class="rk-tab<?php echo $statusView['current'] ? ' current' : ''; ?>"
				data-rk-filter-url="<?php echo esc_url( $statusView['url'] ); ?>"
				<?php echo $statusView['current'] ? ' aria-current="page"' : ''; ?>
			><?php echo esc_html( $statusView['label'] ); ?> <span class="count"><?php echo esc_html( $statusView['count'] ); ?></span></a>
		<?php endforeach; ?>
	</nav>

	<?php /* Filter bar */ ?>
	<div class="rk-filter-bar-wrap">
		<form
			method="get"
			action="<?php echo esc_url( $filtersActionUrl ); ?>"
			class="rk-filter-bar"
			id="rk-filter-form"
		>
			<input type="hidden" name="page" value="<?php echo esc_attr( $screenSlug ); ?>" />

			<div class="rk-filter-left">
				<div class="rk-search-wrap">
					<label for="rk-search-input" class="screen-reader-text"><?php echo esc_html__( 'Search redirects', 'rankkernel' ); ?></label>
					<span class="rk-icon rk-search-icon" aria-hidden="true">search</span>
					<input
						type="search"
						id="rk-search-input"
						name="s"
						value="<?php echo esc_attr( $filterSearch ); ?>"
						placeholder="<?php echo esc_attr__( 'Search redirects...', 'rankkernel' ); ?>"
						class="rk-search-input"
					/>
				</div>
				<?php submit_button( __( 'Filter', 'rankkernel' ), 'secondary rk-filter-submit', 'rk_filter', false ); ?>
			</div>

			<div class="rk-filter-right">
				<label for="rk-filter-match" class="screen-reader-text"><?php echo esc_html__( 'Filter by match type', 'rankkernel' ); ?></label>
				<select name="rk_match" id="rk-filter-match" class="rk-filter-select">
					<option value=""><?php echo esc_html__( 'All match types', 'rankkernel' ); ?></option>
					<?php foreach ( $matchOptions as $matchRow ) : ?>
						<option
							value="<?php echo esc_attr( $matchRow['value'] ); ?>"
							<?php echo selected( (string) $filterMatch, $matchRow['value'], false ); ?>
						><?php echo esc_html( $matchRow['label'] ); ?></option>
					<?php endforeach; ?>
				</select>

				<label for="rk-filter-code" class="screen-reader-text"><?php echo esc_html__( 'Filter by redirect type', 'rankkernel' ); ?></label>
				<select name="rk_code" id="rk-filter-code" class="rk-filter-select">
					<option value=""><?php echo esc_html__( 'All codes', 'rankkernel' ); ?></option>
					<?php foreach ( $codeOptions as $codeRow ) : ?>
						<option
							value="<?php echo esc_attr( $codeRow['value'] ); ?>"
							<?php echo selected( (string) $filterCode, $codeRow['value'], false ); ?>
						><?php echo esc_html( $codeRow['label'] ); ?></option>
					<?php endforeach; ?>
				</select>

				<?php if ( $hasFilter ) : ?>
					<a
						href="<?php echo esc_url( $clearFiltersUrl ); ?>"
						class="rk-filter-clear"
						data-rk-filter-url="<?php echo esc_url( $clearFiltersUrl ); ?>"
					><?php echo esc_html__( 'Clear filters', 'rankkernel' ); ?></a>
				<?php endif; ?>
			</div>
		</form>
	</div>

	<?php /* Empty states */ ?>

	<?php if ( ! $listHasRows ) : ?>

		<div class="rk-empty">
			<?php if ( $hasFilter ) : ?>
				<div class="rk-empty-icon" aria-hidden="true"><span class="rk-icon" aria-hidden="true">search_off</span></div>
				<p class="rk-empty-title"><?php echo esc_html__( 'No redirects match your search.', 'rankkernel' ); ?></p>
				<p class="rk-empty-body"><?php echo esc_html__( 'Try a different search or clear the active filter controls.', 'rankkernel' ); ?></p>
				<a class="button" href="<?php echo esc_url( $clearFiltersUrl ); ?>"><span class="rk-icon" aria-hidden="true">filter_alt_off</span><?php echo esc_html__( 'Clear filters', 'rankkernel' ); ?></a>
			<?php else : ?>
				<div class="rk-empty-icon rk-empty-icon-primary" aria-hidden="true"><span class="rk-icon" aria-hidden="true">alt_route</span></div>
				<p class="rk-empty-title"><?php echo esc_html__( 'No redirects yet.', 'rankkernel' ); ?></p>
				<p class="rk-empty-body"><?php echo esc_html__( 'Add your first redirect to send visitors from an old address to a new one.', 'rankkernel' ); ?></p>
				<a class="button button-primary" href="<?php echo esc_url( $addFirstUrl ); ?>"><span class="rk-icon" aria-hidden="true">add</span><?php echo esc_html__( 'Add your first redirect', 'rankkernel' ); ?></a>
			<?php endif; ?>
		</div>

	<?php else : ?>

		<form
			method="post"
			action="<?php echo esc_url( $bulkFormAction ); ?>"
			id="rk-bulk-form"
			data-rk-confirm="<?php echo esc_attr__( 'Delete the selected redirects? This cannot be undone.', 'rankkernel' ); ?>"
		>
			<?php wp_nonce_field( $nonceBulkAction ); ?>

			<?php /* Top nav: bulk actions + pagination */ ?>
			<div class="rk-tablenav rk-tablenav-top">
				<div class="rk-bulk-select">
					<label for="rk-bulk-action" class="screen-reader-text"><?php echo esc_html__( 'Select bulk action', 'rankkernel' ); ?></label>
					<select name="rk_bulk_action" id="rk-bulk-action">
						<option value=""><?php echo esc_html__( 'Bulk actions', 'rankkernel' ); ?></option>
						<option value="activate"><?php echo esc_html__( 'Activate', 'rankkernel' ); ?></option>
						<option value="deactivate"><?php echo esc_html__( 'Deactivate', 'rankkernel' ); ?></option>
						<option value="delete"><?php echo esc_html__( 'Delete', 'rankkernel' ); ?></option>
					</select>
					<?php submit_button( __( 'Apply', 'rankkernel' ), 'action', 'rankkernel_redirect_bulk', false ); ?>
					<span class="rk-selected-chip" id="rk-selected-chip" hidden></span>
				</div>

				<div class="rk-pages">
					<?php if ( $pagination['show'] ) : ?>
						<span class="paging-text"><?php echo esc_html( $paginationText ); ?></span>
						<?php if ( '' !== $pagination['prevUrl'] ) : ?>
							<a class="button" href="<?php echo esc_url( $pagination['prevUrl'] ); ?>" data-rk-filter-url="<?php echo esc_url( $pagination['prevUrl'] ); ?>"><?php echo esc_html__( 'Previous', 'rankkernel' ); ?></a>
						<?php endif; ?>
						<?php if ( '' !== $pagination['nextUrl'] ) : ?>
							<a class="button" href="<?php echo esc_url( $pagination['nextUrl'] ); ?>" data-rk-filter-url="<?php echo esc_url( $pagination['nextUrl'] ); ?>"><?php echo esc_html__( 'Next', 'rankkernel' ); ?></a>
						<?php endif; ?>
					<?php endif; ?>
					<span class="rk-items-count"><?php echo esc_html( $totalLabel ); ?></span>
				</div>
			</div>

			<div class="rk-table-wrap">
				<table class="wp-list-table widefat fixed striped rk-table">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column rk-col-cb">
								<label for="rk-select-all" class="screen-reader-text"><?php echo esc_html__( 'Select All', 'rankkernel' ); ?></label>
								<input type="checkbox" id="rk-select-all" />
							</td>
							<?php
							$rkHeaderColMap = [
								'source'        => 'rk-col-from',
								'target'        => 'rk-col-to',
								'code'          => 'rk-col-code',
								'match_type'    => 'rk-col-match',
								'hits'          => 'rk-col-hits',
								'last_accessed' => 'rk-col-accessed',
							];
							?>
							<?php foreach ( $sortableHeaders as $columnHeader ) : ?>
								<th
									scope="col"
									class="manage-column sortable<?php echo $columnHeader['current'] ? ' sorted' : ''; ?> <?php echo esc_attr( $rkHeaderColMap[ (string) $columnHeader['column'] ] ?? '' ); ?>"
								>
									<a
										href="<?php echo esc_url( $columnHeader['url'] ); ?>"
										data-rk-filter-url="<?php echo esc_url( $columnHeader['url'] ); ?>"
									><span><?php echo esc_html( $columnHeader['label'] ); ?><?php echo esc_html( $columnHeader['arrow'] ); ?></span></a>
								</th>
							<?php endforeach; ?>
							<th scope="col" class="rk-col-status"><?php echo esc_html__( 'Status', 'rankkernel' ); ?></th>
							<th scope="col" class="rk-col-actions"><?php echo esc_html__( 'Actions', 'rankkernel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $listRows as $ruleRow ) : ?>
							<tr>
								<th scope="row" class="check-column rk-col-cb">
									<input
										type="checkbox"
										name="rule_ids[]"
										value="<?php echo esc_attr( (string) $ruleRow['id'] ); ?>"
										aria-label="<?php echo esc_attr( $ruleRow['selectLabel'] ); ?>"
									/>
								</th>

								<td class="rk-col-from">
									<strong><?php echo esc_html( $ruleRow['source'] ); ?></strong>
								</td>

								<td class="rk-col-to">
									<?php if ( '' === $ruleRow['target'] ) : ?>
										<span class="rk-muted"><?php echo esc_html__( '(none)', 'rankkernel' ); ?></span>
									<?php else : ?>
										<?php echo esc_html( $ruleRow['target'] ); ?>
									<?php endif; ?>
								</td>

								<?php
								$rkCodeVal   = esc_html( $ruleRow['code'] );
								$rkCodeClass = $rkListCodeBadgeMap[ $ruleRow['code'] ] ?? 'rk-badge rk-badge-exact';
								?>
								<td class="rk-col-code">
									<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $rkCodeVal is esc_html() escaped when assigned above. ?>
									<span class="<?php echo esc_attr( $rkCodeClass ); ?>"><?php echo $rkCodeVal; ?></span>
								</td>

								<?php
								$rkMatchVal   = esc_html( $ruleRow['match'] );
								$rkMatchClass = $rkListMatchBadgeMap[ strtolower( $ruleRow['match'] ) ] ?? 'rk-badge rk-badge-exact';
								?>
								<td class="rk-col-match">
									<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $rkMatchVal is esc_html() escaped when assigned above. ?>
									<span class="<?php echo esc_attr( $rkMatchClass ); ?>"><?php echo $rkMatchVal; ?></span>
								</td>

								<td class="rk-col-hits<?php echo $ruleRow['hitsDim'] ? ' rk-dim' : ''; ?>"><?php echo esc_html( $ruleRow['hitsLabel'] ); ?></td>
								<td class="rk-col-accessed<?php echo $ruleRow['accessedDim'] ? ' rk-dim' : ''; ?>"><?php echo esc_html( $ruleRow['accessedLabel'] ); ?></td>

								<td class="rk-col-status">
									<span class="<?php echo esc_attr( $ruleRow['statusPillClass'] ); ?>"><?php echo esc_html( $ruleRow['statusLabel'] ); ?></span>
								</td>

								<?php
								$rkToggleAria = ! empty( $ruleRow['active'] )
									? sprintf( /* translators: %s: redirect source URL */ __( 'Deactivate redirect for %s', 'rankkernel' ), $ruleRow['source'] )
									: sprintf( /* translators: %s: redirect source URL */ __( 'Activate redirect for %s', 'rankkernel' ), $ruleRow['source'] );
								?>
								<td class="rk-col-actions">
									<div class="row-actions">
										<span class="edit"><a href="<?php echo esc_url( $ruleRow['editUrl'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: redirect source URL */ __( 'Edit redirect for %s', 'rankkernel' ), $ruleRow['source'] ) ); ?>"><?php echo esc_html__( 'Edit', 'rankkernel' ); ?></a></span><span class="rk-row-sep" aria-hidden="true">|</span>
										<span class="toggle"><a href="<?php echo esc_url( $ruleRow['toggleUrl'] ); ?>" aria-label="<?php echo esc_attr( $rkToggleAria ); ?>"><?php echo esc_html( $ruleRow['toggleLabel'] ); ?></a></span><span class="rk-row-sep" aria-hidden="true">|</span>
										<span class="trash"><a href="<?php echo esc_url( $ruleRow['deleteUrl'] ); ?>" class="rk-confirm" data-rk-confirm="<?php echo esc_attr__( 'Delete this redirect? This cannot be undone.', 'rankkernel' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: redirect source URL */ __( 'Delete redirect for %s', 'rankkernel' ), $ruleRow['source'] ) ); ?>"><?php echo esc_html__( 'Trash', 'rankkernel' ); ?></a></span>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div><!-- .rk-table-wrap -->
		</form>

			<?php /* Bottom nav: item count + pagination. Outside the bulk form so the rows per page GET form never nests. */ ?>
			<div class="rk-tablenav rk-tablenav-bottom">
				<span class="rk-showing"><?php echo wp_kses( $showingLabel, [ 'strong' => [] ] ); ?></span>
				<div class="rk-pages">
					<form method="get" action="<?php echo esc_url( $filtersActionUrl ); ?>" class="rk-perpage-form" id="rk-perpage-form">
						<input type="hidden" name="page" value="<?php echo esc_attr( $screenSlug ); ?>" />
						<input type="hidden" name="s" value="<?php echo esc_attr( $filterSearch ); ?>" />
						<input type="hidden" name="rk_status" value="<?php echo esc_attr( $filterStatus ); ?>" />
						<input type="hidden" name="rk_match" value="<?php echo esc_attr( $filterMatch ); ?>" />
						<input type="hidden" name="rk_code" value="<?php echo esc_attr( $filterCode ); ?>" />
						<label for="rk-perpage-select" class="rk-perpage-label"><?php echo esc_html__( 'Rows per page:', 'rankkernel' ); ?></label>
						<select name="rk_per_page" id="rk-perpage-select" class="rk-perpage-select">
							<?php foreach ( $perPageOptions as $perPageOption ) : ?>
								<option
									value="<?php echo esc_attr( (string) $perPageOption ); ?>"
									<?php echo selected( $perPage, $perPageOption, false ); ?>
								><?php echo esc_html( (string) $perPageOption ); ?></option>
							<?php endforeach; ?>
						</select>
						<noscript><button type="submit" class="button"><?php echo esc_html__( 'Apply', 'rankkernel' ); ?></button></noscript>
					</form>
					<?php if ( $pagination['show'] ) : ?>
						<div class="rk-page-nums" role="navigation" aria-label="<?php echo esc_attr__( 'Redirect list pages', 'rankkernel' ); ?>">
							<?php if ( '' !== $pagination['prevUrl'] ) : ?>
								<a class="button rk-page-prev" href="<?php echo esc_url( $pagination['prevUrl'] ); ?>" data-rk-filter-url="<?php echo esc_url( $pagination['prevUrl'] ); ?>"><?php echo esc_html__( 'Previous', 'rankkernel' ); ?></a>
							<?php endif; ?>
							<?php foreach ( $pagination['pages'] as $pageEntry ) : ?>
								<?php if ( '' === $pageEntry['url'] ) : ?>
									<span class="rk-page-gap" aria-hidden="true"><?php echo esc_html( $pageEntry['label'] ); ?></span>
								<?php elseif ( $pageEntry['current'] ) : ?>
									<span class="button rk-page-num rk-page-current" aria-current="page"><?php echo esc_html( $pageEntry['label'] ); ?></span>
								<?php else : ?>
									<a class="button rk-page-num" href="<?php echo esc_url( $pageEntry['url'] ); ?>" data-rk-filter-url="<?php echo esc_url( $pageEntry['url'] ); ?>"><?php echo esc_html( $pageEntry['label'] ); ?></a>
								<?php endif; ?>
							<?php endforeach; ?>
							<?php if ( '' !== $pagination['nextUrl'] ) : ?>
								<a class="button rk-page-next" href="<?php echo esc_url( $pagination['nextUrl'] ); ?>" data-rk-filter-url="<?php echo esc_url( $pagination['nextUrl'] ); ?>"><?php echo esc_html__( 'Next', 'rankkernel' ); ?></a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

	<?php endif; ?>

</div><!-- #rk-list-section -->
