<?php
/**
 * 404 Monitor admin page, summary plus list plus settings.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\Monitor\Exclusions;
use RankKernel\Modules\Monitor\MonitorRepository;
use RankKernel\Modules\Monitor\MonitorSettings;
use RankKernel\Plugin;

/**
 * Renders the 404 Monitor dashboard and handles its saves.
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
	 */
	private MonitorRepository $repository;

	/**
	 * Module settings store.
	 */
	private MonitorSettings $monitorSettings;

	/**
	 * Shared module enable map.
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
		wp_register_script( 'rankkernel-monitor-admin', $js, [], $version, true );
		wp_enqueue_script( 'rankkernel-monitor-admin' );
	}

	/**
	 * Render the page.
	 */
	public function render(): void {
		$this->renderNotices();

		echo '<div class="wrap rk-monitor">';

		$this->renderHeader();
		$this->renderSummary();
		$this->renderNearLimit();
		$this->renderList();
		$this->renderSettings();

		echo '</div>';
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
	 * The Redirects form reads the rk_source query value on a fresh add, so
	 * the new rule passes through the normal redirect validation pipeline.
	 *
	 * @param string $uri Normalized 404 URI.
	 * @return string Redirects screen URL with the source attached.
	 */
	public function createRedirectUrl( string $uri ): string {
		$base = admin_url( 'admin.php?page=' . RedirectsPage::SLUG );

		return add_query_arg( [ 'rk_source' => $uri ], $base );
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$rawAction = isset( $_POST['rk_bulk_action'] ) ? (string) wp_unslash( $_POST['rk_bulk_action'] ) : '';
		$action    = sanitize_key( $rawAction );

		// Verified in requireAccess, values unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$unslashedComparators = isset( $_POST['rk_excl_comparator'] ) ? wp_unslash( $_POST['rk_excl_comparator'] ) : [];

		// Verified by the caller, values sanitized pair by pair below.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
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
	 * Render success and error notices.
	 */
	private function renderNotices(): void {
		// Read only display flags.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = isset( $_GET['rk_notice'] ) ? sanitize_key( (string) wp_unslash( $_GET['rk_notice'] ) ) : '';

		if ( '' !== $notice ) {
			$this->renderSuccessNotice( $notice );
		}

		// Read only display flags.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error = isset( $_GET['rk_error'] ) ? sanitize_key( (string) wp_unslash( $_GET['rk_error'] ) ) : '';

		if ( '' !== $error ) {
			$this->renderErrorNotice( $error );
		}
	}

	/**
	 * Render the success notice for a save flag.
	 *
	 * @param string $notice Notice flag from the query string.
	 */
	private function renderSuccessNotice( string $notice ): void {
		$message = '';

		switch ( $notice ) {
			case 'cleared':
				$message = __( '404 log cleared.', 'rankkernel' );
				break;
			case 'deleted':
				$message = __( '404 entry deleted.', 'rankkernel' );
				break;
			case 'settings':
				$message = __( 'Settings saved.', 'rankkernel' );
				break;
			case 'bulk':
				$message = $this->bulkMessage();
				break;
		}

		if ( '' !== $message ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}
	}

	/**
	 * Build the bulk result message from the query flags.
	 *
	 * @return string Message text.
	 */
	private function bulkMessage(): string {
		// Read only display flags, value unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rawCount = isset( $_GET['rk_count'] ) ? wp_unslash( $_GET['rk_count'] ) : 0;
		$count    = max( 0, (int) ( is_scalar( $rawCount ) ? $rawCount : 0 ) );

		return sprintf(
			/* translators: %d: number of deleted 404 entries */
			__( 'Bulk delete finished for %d entries.', 'rankkernel' ),
			$count
		);
	}

	/**
	 * Render the error notice for a failure flag.
	 *
	 * @param string $error Error flag from the query string.
	 */
	private function renderErrorNotice( string $error ): void {
		$messages = [
			'not_found'   => __( 'That 404 entry no longer exists.', 'rankkernel' ),
			'save_failed' => __( 'The action could not be completed. Please try again.', 'rankkernel' ),
			'bulk_none'   => __( 'Choose at least one entry and the delete action.', 'rankkernel' ),
		];

		if ( isset( $messages[ $error ] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $messages[ $error ] ) . '</p></div>';
		}
	}

	/**
	 * Render the page header.
	 */
	private function renderHeader(): void {
		echo '<h1 class="wp-heading-inline">' . esc_html__( '404 Monitor', 'rankkernel' ) . '</h1>';
		echo '<p class="rk-sub">';
		echo esc_html__( 'See which missing pages visitors hit, then turn the busy ones into redirects. Oldest entries prune automatically, and you can clear the log at any time.', 'rankkernel' );
		echo '</p>';
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
	 * Render the operational summary with usage, retention, and Clear Log.
	 */
	private function renderSummary(): void {
		$usage         = $this->usage();
		$count         = max( 0, (int) $usage['count'] );
		$max           = max( 1, (int) $usage['max'] );
		$percent       = min( 100.0, max( 0.0, (float) $usage['percent'] ) );
		$retentionDays = max( 1, min( 365, (int) $this->monitorSettings->get( 'retention_days', 30 ) ) );

		echo '<div class="rk-card rk-summary">';
		echo '<h2>' . esc_html__( 'Log Status', 'rankkernel' ) . '</h2>';

		echo '<div class="rk-stat-grid">';

		echo '<div class="rk-stat"><span class="rk-stat-label">' . esc_html__( 'Tracked addresses', 'rankkernel' ) . '</span>';
		echo '<span class="rk-stat-value">' . esc_html( (string) number_format_i18n( $count ) ) . '</span></div>';

		echo '<div class="rk-stat rk-stat-wide"><span class="rk-stat-label">' . esc_html__( 'Usage', 'rankkernel' ) . '</span>';
		echo '<span class="rk-stat-value">' . esc_html(
			sprintf(
				/* translators: %1$s: current entry count, %2$s: configured maximum */
				__( '%1$s / %2$s', 'rankkernel' ),
				number_format_i18n( $count ),
				number_format_i18n( $max )
			)
		) . '</span>';
		echo '<div class="rk-progress" role="progressbar" aria-valuenow="' . esc_attr( (string) $percent ) . '" aria-valuemin="0" aria-valuemax="100">';
		echo '<div class="rk-progress-fill" style="width:' . esc_attr( (string) $percent ) . '%"></div>';
		echo '</div></div>';

		echo '<div class="rk-stat"><span class="rk-stat-label">' . esc_html__( 'Retention', 'rankkernel' ) . '</span>';
		echo '<span class="rk-stat-value">' . esc_html(
			sprintf(
				/* translators: %d: retention period in days */
				__( '%d days', 'rankkernel' ),
				$retentionDays
			)
		) . '</span>';
		echo '<span class="rk-stat-hint">' . esc_html__( 'Entries older than this are removed automatically, oldest first.', 'rankkernel' ) . '</span></div>';

		echo '</div>';

		echo '<form method="post" action="' . esc_url( $this->pageUrl( [] ) ) . '" id="rk-clear-form" data-rk-confirm="'
			. esc_attr__( 'Clear the whole 404 log? This cannot be undone.', 'rankkernel' ) . '">';

		wp_nonce_field( self::NONCE_CLEAR );

		submit_button( __( 'Clear Log', 'rankkernel' ), 'secondary', 'rankkernel_404_clear', false );

		echo ' <span class="rk-sub">' . esc_html__( 'Manual clearing is separate from automatic pruning and removes entries in bounded batches.', 'rankkernel' ) . '</span>';
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Render the near limit notice when usage passes 80 percent.
	 */
	private function renderNearLimit(): void {
		$usage = $this->usage();
		$state = self::limitState( (float) $usage['percent'] );

		if ( 'normal' === $state ) {
			return;
		}

		$max = max( 1, (int) $usage['max'] );

		if ( 'high' === $state ) {
			echo '<div class="notice notice-warning"><p>' . esc_html(
				sprintf(
					/* translators: %s: configured maximum entry count */
					__( 'Your 404 log is almost at its configured entry limit of %s entries. The oldest entries are removed automatically when the limit is reached. You can clear the log manually at any time.', 'rankkernel' ),
					number_format_i18n( $max )
				)
			) . '</p></div>';

			return;
		}

		echo '<div class="notice notice-info"><p>' . esc_html(
			sprintf(
				/* translators: %s: configured maximum entry count */
				__( 'Your 404 log is approaching its configured entry limit of %s entries. The oldest entries are removed automatically when the limit is reached. You can clear the log manually at any time.', 'rankkernel' ),
				number_format_i18n( $max )
			)
		) . '</p></div>';
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rawOrderBy = isset( $_GET['rk_orderby'] ) ? (string) wp_unslash( $_GET['rk_orderby'] ) : 'last_accessed';
		$orderby    = sanitize_key( $rawOrderBy );

		if ( ! array_key_exists( $orderby, self::SORTABLE ) && 'id' !== $orderby ) {
			$orderby = 'last_accessed';
		}

		// Read only display flags, value validated below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rawOrder = isset( $_GET['rk_order'] ) ? (string) wp_unslash( $_GET['rk_order'] ) : 'DESC';
		$order    = 'asc' === strtolower( $rawOrder ) ? 'ASC' : 'DESC';

		// Read only display flags, value unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
	 * Render the 404 list with search, sorting, and pagination.
	 */
	private function renderList(): void {
		$filters = $this->listFilters();

		$result = $this->repository->paginate(
			[
				'search'   => $filters['search'],
				'orderby'  => $filters['orderby'],
				'order'    => $filters['order'],
				'page'     => $filters['page'],
				'per_page' => 20,
			]
		);

		$rows  = $result['rows'];
		$total = (int) $result['total'];
		$pages = (int) $result['pages'];
		$page  = max( 1, (int) $result['page'] );

		echo '<h2>' . esc_html__( 'Tracked 404s', 'rankkernel' ) . '</h2>';

		$this->renderSearch( $filters );
		$this->renderRedirectDependency();

		if ( [] === $rows ) {
			$this->renderEmptyState( $filters );

			return;
		}

		echo '<form method="post" action="' . esc_url( $this->pageUrl( [] ) ) . '" id="rk-bulk-form" data-rk-confirm="'
			. esc_attr__( 'Delete the selected entries? This cannot be undone.', 'rankkernel' ) . '">';

		wp_nonce_field( self::NONCE_BULK );

		echo '<div class="tablenav top"><div class="alignleft actions bulkactions">';
		echo '<select name="rk_bulk_action" id="rk-bulk-action">';
		echo '<option value="">' . esc_html__( 'Bulk actions', 'rankkernel' ) . '</option>';
		echo '<option value="delete">' . esc_html__( 'Delete', 'rankkernel' ) . '</option>';
		echo '</select> ';
		submit_button( __( 'Apply', 'rankkernel' ), 'action', 'rankkernel_404_bulk', false );
		echo '</div>';
		$this->renderPagination( $page, $pages, $filters, 'top' );
		echo '</div>';

		echo '<table class="wp-list-table widefat fixed striped rk-table">';
		echo '<thead><tr>';
		echo '<td class="manage-column column-cb check-column"><input type="checkbox" id="rk-select-all" /></td>';
		$this->renderSortableHeaders( $filters );
		echo '<th scope="col">' . esc_html__( 'Actions', 'rankkernel' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$this->renderRow( $row );
			}
		}

		echo '</tbody></table>';

		echo '<div class="tablenav bottom"><div class="alignleft actions bulkactions">';
		echo '<span class="displaying-num">' . esc_html(
			sprintf(
				/* translators: %d: total number of 404 entries */
				__( '%d items', 'rankkernel' ),
				$total
			)
		) . '</span>';
		echo '</div>';
		$this->renderPagination( $page, $pages, $filters, 'bottom' );
		echo '</div>';

		echo '</form>';
	}

	/**
	 * Render the list search box.
	 *
	 * @param array<string, mixed> $filters Current filters.
	 */
	private function renderSearch( array $filters ): void {
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="rk-filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		echo '<p class="search-box">';
		echo '<input type="search" name="s" value="' . esc_attr( (string) $filters['search'] ) . '" placeholder="'
			. esc_attr__( 'Search addresses', 'rankkernel' ) . '" />';
		submit_button( __( 'Search', 'rankkernel' ), '', '', false );
		echo '</p><br class="clear" />';
		echo '</form>';
	}

	/**
	 * Explain the Redirects dependency when the module is off.
	 *
	 * The per row Create Redirect action stays hidden in that case.
	 */
	private function renderRedirectDependency(): void {
		if ( $this->isRedirectsEnabled() ) {
			return;
		}

		echo '<div class="notice notice-info inline"><p>';
		echo esc_html__( 'Redirect creation needs the Redirects module. Enable Redirects to turn a 404 entry into a redirect.', 'rankkernel' );
		echo '</p></div>';
	}

	/**
	 * Render the empty state, helpful on first use and on empty searches.
	 *
	 * @param array<string, mixed> $filters Current filters.
	 */
	private function renderEmptyState( array $filters ): void {
		$hasFilter = '' !== (string) $filters['search'];

		echo '<div class="rk-empty">';

		if ( $hasFilter ) {
			echo '<p><strong>' . esc_html__( 'No 404 entries match your search.', 'rankkernel' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'Try a different search or clear it to see every tracked address.', 'rankkernel' ) . '</p>';
			echo '<p><a class="button" href="' . esc_url( $this->pageUrl( [] ) ) . '">';
			echo esc_html__( 'Clear search', 'rankkernel' );
			echo '</a></p>';
		} else {
			echo '<p><strong>' . esc_html__( 'No 404 entries are being tracked yet.', 'rankkernel' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'When visitors hit a missing page, its address appears here with hit counts and first and last seen times.', 'rankkernel' ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Render sortable column headers, preserving the current filters.
	 *
	 * @param array<string, mixed> $filters Current filters.
	 */
	private function renderSortableHeaders( array $filters ): void {
		foreach ( self::SORTABLE as $column => $label ) {
			$current = (string) $filters['orderby'] === $column;
			$next    = $current && 'ASC' === (string) $filters['order'] ? 'desc' : 'asc';
			$arrow   = $current ? ( 'ASC' === (string) $filters['order'] ? ' ↑' : ' ↓' ) : '';

			$url = $this->pageUrl(
				[
					's'          => $filters['search'],
					'rk_orderby' => $column,
					'rk_order'   => $next,
				]
			);

			echo '<th scope="col" class="manage-column sortable' . ( $current ? ' sorted' : '' ) . '">';
			echo '<a href="' . esc_url( $url ) . '"><span>' . esc_html( $label ) . esc_html( $arrow ) . '</span></a>';
			echo '</th>';
		}
	}

	/**
	 * Render one 404 row with its actions.
	 *
	 * @param array<string, mixed> $row Log row.
	 */
	private function renderRow( array $row ): void {
		$id      = (int) ( $row['id'] ?? 0 );
		$uri     = (string) ( $row['uri'] ?? '' );
		$hits    = max( 0, (int) ( $row['hits'] ?? 0 ) );
		$created = (string) ( $row['created'] ?? '' );
		$seen    = (string) ( $row['last_accessed'] ?? '' );

		$base      = admin_url( 'admin.php?page=' . self::SLUG );
		$deleteUrl = wp_nonce_url( $base . '&rk_action=delete&entry=' . $id, self::NONCE_ROW );

		echo '<tr>';
		echo '<th scope="row" class="check-column"><input type="checkbox" name="entry_ids[]" value="' . esc_attr( (string) $id ) . '" /></th>';

		echo '<td class="rk-col-uri"><strong>' . esc_html( $uri ) . '</strong>';
		echo '<div class="row-actions">';

		if ( $this->isRedirectsEnabled() ) {
			echo '<span class="create"><a href="' . esc_url( $this->createRedirectUrl( $uri ) ) . '">'
				. esc_html__( 'Create Redirect', 'rankkernel' ) . '</a> | </span>';
		}

		echo '<span class="trash"><a href="' . esc_url( $deleteUrl ) . '" class="rk-confirm" data-rk-confirm="'
			. esc_attr__( 'Delete this entry? This cannot be undone.', 'rankkernel' ) . '">'
			. esc_html__( 'Delete', 'rankkernel' ) . '</a></span>';
		echo '</div></td>';

		echo '<td class="rk-col-hits">' . esc_html( (string) number_format_i18n( $hits ) ) . '</td>';
		echo '<td class="rk-col-created">' . ( '' === $created ? esc_html__( 'Unknown', 'rankkernel' ) : esc_html( $created ) ) . '</td>';
		echo '<td class="rk-col-seen">' . ( '' === $seen ? esc_html__( 'Unknown', 'rankkernel' ) : esc_html( $seen ) ) . '</td>';

		echo '<td class="rk-col-actions">';

		if ( $this->isRedirectsEnabled() ) {
			echo '<a class="button button-small" href="' . esc_url( $this->createRedirectUrl( $uri ) ) . '">';
			echo esc_html__( 'Create Redirect', 'rankkernel' );
			echo '</a> ';
		}

		echo '<a class="button button-small rk-confirm" href="' . esc_url( $deleteUrl ) . '" data-rk-confirm="'
			. esc_attr__( 'Delete this entry? This cannot be undone.', 'rankkernel' ) . '">';
		echo esc_html__( 'Delete', 'rankkernel' );
		echo '</a>';

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Render pagination controls.
	 *
	 * @param int                  $page     Current page.
	 * @param int                  $pages    Total pages.
	 * @param array<string, mixed> $filters  Current filters.
	 * @param string               $position Top or bottom marker for styling.
	 */
	private function renderPagination( int $page, int $pages, array $filters, string $position ): void {
		if ( $pages <= 1 ) {
			return;
		}

		$base = [
			's'          => $filters['search'],
			'rk_orderby' => $filters['orderby'],
			'rk_order'   => strtolower( (string) $filters['order'] ),
		];

		echo '<div class="tablenav-pages rk-pages-' . esc_attr( $position ) . '">';
		echo '<span class="paging-text">' . esc_html(
			sprintf(
				/* translators: %1$d: current page, %2$d: total pages */
				__( 'Page %1$d of %2$d', 'rankkernel' ),
				$page,
				$pages
			)
		) . '</span> ';

		if ( $page > 1 ) {
			echo '<a class="button" href="' . esc_url( $this->pageUrl( array_merge( $base, [ 'rk_paged' => $page - 1 ] ) ) ) . '">';
			echo esc_html__( 'Previous', 'rankkernel' );
			echo '</a> ';
		}

		if ( $page < $pages ) {
			echo '<a class="button" href="' . esc_url( $this->pageUrl( array_merge( $base, [ 'rk_paged' => $page + 1 ] ) ) ) . '">';
			echo esc_html__( 'Next', 'rankkernel' );
			echo '</a>';
		}

		echo '</div>';
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

	/**
	 * Render the settings card.
	 */
	private function renderSettings(): void {
		$all         = $this->monitorSettings->all();
		$advanced    = ! empty( $all['advanced_fields'] );
		$ignoreQuery = ! empty( $all['ignore_query'] );
		$retention   = max( 1, min( 365, (int) ( $all['retention_days'] ?? 30 ) ) );
		$maxRows     = max( 100, min( 10000, (int) ( $all['max_rows'] ?? 1000 ) ) );
		$floodBudget = max( 1, min( 1000, (int) ( $all['flood_budget'] ?? 50 ) ) );
		$floodWindow = max( 60, min( 3600, (int) ( $all['flood_window'] ?? 300 ) ) );
		$exclusions  = $this->monitorSettings->getExclusions();
		$comparators = $this->exclusionOptions();

		echo '<div class="rk-card" id="rk-monitor-settings">';
		echo '<h2>' . esc_html__( 'Monitor Settings', 'rankkernel' ) . '</h2>';

		echo '<form method="post" action="">';

		wp_nonce_field( self::NONCE_SETTINGS );

		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'Advanced fields', 'rankkernel' ) . '</th><td>';
		echo '<label><input type="checkbox" name="rk_advanced_fields" value="1" ' . checked( $advanced, true, false ) . ' /> ';
		echo esc_html__( 'Store the referer and user agent with each entry.', 'rankkernel' );
		echo '</label>';
		echo '<p class="description">';
		echo esc_html__( 'Optional and off by default. Values are truncated to 255 characters. IP addresses are never stored.', 'rankkernel' );
		echo '</p></td></tr>';

		echo '<tr><th scope="row"><label for="rk-retention">' . esc_html__( 'Retention days', 'rankkernel' ) . '</label></th><td>';
		echo '<input type="number" id="rk-retention" name="rk_retention_days" value="' . esc_attr( (string) $retention ) . '" class="small-text" min="1" max="365" />';
		echo '<p class="description">';
		echo esc_html__( 'Entries older than this many days are removed automatically, from 1 to 365.', 'rankkernel' );
		echo '</p></td></tr>';

		echo '<tr><th scope="row"><label for="rk-max-rows">' . esc_html__( 'Maximum entries', 'rankkernel' ) . '</label></th><td>';
		echo '<input type="number" id="rk-max-rows" name="rk_max_rows" value="' . esc_attr( (string) $maxRows ) . '" class="small-text" min="100" max="10000" />';
		echo '<p class="description">';
		echo esc_html__( 'Maximum entries kept in the log, from 100 to 10000. When the limit is reached, the oldest entries are removed first.', 'rankkernel' );
		echo '</p></td></tr>';

		echo '<tr><th scope="row"><label for="rk-flood-budget">' . esc_html__( 'Flood budget', 'rankkernel' ) . '</label></th><td>';
		echo '<input type="number" id="rk-flood-budget" name="rk_flood_budget" value="' . esc_attr( (string) $floodBudget ) . '" class="small-text" min="1" max="1000" />';
		echo '<p class="description">';
		echo esc_html__( 'New addresses allowed per time window, from 1 to 1000. Repeat hits on known addresses always keep counting.', 'rankkernel' );
		echo '</p></td></tr>';

		echo '<tr><th scope="row"><label for="rk-flood-window">' . esc_html__( 'Flood window', 'rankkernel' ) . '</label></th><td>';
		echo '<input type="number" id="rk-flood-window" name="rk_flood_window" value="' . esc_attr( (string) $floodWindow ) . '" class="small-text" min="60" max="3600" />';
		echo '<p class="description">';
		echo esc_html__( 'Length of the flood window in seconds, from 60 to 3600.', 'rankkernel' );
		echo '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Query strings', 'rankkernel' ) . '</th><td>';
		echo '<label><input type="checkbox" name="rk_ignore_query" value="1" ' . checked( $ignoreQuery, true, false ) . ' /> ';
		echo esc_html__( 'Ignore the query string when logging.', 'rankkernel' );
		echo '</label>';
		echo '<p class="description">';
		echo esc_html__( 'On by default. Turn off to track each query string as a separate entry.', 'rankkernel' );
		echo '</p></td></tr>';

		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'Exclusions', 'rankkernel' ) . '</h3>';
		echo '<p class="rk-sub">';
		echo esc_html__( 'Skip logging for addresses that match a rule. Choose how to compare, then enter the value. Matching is case sensitive.', 'rankkernel' );
		echo '</p>';

		echo '<table class="widefat striped rk-exclusions"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Compare', 'rankkernel' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Value', 'rankkernel' ) . '</th>';
		echo '</tr></thead><tbody>';

		/** @var array<int, array<string, mixed>> $rows */
		$rows = array_merge(
			$exclusions,
			[
				[
					'comparator' => 'prefix',
					'value'      => '',
				],
				[
					'comparator' => 'prefix',
					'value'      => '',
				],
			]
		);

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$comparator = (string) ( $row['comparator'] ?? 'prefix' );
			$value      = (string) ( $row['value'] ?? '' );

			echo '<tr><td>';
			echo '<select name="rk_excl_comparator[]">';

			foreach ( $comparators as $option => $label ) {
				echo '<option value="' . esc_attr( $option ) . '"' . selected( $comparator, $option, false ) . '>';
				echo esc_html( $label );
				echo '</option>';
			}

			echo '</select></td>';
			echo '<td><input type="text" name="rk_excl_value[]" value="' . esc_attr( $value ) . '" class="regular-text code" maxlength="500" /></td></tr>';
		}

		echo '</tbody></table>';

		submit_button( __( 'Save Monitor Settings', 'rankkernel' ), 'secondary', 'rankkernel_404_settings_save' );

		echo '</form>';
		echo '</div>';
	}
}
