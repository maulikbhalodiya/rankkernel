<?php
/**
 * Redirects admin page, form plus list plus settings.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

use RankKernel\Modules\Redirects\CsvHandler;
use RankKernel\Modules\Redirects\DestinationValidator;
use RankKernel\Modules\Redirects\Normalizer;
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
		'source'        => 'From',
		'target'        => 'To',
		'code'          => 'Code',
		'match_type'    => 'Match',
		'hits'          => 'Hits',
		'last_accessed' => 'Last Accessed',
	];

	/**
	 * Rule repository.
	 */
	private RedirectRepository $repository;

	/**
	 * Module settings store.
	 */
	private RedirectsSettings $redirectSettings;

	/**
	 * Loop and chain analyzer.
	 */
	private Validator $validator;

	/**
	 * Destination policy checker.
	 */
	private DestinationValidator $destinationValidator;

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
	 * @param RedirectRepository|null   $repository           Rule repository, fresh one when null.
	 * @param RedirectsSettings|null    $redirectSettings     Settings store, fresh one when null.
	 * @param Validator|null            $validator            Safety analyzer, fresh one when null.
	 * @param DestinationValidator|null $destinationValidator Destination checker, fresh one when null.
	 */
	public function __construct(
		?RedirectRepository $repository = null,
		?RedirectsSettings $redirectSettings = null,
		?Validator $validator = null,
		?DestinationValidator $destinationValidator = null
	) {
		$this->repository           = $repository ?? new RedirectRepository();
		$this->redirectSettings     = $redirectSettings ?? new RedirectsSettings();
		$this->validator            = $validator ?? new Validator();
		$this->destinationValidator = $destinationValidator ?? new DestinationValidator();
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
		wp_register_script( 'rankkernel-redirects-admin', $js, [], $version, true );
		wp_enqueue_script( 'rankkernel-redirects-admin' );
	}

	/**
	 * Render the page.
	 */
	public function render(): void {
		$this->renderNotices();

		echo '<div class="wrap rk-redirects">';

		$this->renderHeader();
		$this->renderEditor();
		$this->renderList();
		$this->renderImportExport();
		$this->renderSettings();

		echo '</div>';
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$rawAction = isset( $_POST['rk_bulk_action'] ) ? (string) wp_unslash( $_POST['rk_bulk_action'] ) : '';
		$action    = sanitize_key( $rawAction );

		// Verified in requireAccess, values unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$rawPerPage                = isset( $_POST['rk_rules_per_page'] ) ? wp_unslash( $_POST['rk_rules_per_page'] ) : 20;
		$partial['rules_per_page'] = max( 1, min( 100, (int) ( is_scalar( $rawPerPage ) ? $rawPerPage : 20 ) ) );

		$this->redirectSettings->set( $partial );

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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
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

		$tmp = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';

		if ( '' === $tmp || ! is_readable( $tmp ) ) {
			$this->importResult = $this->importFileError( __( 'The uploaded file could not be read.', 'rankkernel' ) );

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
	 * Render success, warning, information, and error notices.
	 */
	private function renderNotices(): void {
		if ( $this->hasFormAttempt && [] !== $this->formErrors ) {
			$blocked = $this->formErrors['blocked'] ?? '';

			if ( '' === $blocked ) {
				$blocked = __( 'Please fix the highlighted fields and try again.', 'rankkernel' );
			}

			echo '<div class="notice notice-error"><p>' . esc_html( $blocked ) . '</p></div>';
		}

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

		$this->renderAnalysisNotices();
	}

	/**
	 * Render the success notice for a save flag.
	 *
	 * @param string $notice Notice flag from the query string.
	 */
	private function renderSuccessNotice( string $notice ): void {
		$message = '';

		switch ( $notice ) {
			case 'saved':
				$message = __( 'Redirect saved.', 'rankkernel' );
				break;
			case 'updated':
				$message = __( 'Redirect updated.', 'rankkernel' );
				break;
			case 'deleted':
				$message = __( 'Redirect deleted.', 'rankkernel' );
				break;
			case 'activated':
				$message = __( 'Redirect activated.', 'rankkernel' );
				break;
			case 'deactivated':
				$message = __( 'Redirect deactivated.', 'rankkernel' );
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
		// Read only display flags.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rawBulk = isset( $_GET['rk_bulk'] ) ? (string) wp_unslash( $_GET['rk_bulk'] ) : '';
		$bulk    = sanitize_key( $rawBulk );

		// Read only display flags, value unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
	 * Render the error notice for a failure flag.
	 *
	 * @param string $error Error flag from the query string.
	 */
	private function renderErrorNotice( string $error ): void {
		$messages = [
			'not_found'   => __( 'That redirect no longer exists.', 'rankkernel' ),
			'save_failed' => __( 'The action could not be completed. Please try again.', 'rankkernel' ),
			'bulk_none'   => __( 'Choose at least one redirect and a bulk action.', 'rankkernel' ),
		];

		if ( 'loop' === $error ) {
			// Read only display flag, sanitized and escaped below.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$rawPath = isset( $_GET['rk_path'] ) ? (string) wp_unslash( $_GET['rk_path'] ) : '';
			$path    = sanitize_text_field( $rawPath );

			echo '<div class="notice notice-error"><p>';
			echo esc_html(
				sprintf(
					/* translators: %s: redirect chain path showing the loop */
					__( 'This redirect would create a redirect loop: %s. The rule was not saved.', 'rankkernel' ),
					$path
				)
			);
			echo '</p></div>';

			return;
		}

		if ( isset( $messages[ $error ] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $messages[ $error ] ) . '</p></div>';
		}
	}

	/**
	 * Render chain warnings and inconclusive information notices.
	 *
	 * A chain always saves with a warning, never a block. When the analysis
	 * is inconclusive the notice says plainly that the final destination is
	 * unknown and offers no recommendation, so uncertainty is never
	 * presented as safe.
	 */
	private function renderAnalysisNotices(): void {
		// Read only display flags, sanitized and escaped below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rawChain = isset( $_GET['rk_chain'] ) ? (string) wp_unslash( $_GET['rk_chain'] ) : '';
		$chain    = sanitize_text_field( $rawChain );

		// Read only display flags.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$chainUnknown = isset( $_GET['rk_chain_unknown'] );

		if ( '' !== $chain ) {
			// Read only display flag, sanitized and escaped below.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$rawFinal = isset( $_GET['rk_final'] ) ? (string) wp_unslash( $_GET['rk_final'] ) : '';
			$final    = sanitize_text_field( $rawFinal );

			echo '<div class="notice notice-warning is-dismissible"><p>';
			echo esc_html(
				sprintf(
					/* translators: %s: redirect chain path */
					__( 'Redirect chain detected: %s.', 'rankkernel' ),
					$chain
				)
			);

			if ( $chainUnknown || '' === $final ) {
				echo ' ';
				echo esc_html__( 'RankKernel could not determine the final destination, so please verify the chain manually. Saved as entered.', 'rankkernel' );
			} else {
				echo ' ';
				echo esc_html(
					sprintf(
						/* translators: %s: recommended final destination */
						__( 'Consider pointing the source directly to %s.', 'rankkernel' ),
						$final
					)
				);
				echo ' ';
				echo '<button type="button" class="button button-small" data-rk-use-destination="' . esc_attr( $final ) . '">';
				echo esc_html__( 'Use recommended destination', 'rankkernel' );
				echo '</button>';
			}

			echo '</p></div>';
		}

		// Read only display flags.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rk_mayloop'] ) ) {
			echo '<div class="notice notice-warning is-dismissible"><p>';
			echo esc_html__( 'The loop check could not fully verify this redirect, so a loop is still possible. Please verify it manually.', 'rankkernel' );
			echo '</p></div>';
		}

		// Read only display flags. Shown only without a chain path, the chain
		// branch above already carries the unknown wording when both appear.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $chainUnknown && '' === $chain ) {
			echo '<div class="notice notice-info is-dismissible"><p>';
			echo esc_html__( 'Chain analysis could not determine the final destination because the next rule uses a pattern matcher. Saved as entered.', 'rankkernel' );
			echo '</p></div>';
		}
	}

	/**
	 * Render the page header with the primary action.
	 *
	 * The Add Redirect control toggles the collapsible editor below. The
	 * link target opens the editor server side, so the flow works without
	 * JavaScript, while the script turns it into an instant toggle.
	 */
	private function renderHeader(): void {
		$open     = $this->isEditorOpen();
		$toggle   = $this->pageUrl( [ 'rk_open' => 1 ] );
		$expanded = $open ? 'true' : 'false';

		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Redirects', 'rankkernel' ) . '</h1>';
		echo ' <a href="' . esc_url( $toggle ) . '" class="page-title-action" id="rk-add-toggle" aria-expanded="' . esc_attr( $expanded ) . '" aria-controls="rk-redirect-editor">';
		echo esc_html__( 'Add Redirect', 'rankkernel' );
		echo '</a>';
		echo '<p class="rk-sub">';
		echo esc_html__( 'Send visitors from old addresses to new ones. Loops are blocked at save, chains save with a warning.', 'rankkernel' );
		echo '</p>';
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rawOpen = isset( $_GET['rk_open'] ) ? (string) wp_unslash( $_GET['rk_open'] ) : '';

		if ( '1' === $rawOpen ) {
			return true;
		}

		// Read only display flags, value unslashed then cast to int below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
	 * Render the collapsible add and edit editor.
	 *
	 * One container serves both add and edit, so there is a single form to
	 * learn. Closed it hides completely and shows only the list. Basic
	 * fields render first, Advanced options stays collapsed, and only the
	 * controls relevant to the current match type and code render open.
	 */
	private function renderEditor(): void {
		$values   = $this->formValues();
		$editId   = (int) ( $values['rule_id'] ?? 0 );
		$isEdit   = $editId > 0;
		$match    = (string) ( $values['match_type'] ?? 'exact' );
		$code     = (string) ( $values['code'] ?? '301' );
		$open     = $this->isEditorOpen();
		$matches  = $this->matchOptions();
		$codes    = $this->codeOptions();
		$terminal = in_array( $code, Normalizer::TERMINAL_CODES, true );
		$isRegex  = 'regex' === $match;
		$rawBack  = $values['return_to'] ?? '';
		$returnTo = is_string( $rawBack ) ? $rawBack : '';

		echo '<div class="rk-card rk-editor" id="rk-redirect-editor"' . ( $open ? '' : ' hidden' ) . '>';
		echo '<h2 id="rk-editor-heading">' . esc_html( $isEdit ? __( 'Edit Redirect', 'rankkernel' ) : __( 'Add Redirect', 'rankkernel' ) ) . '</h2>';

		if ( '' !== $returnTo && ! $isEdit ) {
			echo '<p class="rk-prefill">';
			echo esc_html__( 'Source prefilled from the 404 Monitor. Add a destination and save to return to the monitor.', 'rankkernel' );
			echo '</p>';
		}

		echo '<form method="post" action="" aria-labelledby="rk-editor-heading">';

		wp_nonce_field( self::NONCE_SAVE );

		if ( $isEdit ) {
			echo '<input type="hidden" name="rule_id" value="' . esc_attr( (string) $editId ) . '" />';
		}

		if ( '' !== $returnTo ) {
			echo '<input type="hidden" name="rk_return" value="' . esc_attr( $returnTo ) . '" />';
		}

		echo '<table class="form-table" role="presentation"><tbody>';

		$this->renderSourceField( (string) ( $values['source'] ?? '' ) );
		$this->renderMatchField( $match, $matches );
		$this->renderRegexField( (string) ( $values['source'] ?? '' ), $isRegex );
		$this->renderTargetField( (string) ( $values['target'] ?? '' ), $terminal );
		$this->renderCodeField( $code, $codes, $terminal );

		echo '<tr><th scope="row">' . esc_html__( 'Active', 'rankkernel' ) . '</th><td>';
		echo '<label><input type="checkbox" name="rk_active" value="1" ' . checked( ! empty( $values['is_active'] ), true, false ) . ' /> ';
		echo esc_html__( 'Send visitors now. Turn off to keep the rule saved without redirecting.', 'rankkernel' );
		echo '</label></td></tr>';
		echo '</tbody></table>';

		$this->renderAdvancedOptions();

		submit_button(
			$isEdit ? __( 'Update Redirect', 'rankkernel' ) : __( 'Add Redirect', 'rankkernel' ),
			'primary',
			'rankkernel_redirect_save'
		);

		if ( '' !== $returnTo ) {
			$cancel = admin_url( 'admin.php?page=' . NotFoundPage::SLUG );
		} else {
			$cancel = $this->pageUrl( [] );
		}

		echo ' <a class="button" href="' . esc_url( $cancel ) . '">';
		echo esc_html__( 'Cancel', 'rankkernel' );
		echo '</a>';

		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render the source URL field with fragment guidance.
	 *
	 * @param string $value Current source value.
	 */
	private function renderSourceField( string $value ): void {
		$described = 'rk-source-hint' . ( isset( $this->formErrors['source'] ) ? ' rk-source-error' : '' );

		echo '<tr><th scope="row"><label for="rk-source">' . esc_html__( 'Source URL', 'rankkernel' ) . '</label></th><td>';
		echo '<input type="text" id="rk-source" name="rk_source" value="' . esc_attr( $value ) . '" class="regular-text code" aria-describedby="' . esc_attr( $described ) . '" />';
		$this->fieldError( 'source' );
		echo '<p class="description" id="rk-source-hint" data-fragment="'
			. esc_attr__( 'Remove the part starting with #. Fragments stay in the browser and are never sent to the server.', 'rankkernel' ) . '">';
		echo esc_html__( 'Enter the old path, for example /old page. The query string is ignored when matching.', 'rankkernel' );
		echo '</p></td></tr>';
	}

	/**
	 * Render the match type field with a live short meaning.
	 *
	 * Every option carries its meaning as a data attribute, so the hint
	 * below updates instantly while the server renders the current one for
	 * the no script path.
	 *
	 * @param string                $current Current match type.
	 * @param array<string, string> $matches Match values to labels.
	 */
	private function renderMatchField( string $current, array $matches ): void {
		$hints = $this->matchHints();

		echo '<tr><th scope="row"><label for="rk-match">' . esc_html__( 'Match type', 'rankkernel' ) . '</label></th><td>';
		echo '<select id="rk-match" name="rk_match_type" aria-describedby="rk-match-hint">';

		foreach ( $matches as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false )
				. ' data-hint="' . esc_attr( $hints[ $value ] ?? '' ) . '">';
			echo esc_html( $label );
			echo '</option>';
		}

		echo '</select>';
		$this->fieldError( 'match_type' );
		echo '<p class="description" id="rk-match-hint">' . esc_html( $hints[ $current ] ?? '' ) . '</p>';
		echo '</td></tr>';
	}

	/**
	 * Render the regex validation row, open only for the regex matcher.
	 *
	 * The feedback element carries its messages as data attributes, so the
	 * client script stays free of hardcoded strings while the server
	 * renders the same state for the no script path.
	 *
	 * @param string $value   Current source value.
	 * @param bool   $isRegex Whether the regex matcher is selected.
	 */
	private function renderRegexField( string $value, bool $isRegex ): void {
		$state = $this->regexState( $value );
		$class = $state['ok'] ? 'rk-regex-ok' : 'rk-regex-bad';

		echo '<tr id="rk-regex-row"' . ( $isRegex ? '' : ' hidden' ) . '><th scope="row">'
			. esc_html__( 'Pattern check', 'rankkernel' ) . '</th><td>';
		echo '<p class="' . esc_attr( $class ) . '" id="rk-regex-feedback" role="status"'
			. ' data-msg-empty="' . esc_attr__( 'Enter a pattern to check it. Patterns are limited to 200 characters.', 'rankkernel' ) . '"'
			. ' data-msg-long="' . esc_attr__( 'That pattern is too long. Please keep regex patterns under 200 characters.', 'rankkernel' ) . '"'
			. ' data-msg-invalid="' . esc_attr__( 'That pattern does not compile. Check the syntax and try again.', 'rankkernel' ) . '"'
			. ' data-msg-valid="' . esc_attr__( 'Pattern compiles cleanly.', 'rankkernel' ) . '"'
			. ' data-msg-anchor="' . esc_attr__( 'Tip: add ^ at the start and $ at the end to match the whole path.', 'rankkernel' ) . '">';
		echo esc_html( $state['message'] );
		echo '</p>';
		echo '<p class="description" id="rk-regex-help">';
		echo esc_html__( 'Full pattern match for advanced use, limited to 200 characters. Anchor with ^ and $ when the whole path must match, for example ^/blog/[0-9]+$.', 'rankkernel' );
		echo '</p></td></tr>';
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
	 * Render the destination field, hidden and disabled for terminal codes.
	 *
	 * @param string $value    Current destination value.
	 * @param bool   $terminal Whether the selected code needs no destination.
	 */
	private function renderTargetField( string $value, bool $terminal ): void {
		$described = 'rk-target-hint' . ( isset( $this->formErrors['target'] ) ? ' rk-target-error' : '' );

		echo '<tr id="rk-target-row"' . ( $terminal ? ' hidden' : '' ) . '><th scope="row"><label for="rk-target">'
			. esc_html__( 'Destination URL', 'rankkernel' ) . '</label></th><td>';
		echo '<input type="text" id="rk-target" name="rk_target" value="' . esc_attr( $value ) . '" class="regular-text code"'
			. ' aria-describedby="' . esc_attr( $described ) . '"' . ( $terminal ? ' disabled' : '' ) . ' />';
		$this->fieldError( 'target' );
		echo '<p class="description" id="rk-target-hint">';
		echo esc_html__( 'Enter where visitors should go, for example /new page. Leave empty only for 410 and 451.', 'rankkernel' );
		echo '</p></td></tr>';
	}

	/**
	 * Render the redirect type field with a live short meaning.
	 *
	 * @param string             $code     Current status code.
	 * @param array<int, string> $codes    Code values to labels.
	 * @param bool               $terminal Whether the selected code is terminal.
	 */
	private function renderCodeField( string $code, array $codes, bool $terminal ): void {
		$hints = $this->codeHints();

		echo '<tr><th scope="row"><label for="rk-code">' . esc_html__( 'Redirect type', 'rankkernel' ) . '</label></th><td>';
		echo '<select id="rk-code" name="rk_code" aria-describedby="rk-code-hint rk-terminal-note">';

		foreach ( $codes as $value => $label ) {
			$codeValue = (string) $value;

			echo '<option value="' . esc_attr( $codeValue ) . '"' . selected( $code, $codeValue, false )
				. ' data-hint="' . esc_attr( (string) ( $hints[ $value ] ?? '' ) ) . '">';
			echo esc_html( $label );
			echo '</option>';
		}

		echo '</select>';
		$this->fieldError( 'code' );
		echo '<p class="description" id="rk-code-hint">' . esc_html( (string) ( $hints[ (int) $code ] ?? $hints[ $code ] ?? '' ) ) . '</p>';
		echo '<p class="description" id="rk-terminal-note"' . ( $terminal ? '' : ' hidden' ) . '>';
		echo esc_html__( '410 Gone and 451 mean the content is intentionally unavailable, so no destination is needed. The destination field stays disabled while one of these is selected.', 'rankkernel' );
		echo '</p></td></tr>';
	}

	/**
	 * Render the collapsed Advanced options disclosure.
	 *
	 * Holds query behavior, the pattern cap, and the full matcher and code
	 * explanations, so the basic form stays short while nothing is lost.
	 */
	private function renderAdvancedOptions(): void {
		$all      = $this->redirectSettings->all();
		$preserve = ! empty( $all['preserve_query'] );

		echo '<details class="rk-advanced"><summary>';
		echo esc_html__( 'Advanced options', 'rankkernel' );
		echo '</summary>';

		echo '<p class="rk-sub">';

		if ( $preserve ) {
			echo esc_html__( 'Matching ignores the query string, and the query string is currently passed to the destination. Change this under Redirect Settings below.', 'rankkernel' );
		} else {
			echo esc_html__( 'Matching ignores the query string, and the query string is currently dropped. Change this under Redirect Settings below.', 'rankkernel' );
		}

		echo '</p>';

		echo '<p class="rk-sub">';
		echo esc_html__( 'Pattern matchers (everything except Exact) are limited in how many active rules they can hold. Exact matches are unlimited and fastest.', 'rankkernel' );
		echo '</p>';

		$matches = $this->matchOptions();

		echo '<details class="rk-hints"><summary>';
		echo esc_html__( 'What do the match types mean', 'rankkernel' );
		echo '</summary><ul>';

		foreach ( $this->matchHints() as $value => $hint ) {
			echo '<li><strong>' . esc_html( $matches[ $value ] ?? $value ) . '</strong> ';
			echo esc_html( $hint ) . '</li>';
		}

		echo '</ul></details>';

		$codes = $this->codeOptions();

		echo '<details class="rk-hints"><summary>';
		echo esc_html__( 'Which redirect type should I use', 'rankkernel' );
		echo '</summary><ul>';

		foreach ( $this->codeHints() as $value => $hint ) {
			echo '<li><strong>' . esc_html( (string) ( $codes[ $value ] ?? $value ) ) . '</strong> ';
			echo esc_html( $hint ) . '</li>';
		}

		echo '</ul></details>';
		echo '</details>';
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = isset( $_GET['rk_source'] ) ? wp_unslash( $_GET['rk_source'] ) : '';

		return is_string( $raw ) ? sanitize_text_field( $raw ) : '';
	}

	/**
	 * Render one inline field error, linked from the field by describedby.
	 *
	 * @param string $key Field key.
	 */
	private function fieldError( string $key ): void {
		if ( isset( $this->formErrors[ $key ] ) ) {
			echo '<p class="rk-field-error" id="rk-' . esc_attr( $key ) . '-error" role="alert">' . esc_html( $this->formErrors[ $key ] ) . '</p>';
		}
	}

	/**
	 * Current list filters from the query string, sanitized and validated.
	 *
	 * @return array{search: string, status: string, match_type: string, code: string, orderby: string, order: string, page: int}
	 */
	private function listFilters(): array {
		// Read only display flags, every value sanitized below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['s'] ) ) : '';

		// Read only display flags, value validated below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rawStatus = isset( $_GET['rk_status'] ) ? (string) wp_unslash( $_GET['rk_status'] ) : 'all';
		$status    = sanitize_key( $rawStatus );

		if ( ! in_array( $status, [ 'all', 'active', 'inactive' ], true ) ) {
			$status = 'all';
		}

		// Read only display flags, value validated below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rawMatch = isset( $_GET['rk_match'] ) ? (string) wp_unslash( $_GET['rk_match'] ) : '';
		$match    = sanitize_key( $rawMatch );

		if ( '' !== $match && ! Normalizer::isMatchType( $match ) ) {
			$match = '';
		}

		// Read only display flags, value validated below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rawCode = isset( $_GET['rk_code'] ) ? (string) wp_unslash( $_GET['rk_code'] ) : '';
		$code    = sanitize_key( $rawCode );

		if ( '' !== $code && ! Normalizer::isCode( $code ) ) {
			$code = '';
		}

		// Read only display flags, value validated below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rawOrderBy = isset( $_GET['rk_orderby'] ) ? (string) wp_unslash( $_GET['rk_orderby'] ) : 'id';
		$orderby    = sanitize_key( $rawOrderBy );

		if ( ! array_key_exists( $orderby, self::SORTABLE ) && 'id' !== $orderby ) {
			$orderby = 'id';
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
			'search'     => $search,
			'status'     => $status,
			'match_type' => $match,
			'code'       => $code,
			'orderby'    => $orderby,
			'order'      => $order,
			'page'       => $page,
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
	 * Render the redirect list with views, filters, sorting, and pagination.
	 */
	private function renderList(): void {
		$filters = $this->listFilters();
		$perPage = $this->rulesPerPage();

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

		$rows  = $result['rows'];
		$total = (int) $result['total'];
		$pages = (int) $result['pages'];
		$page  = max( 1, (int) $result['page'] );

		echo '<h2>' . esc_html__( 'All Redirects', 'rankkernel' ) . '</h2>';

		$this->renderViews( $filters, $total, (int) $result['active'], (int) $result['inactive'] );
		$this->renderFilters( $filters );

		if ( [] === $rows ) {
			$this->renderEmptyState( $filters );

			return;
		}

		echo '<form method="post" action="' . esc_url( $this->pageUrl( [] ) ) . '" id="rk-bulk-form" data-rk-confirm="'
			. esc_attr__( 'Delete the selected redirects? This cannot be undone.', 'rankkernel' ) . '">';

		wp_nonce_field( self::NONCE_BULK );

		echo '<div class="tablenav top"><div class="alignleft actions bulkactions">';
		echo '<select name="rk_bulk_action" id="rk-bulk-action">';
		echo '<option value="">' . esc_html__( 'Bulk actions', 'rankkernel' ) . '</option>';
		echo '<option value="activate">' . esc_html__( 'Activate', 'rankkernel' ) . '</option>';
		echo '<option value="deactivate">' . esc_html__( 'Deactivate', 'rankkernel' ) . '</option>';
		echo '<option value="delete">' . esc_html__( 'Delete', 'rankkernel' ) . '</option>';
		echo '</select> ';
		submit_button( __( 'Apply', 'rankkernel' ), 'action', 'rankkernel_redirect_bulk', false );
		echo '</div>';
		$this->renderPagination( $page, $pages, $filters, 'top' );
		echo '</div>';

		echo '<table class="wp-list-table widefat fixed striped rk-table">';
		echo '<thead><tr>';
		echo '<td class="manage-column column-cb check-column"><input type="checkbox" id="rk-select-all" /></td>';
		$this->renderSortableHeaders( $filters );
		echo '<th scope="col" class="rk-col-status">' . esc_html__( 'Status', 'rankkernel' ) . '</th>';
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
				/* translators: %d: total number of redirects */
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
	 * Rows per page from settings, clamped to 1 to 100.
	 *
	 * @return int Rows per page.
	 */
	private function rulesPerPage(): int {
		$perPage = (int) $this->redirectSettings->get( 'rules_per_page', 20 );

		return max( 1, min( 100, $perPage ) );
	}

	/**
	 * Render the All, Active, and Inactive status views.
	 *
	 * @param array<string, mixed> $filters  Current filters.
	 * @param int                  $total    Total rows under the other filters.
	 * @param int                  $active   Active rows under the other filters.
	 * @param int                  $inactive Inactive rows under the other filters.
	 */
	private function renderViews( array $filters, int $total, int $active, int $inactive ): void {
		$views = [
			'all'      => [ __( 'All', 'rankkernel' ), $total ],
			'active'   => [ __( 'Active', 'rankkernel' ), $active ],
			'inactive' => [ __( 'Inactive', 'rankkernel' ), $inactive ],
		];

		echo '<ul class="subsubsub">';

		$first = true;

		foreach ( $views as $status => $view ) {
			$params = [
				's'         => $filters['search'],
				'rk_status' => 'all' === $status ? '' : $status,
				'rk_match'  => $filters['match_type'],
				'rk_code'   => $filters['code'],
			];

			$class = (string) $filters['status'] === (string) $status ? ' class="current"' : '';

			echo ( $first ? '' : ' | ' ) . '<li><a href="' . esc_url( $this->pageUrl( $params ) ) . '"' . esc_attr( $class ) . '>';
			echo esc_html( $view[0] ) . ' <span class="count">(' . esc_html( (string) $view[1] ) . ')</span>';
			echo '</a></li>';

			$first = false;
		}

		echo '</ul><br class="clear" />';
	}

	/**
	 * Render the search plus match type plus code filters.
	 *
	 * @param array<string, mixed> $filters Current filters.
	 */
	private function renderFilters( array $filters ): void {
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="rk-filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		echo '<p class="search-box">';
		echo '<input type="search" name="s" value="' . esc_attr( (string) $filters['search'] ) . '" placeholder="'
			. esc_attr__( 'Search redirects', 'rankkernel' ) . '" />';
		submit_button( __( 'Search', 'rankkernel' ), '', '', false );
		echo '</p>';

		echo '<div class="alignleft actions">';
		echo '<select name="rk_status">';
		echo '<option value="all"' . selected( $filters['status'], 'all', false ) . '>' . esc_html__( 'All statuses', 'rankkernel' ) . '</option>';
		echo '<option value="active"' . selected( $filters['status'], 'active', false ) . '>' . esc_html__( 'Active', 'rankkernel' ) . '</option>';
		echo '<option value="inactive"' . selected( $filters['status'], 'inactive', false ) . '>' . esc_html__( 'Inactive', 'rankkernel' ) . '</option>';
		echo '</select> ';

		echo '<select name="rk_match">';
		echo '<option value="">' . esc_html__( 'All match types', 'rankkernel' ) . '</option>';

		foreach ( $this->matchOptions() as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( (string) $filters['match_type'], $value, false ) . '>';
			echo esc_html( $label );
			echo '</option>';
		}

		echo '</select> ';

		echo '<select name="rk_code">';
		echo '<option value="">' . esc_html__( 'All codes', 'rankkernel' ) . '</option>';

		foreach ( $this->codeOptions() as $value => $label ) {
			$codeValue = (string) $value;

			echo '<option value="' . esc_attr( $codeValue ) . '"' . selected( (string) $filters['code'], $codeValue, false ) . '>';
			echo esc_html( $label );
			echo '</option>';
		}

		echo '</select> ';
		submit_button( __( 'Filter', 'rankkernel' ), '', 'rk_filter', false );
		echo '</div><br class="clear" />';
		echo '</form>';
	}

	/**
	 * Render the empty state, helpful on first use and on empty searches.
	 *
	 * @param array<string, mixed> $filters Current filters.
	 */
	private function renderEmptyState( array $filters ): void {
		$hasFilter = '' !== (string) $filters['search'] || 'all' !== (string) $filters['status']
			|| '' !== (string) $filters['match_type'] || '' !== (string) $filters['code'];

		echo '<div class="rk-empty">';

		if ( $hasFilter ) {
			echo '<p><strong>' . esc_html__( 'No redirects match your search.', 'rankkernel' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'Try a different search or clear the filters to see every redirect.', 'rankkernel' ) . '</p>';
			echo '<p><a class="button" href="' . esc_url( $this->pageUrl( [] ) ) . '">';
			echo esc_html__( 'Clear filters', 'rankkernel' );
			echo '</a></p>';
		} else {
			echo '<p><strong>' . esc_html__( 'No redirects yet.', 'rankkernel' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'Add your first redirect above to send visitors from an old address to a new one.', 'rankkernel' ) . '</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( $this->pageUrl( [ 'rk_open' => 1 ] ) ) . '">';
			echo esc_html__( 'Add your first redirect', 'rankkernel' );
			echo '</a></p>';
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
					'rk_status'  => 'all' === (string) $filters['status'] ? '' : $filters['status'],
					'rk_match'   => $filters['match_type'],
					'rk_code'    => $filters['code'],
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
	 * Render one redirect row with status pill and row actions.
	 *
	 * @param array<string, mixed> $row Rule row.
	 */
	private function renderRow( array $row ): void {
		$id       = (int) ( $row['id'] ?? 0 );
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
		$editUrl   = add_query_arg( [ 'rk_edit' => $id ], $base );
		$toggleUrl = wp_nonce_url( $base . '&rk_action=' . $toggle . '&rule=' . $id, self::NONCE_ROW );
		$deleteUrl = wp_nonce_url( $base . '&rk_action=delete&rule=' . $id, self::NONCE_ROW );

		echo '<tr>';
		echo '<th scope="row" class="check-column"><input type="checkbox" name="rule_ids[]" value="' . esc_attr( (string) $id ) . '" /></th>';

		echo '<td class="rk-col-from"><strong>' . esc_html( $source ) . '</strong>';
		echo '<div class="row-actions">';
		echo '<span class="edit"><a href="' . esc_url( $editUrl ) . '">' . esc_html__( 'Edit', 'rankkernel' ) . '</a> | </span>';
		echo '<span class="toggle"><a href="' . esc_url( $toggleUrl ) . '">' . esc_html( $toggleLabel ) . '</a> | </span>';
		echo '<span class="trash"><a href="' . esc_url( $deleteUrl ) . '" class="rk-confirm" data-rk-confirm="'
			. esc_attr__( 'Delete this redirect? This cannot be undone.', 'rankkernel' ) . '">'
			. esc_html__( 'Trash', 'rankkernel' ) . '</a></span>';
		echo '</div></td>';

		echo '<td class="rk-col-to">' . ( '' === $target ? '<span class="rk-muted">' . esc_html__( '(none)', 'rankkernel' ) . '</span>' : esc_html( $target ) ) . '</td>';
		echo '<td class="rk-col-code">' . esc_html( $code ) . '</td>';
		echo '<td class="rk-col-match">' . esc_html( $match ) . '</td>';
		echo '<td class="rk-col-hits">' . esc_html( (string) number_format_i18n( $hits ) ) . '</td>';
		echo '<td class="rk-col-accessed">' . ( '' === $accessed ? esc_html__( 'Never', 'rankkernel' ) : esc_html( $accessed ) ) . '</td>';

		echo '<td class="rk-col-status">';

		if ( $active ) {
			echo '<span class="rk-pill rk-pill-active">' . esc_html__( 'Active', 'rankkernel' ) . '</span>';
		} else {
			echo '<span class="rk-pill rk-pill-inactive">' . esc_html__( 'Inactive', 'rankkernel' ) . '</span>';
		}

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Render pagination controls.
	 *
	 * @param array<string, mixed> $filters  Current filters.
	 * @param int                  $page     Current page.
	 * @param int                  $pages    Total pages.
	 * @param string               $position Top or bottom marker for styling.
	 */
	private function renderPagination( int $page, int $pages, array $filters, string $position ): void {
		if ( $pages <= 1 ) {
			return;
		}

		$base = [
			's'          => $filters['search'],
			'rk_status'  => 'all' === (string) $filters['status'] ? '' : $filters['status'],
			'rk_match'   => $filters['match_type'],
			'rk_code'    => $filters['code'],
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
	 * Render the CSV import and export card.
	 */
	private function renderImportExport(): void {
		$exportUrl = wp_nonce_url( $this->pageUrl( [ 'rk_action' => 'export' ] ), self::NONCE_EXPORT );

		echo '<div class="rk-card" id="rk-redirect-csv">';
		echo '<h2>' . esc_html__( 'Import and Export', 'rankkernel' ) . '</h2>';
		echo '<p class="rk-sub">';
		echo esc_html__( 'Move redirects in and out with a CSV file. Columns in order: source, target, code, match type, active, hits, last accessed. Hits and last accessed are export only and are ignored on import.', 'rankkernel' );
		echo '</p>';

		echo '<h3>' . esc_html__( 'Export', 'rankkernel' ) . '</h3>';
		echo '<p><a class="button" href="' . esc_url( $exportUrl ) . '">';
		echo esc_html__( 'Export Redirects', 'rankkernel' );
		echo '</a></p>';

		echo '<h3>' . esc_html__( 'Import', 'rankkernel' ) . '</h3>';
		echo '<form method="post" action="" enctype="multipart/form-data">';
		wp_nonce_field( self::NONCE_IMPORT );
		echo '<p><label for="rk-csv-file">' . esc_html__( 'CSV file', 'rankkernel' ) . '</label><br />';
		echo '<input type="file" id="rk-csv-file" name="rk_csv_file" accept=".csv,text/csv" /></p>';
		echo '<p><label><input type="checkbox" name="rk_csv_update" value="1" /> ';
		echo esc_html__( 'Update existing redirects when the source and match type already exist. Leave off to skip duplicates.', 'rankkernel' );
		echo '</label></p>';
		submit_button( __( 'Import Redirects', 'rankkernel' ), 'secondary', 'rankkernel_redirect_import', false );
		echo '</form>';

		$this->renderImportResult();

		echo '</div>';
	}

	/**
	 * Render the import summary plus the per row error list.
	 */
	private function renderImportResult(): void {
		$result = $this->importResult;

		if ( ! is_array( $result ) ) {
			return;
		}

		$created = max( 0, (int) ( $result['created'] ?? 0 ) );
		$updated = max( 0, (int) ( $result['updated'] ?? 0 ) );
		$skipped = max( 0, (int) ( $result['skipped'] ?? 0 ) );
		$errors  = isset( $result['errors'] ) && is_array( $result['errors'] ) ? $result['errors'] : [];

		echo '<div class="rk-import-report">';
		echo '<p><strong>' . esc_html(
			sprintf(
				/* translators: %1$d: created count, %2$d: updated count, %3$d: skipped count, %4$d: error count */
				__( 'Import finished: %1$d created, %2$d updated, %3$d skipped, %4$d with errors.', 'rankkernel' ),
				$created,
				$updated,
				$skipped,
				count( $errors )
			)
		) . '</strong></p>';

		if ( [] !== $errors ) {
			echo '<ul class="rk-import-errors">';

			foreach ( $errors as $error ) {
				if ( ! is_array( $error ) ) {
					continue;
				}

				$row    = max( 0, (int) ( $error['row'] ?? 0 ) );
				$reason = (string) ( $error['reason'] ?? '' );

				if ( $row > 0 ) {
					echo '<li>' . esc_html(
						sprintf(
							/* translators: %1$d: CSV row number, %2$s: reason the row was rejected */
							__( 'Row %1$d: %2$s', 'rankkernel' ),
							$row,
							$reason
						)
					) . '</li>';
				} else {
					echo '<li>' . esc_html( $reason ) . '</li>';
				}
			}

			echo '</ul>';
		}

		$warnings = isset( $result['warnings'] ) && is_array( $result['warnings'] ) ? $result['warnings'] : [];

		if ( [] !== $warnings ) {
			echo '<ul class="rk-import-warnings">';

			foreach ( $warnings as $warning ) {
				if ( ! is_array( $warning ) ) {
					continue;
				}

				$row     = max( 0, (int) ( $warning['row'] ?? 0 ) );
				$message = (string) ( $warning['message'] ?? '' );

				if ( '' === $message ) {
					continue;
				}

				if ( $row > 0 ) {
					echo '<li>' . esc_html(
						sprintf(
							/* translators: %1$d: CSV row number, %2$s: advisory warning text */
							__( 'Row %1$d: %2$s', 'rankkernel' ),
							$row,
							$message
						)
					) . '</li>';
				} else {
					echo '<li>' . esc_html( $message ) . '</li>';
				}
			}

			echo '</ul>';
		}

		echo '</div>';
	}

	/**
	 * Render the settings card.
	 */
	private function renderSettings(): void {
		$all      = $this->redirectSettings->all();
		$preserve = ! empty( $all['preserve_query'] );
		$autoSlug = ! empty( $all['auto_slug_redirect'] );
		$perPage  = max( 1, min( 100, (int) ( $all['rules_per_page'] ?? 20 ) ) );

		echo '<div class="rk-card" id="rk-redirect-settings">';
		echo '<h2>' . esc_html__( 'Redirect Settings', 'rankkernel' ) . '</h2>';

		echo '<form method="post" action="">';

		wp_nonce_field( self::NONCE_SETTINGS );

		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Query strings', 'rankkernel' ) . '</th><td>';
		echo '<label><input type="checkbox" name="rk_preserve_query" value="1" ' . checked( $preserve, true, false ) . ' /> ';
		echo esc_html__( 'Pass the query string to the destination. Turn off to drop it.', 'rankkernel' );
		echo '</label></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Slug changes', 'rankkernel' ) . '</th><td>';
		echo '<label><input type="checkbox" name="rk_auto_slug_redirect" value="1" ' . checked( $autoSlug, true, false ) . ' /> ';
		echo esc_html__( 'Create a 301 redirect automatically when a post slug changes. Turn off to stop creating them.', 'rankkernel' );
		echo '</label></td></tr>';

		echo '<tr><th scope="row"><label for="rk-per-page">' . esc_html__( 'Rows per page', 'rankkernel' ) . '</label></th><td>';
		echo '<input type="number" id="rk-per-page" name="rk_rules_per_page" value="' . esc_attr( (string) $perPage ) . '" class="small-text" min="1" max="100" />';
		echo '<p class="description">';
		echo esc_html__( 'How many redirects to show per page, from 1 to 100.', 'rankkernel' );
		echo '</p></td></tr>';
		echo '</tbody></table>';

		submit_button( __( 'Save Redirect Settings', 'rankkernel' ), 'secondary', 'rankkernel_redirect_settings_save' );

		echo '</form>';
		echo '</div>';
	}
}
