<?php
/**
 * Importer admin page, Detect plus Preview plus Run shell.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Admin;

use RankKernel\Modules\Importer\SourceReader;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the importer screen skeleton.
 *
 * Phase 1 owns the three step shell only: Detect lists installed source
 * plugins, Preview shows the dry run table, Run executes the confirmed
 * import. Later phases fill each step; the shell shape stays pinned by
 * test.
 */
final class ImporterPage {
	/**
	 * Menu slug for the screen.
	 */
	public const SLUG = 'rankkernel-importer';

	/**
	 * Hook suffix for the screen, used to gate asset loading.
	 */
	public const HOOK_SUFFIX = 'rankkernel_page_rankkernel-importer';

	/**
	 * Source readers shown on the Detect step.
	 *
	 * @var SourceReader[]
	 */
	private array $readers = [];

	/**
	 * Constructor.
	 *
	 * Readers default to none so the shell renders standalone in tests;
	 * production passes the three source readers from AdminMenu.
	 *
	 * @param SourceReader[] $readers Source readers.
	 */
	public function __construct( array $readers = [] ) {
		$this->readers = $readers;
	}

	/**
	 * Render the importer screen.
	 */
	public function render(): void {
		?>
		<div class="wrap rk-importer rk-ui" data-rk-importer-root="1">
			<h1><?php echo esc_html__( 'Importer', 'rankkernel' ); ?></h1>
			<ol class="rk-importer-steps">
				<li id="rk-importer-step-detect"><?php echo esc_html__( 'Detect', 'rankkernel' ); ?></li>
				<li id="rk-importer-step-preview"><?php echo esc_html__( 'Preview', 'rankkernel' ); ?></li>
				<li id="rk-importer-step-run"><?php echo esc_html__( 'Run', 'rankkernel' ); ?></li>
			</ol>
			<?php $this->renderDetect(); ?>
			<?php $this->renderRunPath(); ?>
		</div>
		<?php
	}

	/**
	 * Render the Detect step table.
	 *
	 * One row per source with its counts; an empty source shows the
	 * nothing-to-import note instead of numbers. No selection buttons
	 * yet: those arrive with the Phase 5 page script, and a button that
	 * does nothing would be dead UI.
	 */
	private function renderDetect(): void {
		if ( [] === $this->readers ) {
			return;
		}
		?>
		<table class="widefat rk-importer-detect">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Source', 'rankkernel' ); ?></th>
					<th><?php echo esc_html__( 'Posts', 'rankkernel' ); ?></th>
					<th><?php echo esc_html__( 'Terms', 'rankkernel' ); ?></th>
					<th><?php echo esc_html__( 'Settings', 'rankkernel' ); ?></th>
					<th><?php echo esc_html__( 'Redirects', 'rankkernel' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'rankkernel' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $this->readers as $reader ) : ?>
					<?php $counts = $reader->detect(); ?>
					<?php $total = $counts['posts'] + $counts['terms'] + ( $counts['settings'] ? 1 : 0 ) + $counts['redirects']; ?>
					<tr>
						<td><?php echo esc_html( $reader->label() ); ?></td>
						<td><?php echo esc_html( (string) $counts['posts'] ); ?></td>
						<td><?php echo esc_html( (string) $counts['terms'] ); ?></td>
						<td><?php echo esc_html( $counts['settings'] ? __( 'Yes', 'rankkernel' ) : __( 'No', 'rankkernel' ) ); ?></td>
						<td><?php echo esc_html( (string) $counts['redirects'] ); ?></td>
						<td>
							<?php if ( 0 === $total ) : ?>
								<?php echo esc_html__( 'Nothing to import', 'rankkernel' ); ?>
							<?php else : ?>
								<?php echo esc_html__( 'Ready', 'rankkernel' ); ?>
								<button type="button" class="button" data-rk-importer-preview="<?php echo esc_attr( $reader->id() ); ?>"><?php echo esc_html__( 'Preview', 'rankkernel' ); ?></button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the Preview plus Confirm plus Run markup the page script drives.
	 *
	 * Each kind renders into its own section mount so large previews
	 * page without flooding the browser; sitewide candidates and bulk
	 * rules get their own selection controls. The confirmation panel
	 * stays empty until the script fills the pre-run summary, and the
	 * mode radio defaults to skip existing values.
	 */
	private function renderRunPath(): void {
		?>
		<fieldset class="rk-importer-modes">
			<legend><?php echo esc_html__( 'Conflict mode', 'rankkernel' ); ?></legend>
			<label><input type="radio" name="rk-importer-mode" value="skip" checked> <?php echo esc_html__( 'Skip existing values', 'rankkernel' ); ?></label>
			<label><input type="radio" name="rk-importer-mode" value="overwrite"> <?php echo esc_html__( 'Overwrite with incoming values', 'rankkernel' ); ?></label>
		</fieldset>
		<?php foreach ( [ 'posts', 'terms', 'sitewide', 'redirects', 'bulk', 'skipped' ] as $section ) : ?>
			<div class="rk-importer-section" data-rk-importer-section="<?php echo esc_attr( $section ); ?>"></div>
		<?php endforeach; ?>
		<div class="rk-importer-confirm" data-rk-importer-confirm="1"></div>
		<button type="button" class="button button-primary" data-rk-importer-run="1"><?php echo esc_html__( 'Run import', 'rankkernel' ); ?></button>
		<div class="rk-importer-progress" data-rk-importer-progress="1"><div class="rk-importer-bar" data-rk-importer-bar="1"></div></div>
		<div class="rk-importer-announce" data-rk-importer-announce="1" aria-live="polite"></div>
		<div class="rk-importer-error" data-rk-importer-error="1" role="alert"></div>
		<?php
	}

	/**
	 * Enqueue importer assets callback.
	 *
	 * Registers the page script that drives preview plus chunked run
	 * through the import REST endpoint, with the base URL plus nonce.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueAssets( string $hookSuffix = '' ): void {
		if ( self::HOOK_SUFFIX !== $hookSuffix ) {
			return;
		}

		if ( ! function_exists( 'plugins_url' ) ) {
			return;
		}

		$version = \RankKernel\Plugin::version();

		AdminStyles::enqueueTokenLayer( (string) RANKKERNEL_FILE );
		AdminStyles::enqueueUiLayer( (string) RANKKERNEL_FILE );

		$css = plugins_url( 'assets/css/importer-admin.css', (string) RANKKERNEL_FILE );

		if ( function_exists( 'wp_register_style' ) ) {
			wp_register_style( 'rankkernel-importer-admin', $css, [ AdminStyles::TOKEN_HANDLE, AdminStyles::UI_HANDLE ], $version );
		}

		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'rankkernel-importer-admin' );
		}

		if ( ! function_exists( 'wp_register_script' ) || ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}

		$src = (string) plugins_url( 'assets/js/importer-admin.js', RANKKERNEL_FILE );

		wp_register_script( 'rankkernel-importer-admin', $src, [ 'wp-a11y', 'wp-i18n' ], $version, true );

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'rankkernel-importer-admin', 'rankkernel' );
		}

		wp_enqueue_script( 'rankkernel-importer-admin' );

		if ( ! function_exists( 'wp_localize_script' ) ) {
			return;
		}

		wp_localize_script(
			'rankkernel-importer-admin',
			'rankkernelImporter',
			[
				'restBase'  => function_exists( 'rest_url' ) ? (string) rest_url( 'rankkernel/v1/import' ) : '',
				'restNonce' => function_exists( 'wp_create_nonce' ) ? (string) wp_create_nonce( 'wp_rest' ) : '',
			]
		);
	}
}
