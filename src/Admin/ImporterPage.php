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
use RankKernel\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the importer screen.
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
	 * Static per-source storage table map.
	 *
	 * @var array<string, string>
	 */
	private const STORAGE_MAP = [
		'yoast'     => 'postmeta',
		'rank-math' => 'postmeta, redirections',
		'aioseo'    => 'aioseo_posts',
	];

	/**
	 * Static per-source avatar initials and style variant.
	 *
	 * Variants map to token-based classes in importer-admin.css, so no
	 * source-specific color ever hardcodes a hex value into markup.
	 *
	 * @var array<string, array{0: string, 1: string}>
	 */
	private const AVATAR_MAP = [
		'yoast'     => [ 'Y', 'rk-importer-avatar-pink' ],
		'rank-math' => [ 'RM', 'rk-importer-avatar-blue' ],
		'aioseo'    => [ 'AI', 'rk-importer-avatar-teal' ],
	];

	/**
	 * Static per-source plugin main file for get_plugin_data.
	 *
	 * @var array<string, string>
	 */
	private const PLUGIN_FILE_MAP = [
		'yoast'     => 'wordpress-seo/wp-seo.php',
		'rank-math' => 'seo-by-rank-math/rank-math.php',
		'aioseo'    => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
	];

	/**
	 * Source readers shown on the Detect step.
	 *
	 * @var SourceReader[]
	 */
	private array $readers = [];

	/**
	 * Constructor.
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
			<h1 class="screen-reader-text"><?php echo esc_html__( 'Importer', 'rankkernel' ); ?></h1>
			<header class="rk-ui-card rk-ui-page-header">
				<div class="rk-ui-page-header-text">
					<div class="rk-ui-page-header-title-row">
						<h2 class="rk-ui-page-title"><?php echo esc_html__( 'Importer', 'rankkernel' ); ?></h2>
						<span class="rk-version-chip">v<?php echo esc_html( Plugin::version() ); ?></span>
					</div>
					<p class="rk-ui-sub"><?php echo esc_html__( 'Move titles, descriptions, robots rules and redirects from your old SEO plugin into RankKernel.', 'rankkernel' ); ?></p>
				</div>
				<div class="rk-ui-page-header-actions">
					<button type="button" class="rk-ui-btn rk-ui-btn-secondary" data-rk-importer-rescan="1"><span class="rk-icon" aria-hidden="true">search</span><?php echo esc_html__( 'Rescan Database', 'rankkernel' ); ?></button>
				</div>
			</header>

			<section class="rk-importer-steps-card">
				<ol class="rk-importer-steps" role="list">
					<li id="rk-importer-step-detect" class="is-current">
						<div class="rk-importer-step-top">
							<span class="rk-importer-step-number" aria-hidden="true">1</span>
							<span class="rk-importer-step-label"><?php echo esc_html__( 'Detect', 'rankkernel' ); ?></span>
						</div>
						<p class="rk-importer-step-desc"><?php echo esc_html__( 'Scanned active and dormant SEO database tables', 'rankkernel' ); ?></p>
					</li>
					<li id="rk-importer-step-preview">
						<div class="rk-importer-step-top">
							<span class="rk-importer-step-number" aria-hidden="true">2</span>
							<span class="rk-importer-step-label"><?php echo esc_html__( 'Preview', 'rankkernel' ); ?></span>
						</div>
						<p class="rk-importer-step-desc"><?php echo esc_html__( 'Review items, post types and taxonomy mapping', 'rankkernel' ); ?></p>
					</li>
					<li id="rk-importer-step-run">
						<div class="rk-importer-step-top">
							<span class="rk-importer-step-number" aria-hidden="true">3</span>
							<span class="rk-importer-step-label"><?php echo esc_html__( 'Run', 'rankkernel' ); ?></span>
						</div>
						<p class="rk-importer-step-desc"><?php echo esc_html__( 'Execute batch import', 'rankkernel' ); ?></p>
					</li>
				</ol>
			</section>

			<?php $this->renderModes(); ?>
			<?php $this->renderDetect(); ?>
			<?php $this->renderGuide(); ?>
			<?php $this->renderRunPath(); ?>
		</div>
		<?php
	}

	/**
	 * Render the three-step user guide card.
	 *
	 * Static guidance only: it explains the suggested order without
	 * starting anything, so previewing stays a deliberate user action.
	 */
	private function renderGuide(): void {
		?>
		<section class="rk-importer-guide" aria-labelledby="rk-importer-guide-title">
			<h2 id="rk-importer-guide-title"><?php echo esc_html__( 'How this works', 'rankkernel' ); ?></h2>
			<ol class="rk-importer-guide-steps">
				<li><strong><?php echo esc_html__( 'Preview one source first.', 'rankkernel' ); ?></strong> <?php echo esc_html__( 'Pick the Preview button on a single source row above and review what would change.', 'rankkernel' ); ?></li>
				<li><strong><?php echo esc_html__( 'Exclude and choose.', 'rankkernel' ); ?></strong> <?php echo esc_html__( 'Tick Exclude on rows to skip, pick at most one template per slot, and tick only the bulk rules to apply.', 'rankkernel' ); ?></li>
				<li><strong><?php echo esc_html__( 'Confirm, then run.', 'rankkernel' ); ?></strong> <?php echo esc_html__( 'Read the confirmation summary and start the import. Re-running is safe: finished rows report as skipped.', 'rankkernel' ); ?></li>
			</ol>
			<p class="rk-importer-guide-hint"><?php echo esc_html__( 'Suggested order: import the smallest source first, spot-check a few posts, then run the rest. Leave the old plugin active until the results look right.', 'rankkernel' ); ?></p>
		</section>
		<?php
	}

	/**
	 * Render conflict mode cards.
	 */
	private function renderModes(): void {
		?>
		<fieldset class="rk-importer-modes">
			<legend><?php echo esc_html__( 'Conflict Mode', 'rankkernel' ); ?></legend>
			<p class="rk-importer-modes-helper"><?php echo esc_html__( 'Choose how RankKernel handles SEO metadata when a post, page, or term already has data in both databases.', 'rankkernel' ); ?></p>
			<span class="rk-importer-applies"><?php echo esc_html__( 'Applies to all selected sources', 'rankkernel' ); ?></span>
			<div class="rk-importer-modes-grid">
				<label class="rk-importer-mode-card">
					<div class="rk-importer-mode-top">
						<input type="radio" name="rk-importer-mode" value="skip" checked>
						<span class="rk-importer-mode-title"><?php echo esc_html__( 'Skip existing values', 'rankkernel' ); ?></span>
						<span class="rk-importer-badge rk-importer-badge-positive"><?php echo esc_html__( 'Recommended', 'rankkernel' ); ?></span>
					</div>
					<p class="rk-importer-mode-desc"><?php echo esc_html__( 'Preserves all existing RankKernel metadata. Only imports missing fields, empty snippets, or unmapped redirects. Safe and non-destructive.', 'rankkernel' ); ?></p>
				</label>
				<label class="rk-importer-mode-card">
					<div class="rk-importer-mode-top">
						<input type="radio" name="rk-importer-mode" value="overwrite">
						<span class="rk-importer-mode-title"><?php echo esc_html__( 'Overwrite with incoming values', 'rankkernel' ); ?></span>
						<span class="rk-importer-badge rk-importer-badge-warning"><?php echo esc_html__( 'Overwrite', 'rankkernel' ); ?></span>
					</div>
					<p class="rk-importer-mode-desc"><?php echo esc_html__( 'Replaces current RankKernel metadata with data found in the source plugin. Ideal for full migrations to match legacy configurations one to one.', 'rankkernel' ); ?></p>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Render the Detect step table.
	 */
	private function renderDetect(): void {
		$scanStart       = microtime( true );
		$scanStartMemory = memory_get_usage( true );

		$rows = [];
		foreach ( $this->readers as $reader ) {
			$rows[] = [
				'reader' => $reader,
				'counts' => $reader->detect(),
			];
		}

		$scanElapsed = microtime( true ) - $scanStart;
		$scanMemory  = memory_get_usage( true );

		?>
		<section class="rk-importer-detect-card">
			<div class="rk-importer-card-header">
				<div>
					<h2><?php echo esc_html__( 'Detected SEO Data Sources', 'rankkernel' ); ?></h2>
					<p class="rk-importer-card-helper"><?php echo esc_html__( 'RankKernel scanned your database for current or removed SEO plugins. Preview anything before importing.', 'rankkernel' ); ?></p>
				</div>
			</div>
			<div class="rk-importer-table-wrap">
				<table class="widefat rk-importer-detect">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Source', 'rankkernel' ); ?></th>
							<th class="rk-importer-num"><?php echo esc_html__( 'Posts', 'rankkernel' ); ?></th>
							<th class="rk-importer-num"><?php echo esc_html__( 'Terms', 'rankkernel' ); ?></th>
							<th class="rk-importer-num"><?php echo esc_html__( 'Settings', 'rankkernel' ); ?></th>
							<th class="rk-importer-num"><?php echo esc_html__( 'Redirects', 'rankkernel' ); ?></th>
							<th class="rk-importer-status-col"><?php echo esc_html__( 'Status and Action', 'rankkernel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$reader    = $row['reader'];
							$counts    = $row['counts'];
							$id        = $reader->id();
							$label     = $reader->label();
							$posts     = (int) $counts['posts'];
							$terms     = (int) $counts['terms'];
							$settings  = (bool) $counts['settings'];
							$redirects = (int) $counts['redirects'];
							$empty     = ( 0 === $posts && 0 === $terms && 0 === $redirects && ! $settings );

							$avatar  = self::AVATAR_MAP[ $id ] ?? [ strtoupper( substr( $id, 0, 2 ) ), 'rk-importer-avatar-gray' ];
							$storage = self::STORAGE_MAP[ $id ] ?? '';

							$versionBadge = '';
							if ( function_exists( 'get_plugin_data' ) ) {
								$file = self::PLUGIN_FILE_MAP[ $id ] ?? '';
								if ( '' !== $file && defined( 'WP_PLUGIN_DIR' ) ) {
									$data = \get_plugin_data( WP_PLUGIN_DIR . '/' . $file );
									if ( is_array( $data ) && ! empty( $data['Version'] ) ) {
										$versionBadge = (string) $data['Version'];
									}
								}
							}
							?>
							<tr class="<?php echo $empty ? 'rk-importer-empty-row' : ''; ?>">
								<td>
									<div class="rk-importer-source-cell">
										<div class="rk-importer-avatar <?php echo esc_attr( $avatar[1] ); ?>" aria-hidden="true">
											<?php echo esc_html( $avatar[0] ); ?>
										</div>
										<div class="rk-importer-source-text">
											<div class="rk-importer-source-name">
												<?php echo esc_html( $label ); ?>
												<?php if ( '' !== $versionBadge ) : ?>
													<span class="rk-importer-version-badge"><?php echo esc_html( 'v' . $versionBadge ); ?></span>
												<?php endif; ?>
											</div>
											<span class="rk-importer-storage"><?php echo esc_html( $storage ); ?></span>
										</div>
									</div>
								</td>
								<td class="rk-importer-num"><?php echo esc_html( number_format( $posts ) ); ?></td>
								<td class="rk-importer-num"><?php echo esc_html( number_format( $terms ) ); ?></td>
								<td class="rk-importer-num"><?php echo esc_html( $settings ? __( 'Yes', 'rankkernel' ) : __( 'No', 'rankkernel' ) ); ?></td>
								<td class="rk-importer-num"><?php echo esc_html( number_format( $redirects ) ); ?></td>
								<td class="rk-importer-status-col">
									<div class="rk-importer-actions">
										<?php if ( $empty ) : ?>
											<span class="rk-importer-pill rk-importer-pill-muted"><?php echo esc_html__( 'Nothing to import', 'rankkernel' ); ?></span>
											<button type="button" class="rk-importer-preview-btn" disabled><?php echo esc_html__( 'Preview', 'rankkernel' ); ?></button>
										<?php else : ?>
											<span class="rk-importer-pill rk-importer-pill-ready"><?php echo esc_html__( 'Ready', 'rankkernel' ); ?></span>
											<button type="button" class="rk-importer-preview-btn rk-importer-preview-btn-primary" data-rk-importer-preview="<?php echo esc_attr( $id ); ?>">
												<?php echo esc_html__( 'Preview', 'rankkernel' ); ?>
												<span aria-hidden="true">→</span>
											</button>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<div class="rk-importer-detect-footer">
				<div class="rk-importer-note"><?php echo esc_html__( 'Selecting Preview does not make any database changes. You can review mappings before running.', 'rankkernel' ); ?></div>
				<div class="rk-importer-scan-stats">
					<?php echo esc_html( sprintf( 'Scan execution: %ss', number_format( $scanElapsed, 3 ) ) ); ?>
					<span>•</span>
					<?php echo esc_html( sprintf( 'Memory footprint: %sMB', number_format( $scanMemory / 1048576, 1 ) ) ); ?>
				</div>
			</div>
		</section>

		<div class="rk-importer-info-box">
			<strong><?php echo esc_html__( 'Safe, non-blocking execution:', 'rankkernel' ); ?></strong>
			<?php echo esc_html__( 'RankKernel reads source data in small bounded batches to keep server load minimal. Your source plugin data stays untouched until you decide to deactivate the plugin yourself.', 'rankkernel' ); ?>
		</div>
		<?php
	}

	/**
	 * Render the Preview plus Confirm plus Run markup.
	 */
	private function renderRunPath(): void {
		?>
		<ol class="rk-importer-kind-nav" data-rk-importer-kind-nav="1" data-rk-importer-kind-stepper="1"></ol>
		<?php foreach ( [ 'posts', 'terms', 'sitewide', 'redirects', 'bulk', 'skipped' ] as $section ) : ?>
			<div class="rk-importer-section" data-rk-importer-section="<?php echo esc_attr( $section ); ?>"></div>
		<?php endforeach; ?>
		<div class="rk-importer-confirm" data-rk-importer-confirm="1"></div>
		<div class="rk-importer-wizard-nav" data-rk-importer-wizard-nav="1">
			<button type="button" data-rk-importer-back="1" data-rk-importer-prev="1"><?php echo esc_html__( 'Back', 'rankkernel' ); ?></button>
			<button type="button" data-rk-importer-skip="1" data-rk-importer-skip-kind="1"><?php echo esc_html__( 'Skip kind', 'rankkernel' ); ?></button>
			<button type="button" data-rk-importer-next="1" data-rk-importer-kind-next="1"><?php echo esc_html__( 'Next', 'rankkernel' ); ?></button>
		</div>
		<button type="button" class="button button-primary" data-rk-importer-run="1"><?php echo esc_html__( 'Run import', 'rankkernel' ); ?></button>
		<div class="rk-importer-progress" data-rk-importer-progress="1"><div class="rk-importer-bar" data-rk-importer-bar="1"></div></div>
		<div class="rk-importer-announce" data-rk-importer-announce="1" aria-live="polite"></div>
		<div class="rk-importer-error" data-rk-importer-error="1" role="alert"></div>
		<?php
	}

	/**
	 * Enqueue importer assets callback.
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

		\RankKernel\Admin\AdminStyles::enqueueTokenLayer( (string) RANKKERNEL_FILE );
		\RankKernel\Admin\AdminStyles::enqueueUiLayer( (string) RANKKERNEL_FILE );

		$css = plugins_url( 'assets/css/importer-admin.css', (string) RANKKERNEL_FILE );

		if ( function_exists( 'wp_register_style' ) ) {
			wp_register_style( 'rankkernel-importer-admin', $css, [ \RankKernel\Admin\AdminStyles::TOKEN_HANDLE, \RankKernel\Admin\AdminStyles::UI_HANDLE ], $version );
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
