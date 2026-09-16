<?php
/**
 * 404 Monitor admin page, summary plus list plus settings.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\Monitor\Exclusions;
use RankKernel\Modules\Monitor\MonitorRepository;
use RankKernel\Modules\Monitor\MonitorSettings;
use RankKernel\Plugin;

/**
 * Prepares the 404 Monitor dashboard view state and handles its saves.
 *
 * Owns capability checks, nonce verification, saving, validation, redirects,
 * and view state preparation. The HTML lives in
 * src/Admin/Views/not-found.php.
 *
 * The operational summary, the searchable sortable paginated list, and the
 * monitor settings share one screen under the RankKernel menu. Saves run on
 * the load hook so the redirect after save stays header safe. Clear and
 * delete use the bounded repository path, never a raw truncate. Creating a
 * redirect from a row links to the Redirects screen with the source
 * prefilled, and the action stays hidden while the Redirects module is off.
 */
final class NotFoundPage {
	/**
	 * Menu slug for the screen.
	 */
	public const SLUG = 'rankkernel-404';

	/**
	 * Hook suffix for the screen, used to gate asset loading.
	 */
	public const HOOK_SUFFIX = 'rankkernel_page_rankkernel-404';

	/**
	 * Nonce action for the clear log form.
	 */
	private const NONCE_CLEAR = 'rankkernel_404_clear';

	/**
	 * Nonce action for single row links.
	 */
	private const NONCE_ROW = 'rankkernel_404_row';

	/**
	 * Nonce action for bulk actions.
	 */
	private const NONCE_BULK = 'rankkernel_404_bulk';

	/**
	 * Nonce action for the settings form.
	 */
	private const NONCE_SETTINGS = 'rankkernel_404_settings';

	/**
	 * Usage percent where the informational notice appears.
	 */
	private const NEAR_LIMIT = 80;

	/**
	 * Usage percent where the stronger notice appears.
	 */
	private const HIGH_LIMIT = 90;

	/**
	 * Rows removed per clear pass.
	 */
	private const CLEAR_BATCH = 500;

	/**
	 * Maximum clear passes per request.
	 */
	private const CLEAR_PASSES = 20;

	/**
	 * Sortable columns shown in the list.
	 *
	 * @var array<string, string>
	 */
	private const SORTABLE = [
		'uri'           => 'URL',
		'hits'          => 'Hits',
		'created'       => 'First Seen',
		'last_accessed' => 'Last Seen',
	];

	/**
	 * Log repository.
	 *
	 * @var MonitorRepository
	 */
	private MonitorRepository $repository;

	/**
	 * Module settings store.
	 *
	 * @var MonitorSettings
	 */
	private MonitorSettings $monitorSettings;

	/**
	 * Shared module enable map.
	 *
	 * @var ModuleEnableMap
	 */
	private ModuleEnableMap $enableMap;

	/**
	 * Constructor.
	 *
	 * @param MonitorRepository|null $repository      Log repository, fresh one when null.
	 * @param MonitorSettings|null   $monitorSettings Settings store, fresh one when null.
	 * @param ModuleEnableMap|null   $enableMap       Enable map, fresh one when null.
	 */
	public function __construct(
		?MonitorRepository $repository = null,
		?MonitorSettings $monitorSettings = null,
		?ModuleEnableMap $enableMap = null
	) {
		$this->repository      = $repository ?? new MonitorRepository();
		$this->monitorSettings = $monitorSettings ?? new MonitorSettings();
		$this->enableMap       = $enableMap ?? new ModuleEnableMap();
	}

	/**
	 * Handle a save on the load hook, before any output is sent.
	 *
	 * Runs on load rankkernel page rankkernel 404, so wp safe redirect can
	 * still send headers. Destructive forms arrive by POST with a marker
	 * field, single row deletes arrive by GET.
	 */
	public function maybeHandleSave(): void {
		// Delegates to a handler which verifies capability plus its own nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- delegates to a handler which verifies capability plus its own nonce, compared strictly against a literal, never stored or output.
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			// Marker read only, this branch verifies its nonce in requireAccess.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['rankkernel_404_clear'] ) ) {
				$this->handleClear();

				return;
			}

			// Marker read only, this branch verifies its nonce in requireAccess.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['rankkernel_404_bulk'] ) ) {
				$this->handleBulk();

