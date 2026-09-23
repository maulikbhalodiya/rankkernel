<?php
/**
 * Redirects admin page, form plus list plus settings.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Redirects\CsvHandler;
use RankKernel\Modules\Redirects\DestinationValidator;
use RankKernel\Modules\Redirects\Normalizer;
use RankKernel\Modules\Redirects\RedirectCache;
use RankKernel\Modules\Redirects\RedirectRepository;
use RankKernel\Modules\Redirects\RedirectsSettings;
use RankKernel\Modules\Redirects\Validator;
use RankKernel\Plugin;

/**
 * Renders the Redirect Manager and handles its saves.
 *
 * The add and edit form, the searchable filterable sortable paginated list,
 * and the module settings share one screen under the RankKernel menu. Saves
 * run on the load hook so the redirect after save stays header safe. A loop
 * finding blocks the save, a chain finding saves with a warning, and an
 * inconclusive analysis saves with an informational notice.
 */
final class RedirectsPage {
	/**
	 * Menu slug for the screen.
	 */
	public const SLUG = 'rankkernel-redirects';

	/**
	 * Hook suffix for the screen, used to gate asset loading.
	 */
	public const HOOK_SUFFIX = 'rankkernel_page_rankkernel-redirects';

	/**
	 * Nonce action for the add and edit form.
	 */
	private const NONCE_SAVE = 'rankkernel_redirect_save';

	/**
	 * Nonce action for single row links.
	 */
	private const NONCE_ROW = 'rankkernel_redirect_row';

	/**
	 * Nonce action for bulk actions.
	 */
	private const NONCE_BULK = 'rankkernel_redirect_bulk';

	/**
	 * Nonce action for the settings form.
	 */
	private const NONCE_SETTINGS = 'rankkernel_redirect_settings';

	/**
	 * Nonce action for the CSV import form.
	 */
	private const NONCE_IMPORT = 'rankkernel_redirect_import';

	/**
	 * Nonce action for the CSV export download.
	 */
	private const NONCE_EXPORT = 'rankkernel_redirect_export';

	/**
	 * Sortable columns shown in the list.
	 *
	 * @var array<string, string>
	 */
	private const SORTABLE = [
		'source'        => 'Source',
		'target'        => 'Destination',
		'code'          => 'Code',
		'match_type'    => 'Match',
		'hits'          => 'Hits',
		'last_accessed' => 'Last Accessed',
	];

	/**
	 * Rule repository.
	 *
	 * @var RedirectRepository
	 */
	private RedirectRepository $repository;

	/**
	 * Module settings store.
	 *
	 * @var RedirectsSettings
	 */
	private RedirectsSettings $redirectSettings;

	/**
	 * Loop and chain analyzer.
	 *
	 * @var Validator
	 */
	private Validator $validator;

	/**
	 * Destination policy checker.
	 *
	 * @var DestinationValidator
	 */
	private DestinationValidator $destinationValidator;

	/**
	 * Upload probe, true for genuine HTTP uploads.
	 *
	 * @var callable(string): bool
	 */
	private $isUploadedFile;

	/**
	 * Field errors from a save that stayed on the page, keyed by field.
	 *
	 * @var array<string, string>
	 */
	private array $formErrors = [];

	/**
	 * Entered values from a save that stayed on the page.
	 *
	 * @var array<string, mixed>
	 */
	private array $formValues = [];

	/**
	 * Whether a form save was attempted without a redirect.
	 *
	 * @var bool
	 */
	private bool $hasFormAttempt = false;

	/**
	 * CSV import outcome kept on the page for the result card.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $importResult = null;

	/**
	 * Constructor.
	 *
	 * @param RedirectRepository|null     $repository           Rule repository, fresh one when null.
	 * @param RedirectsSettings|null      $redirectSettings     Settings store, fresh one when null.
	 * @param Validator|null              $validator            Safety analyzer, fresh one when null.
	 * @param DestinationValidator|null   $destinationValidator Destination checker, fresh one when null.
	 * @param callable(string): bool|null $isUploadedFile      Upload probe override, test double seam.
	 */
	public function __construct(
		?RedirectRepository $repository = null,
		?RedirectsSettings $redirectSettings = null,
		?Validator $validator = null,
		?DestinationValidator $destinationValidator = null,
		?callable $isUploadedFile = null
	) {
		$this->repository           = $repository ?? new RedirectRepository();
		$this->redirectSettings     = $redirectSettings ?? new RedirectsSettings();
		$this->validator            = $validator ?? new Validator();
		$this->destinationValidator = $destinationValidator ?? new DestinationValidator();
		$this->isUploadedFile       = $isUploadedFile ?? 'is_uploaded_file';
	}

	/**
	 * Handle a save on the load hook, before any output is sent.
	 *
	 * Runs on load rankkernel page rankkernel redirects, so wp safe redirect
	 * can still send headers. Row links arrive by GET, every form arrives by
	 * POST with its own marker field.
	 */
	public function maybeHandleSave(): void {
		// Delegates to a handler which verifies capability plus its own nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- delegates to a handler which verifies capability plus its own nonce, compared strictly against a literal, never stored or output.
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			// Marker read only, this branch verifies its nonce in requireAccess.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['rankkernel_redirect_save'] ) ) {
				$this->handleFormSave();

				return;
			}

			// Marker read only, each branch verifies its nonce in requireAccess.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['rankkernel_redirect_bulk'] ) ) {
				$this->handleBulk();

