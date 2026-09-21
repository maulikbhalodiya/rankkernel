<?php
/**
 * WP-CLI recalculation of stored analysis scores.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Recalculates scores in batches through the same service the save handler uses.
 *
 * The two documented purposes are to score content that predates the feature
 * and to refresh scores after a rules version bump.
 *
 *     wp rankkernel analysis [--post-type=<type>] [--post-status=<status>] [--dry-run] [--batch=<n>]
 */
final class RecalculateCommand {
	/**
	 * How many posts are processed between progress lines.
	 */
	private const PROGRESS_INTERVAL = 50;

	/**
	 * Score service.
	 *
	 * @var AnalysisScore
	 */
	private AnalysisScore $score;

	/**
	 * Constructor.
	 *
	 * @param AnalysisScore|null $score Optional score service, for tests.
	 */
	public function __construct( ?AnalysisScore $score = null ) {
		$this->score = $score ?? new AnalysisScore();
	}

	/**
	 * Register the command when WP-CLI is running.
	 *
	 * @param AnalysisScore|null $score Optional score service.
	 */
	public static function register( ?AnalysisScore $score = null ): void {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'rankkernel analysis', [ new self( $score ), 'recalculate' ] );
	}

	/**
	 * Parse the WP-CLI associative arguments into batch options.
	 *
	 * @param array<string, mixed> $assoc Associative arguments.
	 * @return array<string, mixed> The result.
	 */
	public function options( array $assoc ): array {
		$postType = isset( $assoc['post-type'] ) ? sanitize_key( (string) $assoc['post-type'] ) : '';

		return [
			'post_type'   => '' !== $postType ? [ $postType ] : [],
			'post_status' => isset( $assoc['post-status'] ) ? sanitize_key( (string) $assoc['post-status'] ) : 'any',
			// A bare flag can arrive as null, so the key is the signal, not the value.
			'dry_run'     => array_key_exists( 'dry-run', $assoc ),
			'batch'       => isset( $assoc['batch'] ) ? (int) $assoc['batch'] : 100,
		];
	}

	/**
	 * Recalculate the stored score for a batch of posts.
	 *
	 * @param array<string, mixed> $options  Options.
	 * @param callable|null        $progress Optional progress callback (int $postId, array $record, int $seen).
	 * @return array{scanned: int, stored: int, skipped: int, dry_run: bool} The result.
	 */
	public function batch( array $options, ?callable $progress = null ): array {
		$postType = [];

		if ( isset( $options['post_type'] ) && is_array( $options['post_type'] ) ) {
			foreach ( $options['post_type'] as $type ) {
				$postType[] = (string) $type;
			}
		}

		if ( [] === $postType ) {
			$postType = AnalysisScore::supportedPostTypes();
		}

		$postStatus = isset( $options['post_status'] ) ? (string) $options['post_status'] : 'any';
		$perPage    = isset( $options['batch'] ) ? max( 1, (int) $options['batch'] ) : 100;
		$dryRun     = ! empty( $options['dry_run'] );

		$scanned = 0;
		$stored  = 0;
		$skipped = 0;
		$paged   = 1;

		do {
			$ids = $this->page( $postType, $postStatus, $perPage, $paged );

			foreach ( $ids as $id ) {
				++$scanned;

				$record = $dryRun ? $this->score->compute( $id ) : $this->score->store( $id );
				$record = $record ?? [];

				if ( [] === $record ) {
					++$skipped;
				} else {
					++$stored;
				}

				if ( null !== $progress ) {
					$progress( $id, $record, $scanned );
				}
			}

			++$paged;
		} while ( [] !== $ids );

		return [
			'scanned' => $scanned,
			'stored'  => $stored,
			'skipped' => $skipped,
			'dry_run' => $dryRun,
		];
	}

	/**
	 * Fetch one page of post ids.
	 *
	 * @param array<int, string> $postType   Post types.
	 * @param string             $postStatus Post status.
	 * @param int                $perPage    Page size.
	 * @param int                $paged      Page number.
	 * @return int[] The result.
	 */
	private function page( array $postType, string $postStatus, int $perPage, int $paged ): array {
		$ids = get_posts(
			[
				'post_type'      => $postType,
				'post_status'    => $postStatus,
				'posts_per_page' => $perPage,
				'paged'          => $paged,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		if ( ! is_array( $ids ) ) {
			return [];
		}

		$out = [];

		foreach ( $ids as $id ) {
			$out[] = (int) $id;
		}

		return $out;
	}

	/**
	 * Run the command and print progress.
	 *
	 * Only every fiftieth post is reported, so a large batch prints a bounded
	 * number of lines, and the final summary always states the real totals.
	 *
	 * @param array<int, string>   $args  Positional arguments.
	 * @param array<string, mixed> $assoc Associative arguments.
	 */
	public function recalculate( array $args, array $assoc ): void {
		// The command takes no positional arguments, only options.
		unset( $args );

		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		$result = $this->batch(
			$this->options( $assoc ),
			function ( int $postId, array $record, int $seen ): void {
				if ( 0 !== $seen % self::PROGRESS_INTERVAL ) {
					return;
				}

				$this->log(
					sprintf(
						'Post %1$d: %2$s (%3$d seen)',
						$postId,
						[] === $record ? 'skipped' : 'scored',
						$seen
					)
				);
			}
		);

		$this->success(
			sprintf(
				'Scanned %1$d, stored %2$d, skipped %3$d%4$s.',
				$result['scanned'],
				$result['stored'],
				$result['skipped'],
				$result['dry_run'] ? ' (dry run)' : ''
			)
		);
	}

	/**
	 * Print one progress line.
	 *
	 * @param string $message Message.
	 */
	private function log( string $message ): void {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		\WP_CLI::log( $message );
	}

	/**
	 * Print the final summary.
	 *
	 * @param string $message Message.
	 */
	private function success( string $message ): void {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		\WP_CLI::success( $message );
	}
}
