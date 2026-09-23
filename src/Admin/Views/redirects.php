<?php
/**
 * Redirects view.
 *
 * Presentation only. RedirectsPage prepares every variable used below, and owns
 * capability checks, nonce verification, request handling, validation and
 * redirects.
 *
 * Layout note: the outer .wrap div is intentionally opened here and closed at
 * the very end. WordPress injects its own admin notices into .wrap before our
 * content, so our RankKernel-styled notices are rendered first inside
 * .rk-redirects, while native WP plugin notices from other sources appear
 * above the page header where WordPress places them.
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
 * @var string $countPill            Header rule total pill text.
 * @var string $sourceCount          Source field character count text.
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
 * @var string $targetCount          Destination field character count text.
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
 * @var string $listSectionHtml      Pre-rendered list section HTML from renderListSection().
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
 * @var array<int, array{label: string, url: string, current: bool, arrow: string, column: string}> $sortableHeaders Sortable headers.
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

/*
 * Badge maps: built once, used in the table loop below.
 * Unknown codes and match types fall back to the neutral exact style.
 */
$rkCodeBadgeMap = [
	'301' => 'rk-badge rk-badge-301',
	'302' => 'rk-badge rk-badge-302',
	'307' => 'rk-badge rk-badge-307',
	'410' => 'rk-badge rk-badge-410',
	'451' => 'rk-badge rk-badge-451',
];

