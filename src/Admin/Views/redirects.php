<?php
/**
 * Redirects view.
 *
 * Presentation only. RedirectsPage prepares every variable used below, and owns
 * capability checks, nonce verification, request handling, validation and
 * redirects.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var string $blockedNotice        Form attempt error notice text, empty when none.
 * @var string $successNotice        Success notice text, empty when none.
 * @var string $errorNotice          Error notice text, empty when none.
 * @var string $chainSummary         Chain warning summary, empty when no chain.
 * @var string $chainRecommendation  Chain recommendation text, empty when inconclusive.
 * @var string $chainFinal           Recommended final destination.
 * @var bool   $chainUnknown         Whether the chain analysis was inconclusive.
 * @var string $chainPath            Sanitized chain path, empty when no chain.
 * @var bool   $mayLoop              Whether the loop check was inconclusive.
 * @var bool   $editorOpen           Whether the editor renders open.
 * @var string $toggleUrl            Add Redirect toggle URL.
 * @var string $editorHeading        Editor heading text.
 * @var bool   $editorIsEdit         Whether the editor is editing an existing row.
 * @var int    $editId               Edited row id, zero when adding.
 * @var string $returnTo             Validated return target, empty when none.
 * @var string $sourceValue          Current source value.
 * @var string $sourceDescribed      Source aria-describedby value.
 * @var string $sourceError          Source field error text, empty when none.
 * @var string $matchValue           Current match type.
 * @var string $matchError           Match type field error text, empty when none.
 * @var array<int, array{value: string, label: string, hint: string}> $matchOptions Match type options.
 * @var string $matchHint            Current match type hint.
 * @var bool   $isRegex              Whether the regex matcher is selected.
 * @var array{ok: bool, message: string} $regexState Regex state.
 * @var string $regexClass           Regex feedback class.
 * @var string $targetValue          Current destination value.
 * @var string $targetDescribed      Destination aria-describedby value.
 * @var string $targetError          Destination field error text, empty when none.
 * @var bool   $terminal             Whether the selected code needs no destination.
 * @var string $codeValue            Current status code.
 * @var string $codeError            Code field error text, empty when none.
 * @var array<int, array{value: string, label: string, hint: string}> $codeOptions Code options.
 * @var string $codeHint             Current code hint.
 * @var bool   $activeChecked        Active checkbox state.
 * @var bool   $preserveQuery        Preserve query setting.
 * @var array<int, array{label: string, hint: string}> $matchHintRows Match type explanations.
 * @var array<int, array{label: string, hint: string}> $codeHintRows  Code explanations.
 * @var string $cancelUrl            Editor cancel URL.
 * @var bool   $listHasRows          Whether the list has rows.
 * @var array<int, array<string, mixed>> $listRows Prepared redirect rows.
 * @var array<int, array{url: string, current: bool, label: string, count: string}> $statusViews Status views.
 * @var string $filtersActionUrl     Filters form action URL.
 * @var string $screenSlug           Screen slug.
 * @var string $filterSearch         Current search filter.
 * @var string $filterStatus         Current status filter.
 * @var string $filterMatch          Current match type filter.
 * @var string $filterCode           Current code filter.
 * @var bool   $hasFilter            Whether any filter is active.
 * @var string $clearFiltersUrl      Clear filters URL.
 * @var string $addFirstUrl          Add first redirect URL.
 * @var string $bulkFormAction       Bulk form action URL.
 * @var array<int, array{label: string, url: string, current: bool, arrow: string}> $sortableHeaders Sortable headers.
 * @var array{show: bool, prevUrl: string, nextUrl: string} $pagination Pagination links.
 * @var string $paginationText       Pagination page text.
 * @var string $itemsLabel           Total items label.
 * @var string $exportUrl            CSV export URL.
 * @var bool   $showImportReport     Whether the import report renders.
 * @var string $importSummary        Import summary text.
 * @var string[] $importErrors       Import error lines.
 * @var string[] $importWarnings     Import warning lines.
 * @var bool   $autoSlugRedirect     Auto slug redirect setting.
 * @var int    $rulesPerPage         Rows per page setting.
 * @var string $nonceSaveAction      Save form nonce action.
 * @var string $nonceBulkAction      Bulk form nonce action.
 * @var string $nonceSettingsAction  Settings form nonce action.
 * @var string $nonceImportAction    Import form nonce action.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( '' !== $blockedNotice ) :
	?>
	<div class="notice notice-error"><p><?php echo esc_html( $blockedNotice ); ?></p></div>
	<?php
endif;

if ( '' !== $successNotice ) :
	?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $successNotice ); ?></p></div>
	<?php
endif;

if ( '' !== $errorNotice ) :
	?>
	<div class="notice notice-error"><p><?php echo esc_html( $errorNotice ); ?></p></div>
	<?php
endif;

if ( '' !== $chainSummary ) :
	?>
	<div class="notice notice-warning is-dismissible"><p>
		<?php echo esc_html( $chainSummary ); ?>
		<?php if ( '' !== $chainRecommendation ) : ?>
			<?php echo esc_html( $chainRecommendation ); ?> <button type="button" class="button button-small" data-rk-use-destination="<?php echo esc_attr( $chainFinal ); ?>"><?php echo esc_html__( 'Use recommended destination', 'rankkernel' ); ?></button>
		<?php else : ?>
			<?php echo esc_html__( 'RankKernel could not determine the final destination, so please verify the chain manually. Saved as entered.', 'rankkernel' ); ?>
		<?php endif; ?>
	</p></div>
	<?php
endif;

if ( $mayLoop ) :
	?>
	<div class="notice notice-warning is-dismissible"><p><?php echo esc_html__( 'The loop check could not fully verify this redirect, so a loop is still possible. Please verify it manually.', 'rankkernel' ); ?></p></div>
	<?php
endif;

if ( $chainUnknown && '' === $chainPath ) :
	?>
	<div class="notice notice-info is-dismissible"><p><?php echo esc_html__( 'Chain analysis could not determine the final destination because the next rule uses a pattern matcher. Saved as entered.', 'rankkernel' ); ?></p></div>
	<?php
endif;
?>
<div class="wrap rk-redirects">
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Redirects', 'rankkernel' ); ?></h1>
	<a href="<?php echo esc_url( $toggleUrl ); ?>" class="page-title-action" id="rk-add-toggle" aria-expanded="<?php echo esc_attr( $editorOpen ? 'true' : 'false' ); ?>" aria-controls="rk-redirect-editor"><?php echo esc_html__( 'Add Redirect', 'rankkernel' ); ?></a>
	<p class="rk-sub"><?php echo esc_html__( 'Send visitors from old addresses to new ones. Loops are blocked at save, chains save with a warning.', 'rankkernel' ); ?></p>

	<div class="rk-card rk-editor" id="rk-redirect-editor"<?php echo $editorOpen ? '' : ' hidden'; ?>>
		<h2 id="rk-editor-heading"><?php echo esc_html( $editorHeading ); ?></h2>

		<?php if ( '' !== $returnTo && ! $editorIsEdit ) : ?>
			<p class="rk-prefill"><?php echo esc_html__( 'Source prefilled from the 404 Monitor. Add a destination and save to return to the monitor.', 'rankkernel' ); ?></p>
		<?php endif; ?>

		<form method="post" action="" aria-labelledby="rk-editor-heading">
			<?php wp_nonce_field( $nonceSaveAction ); ?>

			<?php if ( $editorIsEdit ) : ?>
				<input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $editId ); ?>" />
			<?php endif; ?>

			<?php if ( '' !== $returnTo ) : ?>
				<input type="hidden" name="rk_return" value="<?php echo esc_attr( $returnTo ); ?>" />
			<?php endif; ?>

			<table class="form-table" role="presentation"><tbody>
				<tr><th scope="row"><label for="rk-source"><?php echo esc_html__( 'Source URL', 'rankkernel' ); ?></label></th><td>
					<input type="text" id="rk-source" name="rk_source" value="<?php echo esc_attr( $sourceValue ); ?>" class="regular-text code" aria-describedby="<?php echo esc_attr( $sourceDescribed ); ?>" />
					<?php if ( '' !== $sourceError ) : ?>
						<p class="rk-field-error" id="rk-source-error" role="alert"><?php echo esc_html( $sourceError ); ?></p>
					<?php endif; ?>
					<p class="description" id="rk-source-hint" data-fragment="<?php echo esc_attr__( 'Remove the part starting with #. Fragments stay in the browser and are never sent to the server.', 'rankkernel' ); ?>"><?php echo esc_html__( 'Enter the old path, for example /old page. The query string is ignored when matching.', 'rankkernel' ); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="rk-match"><?php echo esc_html__( 'Match type', 'rankkernel' ); ?></label></th><td>
					<select id="rk-match" name="rk_match_type" aria-describedby="rk-match-hint">
						<?php foreach ( $matchOptions as $matchRow ) : ?>
							<option value="<?php echo esc_attr( $matchRow['value'] ); ?>"<?php echo selected( $matchValue, $matchRow['value'], false ); ?> data-hint="<?php echo esc_attr( $matchRow['hint'] ); ?>"><?php echo esc_html( $matchRow['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( '' !== $matchError ) : ?>
						<p class="rk-field-error" id="rk-match_type-error" role="alert"><?php echo esc_html( $matchError ); ?></p>
					<?php endif; ?>
					<p class="description" id="rk-match-hint"><?php echo esc_html( $matchHint ); ?></p>
				</td></tr>
				<tr id="rk-regex-row"<?php echo $isRegex ? '' : ' hidden'; ?>><th scope="row"><?php echo esc_html__( 'Pattern check', 'rankkernel' ); ?></th><td>
					<p class="<?php echo esc_attr( $regexClass ); ?>" id="rk-regex-feedback" role="status" data-msg-empty="<?php echo esc_attr__( 'Enter a pattern to check it. Patterns are limited to 200 characters.', 'rankkernel' ); ?>" data-msg-long="<?php echo esc_attr__( 'That pattern is too long. Please keep regex patterns under 200 characters.', 'rankkernel' ); ?>" data-msg-invalid="<?php echo esc_attr__( 'That pattern does not compile. Check the syntax and try again.', 'rankkernel' ); ?>" data-msg-valid="<?php echo esc_attr__( 'Pattern compiles cleanly.', 'rankkernel' ); ?>" data-msg-anchor="<?php echo esc_attr__( 'Tip: add ^ at the start and $ at the end to match the whole path.', 'rankkernel' ); ?>"><?php echo esc_html( $regexState['message'] ); ?></p>
					<p class="description" id="rk-regex-help"><?php echo esc_html__( 'Full pattern match for advanced use, limited to 200 characters. Anchor with ^ and $ when the whole path must match, for example ^/blog/[0-9]+$.', 'rankkernel' ); ?></p>
				</td></tr>
				<tr id="rk-target-row"<?php echo $terminal ? ' hidden' : ''; ?>><th scope="row"><label for="rk-target"><?php echo esc_html__( 'Destination URL', 'rankkernel' ); ?></label></th><td>
					<input type="text" id="rk-target" name="rk_target" value="<?php echo esc_attr( $targetValue ); ?>" class="regular-text code" aria-describedby="<?php echo esc_attr( $targetDescribed ); ?>"<?php echo $terminal ? ' disabled' : ''; ?> />
					<?php if ( '' !== $targetError ) : ?>
						<p class="rk-field-error" id="rk-target-error" role="alert"><?php echo esc_html( $targetError ); ?></p>
					<?php endif; ?>
					<p class="description" id="rk-target-hint"><?php echo esc_html__( 'Enter where visitors should go, for example /new page. Leave empty only for 410 and 451.', 'rankkernel' ); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="rk-code"><?php echo esc_html__( 'Redirect type', 'rankkernel' ); ?></label></th><td>
					<select id="rk-code" name="rk_code" aria-describedby="rk-code-hint rk-terminal-note">
						<?php foreach ( $codeOptions as $codeRow ) : ?>
							<option value="<?php echo esc_attr( $codeRow['value'] ); ?>"<?php echo selected( $codeValue, $codeRow['value'], false ); ?> data-hint="<?php echo esc_attr( $codeRow['hint'] ); ?>"><?php echo esc_html( $codeRow['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( '' !== $codeError ) : ?>
						<p class="rk-field-error" id="rk-code-error" role="alert"><?php echo esc_html( $codeError ); ?></p>
					<?php endif; ?>
					<p class="description" id="rk-code-hint"><?php echo esc_html( $codeHint ); ?></p>
					<p class="description" id="rk-terminal-note"<?php echo $terminal ? '' : ' hidden'; ?>><?php echo esc_html__( '410 Gone and 451 mean the content is intentionally unavailable, so no destination is needed. The destination field stays disabled while one of these is selected.', 'rankkernel' ); ?></p>
				</td></tr>
				<tr><th scope="row"><?php echo esc_html__( 'Active', 'rankkernel' ); ?></th><td>
					<label><input type="checkbox" name="rk_active" value="1" <?php echo checked( $activeChecked, true, false ); ?> /> <?php echo esc_html__( 'Send visitors now. Turn off to keep the rule saved without redirecting.', 'rankkernel' ); ?></label>
				</td></tr>
			</tbody></table>

			<details class="rk-advanced"><summary><?php echo esc_html__( 'Advanced options', 'rankkernel' ); ?></summary>
				<p class="rk-sub">
					<?php if ( $preserveQuery ) : ?>
						<?php echo esc_html__( 'Matching ignores the query string, and the query string is currently passed to the destination. Change this under Redirect Settings below.', 'rankkernel' ); ?>
					<?php else : ?>
						<?php echo esc_html__( 'Matching ignores the query string, and the query string is currently dropped. Change this under Redirect Settings below.', 'rankkernel' ); ?>
					<?php endif; ?>
				</p>
				<p class="rk-sub"><?php echo esc_html__( 'Pattern matchers (everything except Exact) are limited in how many active rules they can hold. Exact matches are unlimited and fastest.', 'rankkernel' ); ?></p>

				<details class="rk-hints"><summary><?php echo esc_html__( 'What do the match types mean', 'rankkernel' ); ?></summary><ul>
					<?php foreach ( $matchHintRows as $matchHintRow ) : ?>
						<li><strong><?php echo esc_html( $matchHintRow['label'] ); ?></strong> <?php echo esc_html( $matchHintRow['hint'] ); ?></li>
					<?php endforeach; ?>
				</ul></details>

				<details class="rk-hints"><summary><?php echo esc_html__( 'Which redirect type should I use', 'rankkernel' ); ?></summary><ul>
					<?php foreach ( $codeHintRows as $codeHintRow ) : ?>
						<li><strong><?php echo esc_html( $codeHintRow['label'] ); ?></strong> <?php echo esc_html( $codeHintRow['hint'] ); ?></li>
					<?php endforeach; ?>
				</ul></details>
			</details>

			<?php submit_button( $editorIsEdit ? __( 'Update Redirect', 'rankkernel' ) : __( 'Add Redirect', 'rankkernel' ), 'primary', 'rankkernel_redirect_save' ); ?>
			<a class="button" href="<?php echo esc_url( $cancelUrl ); ?>"><?php echo esc_html__( 'Cancel', 'rankkernel' ); ?></a>
		</form>
	</div>

	<h2><?php echo esc_html__( 'All Redirects', 'rankkernel' ); ?></h2>

	<ul class="subsubsub">
		<?php foreach ( $statusViews as $viewIndex => $statusView ) : ?>
			<?php echo 0 === $viewIndex ? '' : ' | '; ?><li><a href="<?php echo esc_url( $statusView['url'] ); ?>"<?php echo esc_attr( $statusView['current'] ? ' class="current"' : '' ); ?>><?php echo esc_html( $statusView['label'] ); ?> <span class="count">(<?php echo esc_html( $statusView['count'] ); ?>)</span></a></li>
		<?php endforeach; ?>
	</ul><br class="clear" />

	<form method="get" action="<?php echo esc_url( $filtersActionUrl ); ?>" class="rk-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( $screenSlug ); ?>" />
		<p class="search-box">
			<label for="rk-search-input" class="screen-reader-text"><?php echo esc_html__( 'Search redirects', 'rankkernel' ); ?></label>
			<input type="search" id="rk-search-input" name="s" value="<?php echo esc_attr( $filterSearch ); ?>" placeholder="<?php echo esc_attr__( 'Search redirects', 'rankkernel' ); ?>" />
			<?php submit_button( __( 'Search', 'rankkernel' ), '', '', false ); ?>
		</p>

		<div class="alignleft actions">
			<label for="rk-filter-status" class="screen-reader-text"><?php echo esc_html__( 'Filter by status', 'rankkernel' ); ?></label>
			<select name="rk_status" id="rk-filter-status">
				<option value="all"<?php echo selected( $filterStatus, 'all', false ); ?>><?php echo esc_html__( 'All statuses', 'rankkernel' ); ?></option>
				<option value="active"<?php echo selected( $filterStatus, 'active', false ); ?>><?php echo esc_html__( 'Active', 'rankkernel' ); ?></option>
				<option value="inactive"<?php echo selected( $filterStatus, 'inactive', false ); ?>><?php echo esc_html__( 'Inactive', 'rankkernel' ); ?></option>
			</select> 

			<label for="rk-filter-match" class="screen-reader-text"><?php echo esc_html__( 'Filter by match type', 'rankkernel' ); ?></label>
			<select name="rk_match" id="rk-filter-match">
				<option value=""><?php echo esc_html__( 'All match types', 'rankkernel' ); ?></option>
				<?php foreach ( $matchOptions as $matchRow ) : ?>
					<option value="<?php echo esc_attr( $matchRow['value'] ); ?>"<?php echo selected( (string) $filterMatch, $matchRow['value'], false ); ?>><?php echo esc_html( $matchRow['label'] ); ?></option>
				<?php endforeach; ?>
			</select> 

			<label for="rk-filter-code" class="screen-reader-text"><?php echo esc_html__( 'Filter by redirect type', 'rankkernel' ); ?></label>
			<select name="rk_code" id="rk-filter-code">
				<option value=""><?php echo esc_html__( 'All codes', 'rankkernel' ); ?></option>
				<?php foreach ( $codeOptions as $codeRow ) : ?>
					<option value="<?php echo esc_attr( $codeRow['value'] ); ?>"<?php echo selected( (string) $filterCode, $codeRow['value'], false ); ?>><?php echo esc_html( $codeRow['label'] ); ?></option>
				<?php endforeach; ?>
			</select> 
			<?php submit_button( __( 'Filter', 'rankkernel' ), '', 'rk_filter', false ); ?>
		</div><br class="clear" />
	</form>

	<?php if ( ! $listHasRows ) : ?>
		<div class="rk-empty">
			<?php if ( $hasFilter ) : ?>
				<p><strong><?php echo esc_html__( 'No redirects match your search.', 'rankkernel' ); ?></strong></p>
				<p><?php echo esc_html__( 'Try a different search or clear the filters to see every redirect.', 'rankkernel' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( $clearFiltersUrl ); ?>"><?php echo esc_html__( 'Clear filters', 'rankkernel' ); ?></a></p>
			<?php else : ?>
				<p><strong><?php echo esc_html__( 'No redirects yet.', 'rankkernel' ); ?></strong></p>
				<p><?php echo esc_html__( 'Add your first redirect above to send visitors from an old address to a new one.', 'rankkernel' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( $addFirstUrl ); ?>"><?php echo esc_html__( 'Add your first redirect', 'rankkernel' ); ?></a></p>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( $bulkFormAction ); ?>" id="rk-bulk-form" data-rk-confirm="<?php echo esc_attr__( 'Delete the selected redirects? This cannot be undone.', 'rankkernel' ); ?>">
			<?php wp_nonce_field( $nonceBulkAction ); ?>

			<div class="tablenav top"><div class="alignleft actions bulkactions">
				<label for="rk-bulk-action" class="screen-reader-text"><?php echo esc_html__( 'Select bulk action', 'rankkernel' ); ?></label>
				<select name="rk_bulk_action" id="rk-bulk-action">
					<option value=""><?php echo esc_html__( 'Bulk actions', 'rankkernel' ); ?></option>
					<option value="activate"><?php echo esc_html__( 'Activate', 'rankkernel' ); ?></option>
					<option value="deactivate"><?php echo esc_html__( 'Deactivate', 'rankkernel' ); ?></option>
					<option value="delete"><?php echo esc_html__( 'Delete', 'rankkernel' ); ?></option>
				</select>
				<?php submit_button( __( 'Apply', 'rankkernel' ), 'action', 'rankkernel_redirect_bulk', false ); ?>
			</div>
			<?php if ( $pagination['show'] ) : ?>
				<div class="tablenav-pages rk-pages-top">
					<span class="paging-text"><?php echo esc_html( $paginationText ); ?></span>
					<?php if ( '' !== $pagination['prevUrl'] ) : ?>
						<a class="button" href="<?php echo esc_url( $pagination['prevUrl'] ); ?>"><?php echo esc_html__( 'Previous', 'rankkernel' ); ?></a>
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
					<?php foreach ( $sortableHeaders as $columnHeader ) : ?>
						<th scope="col" class="manage-column sortable<?php echo $columnHeader['current'] ? ' sorted' : ''; ?>">
							<a href="<?php echo esc_url( $columnHeader['url'] ); ?>"><span><?php echo esc_html( $columnHeader['label'] ); ?><?php echo esc_html( $columnHeader['arrow'] ); ?></span></a>
						</th>
					<?php endforeach; ?>
					<th scope="col" class="rk-col-status"><?php echo esc_html__( 'Status', 'rankkernel' ); ?></th>
				</tr></thead><tbody>
				<?php foreach ( $listRows as $ruleRow ) : ?>
					<tr>
						<th scope="row" class="check-column"><input type="checkbox" name="rule_ids[]" value="<?php echo esc_attr( (string) $ruleRow['id'] ); ?>" aria-label="<?php echo esc_attr( $ruleRow['selectLabel'] ); ?>" /></th>
						<td class="rk-col-from"><strong><?php echo esc_html( $ruleRow['source'] ); ?></strong>
							<div class="row-actions">
								<span class="edit"><a href="<?php echo esc_url( $ruleRow['editUrl'] ); ?>"><?php echo esc_html__( 'Edit', 'rankkernel' ); ?></a> | </span>
								<span class="toggle"><a href="<?php echo esc_url( $ruleRow['toggleUrl'] ); ?>"><?php echo esc_html( $ruleRow['toggleLabel'] ); ?></a> | </span>
								<span class="trash"><a href="<?php echo esc_url( $ruleRow['deleteUrl'] ); ?>" class="rk-confirm" data-rk-confirm="<?php echo esc_attr__( 'Delete this redirect? This cannot be undone.', 'rankkernel' ); ?>"><?php echo esc_html__( 'Trash', 'rankkernel' ); ?></a></span>
							</div>
						</td>
						<td class="rk-col-to">
							<?php if ( '' === $ruleRow['target'] ) : ?>
								<span class="rk-muted"><?php echo esc_html__( '(none)', 'rankkernel' ); ?></span>
							<?php else : ?>
								<?php echo esc_html( $ruleRow['target'] ); ?>
							<?php endif; ?>
						</td>
						<td class="rk-col-code"><?php echo esc_html( $ruleRow['code'] ); ?></td>
						<td class="rk-col-match"><?php echo esc_html( $ruleRow['match'] ); ?></td>
						<td class="rk-col-hits"><?php echo esc_html( $ruleRow['hitsLabel'] ); ?></td>
						<td class="rk-col-accessed"><?php echo esc_html( $ruleRow['accessedLabel'] ); ?></td>
						<td class="rk-col-status"><span class="<?php echo esc_attr( $ruleRow['statusPillClass'] ); ?>"><?php echo esc_html( $ruleRow['statusLabel'] ); ?></span></td>
					</tr>
				<?php endforeach; ?>
			</tbody></table>

			<div class="tablenav bottom"><div class="alignleft actions bulkactions">
				<span class="displaying-num"><?php echo esc_html( $itemsLabel ); ?></span>
			</div>
			<?php if ( $pagination['show'] ) : ?>
				<div class="tablenav-pages rk-pages-bottom">
					<span class="paging-text"><?php echo esc_html( $paginationText ); ?></span>
					<?php if ( '' !== $pagination['prevUrl'] ) : ?>
						<a class="button" href="<?php echo esc_url( $pagination['prevUrl'] ); ?>"><?php echo esc_html__( 'Previous', 'rankkernel' ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $pagination['nextUrl'] ) : ?>
						<a class="button" href="<?php echo esc_url( $pagination['nextUrl'] ); ?>"><?php echo esc_html__( 'Next', 'rankkernel' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			</div>
		</form>
	<?php endif; ?>

	<div class="rk-card" id="rk-redirect-csv">
		<h2><?php echo esc_html__( 'Import and Export', 'rankkernel' ); ?></h2>
		<p class="rk-sub"><?php echo esc_html__( 'Move redirects in and out with a CSV file. Columns in order: source, target, code, match type, active, hits, last accessed. Hits and last accessed are export only and are ignored on import.', 'rankkernel' ); ?></p>

		<h3><?php echo esc_html__( 'Export', 'rankkernel' ); ?></h3>
		<p><a class="button" href="<?php echo esc_url( $exportUrl ); ?>"><?php echo esc_html__( 'Export Redirects', 'rankkernel' ); ?></a></p>

		<h3><?php echo esc_html__( 'Import', 'rankkernel' ); ?></h3>
		<form method="post" action="" enctype="multipart/form-data">
			<?php wp_nonce_field( $nonceImportAction ); ?>
			<p><label for="rk-csv-file"><?php echo esc_html__( 'CSV file', 'rankkernel' ); ?></label><br />
			<input type="file" id="rk-csv-file" name="rk_csv_file" accept=".csv,text/csv" /></p>
			<p><label><input type="checkbox" name="rk_csv_update" value="1" /> <?php echo esc_html__( 'Update existing redirects when the source and match type already exist. Leave off to skip duplicates.', 'rankkernel' ); ?></label></p>
			<?php submit_button( __( 'Import Redirects', 'rankkernel' ), 'secondary', 'rankkernel_redirect_import', false ); ?>
		</form>

		<?php if ( $showImportReport ) : ?>
			<div class="rk-import-report">
				<p><strong><?php echo esc_html( $importSummary ); ?></strong></p>

				<?php if ( [] !== $importErrors ) : ?>
					<ul class="rk-import-errors">
						<?php foreach ( $importErrors as $errorLine ) : ?>
							<li><?php echo esc_html( $errorLine ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( [] !== $importWarnings ) : ?>
					<ul class="rk-import-warnings">
						<?php foreach ( $importWarnings as $warningLine ) : ?>
							<li><?php echo esc_html( $warningLine ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="rk-card" id="rk-redirect-settings">
		<h2><?php echo esc_html__( 'Redirect Settings', 'rankkernel' ); ?></h2>

		<form method="post" action="">
			<?php wp_nonce_field( $nonceSettingsAction ); ?>

			<table class="form-table" role="presentation"><tbody>
				<tr><th scope="row"><?php echo esc_html__( 'Query strings', 'rankkernel' ); ?></th><td>
					<label><input type="checkbox" name="rk_preserve_query" value="1" <?php echo checked( $preserveQuery, true, false ); ?> /> <?php echo esc_html__( 'Pass the query string to the destination. Turn off to drop it.', 'rankkernel' ); ?></label>
				</td></tr>
				<tr><th scope="row"><?php echo esc_html__( 'Slug changes', 'rankkernel' ); ?></th><td>
					<label><input type="checkbox" name="rk_auto_slug_redirect" value="1" <?php echo checked( $autoSlugRedirect, true, false ); ?> /> <?php echo esc_html__( 'Create a 301 redirect automatically when a post slug changes. Turn off to stop creating them.', 'rankkernel' ); ?></label>
				</td></tr>
				<tr><th scope="row"><label for="rk-per-page"><?php echo esc_html__( 'Rows per page', 'rankkernel' ); ?></label></th><td>
					<input type="number" id="rk-per-page" name="rk_rules_per_page" value="<?php echo esc_attr( (string) $rulesPerPage ); ?>" class="small-text" min="1" max="100" />
					<p class="description"><?php echo esc_html__( 'How many redirects to show per page, from 1 to 100.', 'rankkernel' ); ?></p>
				</td></tr>
			</tbody></table>

			<?php submit_button( __( 'Save Redirect Settings', 'rankkernel' ), 'secondary', 'rankkernel_redirect_settings_save' ); ?>
		</form>
	</div>
</div>