				return;
			}

			// Marker read only, this branch verifies its nonce in requireAccess.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['rankkernel_404_settings_save'] ) ) {
				$this->handleSettingsSave();

				return;
			}
		}

		// Read only routing flag, the row handler verifies capability plus nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only routing flag, the row handler verifies capability plus nonce, unslashed here, sanitized on the following statement.
		$rawAction = isset( $_GET['rk_action'] ) ? (string) wp_unslash( $_GET['rk_action'] ) : '';
		$action    = sanitize_key( $rawAction );

		if ( 'delete' === $action ) {
			$this->handleRowDelete();
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

		$css = plugins_url( 'assets/css/monitor-admin.css', (string) RANKKERNEL_FILE );
		wp_register_style( 'rankkernel-monitor-admin', $css, [], $version );
		wp_enqueue_style( 'rankkernel-monitor-admin' );

		$js = plugins_url( 'assets/js/monitor-admin.js', (string) RANKKERNEL_FILE );
		wp_register_script( 'rankkernel-monitor-admin', $js, [ 'wp-a11y', 'wp-i18n' ], $version, true );
		wp_enqueue_script( 'rankkernel-monitor-admin' );
	}

	/**
	 * Prepare the view state and load the 404 Monitor view.
	 */
	public function render(): void {
		// Read only display flags.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = isset( $_GET['rk_notice'] ) ? sanitize_key( (string) wp_unslash( $_GET['rk_notice'] ) ) : '';

		$noticeSuccess = '';

		switch ( $notice ) {
			case 'cleared':
				$noticeSuccess = __( '404 log cleared.', 'rankkernel' );
				break;
			case 'deleted':
				$noticeSuccess = __( '404 entry deleted.', 'rankkernel' );
				break;
			case 'settings':
				$noticeSuccess = __( 'Settings saved.', 'rankkernel' );
				break;
			case 'redirect_saved':
				$noticeSuccess = __( 'Redirect saved.', 'rankkernel' );
				break;
			case 'bulk':
				$noticeSuccess = $this->bulkMessage();
				break;
		}

		// Read only display flags.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error = isset( $_GET['rk_error'] ) ? sanitize_key( (string) wp_unslash( $_GET['rk_error'] ) ) : '';

		$errorMessages = [
			'not_found'   => __( 'That 404 entry no longer exists.', 'rankkernel' ),
			'save_failed' => __( 'The action could not be completed. Please try again.', 'rankkernel' ),
			'bulk_none'   => __( 'Choose at least one entry and the delete action.', 'rankkernel' ),
		];

		$noticeError = isset( $errorMessages[ $error ] ) ? $errorMessages[ $error ] : '';

		// Read only display flags, sanitized and escaped below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only display flags, sanitized and escaped below, unslashed here, sanitized on the following statement.
		$rawChain     = isset( $_GET['rk_chain'] ) ? (string) wp_unslash( $_GET['rk_chain'] ) : '';
		$carriedChain = sanitize_text_field( $rawChain );

		// Read only display flags.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$carriedChainUnknown = isset( $_GET['rk_chain_unknown'] );

		// Read only display flag, sanitized and escaped below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only display flag, sanitized and escaped below, unslashed here, sanitized on the following statement.
		$rawFinal     = isset( $_GET['rk_final'] ) ? (string) wp_unslash( $_GET['rk_final'] ) : '';
		$carriedFinal = sanitize_text_field( $rawFinal );

		// Read only display flags.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$carriedMayLoop = isset( $_GET['rk_mayloop'] );

		$listSlug      = self::SLUG;
		$baseScreenUrl = $this->pageUrl( [] );
		$searchFormUrl = admin_url( 'admin.php' );

		$usage                = $this->usage();
		$summaryCount         = max( 0, (int) $usage['count'] );
		$summaryMax           = max( 1, (int) $usage['max'] );
		$summaryPercent       = min( 100.0, max( 0.0, (float) $usage['percent'] ) );
		$summaryRetentionDays = max( 1, min( 365, (int) $this->monitorSettings->get( 'retention_days', 30 ) ) );

		$recent            = $this->recentActivity();
		$summaryHasRecent  = is_array( $recent );
		$summaryRecentUri  = is_array( $recent ) ? (string) ( $recent['uri'] ?? '' ) : '';
		$summaryRecentSeen = is_array( $recent ) ? (string) ( $recent['last_accessed'] ?? '' ) : '';

		$nearUsage = $this->usage();
		$nearState = self::limitState( (float) $nearUsage['percent'] );
		$nearMax   = max( 1, (int) $nearUsage['max'] );

		$redirectsEnabled = $this->isRedirectsEnabled();
		$advancedFields   = $this->monitorSettings->isAdvancedFields();

		$listFilters = $this->listFilters();

		$listResult = $this->repository->paginate(
			[
				'search'   => $listFilters['search'],
				'orderby'  => $listFilters['orderby'],
				'order'    => $listFilters['order'],
				'page'     => $listFilters['page'],
				'per_page' => 20,
			]
		);

		$listRows      = $listResult['rows'];
		$listTotal     = (int) $listResult['total'];
		$listPages     = (int) $listResult['pages'];
		$listPage      = max( 1, (int) $listResult['page'] );
		$listIsEmpty   = ( [] === $listRows );
		$listHasFilter = '' !== (string) $listFilters['search'];

		$sortColumns = [];

		foreach ( self::SORTABLE as $column => $label ) {
			$current = (string) $listFilters['orderby'] === $column;
			$next    = $current && 'ASC' === (string) $listFilters['order'] ? 'desc' : 'asc';
			$arrow   = $current ? ( 'ASC' === (string) $listFilters['order'] ? ' ↑' : ' ↓' ) : '';

			$url = $this->pageUrl(
				[
					's'          => $listFilters['search'],
					'rk_orderby' => $column,
					'rk_order'   => $next,
				]
			);

			$sortColumns[] = [
				'label' => $label,
				'class' => 'manage-column sortable' . ( $current ? ' sorted' : '' ),
				'url'   => $url,
				'arrow' => $arrow,
			];
		}

		$listRowItems = [];

		foreach ( $listRows as $listRow ) {
			if ( ! is_array( $listRow ) ) {
				continue;
			}

			$listRowId      = (int) ( $listRow['id'] ?? 0 );
			$listRowUri     = (string) ( $listRow['uri'] ?? '' );
			$listRowHits    = max( 0, (int) ( $listRow['hits'] ?? 0 ) );
			$listRowCreated = (string) ( $listRow['created'] ?? '' );
			$listRowSeen    = (string) ( $listRow['last_accessed'] ?? '' );
			$listRowReferer = (string) ( $listRow['referer'] ?? '' );
			$listRowAgent   = (string) ( $listRow['user_agent'] ?? '' );

			$listRowBase      = admin_url( 'admin.php?page=' . self::SLUG );
			$listRowDeleteUrl = wp_nonce_url( $listRowBase . '&rk_action=delete&entry=' . $listRowId, self::NONCE_ROW );

			$listRowSelectLabel = sprintf(
				/* translators: %s: 404 URI */
				__( 'Select 404 entry for %s', 'rankkernel' ),
				$listRowUri
			);

			$listRowItems[] = [
				'id'                => $listRowId,
				'uri'               => $listRowUri,
				'hits'              => $listRowHits,
				'created'           => $listRowCreated,
				'seen'              => $listRowSeen,
				'referer'           => $listRowReferer,
				'agent'             => $listRowAgent,
				'deleteUrl'         => $listRowDeleteUrl,
				'selectLabel'       => $listRowSelectLabel,
				'createRedirectUrl' => $redirectsEnabled ? $this->createRedirectUrl( $listRowUri ) : '',
			];
		}

		$paginationBase = [
			's'          => $listFilters['search'],
			'rk_orderby' => $listFilters['orderby'],
			'rk_order'   => strtolower( (string) $listFilters['order'] ),
		];

		$pagination = [
			'has'         => $listPages > 1,
			'label'       => sprintf(
				/* translators: %1$d: current page, %2$d: total pages */
				__( 'Page %1$d of %2$d', 'rankkernel' ),
				$listPage,
				$listPages
			),
			'previousUrl' => $listPage > 1 ? $this->pageUrl( array_merge( $paginationBase, [ 'rk_paged' => $listPage - 1 ] ) ) : '',
			'nextUrl'     => $listPage < $listPages ? $this->pageUrl( array_merge( $paginationBase, [ 'rk_paged' => $listPage + 1 ] ) ) : '',
		];

		$settingsAll         = $this->monitorSettings->all();
		$settingsAdvanced    = ! empty( $settingsAll['advanced_fields'] );
		$settingsIgnoreQuery = ! empty( $settingsAll['ignore_query'] );
		$settingsRetention   = max( 1, min( 365, (int) ( $settingsAll['retention_days'] ?? 30 ) ) );
		$settingsMaxRows     = max( 100, min( 10000, (int) ( $settingsAll['max_rows'] ?? 1000 ) ) );
		$settingsFloodBudget = max( 1, min( 1000, (int) ( $settingsAll['flood_budget'] ?? 50 ) ) );
		$settingsFloodWindow = max( 60, min( 3600, (int) ( $settingsAll['flood_window'] ?? 300 ) ) );
		$settingsComparators = $this->exclusionOptions();

		/**
		 * Exclusion rows for the table.
		 *
		 * @var array<int, array<string, mixed>> $exclusionRows
		 */
		$exclusionRows = array_merge(
			$this->monitorSettings->getExclusions(),
			[
				[
					'comparator' => 'prefix',
					'value'      => '',
				],
			]
		);

		$exclusionRowItems = [];

		foreach ( $exclusionRows as $exclusionRow ) {
			if ( ! is_array( $exclusionRow ) ) {
				continue;
			}

			$exclusionRowItems[] = [
				'comparator' => (string) ( $exclusionRow['comparator'] ?? 'prefix' ),
				'value'      => (string) ( $exclusionRow['value'] ?? '' ),
			];
		}

		require __DIR__ . '/Views/not-found.php';
	}

	/**
	 * Near limit state for a usage percent.
	 *
	 * Below 80 is normal, 80 to below 90 is the informational state, 90 and
	 * above is the stronger state.
	 *
	 * @param float $percent Usage percent.
	 * @return string One of normal, warn, high.
	 */
	public static function limitState( float $percent ): string {
		if ( $percent >= self::HIGH_LIMIT ) {
			return 'high';
		}

		if ( $percent >= self::NEAR_LIMIT ) {
			return 'warn';
		}

		return 'normal';
	}

	/**
	 * Whether the Redirects module is enabled.
	 *
	 * The per row Create Redirect action requires it.
	 *
	 * @return bool True when the Redirects module is enabled.
	 */
	public function isRedirectsEnabled(): bool {
		return $this->enableMap->isEnabled( 'redirects' );
	}

	/**
	 * Redirects add screen URL with the 404 source prefilled.
	 *
	 * The Redirects editor reads the rk_source query value on a fresh add,
	 * so the new rule passes through the normal redirect validation
	 * pipeline. The rk_return flag opens the editor and routes the save
	 * back to the monitor, so the address is never copied by hand.
	 *
	 * @param string $uri Normalized 404 URI.
	 * @return string Redirects screen URL with source plus return attached.
	 */
	public function createRedirectUrl( string $uri ): string {
		$base = admin_url( 'admin.php?page=' . RedirectsPage::SLUG );

		return add_query_arg(
			[
				'rk_source' => $uri,
				'rk_return' => self::SLUG,
			],
			$base
		);
	}

	/**
	 * Verify capability plus nonce, stopping with 403 otherwise.
	 *
	 * @param string $nonceAction Nonce action expected for this save.
	 */
	private function requireAccess( string $nonceAction ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to manage the 404 log.', 'rankkernel' ),
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
	 * @param string $query Query flags starting with an ampersand.
	 */
	private function redirect( string $query ): void {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . $query ) );

		if ( ! defined( 'RANKKERNEL_TESTING' ) ) {
			exit;
		}
	}

	/**
	 * Read a POST integer field with a fallback.
	 *
	 * Every caller verifies its nonce in requireAccess first.
	 *
	 * @param string $key      Field name.
	 * @param int    $fallback Value when the field is missing.
	 * @return int Value or the fallback.
	 */
	private function postInt( string $key, int $fallback ): int {
		// Verified by the caller, value unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified by the caller, value unslashed then cast to int below, unslashed here, cast to scalar on the following statement.
		$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $fallback;

		return (int) ( is_scalar( $raw ) ? $raw : $fallback );
	}

	/**
	 * Handle the Clear Log action through the bounded deletion path.
	 *
	 * Loops one limited batch at a time until the log is empty or the pass
	 * cap is hit. Automatic pruning stays untouched, this action is the
	 * manual control next to it.
	 */
	private function handleClear(): void {
		$this->requireAccess( self::NONCE_CLEAR );

		for ( $pass = 0; $pass < self::CLEAR_PASSES; $pass++ ) {
			if ( $this->repository->count() <= 0 ) {
				break;
			}

			$deleted = $this->repository->clearAllBounded( self::CLEAR_BATCH );

			if ( $deleted <= 0 ) {
				break;
			}
		}

		$this->redirect( '&rk_notice=cleared' );
	}

	/**
	 * Handle a single row delete link.
	 */
	private function handleRowDelete(): void {
		$this->requireAccess( self::NONCE_ROW );

		// Verified in requireAccess, value unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in requireAccess, value unslashed then cast to int below, unslashed here, cast to scalar on the following statement.
		$rawEntry = isset( $_GET['entry'] ) ? wp_unslash( $_GET['entry'] ) : 0;
		$id       = max( 0, (int) ( is_scalar( $rawEntry ) ? $rawEntry : 0 ) );

		if ( $id <= 0 || null === $this->repository->findById( $id ) ) {
			$this->redirect( '&rk_error=not_found' );

			return;
		}

		$ok = $this->repository->deleteById( $id );
		$this->redirect( $ok ? '&rk_notice=deleted' : '&rk_error=save_failed' );
	}

	/**
	 * Handle bulk delete.
	 */
	private function handleBulk(): void {
		$this->requireAccess( self::NONCE_BULK );

		// Verified in requireAccess, value passed through sanitize_key below.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in requireAccess, value passed through sanitize_key below, unslashed here, sanitized on the following statement.
		$rawAction = isset( $_POST['rk_bulk_action'] ) ? (string) wp_unslash( $_POST['rk_bulk_action'] ) : '';
		$action    = sanitize_key( $rawAction );

		// Verified in requireAccess, values unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified in requireAccess, values unslashed then cast to int below, unslashed here, sanitized or validated on the following statements.
		$unslashedIds = isset( $_POST['entry_ids'] ) ? wp_unslash( $_POST['entry_ids'] ) : [];
		$rawIds       = is_array( $unslashedIds ) ? $unslashedIds : [];
		$ids          = [];

		foreach ( $rawIds as $rawId ) {
			$id = (int) ( is_scalar( $rawId ) ? $rawId : 0 );

			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		if ( 'delete' !== $action || [] === $ids ) {
			$this->redirect( '&rk_error=bulk_none' );

			return;
		}

		$deleted = $this->repository->deleteMany( $ids );

		$this->redirect( '&rk_notice=bulk&rk_count=' . $deleted );
	}

	/**
	 * Handle the settings form save with clamped ranges.
	 */
	private function handleSettingsSave(): void {
		$this->requireAccess( self::NONCE_SETTINGS );

		$partial = [
			// Verified in requireAccess, checkbox presence is the value.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'advanced_fields' => isset( $_POST['rk_advanced_fields'] ),
			// Verified in requireAccess, checkbox presence is the value.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'ignore_query'    => isset( $_POST['rk_ignore_query'] ),
			'retention_days'  => max( 1, min( 365, $this->postInt( 'rk_retention_days', 30 ) ) ),
			'max_rows'        => max( 100, min( 10000, $this->postInt( 'rk_max_rows', 1000 ) ) ),
			'flood_budget'    => max( 1, min( 1000, $this->postInt( 'rk_flood_budget', 50 ) ) ),
			'flood_window'    => max( 60, min( 3600, $this->postInt( 'rk_flood_window', 300 ) ) ),
			'exclusions'      => $this->postedExclusions(),
		];

		$this->monitorSettings->set( $partial );

		$this->redirect( '&rk_notice=settings' );
	}

	/**
	 * Collect exclusion rows from POST, comparator plus value pairs.
	 *
	 * Every caller verifies its nonce in requireAccess first. Unknown
	 * comparators and empty values are skipped, values are capped in length
	 * and the row count stays bounded.
	 *
	 * @return array<int, array{comparator: string, value: string}>
	 */
	private function postedExclusions(): array {
		// Verified by the caller, values sanitized pair by pair below.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified by the caller, values sanitized pair by pair below, unslashed here, sanitized or validated on the following statements.
		$unslashedComparators = isset( $_POST['rk_excl_comparator'] ) ? wp_unslash( $_POST['rk_excl_comparator'] ) : [];

		// Verified by the caller, values sanitized pair by pair below.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified by the caller, values sanitized pair by pair below, unslashed here, sanitized or validated on the following statements.
		$unslashedValues = isset( $_POST['rk_excl_value'] ) ? wp_unslash( $_POST['rk_excl_value'] ) : [];

		$comparators = is_array( $unslashedComparators ) ? array_values( $unslashedComparators ) : [];
		$values      = is_array( $unslashedValues ) ? array_values( $unslashedValues ) : [];
		$count       = min( count( $comparators ), count( $values ), MonitorSettings::MAX_EXCLUSIONS );
		$clean       = [];

		for ( $i = 0; $i < $count; $i++ ) {
			$comparator = sanitize_key( (string) ( is_scalar( $comparators[ $i ] ) ? $comparators[ $i ] : '' ) );
			$value      = trim( sanitize_text_field( (string) ( is_scalar( $values[ $i ] ) ? $values[ $i ] : '' ) ) );

			if ( '' === $value || ! Exclusions::isComparator( $comparator ) ) {
				continue;
			}

			if ( strlen( $value ) > MonitorSettings::MAX_EXCLUSION_LENGTH ) {
				$value = (string) substr( $value, 0, MonitorSettings::MAX_EXCLUSION_LENGTH );
			}

			$clean[] = [
				'comparator' => $comparator,
				'value'      => $value,
			];
		}

		return $clean;
	}

	/**
	 * Build the bulk result message from the query flags.
	 *
	 * @return string Message text.
	 */
	private function bulkMessage(): string {
		// Read only display flags, value unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only display flags, value unslashed then cast to int below, unslashed here, cast to scalar on the following statement.
		$rawCount = isset( $_GET['rk_count'] ) ? wp_unslash( $_GET['rk_count'] ) : 0;
		$count    = max( 0, (int) ( is_scalar( $rawCount ) ? $rawCount : 0 ) );

		return sprintf(
			/* translators: %d: number of deleted 404 entries */
			__( 'Bulk delete finished for %d entries.', 'rankkernel' ),
			$count
		);
	}

	/**
	 * Current usage snapshot against the configured maximum.
	 *
	 * @return array{count: int, max: int, percent: float} Usage snapshot.
	 */
	private function usage(): array {
		$maxRows = max( 100, min( 10000, (int) $this->monitorSettings->get( 'max_rows', 1000 ) ) );

		return $this->repository->usage( $maxRows );
	}

	/**
	 * Most recently hit entry, for the summary recent activity stat.
	 *
	 * A single bounded row keeps the summary honest without a heavy query.
	 * Null when the log is empty.
	 *
	 * @return array<string, mixed>|null Newest row or null.
	 */
	private function recentActivity(): ?array {
		$result = $this->repository->paginate(
			[
				'orderby'  => 'last_accessed',
				'order'    => 'DESC',
				'page'     => 1,
				'per_page' => 1,
			]
		);

		$first = $result['rows'][0] ?? null;

		return is_array( $first ) ? $first : null;
	}

	/**
	 * Current list filters from the query string, sanitized and validated.
	 *
	 * @return array{search: string, orderby: string, order: string, page: int}
	 */
	private function listFilters(): array {
		// Read only display flags, every value sanitized below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['s'] ) ) : '';

		// Read only display flags, value validated below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only display flags, value validated below, unslashed here, sanitized on the following statement.
		$rawOrderBy = isset( $_GET['rk_orderby'] ) ? (string) wp_unslash( $_GET['rk_orderby'] ) : 'last_accessed';
		$orderby    = sanitize_key( $rawOrderBy );

		if ( ! array_key_exists( $orderby, self::SORTABLE ) && 'id' !== $orderby ) {
			$orderby = 'last_accessed';
		}

		// Read only display flags, value validated below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only display flags, value validated below, unslashed here, validated against an allow list below.
		$rawOrder = isset( $_GET['rk_order'] ) ? (string) wp_unslash( $_GET['rk_order'] ) : 'DESC';
		$order    = 'asc' === strtolower( $rawOrder ) ? 'ASC' : 'DESC';

		// Read only display flags, value unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only display flags, value unslashed then cast to int below, unslashed here, cast to scalar on the following statement.
		$rawPage = isset( $_GET['rk_paged'] ) ? wp_unslash( $_GET['rk_paged'] ) : 1;
		$page    = max( 1, (int) ( is_scalar( $rawPage ) ? $rawPage : 1 ) );

		return [
			'search'  => $search,
			'orderby' => $orderby,
			'order'   => $order,
			'page'    => $page,
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
	 * Comparator values to labels for the exclusions editor.
	 *
	 * @return array<string, string>
	 */
	private function exclusionOptions(): array {
		return [
			'exact'    => __( 'Exact', 'rankkernel' ),
			'prefix'   => __( 'Prefix', 'rankkernel' ),
			'contains' => __( 'Contains', 'rankkernel' ),
			'suffix'   => __( 'Suffix', 'rankkernel' ),
			'wildcard' => __( 'Wildcard', 'rankkernel' ),
		];
	}
}