				return;
			}

			// Marker read only, each branch verifies its nonce in requireAccess.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['rankkernel_redirect_settings_save'] ) ) {
				$this->handleSettingsSave();

				return;
			}

			// Marker read only, the import branch verifies its nonce in requireAccess.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['rankkernel_redirect_import'] ) ) {
				$this->handleImport();

				return;
			}
		}

		// Read only routing flag, the row handler verifies capability plus nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only routing flag, the row handler verifies capability plus nonce, unslashed here, sanitized on the following statement.
		$rawAction = isset( $_GET['rk_action'] ) ? (string) wp_unslash( $_GET['rk_action'] ) : '';
		$action    = sanitize_key( $rawAction );

		if ( 'export' === $action ) {
			$this->handleExport();

			return;
		}

		if ( in_array( $action, [ 'delete', 'activate', 'deactivate' ], true ) ) {
			$this->handleRowAction( $action );
		}
	}

	/**
	 * Enqueue screen assets, and only on this screen.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueAssets( string $hookSuffix ): void {
		if ( self::HOOK_SUFFIX !== $hookSuffix ) {
			return;
		}

		if ( ! function_exists( 'plugins_url' ) ) {
			return;
		}

		$version = Plugin::version();

		$css = plugins_url( 'assets/css/redirects-admin.css', (string) RANKKERNEL_FILE );
		wp_register_style( 'rankkernel-redirects-admin', $css, [], $version );
		wp_enqueue_style( 'rankkernel-redirects-admin' );

		$js = plugins_url( 'assets/js/redirects-admin.js', (string) RANKKERNEL_FILE );
		wp_register_script( 'rankkernel-redirects-admin', $js, [ 'wp-a11y', 'wp-i18n' ], $version, true );
		wp_enqueue_script( 'rankkernel-redirects-admin' );

		/*
		 * Pass configuration to JS so it can make authenticated AJAX requests
		 * to swap only the list section without reloading the whole page.
		 * wp_localize_script must be called after wp_register_script.
		 */
		wp_localize_script(
			'rankkernel-redirects-admin',
			'rkRedirects',
			[
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'rankkernel_redirects_list' ),
				'screenSlug' => self::SLUG,
			]
		);
	}

	/**
	 * Handle the wp_ajax list request.
	 *
	 * Returns the rendered HTML for #rk-list-section only. Capability and
	 * nonce are verified before any output is produced.
	 */
	public function handleAjaxList(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'rankkernel' ) ], 403 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed then verified by check_ajax_referer below.
		check_ajax_referer( 'rankkernel_redirects_list' );

		ob_start();
		// Read only display filters from the nonce verified request, every value sanitized and validated by listFilters().
		$this->renderListSection( $this->listFilters( $_POST ) );
		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html ] );
	}

	/**
	 * Render the list section (tabs + filter bar + table or empty state) as HTML.
	 *
	 * Used both by render() for the full page load and by handleAjaxList() for
	 * the AJAX partial refresh.
	 *
	 * @param array<string, mixed> $filters Validated filter set from listFilters().
	 */
	public function renderListSection( array $filters ): void {
		$fallbackPerPage = $this->rulesPerPage();
		$perPage         = (int) ( $filters['per_page'] ?? 0 );

		if ( $perPage < 1 ) {
			$perPage = $fallbackPerPage;
		}

		$perPage = max( 1, min( 100, $perPage ) );

		$result = $this->repository->paginate(
			[
				'search'     => $filters['search'],
				'status'     => $filters['status'],
				'match_type' => $filters['match_type'],
				'code'       => $filters['code'],
				'orderby'    => $filters['orderby'],
				'order'      => $filters['order'],
				'page'       => $filters['page'],
				'per_page'   => $perPage,
			]
		);

		$listRows    = [];
		$listHasRows = [] !== $result['rows'];

		foreach ( $result['rows'] as $row ) {
			if ( is_array( $row ) ) {
				$listRows[] = $this->rowState( $row );
			}
		}

		$totalRows  = (int) $result['total'];
		$pageNumber = max( 1, (int) $result['page'] );
		$pageCount  = (int) $result['pages'];
		$perPage    = max( 1, (int) $result['per_page'] );

		/*
		 * The status tabs count under the other filters, so All is the combined
		 * active plus inactive total, not the status filtered total that
		 * pagination and the item count report.
		 */
		$allRows         = (int) $result['active'] + (int) $result['inactive'];
		$statusViews     = $this->statusViews( $filters, $allRows, (int) $result['active'], (int) $result['inactive'] );
		$sortableHeaders = $this->sortableHeaders( $filters );
		$pagination      = $this->paginationState( $pageNumber, $pageCount, $filters );

		$paginationText = sprintf(
			/* translators: %1$d: current page, %2$d: total pages */
			__( 'Page %1$d of %2$d', 'rankkernel' ),
			$pageNumber,
			max( 1, $pageCount )
		);

		$rangeStart = $totalRows > 0 ? ( $pageNumber - 1 ) * $perPage + 1 : 0;
		$rangeEnd   = min( $pageNumber * $perPage, $totalRows );

		$showingLabel = sprintf(
			/* translators: %1$s: first visible row, %2$s: last visible row, %3$s: total rows */
			__( 'Showing %1$s to %2$s of %3$s redirects', 'rankkernel' ),
			number_format_i18n( $rangeStart ),
			number_format_i18n( $rangeEnd ),
			number_format_i18n( $totalRows )
		);

		$totalLabel = sprintf(
			/* translators: %s: total number of redirects */
			__( '%s redirects', 'rankkernel' ),
			number_format_i18n( $totalRows )
		);

		$perPageOptions = [ 10, 20, 25, 50, 100 ];

		if ( ! in_array( $perPage, $perPageOptions, true ) ) {
			$perPageOptions[] = $perPage;
			sort( $perPageOptions );
		}

		$filterSearch = (string) $filters['search'];
		$filterStatus = (string) $filters['status'];
		$filterMatch  = (string) $filters['match_type'];
		$filterCode   = (string) $filters['code'];
		$hasFilter    = '' !== $filterSearch || 'all' !== $filterStatus || '' !== $filterMatch || '' !== $filterCode;

		$clearFiltersUrl  = $this->pageUrl( [] );
		$addFirstUrl      = $this->pageUrl( [ 'rk_open' => 1 ] );
		$bulkFormAction   = $this->pageUrl( [] );
		$filtersActionUrl = admin_url( 'admin.php' );
		$screenSlug       = self::SLUG;
		$nonceBulkAction  = self::NONCE_BULK;

		$matchLabels  = $this->matchOptions();
		$matchHints   = $this->matchHints();
		$matchOptions = [];

		foreach ( $matchLabels as $matchKey => $matchLabel ) {
			$matchOptions[] = [
				'value' => (string) $matchKey,
				'label' => $matchLabel,
				'hint'  => (string) ( $matchHints[ $matchKey ] ?? '' ),
			];
		}

		$codeLabels  = $this->codeOptions();
		$codeHints   = $this->codeHints();
		$codeOptions = [];

		foreach ( $codeLabels as $codeKey => $codeLabel ) {
			$codeOptions[] = [
				'value' => (string) $codeKey,
				'label' => $codeLabel,
				'hint'  => (string) ( $codeHints[ $codeKey ] ?? '' ),
			];
		}

		require __DIR__ . '/Views/redirects-list.php';
	}

	/**
	 * Prepare the view state and load the redirects view.
	 */
	public function render(): void {
		$notice = $this->noticeState();

		$blockedNotice       = $notice['blocked'];
		$successNotice       = $notice['success'];
		$errorNotice         = $notice['error'];
		$chainPath           = $notice['chain'];
		$chainSummary        = $notice['chainSummary'];
		$chainRecommendation = $notice['chainRecommendation'];
		$chainFinal          = $notice['chainFinal'];
		$chainUnknown        = $notice['chainUnknown'];
		$mayLoop             = $notice['mayLoop'];

		$editorValues = $this->formValues();
		$editId       = (int) ( $editorValues['rule_id'] ?? 0 );
		$editorIsEdit = $editId > 0;
		$editorOpen   = $this->isEditorOpen();
		$matchValue   = (string) ( $editorValues['match_type'] ?? 'exact' );
		$codeValue    = (string) ( $editorValues['code'] ?? '301' );
		$isRegex      = 'regex' === $matchValue;
		$terminal     = in_array( $codeValue, Normalizer::TERMINAL_CODES, true );
		$rawReturn    = $editorValues['return_to'] ?? '';
		$returnTo     = is_string( $rawReturn ) ? $rawReturn : '';

		$matchLabels  = $this->matchOptions();
		$matchHints   = $this->matchHints();
		$matchOptions = [];

		foreach ( $matchLabels as $matchKey => $matchLabel ) {
			$matchOptions[] = [
				'value' => (string) $matchKey,
				'label' => $matchLabel,
				'hint'  => (string) ( $matchHints[ $matchKey ] ?? '' ),
			];
		}

		$matchHintRows = [];

		foreach ( $matchHints as $matchKey => $matchHint ) {
			$matchHintRows[] = [
				'label' => (string) ( $matchLabels[ $matchKey ] ?? $matchKey ),
				'hint'  => $matchHint,
			];
		}

		$codeLabels  = $this->codeOptions();
		$codeHints   = $this->codeHints();
		$codeOptions = [];

		foreach ( $codeLabels as $codeKey => $codeLabel ) {
			$codeOptions[] = [
				'value' => (string) $codeKey,
				'label' => $codeLabel,
				'hint'  => (string) ( $codeHints[ $codeKey ] ?? '' ),
			];
		}

		$codeHintRows = [];

		foreach ( $codeHints as $codeKey => $codeHint ) {
			$codeHintRows[] = [
				'label' => (string) ( $codeLabels[ $codeKey ] ?? $codeKey ),
				'hint'  => $codeHint,
			];
		}

		$matchHint     = (string) ( $matchHints[ $matchValue ] ?? '' );
		$codeHint      = (string) ( $codeHints[ (int) $codeValue ] ?? $codeHints[ $codeValue ] ?? '' );
		$regexState    = $this->regexState( (string) ( $editorValues['source'] ?? '' ) );
		$regexClass    = $regexState['ok'] ? 'rk-regex-ok' : 'rk-regex-bad';
		$sourceValue   = (string) ( $editorValues['source'] ?? '' );
		$targetValue   = (string) ( $editorValues['target'] ?? '' );
		$activeChecked = ! empty( $editorValues['is_active'] );

		$sourceError     = (string) ( $this->formErrors['source'] ?? '' );
		$matchError      = (string) ( $this->formErrors['match_type'] ?? '' );
		$targetError     = (string) ( $this->formErrors['target'] ?? '' );
		$codeError       = (string) ( $this->formErrors['code'] ?? '' );
		$sourceDescribed = 'rk-source-hint' . ( '' !== $sourceError ? ' rk-source-error' : '' );
		$targetDescribed = 'rk-target-hint' . ( '' !== $targetError ? ' rk-target-error' : '' );

		$editorHeading = $editorIsEdit ? __( 'Edit Redirect', 'rankkernel' ) : __( 'Add Redirect', 'rankkernel' );
		$toggleUrl     = $this->pageUrl( [ 'rk_open' => 1 ] );

		if ( '' !== $returnTo ) {
			$cancelUrl = admin_url( 'admin.php?page=' . NotFoundPage::SLUG );
		} else {
			$cancelUrl = $this->pageUrl( [] );
		}

		$settings         = $this->redirectSettings->all();
		$preserveQuery    = ! empty( $settings['preserve_query'] );
		$autoSlugRedirect = ! empty( $settings['auto_slug_redirect'] );
		$rulesPerPage     = max( 1, min( 100, (int) ( $settings['rules_per_page'] ?? 20 ) ) );

		$totalRules = $this->repository->count();

		if ( 1 === $totalRules ) {
			$countPill = sprintf(
				/* translators: %s: total number of redirect rules */
				__( '%s Total Rule', 'rankkernel' ),
				number_format_i18n( $totalRules )
			);
		} else {
			$countPill = sprintf(
				/* translators: %s: total number of redirect rules */
				__( '%s Total Rules', 'rankkernel' ),
				number_format_i18n( $totalRules )
			);
		}

		$sourceLen = function_exists( 'mb_strlen' ) ? mb_strlen( $sourceValue ) : strlen( $sourceValue );
		$targetLen = function_exists( 'mb_strlen' ) ? mb_strlen( $targetValue ) : strlen( $targetValue );

		if ( 1 === $sourceLen ) {
			$sourceCount = sprintf(
				/* translators: %d: character count of the source field */
				__( '%d char', 'rankkernel' ),
				$sourceLen
			);
		} else {
			$sourceCount = sprintf(
				/* translators: %d: character count of the source field */
				__( '%d chars', 'rankkernel' ),
				$sourceLen
			);
		}

		if ( 1 === $targetLen ) {
			$targetCount = sprintf(
				/* translators: %d: character count of the destination field */
				__( '%d char', 'rankkernel' ),
				$targetLen
			);
		} else {
			$targetCount = sprintf(
				/* translators: %d: character count of the destination field */
				__( '%d chars', 'rankkernel' ),
				$targetLen
			);
		}

		$exportUrl        = wp_nonce_url( $this->pageUrl( [ 'rk_action' => 'export' ] ), self::NONCE_EXPORT );
		$importData       = $this->importResult;
		$showImportReport = is_array( $importData );
		$importSummary    = '';
		$importErrors     = [];
		$importWarnings   = [];

		if ( is_array( $importData ) ) {
			$created = max( 0, (int) ( $importData['created'] ?? 0 ) );
			$updated = max( 0, (int) ( $importData['updated'] ?? 0 ) );
			$skipped = max( 0, (int) ( $importData['skipped'] ?? 0 ) );
			$errors  = isset( $importData['errors'] ) && is_array( $importData['errors'] ) ? $importData['errors'] : [];

			$importSummary = sprintf(
				/* translators: %1$d: created count, %2$d: updated count, %3$d: skipped count, %4$d: error count */
				__( 'Import finished: %1$d created, %2$d updated, %3$d skipped, %4$d with errors.', 'rankkernel' ),
				$created,
				$updated,
				$skipped,
				count( $errors )
			);

			foreach ( $errors as $importError ) {
				if ( ! is_array( $importError ) ) {
					continue;
				}

				$errorRow    = max( 0, (int) ( $importError['row'] ?? 0 ) );
				$errorReason = (string) ( $importError['reason'] ?? '' );

				if ( $errorRow > 0 ) {
					$importErrors[] = sprintf(
						/* translators: %1$d: CSV row number, %2$s: reason the row was rejected */
						__( 'Row %1$d: %2$s', 'rankkernel' ),
						$errorRow,
						$errorReason
					);
				} else {
					$importErrors[] = $errorReason;
				}
			}

			$warnings = isset( $importData['warnings'] ) && is_array( $importData['warnings'] ) ? $importData['warnings'] : [];

			foreach ( $warnings as $importWarning ) {
				if ( ! is_array( $importWarning ) ) {
					continue;
				}

				$warningRow     = max( 0, (int) ( $importWarning['row'] ?? 0 ) );
				$warningMessage = (string) ( $importWarning['message'] ?? '' );

				if ( '' === $warningMessage ) {
					continue;
				}

				if ( $warningRow > 0 ) {
					$importWarnings[] = sprintf(
						/* translators: %1$d: CSV row number, %2$s: advisory warning text */
						__( 'Row %1$d: %2$s', 'rankkernel' ),
						$warningRow,
						$warningMessage
					);
				} else {
					$importWarnings[] = $warningMessage;
				}
			}
		}

		$nonceSaveAction     = self::NONCE_SAVE;
		$nonceBulkAction     = self::NONCE_BULK;
		$nonceSettingsAction = self::NONCE_SETTINGS;
		$nonceImportAction   = self::NONCE_IMPORT;

		/*
		 * Pre-render the list section into a string so the view can embed it
		 * directly. The AJAX handler renders the same section fresh on each
		 * request, so the logic lives in one place: renderListSection().
		 */
		ob_start();
		// Read only display filters, every value sanitized and validated by listFilters().
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only display filters, every value sanitized and validated by listFilters(), no state changes.
		$this->renderListSection( $this->listFilters( $_GET ) );
		$listSectionHtml = (string) ob_get_clean();

		require __DIR__ . '/Views/redirects.php';
	}

	/**
	 * Notice state read from the query string and the current page load.
	 *
	 * @return array{blocked: string, success: string, error: string, chain: string, chainSummary: string, chainRecommendation: string, chainFinal: string, chainUnknown: bool, mayLoop: bool}
	 */
	private function noticeState(): array {
		$blocked = '';

		if ( $this->hasFormAttempt && [] !== $this->formErrors ) {
			$blocked = $this->formErrors['blocked'] ?? '';

			if ( '' === $blocked ) {
				$blocked = __( 'Please fix the highlighted fields and try again.', 'rankkernel' );
			}
		}

		$success = '';

		// Read only display flags.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = isset( $_GET['rk_notice'] ) ? sanitize_key( (string) wp_unslash( $_GET['rk_notice'] ) ) : '';

		if ( '' !== $notice ) {
			$success = $this->successNotice( $notice );
		}

		$error = '';

		// Read only display flags.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$errorFlag = isset( $_GET['rk_error'] ) ? sanitize_key( (string) wp_unslash( $_GET['rk_error'] ) ) : '';

		if ( '' !== $errorFlag ) {
			$error = $this->errorNotice( $errorFlag );
		}

		// Read only display flags, sanitized and escaped below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only display flags, sanitized and escaped below, unslashed here, sanitized on the following statement.
		$rawChain = isset( $_GET['rk_chain'] ) ? (string) wp_unslash( $_GET['rk_chain'] ) : '';
		$chain    = sanitize_text_field( $rawChain );

		// Read only display flags.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$chainUnknown = isset( $_GET['rk_chain_unknown'] );

		$chainSummary        = '';
		$chainRecommendation = '';
		$chainFinal          = '';

		if ( '' !== $chain ) {
			$chainSummary = sprintf(
				/* translators: %s: redirect chain path */
				__( 'Redirect chain detected: %s.', 'rankkernel' ),
				$chain
			);

			// Read only display flag, sanitized and escaped below.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only display flag, sanitized and escaped below, unslashed here, sanitized on the following statement.
			$rawFinal   = isset( $_GET['rk_final'] ) ? (string) wp_unslash( $_GET['rk_final'] ) : '';
			$chainFinal = sanitize_text_field( $rawFinal );

			if ( ! $chainUnknown && '' !== $chainFinal ) {
				$chainRecommendation = sprintf(
					/* translators: %s: recommended final destination */
					__( 'Consider pointing the source directly to %s.', 'rankkernel' ),
					$chainFinal
				);
			}
		}

		// Read only display flags.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$mayLoop = isset( $_GET['rk_mayloop'] );

		return [
			'blocked'             => $blocked,
			'success'             => $success,
			'error'               => $error,
			'chain'               => $chain,
			'chainSummary'        => $chainSummary,
			'chainRecommendation' => $chainRecommendation,
			'chainFinal'          => $chainFinal,
			'chainUnknown'        => $chainUnknown,
			'mayLoop'             => $mayLoop,
		];
	}

	/**
	 * Success notice message for a save flag.
	 *
	 * @param string $notice Notice flag from the query string.
	 * @return string Message text or empty string.
	 */
	private function successNotice( string $notice ): string {
		switch ( $notice ) {
			case 'saved':
				return __( 'Redirect saved.', 'rankkernel' );
			case 'updated':
				return __( 'Redirect updated.', 'rankkernel' );
			case 'deleted':
				return __( 'Redirect deleted.', 'rankkernel' );
			case 'activated':
				return __( 'Redirect activated.', 'rankkernel' );
			case 'deactivated':
				return __( 'Redirect deactivated.', 'rankkernel' );
			case 'settings':
				return __( 'Settings saved.', 'rankkernel' );
			case 'bulk':
				return $this->bulkMessage();
		}

		return '';
	}

	/**
	 * Error notice message for a failure flag.
	 *
	 * @param string $error Error flag from the query string.
	 * @return string Message text or empty string.
	 */
	private function errorNotice( string $error ): string {
		if ( 'loop' === $error ) {
			// Read only display flag, sanitized and escaped below.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only display flag, sanitized and escaped below, unslashed here, sanitized on the following statement.
			$rawPath = isset( $_GET['rk_path'] ) ? (string) wp_unslash( $_GET['rk_path'] ) : '';
			$path    = sanitize_text_field( $rawPath );

			return sprintf(
				/* translators: %s: redirect chain path showing the loop */
				__( 'This redirect would create a redirect loop: %s. The rule was not saved.', 'rankkernel' ),
				$path
			);
		}

		$messages = [
			'not_found'   => __( 'That redirect no longer exists.', 'rankkernel' ),
			'save_failed' => __( 'The action could not be completed. Please try again.', 'rankkernel' ),
			'bulk_none'   => __( 'Choose at least one redirect and a bulk action.', 'rankkernel' ),
		];

		return $messages[ $error ] ?? '';
	}

	/**
	 * Prepared display data for one redirect row.
	 *
	 * @param array<string, mixed> $row Rule row.
	 * @return array<string, mixed> Row display values.
	 */
	private function rowState( array $row ): array {
		$rowId    = (int) ( $row['id'] ?? 0 );
		$source   = (string) ( $row['source'] ?? '' );
		$target   = (string) ( $row['target'] ?? '' );
		$code     = (string) ( $row['code'] ?? '301' );
		$match    = (string) ( $row['match_type'] ?? 'exact' );
		$hits     = max( 0, (int) ( $row['hits'] ?? 0 ) );
		$accessed = (string) ( $row['last_accessed'] ?? '' );
		$active   = 1 === (int) ( $row['is_active'] ?? 0 );

		$toggle      = $active ? 'deactivate' : 'activate';
		$toggleLabel = $active ? __( 'Deactivate', 'rankkernel' ) : __( 'Activate', 'rankkernel' );

		$base      = admin_url( 'admin.php?page=' . self::SLUG );
		$editUrl   = add_query_arg( [ 'rk_edit' => $rowId ], $base );
		$toggleUrl = wp_nonce_url( $base . '&rk_action=' . $toggle . '&rule=' . $rowId, self::NONCE_ROW );
		$deleteUrl = wp_nonce_url( $base . '&rk_action=delete&rule=' . $rowId, self::NONCE_ROW );

		$selectLabel = sprintf(
			/* translators: %s: redirect source URL */
			__( 'Select redirect for %s', 'rankkernel' ),
			$source
		);

		return [
			'id'              => $rowId,
			'source'          => $source,
			'target'          => $target,
			'code'            => $code,
			'match'           => $match,
			'hitsLabel'       => (string) number_format_i18n( $hits ),
			'accessedLabel'   => '' === $accessed ? __( 'Never', 'rankkernel' ) : $accessed,
			'active'          => $active,
			'editUrl'         => $editUrl,
			'toggleUrl'       => $toggleUrl,
			'toggleLabel'     => $toggleLabel,
			'deleteUrl'       => $deleteUrl,
			'selectLabel'     => $selectLabel,
			'statusPillClass' => $active ? 'rk-pill rk-pill-active' : 'rk-pill rk-pill-inactive',
			'statusLabel'     => $active ? __( 'Active', 'rankkernel' ) : __( 'Inactive', 'rankkernel' ),
		];
	}

	/**
	 * Prepared display data for the All, Active, and Inactive status views.
	 *
	 * @param array<string, mixed> $filters  Current filters.
	 * @param int                  $total    Total rows under the other filters.
	 * @param int                  $active   Active rows under the other filters.
	 * @param int                  $inactive Inactive rows under the other filters.
	 * @return array<int, array{url: string, current: bool, label: string, count: string}>
	 */
	private function statusViews( array $filters, int $total, int $active, int $inactive ): array {
		$views = [
			'all'      => [ __( 'All', 'rankkernel' ), $total ],
			'active'   => [ __( 'Active', 'rankkernel' ), $active ],
			'inactive' => [ __( 'Inactive', 'rankkernel' ), $inactive ],
		];

		$out = [];

		foreach ( $views as $status => $view ) {
			$params = [
				's'           => $filters['search'],
				'rk_status'   => 'all' === $status ? '' : $status,
				'rk_match'    => $filters['match_type'],
				'rk_code'     => $filters['code'],
				'rk_per_page' => $filters['per_page'] ?? 0,
			];

			$out[] = [
				'url'     => $this->pageUrl( $params ),
				'current' => (string) $filters['status'] === (string) $status,
				'label'   => (string) $view[0],
				'count'   => (string) $view[1],
			];
		}

		return $out;
	}

	/**
	 * Prepared sortable column headers, preserving the current filters.
	 *
	 * @param array<string, mixed> $filters Current filters.
	 * @return array<int, array{label: string, url: string, current: bool, arrow: string}>
	 */
	private function sortableHeaders( array $filters ): array {
		$out = [];

		foreach ( self::SORTABLE as $column => $label ) {
			$current = (string) $filters['orderby'] === $column;
			$next    = $current && 'ASC' === (string) $filters['order'] ? 'desc' : 'asc';
			$arrow   = $current ? ( 'ASC' === (string) $filters['order'] ? ' ↑' : ' ↓' ) : '';

			$url = $this->pageUrl(
				[
					's'           => $filters['search'],
					'rk_status'   => 'all' === (string) $filters['status'] ? '' : $filters['status'],
					'rk_match'    => $filters['match_type'],
					'rk_code'     => $filters['code'],
					'rk_orderby'  => $column,
					'rk_order'    => $next,
					'rk_per_page' => $filters['per_page'] ?? 0,
				]
			);

			$out[] = [
				'label'   => (string) $label,
				'url'     => $url,
				'current' => $current,
				'arrow'   => $arrow,
			];
		}

		return $out;
	}

	/**
	 * Prepared pagination links, shared by the top and bottom nav.
	 *
	 * @param int                  $pageNumber Current page.
	 * @param int                  $pageCount  Total pages.
	 * @param array<string, mixed> $filters    Current filters.
	 * @return array{show: bool, prevUrl: string, nextUrl: string, pages: array<int, array{label: string, url: string, current: bool}>}
	 */
	private function paginationState( int $pageNumber, int $pageCount, array $filters ): array {
		$base = [
			's'           => $filters['search'],
			'rk_status'   => 'all' === (string) $filters['status'] ? '' : $filters['status'],
			'rk_match'    => $filters['match_type'],
			'rk_code'     => $filters['code'],
			'rk_orderby'  => $filters['orderby'],
			'rk_order'    => strtolower( (string) $filters['order'] ),
			'rk_per_page' => $filters['per_page'] ?? 0,
		];

		$pages = [];

		foreach ( $this->pageWindow( $pageNumber, $pageCount ) as $pageEntry ) {
			if ( ! is_int( $pageEntry ) ) {
				$pages[] = [
					'label'   => '…',
					'url'     => '',
					'current' => false,
				];

				continue;
			}

			$pages[] = [
				'label'   => (string) $pageEntry,
				'url'     => $this->pageUrl( array_merge( $base, [ 'rk_paged' => $pageEntry ] ) ),
				'current' => $pageEntry === $pageNumber,
			];
		}

		if ( $pageCount <= 1 ) {
			return [
				'show'    => false,
				'prevUrl' => '',
				'nextUrl' => '',
				'pages'   => [],
			];
		}

		return [
			'show'    => true,
			'prevUrl' => $pageNumber > 1 ? $this->pageUrl( array_merge( $base, [ 'rk_paged' => $pageNumber - 1 ] ) ) : '',
			'nextUrl' => $pageNumber < $pageCount ? $this->pageUrl( array_merge( $base, [ 'rk_paged' => $pageNumber + 1 ] ) ) : '',
			'pages'   => $pages,
		];
	}

	/**
	 * Page numbers for the numbered pagination buttons.
	 *
	 * Shows every page when there are seven or fewer, otherwise the first
	 * page, the last page, and a one page window around the current page
	 * with string gaps where pages are elided.
	 *
	 * @param int $current Current page.
	 * @param int $total   Total pages.
	 * @return array<int, int|string> Page numbers and gap markers.
	 */
	private function pageWindow( int $current, int $total ): array {
		$total = max( 1, $total );

		if ( $total <= 7 ) {
			return range( 1, $total );
		}

		$window = [ 1 ];

		if ( $current > 3 ) {
			$window[] = 'gap-start';
		}

		foreach ( range( max( 2, $current - 1 ), min( $total - 1, $current + 1 ) ) as $page ) {
			$window[] = $page;
		}

		if ( $current < $total - 2 ) {
			$window[] = 'gap-end';
		}

		$window[] = $total;

		return $window;
	}

	/**
	 * Verify capability plus nonce, stopping with 403 otherwise.
	 *
	 * @param string $nonceAction Nonce action expected for this save.
	 */
	private function requireAccess( string $nonceAction ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to manage redirects.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}

		$verified = check_admin_referer( $nonceAction );

		if ( false === $verified ) {
			wp_die(
				esc_html__( 'Security check failed. Please refresh and try again.', 'rankkernel' ),
				'',
				[ 'response' => 403 ]
			);
		}
	}

	/**
	 * Redirect back to the screen with notice flags, header safe on the load hook.
	 *
	 * The page is whitelisted to this screen and the 404 Monitor, so a flow
	 * that started on the monitor can route back there after save.
	 *
	 * @param string $query Query flags starting with an ampersand.
	 * @param string $page  Screen slug, only the monitor slug is accepted.
	 */
	private function redirect( string $query, string $page = self::SLUG ): void {
		if ( NotFoundPage::SLUG !== $page ) {
			$page = self::SLUG;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . $page . $query ) );

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			exit;
		}
	}

	/**
	 * Validated return target from the editor form.
	 *
	 * Only the 404 Monitor slug is accepted, everything else reads as no
	 * return, so the value can never route the save elsewhere.
	 *
	 * @return string Monitor slug or empty string.
	 */
	private function postedReturn(): string {
		// Verified by the caller in requireAccess before this helper runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified by the caller in requireAccess before this helper runs, unslashed here, sanitized on the following statement.
		$raw   = isset( $_POST['rk_return'] ) ? wp_unslash( $_POST['rk_return'] ) : '';
		$value = is_string( $raw ) ? sanitize_key( $raw ) : '';

		return NotFoundPage::SLUG === $value ? $value : '';
	}

	/**
	 * Validated return target from the query string.
	 *
	 * Only the 404 Monitor slug is accepted, everything else reads as no
	 * return, so the editor never links back somewhere unexpected.
	 *
	 * @return string Monitor slug or empty string.
	 */
	private function requestedReturn(): string {
		// Read only display value, validated against the monitor slug below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only display value, validated against the monitor slug below, unslashed here, sanitized on the following statement.
		$raw   = isset( $_GET['rk_return'] ) ? wp_unslash( $_GET['rk_return'] ) : '';
		$value = is_string( $raw ) ? sanitize_key( $raw ) : '';

		return NotFoundPage::SLUG === $value ? $value : '';
	}

	/**
	 * Read a POST text field, unslashed and sanitized.
	 *
	 * Every caller verifies its nonce in requireAccess first.
	 *
	 * @param string $key Field name.
	 * @return string Sanitized value or empty string.
	 */
	private function postText( string $key ): string {
		// Verified by the caller in requireAccess before this helper runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		// Verified by the caller, value sanitized below.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified by the caller, value sanitized below, unslashed here, sanitized on the following statement.
		$raw = wp_unslash( $_POST[ $key ] );

		return is_string( $raw ) ? sanitize_text_field( $raw ) : '';
	}

	/**
	 * Read a POST integer field, cast to int with a floor of zero.
	 *
	 * Every caller verifies its nonce in requireAccess first.
	 *
	 * @param string $key Field name.
	 * @return int Value or zero.
	 */
	private function postInt( string $key ): int {
		// Verified by the caller, value unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified by the caller, value unslashed then cast to int below, unslashed here, cast to scalar on the following statement.
		$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : 0;

		return max( 0, (int) ( is_scalar( $raw ) ? $raw : 0 ) );
	}

	/**
	 * Handle the add and edit form save.
	 *
	 * Field problems and loop findings stay on the page with inline errors.
	 * Successful saves redirect with a notice flag, plus chain or
	 * inconclusive flags when the analysis reports them.
	 */
	private function handleFormSave(): void {
		$this->requireAccess( self::NONCE_SAVE );

		$editingId = $this->postInt( 'rule_id' );

		$fields = [
			'source'     => $this->postText( 'rk_source' ),
			'match_type' => sanitize_key( $this->postText( 'rk_match_type' ) ),
			'target'     => $this->postText( 'rk_target' ),
			'code'       => sanitize_key( $this->postText( 'rk_code' ) ),
			// Verified in requireAccess, checkbox presence is the value.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'is_active'  => isset( $_POST['rk_active'] ),
			'rule_id'    => $editingId,
			'return_to'  => $this->postedReturn(),
		];

		$clean  = $this->validateFields( $fields );
		$errors = $clean['errors'];

		if ( [] !== $errors ) {
			$this->stayWithErrors( $errors, $fields );

			return;
		}

		if ( $editingId > 0 && null === $this->repository->get( $editingId ) ) {
			$this->stayWithErrors(
				[ 'blocked' => __( 'That redirect no longer exists. It may have been deleted.', 'rankkernel' ) ],
				$fields
			);

			return;
		}

		$proposed = [
			'source'     => $clean['source'],
			'target'     => $clean['target'],
			'code'       => $clean['code'],
			'match_type' => $clean['match_type'],
		];

		$candidates = $this->candidates( $editingId, $proposed, $fields['is_active'] );
		$safety     = $this->validator->assess_safety( $proposed, $candidates );
		$loop       = $safety['loop'];

		if ( 'equivalent' === $safety['verdict'] ) {
			$message = Validator::equivalent_message();

			$this->stayWithErrors(
				[
					'blocked' => $message . ' ' . __( 'The rule was not saved.', 'rankkernel' ),
					'target'  => $message,
				],
				$fields
			);

			return;
		}

		if ( $loop['has_cycle'] ) {
			$this->stayWithErrors(
				[
					'blocked' => sprintf(
						/* translators: %s: redirect chain path showing the loop */
						__( 'This redirect would create a redirect loop: %s. The rule was not saved.', 'rankkernel' ),
						implode( ' → ', $loop['path'] )
					),
				],
				$fields
			);

			return;
		}

		$existing = $this->repository->lookup( $clean['source'], $clean['match_type'] );

		if ( is_array( $existing ) && (int) ( $existing['id'] ?? 0 ) !== $editingId ) {
			$this->stayWithErrors(
				[ 'source' => __( 'A redirect with this source and match type already exists.', 'rankkernel' ) ],
				$fields
			);

			return;
		}

		if ( $this->patternCapReached( $editingId, $proposed, $fields['is_active'] ) ) {
			$this->stayWithErrors(
				[
					'blocked' => sprintf(
						/* translators: %d: maximum active pattern rules */
						__( 'The active pattern rule limit of %d is reached. Deactivate or delete a pattern rule before adding another.', 'rankkernel' ),
						RedirectRepository::MAX_PATTERNS
					),
				],
				$fields
			);

			return;
		}

		$row = [
			'source'     => $clean['source'],
			'match_type' => $clean['match_type'],
			'target'     => $clean['target'],
			'code'       => $clean['code'],
			'is_active'  => $fields['is_active'],
		];

		if ( $editingId > 0 ) {
			$saved  = $this->repository->update( $editingId, $row );
			$notice = 'updated';
		} else {
			$saved  = $this->repository->insert( $row ) > 0;
			$notice = 'saved';
		}

		if ( ! $saved ) {
			$this->stayWithErrors(
				[ 'blocked' => __( 'The redirect could not be saved. Please try again.', 'rankkernel' ) ],
				$fields
			);

			return;
		}

		$chain = $safety['chain'];
		$flags = '&rk_notice=' . $notice;

		if ( $chain['has_chain'] && [] !== $chain['chain'] ) {
			$flags .= '&rk_chain=' . rawurlencode( implode( ' → ', $chain['chain'] ) );

			if ( is_string( $chain['final'] ) && '' !== $chain['final'] ) {
				$flags .= '&rk_final=' . rawurlencode( $chain['final'] );
			}
		}

		if ( $loop['inconclusive'] ) {
			$flags .= '&rk_mayloop=1';
		}

		if ( $chain['inconclusive'] ) {
			$flags .= '&rk_chain_unknown=1';
		}

		$this->redirect( $flags, $fields['return_to'] );
	}

	/**
	 * Whether saving the proposed rule would exceed the pattern cap.
	 *
	 * Rules already inside the active pattern set never count as growth, so
	 * edits that keep a rule active keep passing at the limit.
	 *
	 * @param int                  $editingId Row id being edited, zero when adding.
	 * @param array<string, mixed> $proposed  Proposed source, target, code, match type.
	 * @param bool                 $isActive  Whether the proposed rule stays active.
	 * @return bool True when the cap blocks this save.
	 */
	private function patternCapReached( int $editingId, array $proposed, bool $isActive ): bool {
		if ( ! $isActive || 'exact' === (string) ( $proposed['match_type'] ?? 'exact' ) ) {
			return false;
		}

		if ( $this->repository->count_patterns() < RedirectRepository::MAX_PATTERNS ) {
			return false;
		}

		if ( $editingId > 0 ) {
			$current = $this->repository->get( $editingId );

			if ( is_array( $current )
				&& 1 === (int) ( $current['is_active'] ?? 0 )
				&& 'exact' !== (string) ( $current['match_type'] ?? 'exact' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Keep the entered values on the page with inline errors, no redirect.
	 *
	 * @param array<string, string> $errors Field errors.
	 * @param array<string, mixed>  $fields Entered values.
	 */
	private function stayWithErrors( array $errors, array $fields ): void {
		$this->formErrors     = $errors;
		$this->formValues     = $fields;
		$this->hasFormAttempt = true;
	}

	/**
	 * Validate the collected fields.
	 *
	 * @param array<string, mixed> $fields Raw collected fields.
	 * @return array{errors: array<string, string>, source: string, match_type: string, target: string, code: string}
	 */
	private function validateFields( array $fields ): array {
		$errors = [];

		$matchType = is_string( $fields['match_type'] ) ? $fields['match_type'] : '';

		if ( ! Normalizer::isMatchType( $matchType ) ) {
			$errors['match_type'] = __( 'Please choose a valid match type.', 'rankkernel' );
			$matchType            = 'exact';
		}

		$code = is_string( $fields['code'] ) ? $fields['code'] : '';

		if ( ! Normalizer::isCode( $code ) ) {
			$errors['code'] = __( 'Please choose a valid redirect type.', 'rankkernel' );
			$code           = '301';
		}

		$sourceRaw = is_string( $fields['source'] ) ? $fields['source'] : '';
		$source    = '';

		if ( '' === $sourceRaw ) {
			$errors['source'] = __( 'Please enter a source URL.', 'rankkernel' );
		} elseif ( strlen( $sourceRaw ) > 2000 ) {
			$errors['source'] = __( 'That source is too long. Please keep it under 2000 characters.', 'rankkernel' );
		} elseif ( 'regex' !== $matchType && str_contains( $sourceRaw, '#' ) ) {
			$errors['source'] = __( 'Remove the part starting with #. Fragments stay in the browser and are never sent to the server, so they cannot be matched.', 'rankkernel' );
		} else {
			$source = Normalizer::normalizeSource( $sourceRaw, $matchType );

			if ( '' === $source || Normalizer::isBlockedSource( $source ) ) {
				$errors['source'] = __( 'The home page cannot be used as a redirect source. Please enter a path such as /old page.', 'rankkernel' );
			} elseif ( 'regex' === $matchType && strlen( $sourceRaw ) > 200 ) {
				$errors['source'] = __( 'That pattern is too long. Please keep regex patterns under 200 characters.', 'rankkernel' );
			} elseif ( 'regex' === $matchType && ! $this->regexCompiles( $sourceRaw ) ) {
				$errors['source'] = __( 'That regex pattern could not be compiled. Please check the pattern and try again.', 'rankkernel' );
			}
		}

		$targetRaw = is_string( $fields['target'] ) ? $fields['target'] : '';
		$target    = '';

		if ( strlen( $targetRaw ) > 2000 ) {
			$errors['target'] = __( 'That destination is too long. Please keep it under 2000 characters.', 'rankkernel' );
		} else {
			$checked = $this->destinationValidator->validate( $targetRaw, $code );

			if ( ! $checked['valid'] ) {
				$errors['target'] = $this->destinationError( $checked['reason'] );
			} else {
				$target = $checked['destination'];
			}
		}

		return [
			'errors'     => $errors,
			'source'     => $source,
			'match_type' => $matchType,
			'target'     => $target,
			'code'       => $code,
		];
	}

	/**
	 * Human readable destination error for a validator reason.
	 *
	 * Every message keeps the shared prefix so programmatic checks keep
	 * matching, while the suffix explains what to change.
	 *
	 * @param string $reason Machine readable rejection reason.
	 * @return string Translated error text.
	 */
	private function destinationError( string $reason ): string {
		switch ( $reason ) {
			case 'empty destination':
				return __( 'That destination is not valid: please enter a destination, or choose 410 Gone or 451 which need none.', 'rankkernel' );
			case 'control characters rejected':
				return __( 'That destination is not valid: remove line breaks and control characters.', 'rankkernel' );
			case 'unsafe scheme':
				return __( 'That destination is not valid: blocked scheme. Use a relative path or an http or https URL.', 'rankkernel' );
			case 'external host not allowlisted':
				return __( 'That destination is not valid: external hosts are not allowlisted. Use a relative path or a URL on this site.', 'rankkernel' );
			default:
				return sprintf(
					/* translators: %s: reason the destination was rejected */
					__( 'That destination is not valid: %s.', 'rankkernel' ),
					$reason
				);
		}
	}

	/**
	 * Whether a regex source compiles under the matcher wrapping.
	 *
	 * Mirrors the matcher length cap and delimiter handling so the form
	 * rejects patterns the frontend would fail closed on.
	 *
	 * @param string $pattern Raw regex body as entered.
	 * @return bool True when the pattern compiles cleanly.
	 */
	private function regexCompiles( string $pattern ): bool {
		if ( '' === $pattern || strlen( $pattern ) > 200 ) {
			return false;
		}

		$wrapped = '#' . str_replace( '#', '\\#', $pattern ) . '#u';

		// Bounded compile probe for an entered pattern. The handler swallows only
		// the compile warning and is always restored in the finally block below.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( static fn (): bool => true );

		try {
			$result = preg_match( $wrapped, '/' );
		} finally {
			restore_error_handler();
		}

		return false !== $result && PREG_NO_ERROR === preg_last_error();
	}

	/**
	 * Candidate rows for safety analysis, including the proposed rule itself.
	 *
	 * The proposed rule takes part so a rule that matches its own target is
	 * reported. The edited row is excluded by id when present.
	 *
	 * @param int                  $editingId Row id being edited, zero when adding.
	 * @param array<string, mixed> $proposed  Proposed source, target, code, match type.
	 * @param bool                 $isActive  Whether the proposed rule stays active.
	 * @return array<int, array<string, mixed>>
	 */
	private function candidates( int $editingId, array $proposed, bool $isActive ): array {
		$rows = $this->repository->find_cycle_candidates();
		$out  = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			if ( $editingId > 0 && (int) ( $row['id'] ?? 0 ) === $editingId ) {
				continue;
			}

			$out[] = $row;
		}

		$out[] = array_merge( $proposed, [ 'is_active' => $isActive ? 1 : 0 ] );

		return $out;
	}

	/**
	 * Handle a single row link action.
	 *
	 * @param string $action One of delete, activate, deactivate.
	 */
	private function handleRowAction( string $action ): void {
		$this->requireAccess( self::NONCE_ROW );

		// Verified in requireAccess, value unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in requireAccess, value unslashed then cast to int below, unslashed here, cast to scalar on the following statement.
		$rawRule = isset( $_GET['rule'] ) ? wp_unslash( $_GET['rule'] ) : 0;
		$id      = max( 0, (int) ( is_scalar( $rawRule ) ? $rawRule : 0 ) );

		if ( $id <= 0 || null === $this->repository->get( $id ) ) {
			$this->redirect( '&rk_error=not_found' );

			return;
		}

		if ( 'delete' === $action ) {
			$ok = $this->repository->delete( $id );
			$this->redirect( $ok ? '&rk_notice=deleted' : '&rk_error=save_failed' );

			return;
		}

		$ok = $this->repository->set_active( $id, 'activate' === $action );
		$this->redirect( $ok ? '&rk_notice=' . $action . 'd' : '&rk_error=save_failed' );
	}

	/**
	 * Handle bulk activate, deactivate, and delete.
	 */
	private function handleBulk(): void {
		$this->requireAccess( self::NONCE_BULK );

		// Verified in requireAccess, value passed through sanitize_key below.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in requireAccess, value passed through sanitize_key below, unslashed here, sanitized on the following statement.
		$rawAction = isset( $_POST['rk_bulk_action'] ) ? (string) wp_unslash( $_POST['rk_bulk_action'] ) : '';
		$action    = sanitize_key( $rawAction );

		// Verified in requireAccess, values unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in requireAccess, values unslashed then cast to int below, unslashed here, sanitized or validated on the following statements.
		$unslashedIds = isset( $_POST['rule_ids'] ) ? wp_unslash( $_POST['rule_ids'] ) : [];
		$rawIds       = is_array( $unslashedIds ) ? $unslashedIds : [];
		$ids          = [];

		foreach ( $rawIds as $rawId ) {
			$id = (int) ( is_scalar( $rawId ) ? $rawId : 0 );

			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		if ( ! in_array( $action, [ 'activate', 'deactivate', 'delete' ], true ) || [] === $ids ) {
			$this->redirect( '&rk_error=bulk_none' );

			return;
		}

		$result   = $this->repository->bulk( $action, $ids );
		$affected = 'delete' === $action ? (int) $result['deleted'] : (int) $result['updated'];

		$this->redirect( '&rk_notice=bulk&rk_bulk=' . $action . '&rk_count=' . $affected );
	}

	/**
	 * Handle the settings form save.
	 */
	private function handleSettingsSave(): void {
		$this->requireAccess( self::NONCE_SETTINGS );

		$partial = [
			// Verified in requireAccess, checkbox presence is the value.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'preserve_query'     => isset( $_POST['rk_preserve_query'] ),
			// Verified in requireAccess, checkbox presence is the value.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'auto_slug_redirect' => isset( $_POST['rk_auto_slug_redirect'] ),
		];

		// Verified in requireAccess, value unslashed then clamped to 1 to 100 below.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in requireAccess, value unslashed then clamped to 1 to 100 below, unslashed here, cast to scalar on the following statement.
		$rawPerPage                = isset( $_POST['rk_rules_per_page'] ) ? wp_unslash( $_POST['rk_rules_per_page'] ) : 20;
		$partial['rules_per_page'] = max( 1, min( 100, (int) ( is_scalar( $rawPerPage ) ? $rawPerPage : 20 ) ) );

		$this->redirectSettings->set( $partial );

		// Preserve query and slug watcher settings change frontend dispatch,
		// so a settings save retires the cached matches exactly like a rule
		// write. This runs on the admin write path only, never per request.
		RedirectCache::invalidateAll();

		$this->redirect( '&rk_notice=settings' );
	}

	/**
	 * Latest CSV import outcome, null when no import ran on this load.
	 *
	 * @return array<string, mixed>|null Import summary with per row details.
	 */
	public function import_result(): ?array {
		return $this->importResult;
	}

	/**
	 * Current rules rendered as a CSV string under the documented contract.
	 *
	 * @return string CSV document with the header row first.
	 */
	public function export_csv_string(): string {
		$handler = new CsvHandler( $this->repository );

		return $handler->export_csv();
	}

	/**
	 * Handle the CSV import form save, staying on the page with a report.
	 *
	 * The uploaded file is validated row by row through the normal pipeline.
	 * The summary plus the per row error list renders below, without any
	 * redirect, so the full detail survives.
	 */
	private function handleImport(): void {
		$this->requireAccess( self::NONCE_IMPORT );

		// Verified in requireAccess, checkbox presence is the value.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$updateExisting = isset( $_POST['rk_csv_update'] );

		// Verified in requireAccess, upload metadata is read then validated below.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in requireAccess, upload metadata is read then validated below, upload metadata validated below, file contents never executed.
		$file = $_FILES['rk_csv_file'] ?? null;

		if ( ! is_array( $file ) ) {
			$this->importResult = $this->importFileError( __( 'Please choose a CSV file to import.', 'rankkernel' ) );

			return;
		}

		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $error ) {
			$this->importResult = $this->importFileError( __( 'That upload did not complete. Please try again.', 'rankkernel' ) );

			return;
		}

		$tmp   = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		$probe = $this->isUploadedFile;

		if ( '' === $tmp || ! $probe( $tmp ) || ! is_readable( $tmp ) ) {
			$this->importResult = $this->importFileError( __( 'The uploaded file could not be read.', 'rankkernel' ) );

			return;
		}

		// Fail closed: when the name is unusable or the checker is unavailable, reject rather than pass.
		$name = isset( $file['name'] ) && is_string( $file['name'] ) ? $file['name'] : '';
		$type = '' !== $name && function_exists( 'wp_check_filetype' )
			? wp_check_filetype( $name, [ 'csv' => 'text/csv' ] )
			: false;

		if ( ! is_array( $type ) || empty( $type['ext'] ) ) {
			$this->importResult = $this->importFileError( __( 'Invalid file type. Please upload a valid CSV file.', 'rankkernel' ) );

			return;
		}

		$handler = new CsvHandler( $this->repository, $this->validator, $this->destinationValidator );

		$this->importResult = $handler->import_csv( $tmp, $updateExisting );
	}

	/**
	 * Handle the CSV export download on the load hook, header safe.
	 *
	 * Production streams row batches straight to the download, so export
	 * memory stays flat however many rules exist. Tests stay on the string
	 * path, which returns the identical bytes.
	 */
	private function handleExport(): void {
		$this->requireAccess( self::NONCE_EXPORT );

		if ( defined( 'RANKKERNEL_TESTING' ) ) {
			return;
		}

		nocache_headers();

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=rankkernel-redirects-' . gmdate( 'Ymd-His' ) . '.csv' );

		$handler = new CsvHandler( $this->repository );
		$handler->stream_csv();

		exit;
	}

	/**
	 * Wrap a file level import failure in the report shape.
	 *
	 * @param string $reason Translated reason.
	 * @return array<string, mixed> Empty summary carrying one file error.
	 */
	private function importFileError( string $reason ): array {
		return [
			'created'  => 0,
			'updated'  => 0,
			'skipped'  => 0,
			'errors'   => [
				[
					'row'    => 0,
					'reason' => $reason,
				],
			],
			'warnings' => [],
		];
	}

	/**
	 * Build the bulk result message from the query flags.
	 *
	 * @return string Message text.
	 */
	private function bulkMessage(): string {
		// Read only display flags.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only display flags, unslashed here, sanitized on the following statement.
		$rawBulk = isset( $_GET['rk_bulk'] ) ? (string) wp_unslash( $_GET['rk_bulk'] ) : '';
		$bulk    = sanitize_key( $rawBulk );

		// Read only display flags, value unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only display flags, value unslashed then cast to int below, unslashed here, cast to scalar on the following statement.
		$rawCount = isset( $_GET['rk_count'] ) ? wp_unslash( $_GET['rk_count'] ) : 0;
		$count    = max( 0, (int) ( is_scalar( $rawCount ) ? $rawCount : 0 ) );

		if ( 'delete' === $bulk ) {
			return sprintf(
				/* translators: %d: number of deleted redirects */
				__( 'Bulk delete finished for %d redirects.', 'rankkernel' ),
				$count
			);
		}

		if ( 'activate' === $bulk ) {
			return sprintf(
				/* translators: %d: number of activated redirects */
				__( 'Bulk activate finished for %d redirects.', 'rankkernel' ),
				$count
			);
		}

		if ( 'deactivate' === $bulk ) {
			return sprintf(
				/* translators: %d: number of deactivated redirects */
				__( 'Bulk deactivate finished for %d redirects.', 'rankkernel' ),
				$count
			);
		}

		return __( 'Bulk action finished.', 'rankkernel' );
	}

	/**
	 * Whether the editor renders open on this load.
	 *
	 * Closed shows only the list. Open on explicit request, while editing,
	 * while a failed save keeps its errors, and while a 404 prefill waits
	 * for its destination.
	 *
	 * @return bool True when the editor container renders open.
	 */
	private function isEditorOpen(): bool {
		if ( $this->hasFormAttempt ) {
			return true;
		}

		// Read only display flags, values validated below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only display flags, values validated below, unslashed here, validated against an allow list below.
		$rawOpen = isset( $_GET['rk_open'] ) ? (string) wp_unslash( $_GET['rk_open'] ) : '';

		if ( '1' === $rawOpen ) {
			return true;
		}

		// Read only display flags, value unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only display flags, value unslashed then cast to int below, unslashed here, cast to scalar on the following statement.
		$rawEdit = isset( $_GET['rk_edit'] ) ? wp_unslash( $_GET['rk_edit'] ) : 0;
		$editId  = max( 0, (int) ( is_scalar( $rawEdit ) ? $rawEdit : 0 ) );

		if ( $editId > 0 ) {
			return true;
		}

		return '' !== $this->prefillSource();
	}

	/**
	 * Match type values to labels.
	 *
	 * @return array<string, string>
	 */
	private function matchOptions(): array {
		return [
			'exact'    => __( 'Exact', 'rankkernel' ),
			'prefix'   => __( 'Prefix', 'rankkernel' ),
			'contains' => __( 'Contains', 'rankkernel' ),
			'suffix'   => __( 'Suffix', 'rankkernel' ),
			'wildcard' => __( 'Wildcard', 'rankkernel' ),
			'regex'    => __( 'Regex', 'rankkernel' ),
		];
	}

	/**
	 * Match type values to short explanations.
	 *
	 * @return array<string, string>
	 */
	private function matchHints(): array {
		return [
			'exact'    => __( 'Matches one path exactly. This is the fastest option.', 'rankkernel' ),
			'prefix'   => __( 'Matches the path and everything under it. The longest match wins.', 'rankkernel' ),
			'contains' => __( 'Matches when the path includes this text anywhere.', 'rankkernel' ),
			'suffix'   => __( 'Matches when the path ends with this text.', 'rankkernel' ),
			'wildcard' => __( 'Use * for any characters, for example /blog/*.', 'rankkernel' ),
			'regex'    => __( 'Full pattern match for advanced use. The pattern is tested before save.', 'rankkernel' ),
		];
	}

	/**
	 * Status code values to labels.
	 *
	 * Keys read as integers because PHP casts numeric strings, so every use
	 * site casts the key back to string before display or comparison.
	 *
	 * @return array<int, string>
	 */
	private function codeOptions(): array {
		return [
			'301' => __( '301 Permanent', 'rankkernel' ),
			'302' => __( '302 Temporary', 'rankkernel' ),
			'307' => __( '307 Temporary, method kept', 'rankkernel' ),
			'410' => __( '410 Gone', 'rankkernel' ),
			'451' => __( '451 Legal block', 'rankkernel' ),
		];
	}

	/**
	 * Status code values to short explanations.
	 *
	 * Keys read as integers because PHP casts numeric strings, so every use
	 * site casts the key back to string before display or comparison.
	 *
	 * @return array<int, string>
	 */
	private function codeHints(): array {
		return [
			'301' => __( 'Permanent move. Search engines pass ranking to the new address.', 'rankkernel' ),
			'302' => __( 'Temporary move. The old address stays indexed.', 'rankkernel' ),
			'307' => __( 'Temporary move that keeps the request method.', 'rankkernel' ),
			'410' => __( 'Gone. No destination needed. Use for permanently removed content.', 'rankkernel' ),
			'451' => __( 'Unavailable for legal reasons. No destination needed.', 'rankkernel' ),
		];
	}

	/**
	 * Regex state for a raw pattern, mirroring the backend constraints.
	 *
	 * @param string $pattern Raw pattern as entered.
	 * @return array{ok: bool, message: string} State plus translated message.
	 */
	private function regexState( string $pattern ): array {
		if ( '' === $pattern ) {
			return [
				'ok'      => false,
				'message' => __( 'Enter a pattern to check it. Patterns are limited to 200 characters.', 'rankkernel' ),
			];
		}

		if ( strlen( $pattern ) > 200 ) {
			return [
				'ok'      => false,
				'message' => __( 'That pattern is too long. Please keep regex patterns under 200 characters.', 'rankkernel' ),
			];
		}

		if ( ! $this->regexCompiles( $pattern ) ) {
			return [
				'ok'      => false,
				'message' => __( 'That pattern does not compile. Check the syntax and try again.', 'rankkernel' ),
			];
		}

		$trimmed = trim( $pattern );

		if ( ! str_starts_with( $trimmed, '^' ) || ! str_ends_with( $trimmed, '$' ) ) {
			return [
				'ok'      => true,
				'message' => __( 'Pattern compiles cleanly. Tip: add ^ at the start and $ at the end to match the whole path.', 'rankkernel' ),
			];
		}

		return [
			'ok'      => true,
			'message' => __( 'Pattern compiles cleanly.', 'rankkernel' ),
		];
	}

	/**
	 * Current form values, preferring a failed attempt, then the edited row, then defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function formValues(): array {
		if ( $this->hasFormAttempt ) {
			return $this->formValues;
		}

		// Read only display flag, value unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only display flag, value unslashed then cast to int below, unslashed here, cast to scalar on the following statement.
		$rawEdit = isset( $_GET['rk_edit'] ) ? wp_unslash( $_GET['rk_edit'] ) : 0;
		$id      = max( 0, (int) ( is_scalar( $rawEdit ) ? $rawEdit : 0 ) );

		if ( $id <= 0 ) {
			return [
				'source'     => $this->prefillSource(),
				'match_type' => 'exact',
				'target'     => '',
				'code'       => '301',
				'is_active'  => true,
				'rule_id'    => 0,
				'return_to'  => $this->requestedReturn(),
			];
		}

		$row = $this->repository->get( $id );

		if ( null === $row ) {
			return [
				'source'     => '',
				'match_type' => 'exact',
				'target'     => '',
				'code'       => '301',
				'is_active'  => true,
				'rule_id'    => 0,
			];
		}

		return [
			'source'     => (string) ( $row['source'] ?? '' ),
			'match_type' => (string) ( $row['match_type'] ?? 'exact' ),
			'target'     => (string) ( $row['target'] ?? '' ),
			'code'       => (string) ( $row['code'] ?? '301' ),
			'is_active'  => 1 === (int) ( $row['is_active'] ?? 0 ),
			'rule_id'    => (int) ( $row['id'] ?? 0 ),
		];
	}

	/**
	 * Prefilled source from the 404 Monitor Create Redirect link.
	 *
	 * Read only display value, sanitized below. The save pipeline validates
	 * and normalizes it again, so this is a convenience only.
	 *
	 * @return string Source value or empty string.
	 */
	private function prefillSource(): string {
		// Read only display value, sanitized and escaped by the caller.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only display value, sanitized and escaped by the caller, unslashed here, sanitized on the following statement.
		$raw = isset( $_GET['rk_source'] ) ? wp_unslash( $_GET['rk_source'] ) : '';

		return is_string( $raw ) ? sanitize_text_field( $raw ) : '';
	}

	/**
	 * Current list filters from the given request input, sanitized and validated.
	 *
	 * The input array is explicit so the full page load resolves filters from
	 * $_GET and the AJAX handler resolves them from $_POST, both through this
	 * single validation path.
	 *
	 * @param array<string, mixed> $input Request input, normally $_GET or $_POST.
	 * @return array{search: string, status: string, match_type: string, code: string, orderby: string, order: string, page: int}
	 */
	private function listFilters( array $input ): array {
		// Read only display flag, value sanitized on the following statement.
		$search = isset( $input['s'] ) ? sanitize_text_field( (string) wp_unslash( $input['s'] ) ) : '';

		// Read only display flag, value validated below.
		$rawStatus = isset( $input['rk_status'] ) ? (string) wp_unslash( $input['rk_status'] ) : 'all';
		$status    = sanitize_key( $rawStatus );

		if ( ! in_array( $status, [ 'all', 'active', 'inactive' ], true ) ) {
			$status = 'all';
		}

		// Read only display flag, value validated below.
		$rawMatch = isset( $input['rk_match'] ) ? (string) wp_unslash( $input['rk_match'] ) : '';
		$match    = sanitize_key( $rawMatch );

		if ( '' !== $match && ! Normalizer::isMatchType( $match ) ) {
			$match = '';
		}

		// Read only display flag, value validated below.
		$rawCode = isset( $input['rk_code'] ) ? (string) wp_unslash( $input['rk_code'] ) : '';
		$code    = sanitize_key( $rawCode );

		if ( '' !== $code && ! Normalizer::isCode( $code ) ) {
			$code = '';
		}

		// Read only display flag, value validated below.
		$rawOrderBy = isset( $input['rk_orderby'] ) ? (string) wp_unslash( $input['rk_orderby'] ) : 'id';
		$orderby    = sanitize_key( $rawOrderBy );

		if ( ! array_key_exists( $orderby, self::SORTABLE ) && 'id' !== $orderby ) {
			$orderby = 'id';
		}

		// Read only display flag, value validated below.
		$rawOrder = isset( $input['rk_order'] ) ? (string) wp_unslash( $input['rk_order'] ) : 'DESC';
		$order    = 'asc' === strtolower( $rawOrder ) ? 'ASC' : 'DESC';

		// Read only display flag, value unslashed then cast to int below.
		$rawPage = isset( $input['rk_paged'] ) ? wp_unslash( $input['rk_paged'] ) : 1;
		$page    = max( 1, (int) ( is_scalar( $rawPage ) ? $rawPage : 1 ) );

		// Read only display flag, value unslashed then cast to int below.
		$rawPerPage = isset( $input['rk_per_page'] ) ? wp_unslash( $input['rk_per_page'] ) : 0;
		$perPage    = (int) ( is_scalar( $rawPerPage ) ? $rawPerPage : 0 );

		return [
			'search'     => $search,
			'status'     => $status,
			'match_type' => $match,
			'code'       => $code,
			'orderby'    => $orderby,
			'order'      => $order,
			'page'       => $page,
			'per_page'   => $perPage > 0 ? min( 100, $perPage ) : 0,
		];
	}

	/**
	 * Build a screen URL with the given parameters.
	 *
	 * @param array<string, mixed> $params Query parameters.
	 * @return string Screen URL.
	 */
	private function pageUrl( array $params ): string {
		$url = admin_url( 'admin.php?page=' . self::SLUG );

		if ( [] === $params ) {
			return $url;
		}

		$clean = [];

		foreach ( $params as $key => $value ) {
			if ( '' !== $value && null !== $value ) {
				$clean[ $key ] = $value;
			}
		}

		$result = add_query_arg( $clean, $url );

		return $result;
	}

	/**
	 * Rows per page from settings, clamped to 1 to 100.
	 *
	 * @return int Rows per page.
	 */
	private function rulesPerPage(): int {
		$perPage = (int) $this->redirectSettings->get( 'rules_per_page', 20 );

		return max( 1, min( 100, $perPage ) );
	}
}
