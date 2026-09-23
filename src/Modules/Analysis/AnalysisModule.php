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
 * Wires the analyser into the editors and to the REST API.
 *
 * Both editors score the draft locally through the ported JavaScript engine
 * and render the result without a request. PHP stays the persisted reference
 * engine: the save handler stores the score from the same rules, and the REST
 * route remains for REST and headless consumers.
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
	 * Register the score meta keys, only when the module is on.
	 *
	 * The keys are not exposed in the REST schema, so the editor cannot write
	 * them, and the auth callback ties a write to the edit capability for the
	 * post. The sanitize callback accepts mixed and validates defensively.
	 */
	public function register(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		$auth = static fn ( mixed $value, string $meta_key, int $object_id ): bool
			=> current_user_can( 'edit_post', $object_id );

		register_meta(
			'post',
			AnalysisScore::META_KEY,
			[
				'type'              => 'object',
				'single'            => true,
				'sanitize_callback' => [ AnalysisScore::class, 'sanitize' ],
				'auth_callback'     => $auth,
			]
		);

		register_meta(
			'post',
			AnalysisScore::SCORE_VALUE_KEY,
			[
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth,
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
