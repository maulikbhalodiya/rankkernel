<?php
/**
 * Content Analysis module.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Analysis;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Analysis\AnalysisScore;
use RankKernel\Modules\ModuleEnableMap;
use RankKernel\Modules\ModuleInterface;
use RankKernel\Rest\AnalysisController;

/**
 * Wires the analyser into the editor through one REST route.
 *
 * The engine runs on the server so there is a single tested implementation of
 * every check. The editor posts the content it currently holds, including
 * unsaved edits, and renders what comes back, so a duplicate JavaScript engine
 * never has to be kept in step with this one.
 */
final class AnalysisModule implements ModuleInterface {
	/**
	 * Cached enabled state.
	 *
	 * @var bool|null
	 */
	private ?bool $enabledCache = null;

	/**
	 * Constructor.
	 *
	 * @param ModuleEnableMap         $enableMap  Enable map.
	 * @param AnalysisController|null $controller Optional controller, for tests.
	 */
	public function __construct(
		private readonly ModuleEnableMap $enableMap,
		private readonly ?AnalysisController $controller = null
	) {
	}

	/**
	 * Module id.
	 *
	 * @return string The result.
	 */
	public function getId(): string {
		return 'analysis';
	}

	/**
	 * Module label.
	 *
	 * @return string The result.
	 */
	public function getName(): string {
		return __( 'Content Analysis', 'rankkernel' );
	}

	/**
	 * Boot priority, after the metadata layer.
	 *
	 * @return int The result.
	 */
	public function getPriority(): int {
		return 30;
	}

	/**
	 * Modules this one needs.
	 *
	 * @return string[] The result.
	 */
	public function dependsOn(): array {
		return [];
	}

	/**
	 * Whether the module is enabled.
	 *
	 * @return bool The result.
	 */
	public function isEnabled(): bool {
		if ( null !== $this->enabledCache ) {
			return $this->enabledCache;
		}

		$this->enabledCache = $this->enableMap->isEnabled( 'analysis' );

		return $this->enabledCache;
	}

	/**
	 * Register the score meta key, only when the module is on.
	 *
	 * The key is not exposed in the REST schema, so the editor cannot write
	 * it, and the auth callback ties a write to the edit capability for the
	 * post. The sanitize callback accepts mixed and validates defensively.
	 */
	public function register(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		register_meta(
			'post',
			AnalysisScore::META_KEY,
			[
				'type'              => 'object',
				'single'            => true,
				'sanitize_callback' => [ AnalysisScore::class, 'sanitize' ],
				'auth_callback'     => static fn ( mixed $value, string $meta_key, int $object_id ): bool
					=> current_user_can( 'edit_post', $object_id ),
			]
		);
	}

	/**
	 * Register hooks, only when the module is on.
	 */
	public function boot(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		$controller = $this->controller ?? new AnalysisController();

		add_action( 'rest_api_init', [ $controller, 'registerRoutes' ] );

		$score = new AnalysisScore();

		( new AnalysisSaveHandler( $score ) )->register();

		( new AnalysisColumn( $score ) )->register();

		RecalculateCommand::register( $score );
	}
}