$rkMatchBadgeMap = [
	'exact'  => 'rk-badge rk-badge-exact',
	'prefix' => 'rk-badge rk-badge-prefix',
	'regex'  => 'rk-badge rk-badge-regex',
];
?>
<div class="wrap rk-redirects-wrap">

	<?php
	/*
	 * WordPress injects third-party plugin notices directly into .wrap before
	 * our rendered content. We output a screen-reader heading here so WP has
	 * the h1 it expects, then open .rk-redirects below the native notice area.
	 */
	?>
	<h1 class="screen-reader-text"><?php echo esc_html__( 'Redirects', 'rankkernel' ); ?></h1>

	<div class="rk-redirects">

		<?php /* ---- RankKernel styled notices ---------------------------------- */ ?>

		<?php if ( '' !== $blockedNotice ) : ?>
			<div class="rk-notice rk-notice-error" role="alert">
				<span class="rk-icon rk-notice-icon" aria-hidden="true">cancel</span>
				<div class="rk-notice-body">
					<p class="rk-notice-title"><?php echo esc_html__( 'Action blocked.', 'rankkernel' ); ?></p>
					<p class="rk-notice-text"><?php echo esc_html( $blockedNotice ); ?></p>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $successNotice ) : ?>
			<div class="rk-notice rk-notice-success" role="status">
				<span class="rk-icon rk-notice-icon" aria-hidden="true">check_circle</span>
				<div class="rk-notice-body">
					<p class="rk-notice-title"><?php echo esc_html( $successNotice ); ?></p>
				</div>
				<button type="button" class="rk-notice-dismiss" aria-label="<?php echo esc_attr__( 'Dismiss this notice', 'rankkernel' ); ?>"><span class="rk-icon" aria-hidden="true">close</span></button>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $errorNotice ) : ?>
			<div class="rk-notice rk-notice-error" role="alert">
				<span class="rk-icon rk-notice-icon" aria-hidden="true">cancel</span>
				<div class="rk-notice-body">
					<p class="rk-notice-title"><?php echo esc_html__( 'Action failed.', 'rankkernel' ); ?></p>
					<p class="rk-notice-text"><?php echo esc_html( $errorNotice ); ?></p>
				</div>
				<button type="button" class="rk-notice-dismiss" aria-label="<?php echo esc_attr__( 'Dismiss this notice', 'rankkernel' ); ?>"><span class="rk-icon" aria-hidden="true">close</span></button>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $chainSummary ) : ?>
			<div class="rk-notice rk-notice-warning" role="status">
				<span class="rk-icon rk-notice-icon" aria-hidden="true">warning</span>
				<div class="rk-notice-body">
					<p class="rk-notice-title"><?php echo esc_html__( 'Redirect chain detected.', 'rankkernel' ); ?></p>
					<p class="rk-notice-text"><?php echo esc_html( $chainSummary ); ?></p>
					<?php if ( '' !== $chainRecommendation ) : ?>
						<p class="rk-notice-text"><?php echo esc_html( $chainRecommendation ); ?></p>
						<button
							type="button"
							class="rk-notice-action"
							data-rk-use-destination="<?php echo esc_attr( $chainFinal ); ?>"
						><?php echo esc_html__( 'Use recommended destination', 'rankkernel' ); ?></button>
					<?php else : ?>
						<p class="rk-notice-text"><?php echo esc_html__( 'RankKernel could not determine the final destination, so please verify the chain manually. Saved as entered.', 'rankkernel' ); ?></p>
					<?php endif; ?>
				</div>
				<button type="button" class="rk-notice-dismiss" aria-label="<?php echo esc_attr__( 'Dismiss this notice', 'rankkernel' ); ?>"><span class="rk-icon" aria-hidden="true">close</span></button>
			</div>
		<?php endif; ?>

		<?php if ( $mayLoop ) : ?>
			<div class="rk-notice rk-notice-warning" role="status">
				<span class="rk-icon rk-notice-icon" aria-hidden="true">error</span>
				<div class="rk-notice-body">
					<p class="rk-notice-title"><?php echo esc_html__( 'Loop check inconclusive.', 'rankkernel' ); ?></p>
					<p class="rk-notice-text"><?php echo esc_html__( 'The loop check could not fully verify this redirect, so a loop is still possible. Please verify it manually.', 'rankkernel' ); ?></p>
				</div>
				<button type="button" class="rk-notice-dismiss" aria-label="<?php echo esc_attr__( 'Dismiss this notice', 'rankkernel' ); ?>"><span class="rk-icon" aria-hidden="true">close</span></button>
			</div>
		<?php endif; ?>

		<?php if ( $chainUnknown && '' === $chainPath ) : ?>
			<div class="rk-notice rk-notice-info" role="status">
				<span class="rk-icon rk-notice-icon" aria-hidden="true">info</span>
				<div class="rk-notice-body">
					<p class="rk-notice-title"><?php echo esc_html__( 'Chain analysis incomplete.', 'rankkernel' ); ?></p>
					<p class="rk-notice-text"><?php echo esc_html__( 'Chain analysis could not determine the final destination because the next rule uses a pattern matcher. Saved as entered.', 'rankkernel' ); ?></p>
				</div>
				<button type="button" class="rk-notice-dismiss" aria-label="<?php echo esc_attr__( 'Dismiss this notice', 'rankkernel' ); ?>"><span class="rk-icon" aria-hidden="true">close</span></button>
			</div>
		<?php endif; ?>

		<?php /* ---- Page header card ------------------------------------------ */ ?>

		<div class="rk-page-header">
			<div class="rk-page-header-left">
				<div class="rk-page-header-title-row">
					<h2 class="rk-page-title"><?php echo esc_html__( 'Redirects', 'rankkernel' ); ?></h2>
					<span class="rk-count-pill"><?php echo esc_html( $countPill ); ?></span>
				</div>
				<p class="rk-sub"><?php echo esc_html__( 'Send visitors from old addresses to new ones. Loops are blocked at save, chains save with a warning.', 'rankkernel' ); ?></p>
			</div>
			<div class="rk-page-header-actions">
				<button
					type="button"
					class="button button-primary rk-btn-add"
					id="rk-add-toggle"
					aria-expanded="<?php echo esc_attr( $editorOpen ? 'true' : 'false' ); ?>"
					aria-controls="rk-redirect-editor"
					data-toggle-url="<?php echo esc_url( $toggleUrl ); ?>"
					data-rk-panel-toggle="rk-redirect-editor"
				><span class="rk-icon" aria-hidden="true">add</span><?php echo esc_html__( 'Add Redirect', 'rankkernel' ); ?></button>
				<button
					type="button"
					class="button rk-btn-export"
					id="rk-csv-toggle"
					aria-expanded="false"
					aria-controls="rk-redirect-csv"
					data-rk-panel-toggle="rk-redirect-csv"
				><span class="rk-icon" aria-hidden="true">download</span><?php echo esc_html__( 'Export CSV', 'rankkernel' ); ?></button>
				<button
					type="button"
					class="button rk-btn-settings-toggle"
					id="rk-settings-toggle"
					aria-expanded="false"
					aria-controls="rk-redirect-settings"
					data-rk-panel-toggle="rk-redirect-settings"
					title="<?php echo esc_attr__( 'Redirect Settings', 'rankkernel' ); ?>"
					aria-label="<?php echo esc_attr__( 'Redirect Settings', 'rankkernel' ); ?>"
				><span class="rk-icon" aria-hidden="true">settings</span></button>
			</div>
		</div>

		<?php /* ---- Editor card ------------------------------------------------ */ ?>

		<div
			class="rk-card rk-editor"
			id="rk-redirect-editor"
			<?php echo $editorOpen ? '' : ' hidden'; ?>
		>
			<div class="rk-card-header">
				<div class="rk-card-header-left">
					<span class="rk-card-header-dot" aria-hidden="true"></span>
					<h3 class="rk-card-title" id="rk-editor-heading"><?php echo esc_html( $editorHeading ); ?></h3>
				</div>
				<?php // Cancel is a JS-only action: hides the editor without a page reload. ?>
				<button
					type="button"
					class="rk-card-cancel"
					id="rk-editor-cancel"
				><?php echo esc_html__( 'Cancel', 'rankkernel' ); ?></button>
			</div>

			<?php if ( '' !== $returnTo && ! $editorIsEdit ) : ?>
				<div class="rk-prefill">
					<span class="rk-icon rk-prefill-icon" aria-hidden="true">link</span>
					<div>
						<strong><?php echo esc_html__( 'Source prefilled from the 404 Monitor.', 'rankkernel' ); ?></strong>
						<span class="rk-prefill-hint"><?php echo esc_html__( 'Add a destination and save to return to the monitor.', 'rankkernel' ); ?></span>
					</div>
				</div>
			<?php endif; ?>

			<form method="post" action="" class="rk-editor-form" aria-labelledby="rk-editor-heading">
				<?php wp_nonce_field( $nonceSaveAction ); ?>

				<?php if ( $editorIsEdit ) : ?>
					<input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $editId ); ?>" />
				<?php endif; ?>

				<?php if ( '' !== $returnTo ) : ?>
					<input type="hidden" name="rk_return" value="<?php echo esc_attr( $returnTo ); ?>" />
				<?php endif; ?>

				<div class="rk-form-grid">

					<?php /* Left col: Source URL --------------------------------- */ ?>
					<div class="rk-form-row">
						<div class="rk-form-label-row">
							<label class="rk-form-label" for="rk-source">
								<?php echo esc_html__( 'Source URL', 'rankkernel' ); ?>
								<span class="rk-required" aria-hidden="true">*</span>
							</label>
							<span class="rk-char-count" id="rk-source-count"><?php echo esc_html( $sourceCount ); ?></span>
						</div>
						<div class="rk-input-wrap<?php echo '' !== $sourceError ? ' rk-has-error' : ''; ?>">
							<input
								type="text"
								id="rk-source"
								name="rk_source"
								value="<?php echo esc_attr( $sourceValue ); ?>"
								class="rk-form-input code"
								aria-describedby="<?php echo esc_attr( $sourceDescribed ); ?>"
							/>
							<?php if ( '' !== $sourceError ) : ?>
								<span class="rk-icon rk-input-error-icon" aria-hidden="true">error</span>
							<?php endif; ?>
						</div>
						<?php if ( '' !== $sourceError ) : ?>
							<p class="rk-field-error" id="rk-source-error" role="alert"><?php echo esc_html( $sourceError ); ?></p>
						<?php endif; ?>
						<p
							class="rk-form-hint"
							id="rk-source-hint"
							data-fragment="<?php echo esc_attr__( 'Remove the # part. Fragments stay in the browser and are never sent to the server.', 'rankkernel' ); ?>"
						><?php echo esc_html__( 'Enter the old path, e.g. /old-page. Query strings are ignored when matching.', 'rankkernel' ); ?></p>
					</div>

					<?php /* Left col: Match type --------------------------------- */ ?>
					<div class="rk-form-row">
						<label class="rk-form-label" for="rk-match"><?php echo esc_html__( 'Match Type', 'rankkernel' ); ?></label>
						<div class="rk-select-wrap">
							<select
								id="rk-match"
								name="rk_match_type"
								class="rk-form-select"
								aria-describedby="rk-match-hint"
							>
								<?php foreach ( $matchOptions as $matchRow ) : ?>
									<option
										value="<?php echo esc_attr( $matchRow['value'] ); ?>"
										<?php echo selected( $matchValue, $matchRow['value'], false ); ?>
										data-hint="<?php echo esc_attr( $matchRow['hint'] ); ?>"
									><?php echo esc_html( $matchRow['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="rk-icon rk-select-chevron" aria-hidden="true">expand_more</span>
						</div>
						<?php if ( '' !== $matchError ) : ?>
							<p class="rk-field-error" id="rk-match_type-error" role="alert"><?php echo esc_html( $matchError ); ?></p>
						<?php endif; ?>
						<p class="rk-form-hint" id="rk-match-hint"><?php echo esc_html( $matchHint ); ?></p>
					</div>

					<?php /* Right col: Destination URL -------------------------- */ ?>
					<div
						id="rk-target-row"
						class="rk-form-row"
						<?php echo $terminal ? ' hidden' : ''; ?>
					>
						<div class="rk-form-label-row">
							<label class="rk-form-label" for="rk-target"><?php echo esc_html__( 'Destination URL', 'rankkernel' ); ?></label>
							<span class="rk-char-count" id="rk-target-count"><?php echo esc_html( $targetCount ); ?></span>
						</div>
						<input
							type="text"
							id="rk-target"
							name="rk_target"
							value="<?php echo esc_attr( $targetValue ); ?>"
							class="rk-form-input code<?php echo '' !== $targetError ? ' rk-input-error' : ''; ?>"
							aria-describedby="<?php echo esc_attr( $targetDescribed ); ?>"
							placeholder="/new-page or https://example.com"
							<?php echo $terminal ? 'disabled' : ''; ?>
						/>
						<?php if ( '' !== $targetError ) : ?>
							<p class="rk-field-error" id="rk-target-error" role="alert"><?php echo esc_html( $targetError ); ?></p>
						<?php endif; ?>
						<p class="rk-form-hint" id="rk-target-hint"><?php echo esc_html__( 'Where to send the visitor, e.g. /new-page or https://example.com. Leave empty only for 410 and 451.', 'rankkernel' ); ?></p>
						<p
							class="rk-form-hint rk-terminal-msg"
							id="rk-terminal-note"
							<?php echo $terminal ? '' : ' hidden'; ?>
						><?php echo esc_html__( '410 Gone and 451 mean the content is intentionally unavailable, so no destination is needed.', 'rankkernel' ); ?></p>
					</div>

					<?php /* Right col: Redirect type ----------------------------- */ ?>
					<div class="rk-form-row">
						<label class="rk-form-label" for="rk-code"><?php echo esc_html__( 'Redirect Type', 'rankkernel' ); ?></label>
						<div class="rk-select-wrap">
							<select
								id="rk-code"
								name="rk_code"
								class="rk-form-select"
								aria-describedby="rk-code-hint rk-terminal-note"
							>
								<?php foreach ( $codeOptions as $codeRow ) : ?>
									<option
										value="<?php echo esc_attr( $codeRow['value'] ); ?>"
										<?php echo selected( $codeValue, $codeRow['value'], false ); ?>
										data-hint="<?php echo esc_attr( $codeRow['hint'] ); ?>"
									><?php echo esc_html( $codeRow['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="rk-icon rk-select-chevron" aria-hidden="true">expand_more</span>
						</div>
						<?php if ( '' !== $codeError ) : ?>
							<p class="rk-field-error" id="rk-code-error" role="alert"><?php echo esc_html( $codeError ); ?></p>
						<?php endif; ?>
						<p class="rk-form-hint" id="rk-code-hint"><?php echo esc_html( $codeHint ); ?></p>
					</div>

					<?php /* Full width: Regex pattern check --------------------- */ ?>
					<div
						id="rk-regex-row"
						class="rk-form-row rk-form-row-full rk-regex-row"
						<?php echo $isRegex ? '' : ' hidden'; ?>
					>
						<span class="rk-form-label"><span class="rk-icon" aria-hidden="true">code_blocks</span><?php echo esc_html__( 'Pattern check', 'rankkernel' ); ?></span>
						<p
							class="<?php echo esc_attr( $regexClass ); ?>"
							id="rk-regex-feedback"
							role="status"
							data-msg-empty="<?php echo esc_attr__( 'Enter a pattern to check it. Patterns are limited to 200 characters.', 'rankkernel' ); ?>"
							data-msg-long="<?php echo esc_attr__( 'That pattern is too long. Please keep regex patterns under 200 characters.', 'rankkernel' ); ?>"
							data-msg-preview="<?php echo esc_attr__( 'The browser preview could not compile this pattern. The server will verify it when you save.', 'rankkernel' ); ?>"
							data-msg-valid="<?php echo esc_attr__( 'Pattern compiles in the browser preview. The server tests it again before saving.', 'rankkernel' ); ?>"
							data-msg-anchor="<?php echo esc_attr__( 'Tip: add ^ at the start and $ at the end to match the whole path.', 'rankkernel' ); ?>"
						><?php echo esc_html( $regexState['message'] ); ?></p>
						<p class="rk-form-hint" id="rk-regex-help"><?php echo esc_html__( 'Full pattern match, limited to 200 characters. Anchor with ^ and $ to match the whole path, e.g. ^/blog/[0-9]+$.', 'rankkernel' ); ?></p>
					</div>

					<?php /* Full width: Active checkbox -------------------------- */ ?>
					<div class="rk-form-row rk-form-row-full">
						<label class="rk-form-check">
							<input type="checkbox" name="rk_active" value="1" <?php echo checked( $activeChecked, true, false ); ?> />
							<span>
								<strong><?php echo esc_html__( 'Active', 'rankkernel' ); ?></strong>
								<?php echo esc_html__( 'Send visitors now. Turn off to keep the rule saved without redirecting.', 'rankkernel' ); ?>
							</span>
						</label>
					</div>

				</div><!-- .rk-form-grid -->

				<?php /* Advanced options accordion */ ?>
				<details class="rk-advanced">
					<summary class="rk-advanced-toggle">
						<span class="rk-icon rk-advanced-arrow" aria-hidden="true">arrow_right</span>
						<span><?php echo esc_html__( 'Advanced options', 'rankkernel' ); ?></span>
						<span class="rk-advanced-meta"><?php echo esc_html__( 'Query string handling and matcher limits', 'rankkernel' ); ?></span>
					</summary>
					<p class="rk-form-hint">
						<?php if ( $preserveQuery ) : ?>
							<?php echo esc_html__( 'Matching ignores the query string, and the query string is currently passed to the destination. Change this under Redirect Settings below.', 'rankkernel' ); ?>
						<?php else : ?>
							<?php echo esc_html__( 'Matching ignores the query string, and the query string is currently dropped. Change this under Redirect Settings below.', 'rankkernel' ); ?>
						<?php endif; ?>
					</p>
					<p class="rk-form-hint"><?php echo esc_html__( 'Pattern matchers (everything except Exact) are limited in how many active rules they can hold. Exact matches are unlimited and fastest.', 'rankkernel' ); ?></p>
					<details class="rk-hints">
						<summary><?php echo esc_html__( 'What do the match types mean', 'rankkernel' ); ?></summary>
						<ul>
							<?php foreach ( $matchHintRows as $matchHintRow ) : ?>
								<li><strong><?php echo esc_html( $matchHintRow['label'] ); ?></strong> <?php echo esc_html( $matchHintRow['hint'] ); ?></li>
							<?php endforeach; ?>
						</ul>
					</details>
					<details class="rk-hints">
						<summary><?php echo esc_html__( 'Which redirect type should I use', 'rankkernel' ); ?></summary>
						<ul>
							<?php foreach ( $codeHintRows as $codeHintRow ) : ?>
								<li><strong><?php echo esc_html( $codeHintRow['label'] ); ?></strong> <?php echo esc_html( $codeHintRow['hint'] ); ?></li>
							<?php endforeach; ?>
						</ul>
					</details>
				</details>

				<div class="rk-form-actions">
					<?php
					submit_button(
						$editorIsEdit ? __( 'Update Redirect', 'rankkernel' ) : __( 'Add Redirect', 'rankkernel' ),
						'primary',
						'rankkernel_redirect_save',
						false
					);
					?>
					<button
						type="button"
						class="button rk-editor-cancel-btn"
					><?php echo esc_html__( 'Cancel', 'rankkernel' ); ?></button>
				</div>
			</form>
		</div><!-- #rk-redirect-editor -->

		<?php /* ---- List section: pre-rendered by renderListSection() ----------- */ ?>

		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built by renderListSection() which escapes all output.
		echo $listSectionHtml;
		?>

		<?php /* ---- Import and Export card -------------------------------------- */ ?>

		<div class="rk-card rk-csv-card" id="rk-redirect-csv" hidden>
			<div class="rk-card-header">
				<div class="rk-card-header-left">
					<span class="rk-icon rk-card-header-icon" aria-hidden="true">swap_vert</span>
					<h3 class="rk-card-title"><?php echo esc_html__( 'Import and Export', 'rankkernel' ); ?></h3>
				</div>
				<button
					type="button"
					class="rk-card-collapse"
					data-rk-panel-toggle="rk-redirect-csv"
					aria-expanded="false"
					aria-controls="rk-redirect-csv"
				>
					<span class="rk-icon rk-collapse-icon" aria-hidden="true">expand_less</span>
				</button>
			</div>

			<div class="rk-csv-body" id="rk-csv-body">
				<p class="rk-csv-desc">
					<?php
					echo esc_html__( 'Move redirects in and out with a CSV file. Expected columns:', 'rankkernel' );
					?>
					<code>source</code>, <code>target</code>, <code>code</code>, <code>match type</code>, <code>active</code>.
				</p>

				<div class="rk-csv-grid">

					<?php /* Export column */ ?>
					<div class="rk-csv-col">
						<div class="rk-csv-col-header">
							<span class="rk-csv-dot" aria-hidden="true"></span>
							<h4 class="rk-csv-col-title"><?php echo esc_html__( 'Export', 'rankkernel' ); ?></h4>
						</div>
						<p class="rk-form-hint"><?php echo esc_html__( 'Download all redirects in UTF-8 formatted CSV for backups or external editing.', 'rankkernel' ); ?></p>
						<a class="button rk-btn-export-full" href="<?php echo esc_url( $exportUrl ); ?>"><span class="rk-icon" aria-hidden="true">download</span><?php echo esc_html__( 'Export Redirects', 'rankkernel' ); ?></a>
					</div>

					<?php /* Import column */ ?>
					<div class="rk-csv-col">
						<div class="rk-csv-col-header">
							<span class="rk-csv-dot" aria-hidden="true"></span>
							<h4 class="rk-csv-col-title"><?php echo esc_html__( 'Import', 'rankkernel' ); ?></h4>
						</div>

						<form method="post" action="" enctype="multipart/form-data">
							<?php wp_nonce_field( $nonceImportAction ); ?>

							<div class="rk-file-zone">
								<span class="rk-icon rk-file-zone-icon" aria-hidden="true">cloud_upload</span>
								<label for="rk-csv-file" class="rk-file-zone-label">
									<?php echo esc_html__( 'Choose CSV file', 'rankkernel' ); ?>
								</label>
								<input
									type="file"
									id="rk-csv-file"
									name="rk_csv_file"
									accept=".csv,text/csv"
									class="rk-file-input"
								/>
								<p class="rk-file-zone-hint"><?php echo esc_html( sprintf( /* translators: %s: maximum accepted CSV upload size, for example 2 MB */ __( '.csv files only (up to %s)', 'rankkernel' ), size_format( \RankKernel\Modules\Redirects\CsvHandler::MAX_FILE_SIZE ) ) ); ?></p>
							</div>

							<div class="rk-csv-check-row">
								<label class="rk-form-check">
									<input type="checkbox" name="rk_csv_update" value="1" />
									<span><?php echo esc_html__( 'Update existing redirects when the source and match type already exist. Leave off to skip duplicates.', 'rankkernel' ); ?></span>
								</label>
							</div>

							<?php submit_button( __( 'Import Redirects', 'rankkernel' ), 'secondary', 'rankkernel_redirect_import', false ); ?>
						</form>

						<?php if ( $showImportReport ) : ?>
							<div class="rk-import-report">
								<div class="rk-import-report-header">
									<span class="rk-import-report-dot rk-import-report-dot-success" aria-hidden="true"></span>
									<strong><?php echo esc_html( $importSummary ); ?></strong>
								</div>
								<?php if ( [] !== $importErrors ) : ?>
									<ul class="rk-import-errors">
										<?php foreach ( $importErrors as $importErrorLine ) : ?>
											<li><?php echo esc_html( $importErrorLine ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
								<?php if ( [] !== $importWarnings ) : ?>
									<ul class="rk-import-warnings">
										<?php foreach ( $importWarnings as $importWarningLine ) : ?>
											<li><?php echo esc_html( $importWarningLine ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div><!-- .rk-csv-col -->

				</div><!-- .rk-csv-grid -->
			</div><!-- #rk-csv-body -->
		</div><!-- #rk-redirect-csv -->

		<?php /* ---- Redirect Settings card (hidden by default, gear toggles it) - */ ?>

		<div
			class="rk-settings-card"
			id="rk-redirect-settings"
			hidden
		>
			<div class="rk-card-header">
				<div class="rk-card-header-left">
					<span class="rk-icon rk-card-header-icon" aria-hidden="true">tune</span>
					<h3 class="rk-card-title"><?php echo esc_html__( 'Redirect Settings', 'rankkernel' ); ?></h3>
				</div>
				<button
					type="button"
					class="rk-card-cancel rk-settings-hide"
				><?php echo esc_html__( 'Hide', 'rankkernel' ); ?></button>
			</div>

			<form method="post" action="" class="rk-settings-form">
				<?php wp_nonce_field( $nonceSettingsAction ); ?>

				<div class="rk-settings-row">
					<div class="rk-settings-label-group">
						<span class="rk-settings-label"><?php echo esc_html__( 'Query strings', 'rankkernel' ); ?></span>
						<p class="rk-form-hint"><?php echo esc_html__( 'Determine whether incoming query variables like UTM tags are preserved.', 'rankkernel' ); ?></p>
					</div>
					<label class="rk-form-check">
						<input type="checkbox" name="rk_preserve_query" value="1" <?php echo checked( $preserveQuery, true, false ); ?> />
						<span><?php echo esc_html__( 'Pass the query string to the destination. Turn off to drop it.', 'rankkernel' ); ?></span>
					</label>
				</div>

				<div class="rk-settings-row">
					<div class="rk-settings-label-group">
						<span class="rk-settings-label"><?php echo esc_html__( 'Slug changes', 'rankkernel' ); ?></span>
						<p class="rk-form-hint"><?php echo esc_html__( 'Automated detection when posts, pages, or custom post types change permalinks.', 'rankkernel' ); ?></p>
					</div>
					<label class="rk-form-check">
						<input type="checkbox" name="rk_auto_slug_redirect" value="1" <?php echo checked( $autoSlugRedirect, true, false ); ?> />
						<span><?php echo esc_html__( 'Create a 301 redirect automatically when a post slug changes.', 'rankkernel' ); ?></span>
					</label>
				</div>

				<div class="rk-settings-row">
					<div class="rk-settings-label-group">
						<label class="rk-settings-label" for="rk-per-page"><?php echo esc_html__( 'Rows per page', 'rankkernel' ); ?></label>
						<p class="rk-form-hint"><?php echo esc_html__( 'How many redirects to show per page in the admin table.', 'rankkernel' ); ?></p>
					</div>
					<div class="rk-settings-row-control">
						<input
							type="number"
							id="rk-per-page"
							name="rk_rules_per_page"
							value="<?php echo esc_attr( (string) $rulesPerPage ); ?>"
							class="rk-settings-number"
							min="1"
							max="100"
						/>
						<span class="rk-form-hint"><?php echo esc_html__( 'Min 1, max 100', 'rankkernel' ); ?></span>
					</div>
				</div>

				<div class="rk-settings-footer">
					<?php submit_button( __( 'Save Settings', 'rankkernel' ), 'secondary', 'rankkernel_redirect_settings_save', false ); ?>
				</div>
			</form>
		</div><!-- #rk-redirect-settings -->

	</div><!-- .rk-redirects -->
</div><!-- .wrap.rk-redirects-wrap -->
