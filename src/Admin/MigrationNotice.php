<?php
/**
 * Administrator notice for a newer migration ledger.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

use RankKernel\Database\Migrations\MigrationRunner;

defined( 'ABSPATH' ) || exit;

/**
 * Captures a downgrade signal and renders it once on an admin request.
 *
 * State is request-local on purpose. The migration runner already emits the
 * public downgrade action and a PHP warning; this class only turns that signal
 * into a visible, escaped notice for an authorized administrator. It never
 * writes the ledger, runs a migration, or persists a dismissal.
 */
final class MigrationNotice {
	/**
	 * Captured stored ledger version.
	 *
	 * @var string|null
	 */
	private ?string $ledger = null;

	/**
	 * Captured running code version.
	 *
	 * @var string|null
	 */
	private ?string $code = null;

	/**
	 * Whether the notice has already been rendered this request.
	 *
	 * @var bool
	 */
	private bool $rendered = false;

	/**
	 * Register the downgrade capture and admin notice hooks.
	 */
	public function register(): void {
		add_action( 'rankkernel/migration/downgrade', [ $this, 'capture' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'render' ] );
	}

	/**
	 * Capture the version pair from the migration runner.
	 *
	 * @param string $ledger Stored database ledger version.
	 * @param string $code   Running plugin release version.
	 */
	public function capture( string $ledger, string $code ): void {
		$ledger = trim( $ledger );
		$code   = trim( $code );

		if ( '' === $ledger || '' === $code ) {
			return;
		}

		$this->ledger = $ledger;
		$this->code   = $code;
	}

	/**
	 * Render one warning notice for an authorized administrator.
	 */
	public function render(): void {
		if ( $this->rendered || null === $this->ledger || null === $this->code ) {
			return;
		}

		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->isPluginScreen() ) {
			return;
		}

		$current = function_exists( 'get_option' ) ? get_option( MigrationRunner::LEDGER, '0.0.0' ) : '0.0.0';
		$current = is_string( $current ) && '' !== trim( $current ) ? trim( $current ) : '0.0.0';

		if ( ! version_compare( $current, $this->code, '>' ) ) {
			$this->ledger = null;
			$this->code   = null;

			return;
		}

		$this->rendered = true;

		$message = sprintf(
			/* translators: 1: stored database version, 2: running plugin version. */
			__( 'RankKernel detected a database version newer than the running plugin version. Stored database version: %1$s. Running plugin version: %2$s. Database migrations have been skipped for safety. Restore or install a compatible RankKernel version before proceeding with plugin updates or schema changes.', 'rankkernel' ),
			$current,
			$this->code
		);

		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Whether the current admin screen belongs to RankKernel or Plugins.
	 *
	 * @return bool
	 */
	private function isPluginScreen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! is_object( $screen ) || ! property_exists( $screen, 'id' ) || ! is_string( $screen->id ) ) {
			return false;
		}

		$id = $screen->id;

		return 'toplevel_page_rankkernel' === $id
			|| 'rankkernel' === $id
			|| str_starts_with( $id, 'rankkernel_page_' )
			|| 'plugins' === $id
			|| 'plugins.php' === $id;
	}
}
