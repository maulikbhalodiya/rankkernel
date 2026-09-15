<?php
/**
 * HowTo piece.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Schema\Pieces;

defined( 'ABSPATH' ) || exit;

use RankKernel\Modules\Metadata\Context;
use RankKernel\Modules\Schema\PieceInterface;

/**
 * HowTo steps as a HowTo node.
 *
 * Needed on any singular view, pages included, because Context maps
 * every singular query to the post type, so the post gate covers pages
 * as well. The node needs at least one valid step. The name falls back
 * from the payload name to the block title to the post title. Step text
 * is reduced to plain text at build time, so the schema carries no HTML
 * tags and no scripts. Frontend rendering keeps its safe HTML
 * separately, so visible formatting and structured text stay clean. The
 * Generator encodes the graph once, so pieces never pre encode their
 * own output.
 */
final class HowtoPiece implements PieceInterface {
	/**
	 * Parsed block data memoized per instance, keyed by content hash.
	 *
	 * The isNeeded and build methods run on the same instance within one request,
	 * so the post content parses once, not twice.
	 *
	 * @var array{title: string, description: string, totalTime: string, estimatedCost: string, tools: array<int, string>, materials: array<int, string>, steps: array<int, array{title: string, text: string, image: string}>}|null
	 */
	private ?array $blockData = null;

	/**
	 * Content hash for the memoized block data.
	 *
	 * @var string
	 */
	private string $blockHash = '';

	/**
	 * Get piece id.
	 *
	 * @return string The result.
	 */
	public function getId(): string {
		return 'howto';
	}

	/**
	 * Whether the piece is needed.
	 *
	 * The singular gate covers posts and pages alike, since Context
	 * reports every singular view as the post type.
	 *
	 * @param Context $ctx Request context.
	 * @return bool The result.
	 */
	public function isNeeded( Context $ctx ): bool {
		if ( 'post' !== $ctx->queriedType() ) {
			return false;
		}

		return [] !== $this->steps( $ctx );
	}

	/**
	 * Build the HowTo node.
	 *
	 * The name fallback chain is payload name, then block title, then
	 * post title, so the visible block title feeds the schema when the
	 * meta name is absent. Total time and cost follow the same source
	 * preference documented in howto, and tool and supply lists come
	 * from the blocks, which are their only source.
	 *
	 * @param Context $ctx Request context.
	 * @return array<string, mixed>
	 */
	public function build( Context $ctx ): array {
		$howto = $this->howto( $ctx );

		if ( [] === $howto['steps'] ) {
			return [];
		}

		$permalink = $ctx->permalink();

		if ( '' === $permalink ) {
			return [];
		}

		$name = trim( $howto['name'] );

		if ( '' === $name ) {
			$name = trim( $ctx->title() );
		}

		$steps = [];

		foreach ( $howto['steps'] as $row ) {
			$step = [
				'@type' => 'HowToStep',
				'name'  => $row['title'],
				'text'  => self::plainText( $row['text'] ),
			];

			if ( '' !== $row['image'] ) {
				$step['image'] = $row['image'];
			}

			$steps[] = $step;
		}

		$node = [
			'@type' => 'HowTo',
			'@id'   => $permalink . '#howto',
			'name'  => $name,
			'step'  => $steps,
		];

		$description = trim( $howto['description'] );

		if ( '' !== $description ) {
			$node['description'] = $description;
		}

		if ( '' !== trim( $howto['totalTime'] ) ) {
			$node['totalTime'] = $howto['totalTime'];
		}

		if ( '' !== trim( $howto['cost'] ) ) {
			$node['estimatedCost'] = $howto['cost'];
		}

		if ( [] !== $howto['tools'] ) {
			$node['tool'] = array_values( $howto['tools'] );
		}

		if ( [] !== $howto['materials'] ) {
			$node['supply'] = array_values( $howto['materials'] );
		}

		return $node;
	}

	/**
	 * HowTo data from the payload plus the editor blocks.
	 *
	 * Fresh rows hold an empty schema list, saved rows hold the object
	 * shape. Steps with both an empty title and empty text are dropped
	 * here as well, so unsanitized input can never reach the graph.
	 * Step text is trimmed, so whitespace only input counts as empty.
	 * Image URLs pass the safe scheme check on both sources, so unsafe
	 * URLs never enter the graph.
	 *
	 * Merge order is payload rows first, block rows appended after in
	 * document order, so stored meta always outranks editor blocks and
	 * existing output never reorders. Dedupe keeps the first row for
	 * each lowercased title plus text plus image key, so distinct steps
	 * that differ only by image survive. The merged list caps at 100
	 * rows, which means a full payload list starves every block row.
	 *
	 * Total time prefers the payload value when it matches the ISO
	 * 8601 duration shape, else the block value when that matches.
	 * Anything else is dropped, so invalid strings never reach the graph.
	 * Cost prefers the payload cost, else the block estimated cost,
	 * and the schema key stays estimatedCost in both cases.
	 *
	 * @param Context $ctx Request context.
	 * @return array{name: string, description: string, steps: array<int, array{title: string, text: string, image: string}>, totalTime: string, cost: string, tools: array<int, string>, materials: array<int, string>}
	 */
	private function howto( Context $ctx ): array {
		$out = [
			'name'        => '',
			'description' => '',
			'steps'       => [],
			'totalTime'   => '',
			'cost'        => '',
			'tools'       => [],
			'materials'   => [],
		];

		$meta = $ctx->meta();

		$schema = $meta['schema'] ?? [];

		if ( ! is_array( $schema ) ) {
			return $out;
		}

		$howto = $schema['howto'] ?? [];

		if ( ! is_array( $howto ) ) {
			return $out;
		}

		if ( isset( $howto['name'] ) ) {
			$out['name'] = trim( (string) $howto['name'] );
		}

		$metaTime = isset( $howto['totalTime'] ) ? trim( (string) $howto['totalTime'] ) : '';

		if ( isset( $howto['cost'] ) ) {
			$out['cost'] = trim( (string) $howto['cost'] );
		}

		$rows = $howto['steps'] ?? [];

		if ( ! is_array( $rows ) ) {
			return $out;
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$title = isset( $row['title'] ) ? trim( (string) $row['title'] ) : '';
			$text  = isset( $row['text'] ) ? trim( (string) $row['text'] ) : '';

			if ( '' === $title && '' === $text ) {
				continue;
			}

			$image = isset( $row['image'] ) ? self::safeImageUrl( (string) $row['image'] ) : '';

			$out['steps'][] = [
				'title' => $title,
				'text'  => $text,
				'image' => $image,
			];
		}

		$block = $this->blockData( $ctx );

		if ( '' === $out['name'] ) {
			$out['name'] = $block['title'];
		}

		$out['description'] = $block['description'];
		$out['tools']       = $block['tools'];
		$out['materials']   = $block['materials'];

		if ( self::validDuration( $metaTime ) ) {
			$out['totalTime'] = $metaTime;
		} elseif ( self::validDuration( $block['totalTime'] ) ) {
			$out['totalTime'] = $block['totalTime'];
		}

		if ( '' === $out['cost'] ) {
			$out['cost'] = $block['estimatedCost'];
		}

		foreach ( $block['steps'] as $blockStep ) {
			if ( count( $out['steps'] ) >= 100 ) {
				break;
			}

			$seen = false;

			foreach ( $out['steps'] as $existing ) {
				$sameTitle    = strtolower( trim( $existing['title'] ) ) === strtolower( trim( $blockStep['title'] ) );
				$existingText = strtolower( trim( strip_tags( $existing['text'] ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Plain text key for schema dedupe, markup must not affect equality.
				$blockText    = strtolower( trim( strip_tags( $blockStep['text'] ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Plain text key for schema dedupe, markup must not affect equality.

				if ( $sameTitle && $existingText === $blockText && strtolower( trim( $existing['image'] ) ) === strtolower( trim( $blockStep['image'] ) ) ) {
					$seen = true;
					break;
				}
			}

			if ( ! $seen ) {
				$out['steps'][] = $blockStep;
			}
		}

		return $out;
	}

	/**
	 * Block level HowTo data from rankkernel/howto blocks in order.
	 *
	 * Only on singular contexts with a post id. Scalar fields keep the
	 * first non empty value in document order, tools and materials
	 * merge unique values in document order capped at 100 entries
	 * each, and steps collect in document order. Unknown block names
	 * and malformed attrs are ignored.
	 *
	 * @param Context $ctx Request context.
	 * @return array{title: string, description: string, totalTime: string, estimatedCost: string, tools: array<int, string>, materials: array<int, string>, steps: array<int, array{title: string, text: string, image: string}>}
	 */
	private function blockData( Context $ctx ): array {
		$empty = [
			'title'         => '',
			'description'   => '',
			'totalTime'     => '',
			'estimatedCost' => '',
			'tools'         => [],
			'materials'     => [],
			'steps'         => [],
		];

		if ( 'post' !== $ctx->queriedType() ) {
			return $empty;
		}

		$postId = $ctx->queriedId();

		if ( $postId <= 0 ) {
			return $empty;
		}

		if ( ! function_exists( 'get_post_field' ) || ! function_exists( 'parse_blocks' ) ) {
			return $empty;
		}

		$content = get_post_field( 'post_content', $postId );

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return $empty;
		}

		$hash = md5( $content );

		if ( null !== $this->blockData && $hash === $this->blockHash ) {
			return $this->blockData;
		}

		/**
		 * Parsed blocks from the post content.
		 *
		 * @var array<int, mixed> $blocks
		 */
		$blocks = parse_blocks( $content );

		if ( ! is_array( $blocks ) ) {
			return $empty;
		}

		$data = $empty;

		$this->walkBlocks( $blocks, $data );

		$this->blockData = $data;
		$this->blockHash = $hash;

		return $data;
	}

	/**
	 * Collect block data from a block list, recursing into groups.
	 *
	 * @param array<int, mixed>                                                                                                                                                                                                   $blocks Block list.
	 * @param array{title: string, description: string, totalTime: string, estimatedCost: string, tools: array<int, string>, materials: array<int, string>, steps: array<int, array{title: string, text: string, image: string}>} $data Collected data.
	 */
	private function walkBlocks( array $blocks, array &$data ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( 'rankkernel/howto' === ( $block['blockName'] ?? null ) ) {
				$this->collectFields( $block, $data );
				$this->collectRows( $block, $data['steps'] );
			}

			$inner = $block['innerBlocks'] ?? [];

			if ( is_array( $inner ) && [] !== $inner ) {
				$this->walkBlocks( $inner, $data );
			}
		}
	}

	/**
	 * Collect scalar fields and lists from one HowTo block.
	 *
	 * Scalar fields keep the first non empty value in document order,
	 * so the earliest block with a title names the schema when the
	 * payload name is absent. Tools and materials append unique trimmed
	 * values in document order.
	 *
	 * @param array<string, mixed>                                                                                                                                                                                                $block HowTo block.
	 * @param array{title: string, description: string, totalTime: string, estimatedCost: string, tools: array<int, string>, materials: array<int, string>, steps: array<int, array{title: string, text: string, image: string}>} $data  Collected data.
	 */
	private function collectFields( array $block, array &$data ): void {
		$attrs = $block['attrs'] ?? [];

		if ( ! is_array( $attrs ) ) {
			return;
		}

		foreach ( [ 'title', 'description', 'totalTime', 'estimatedCost' ] as $field ) {
			if ( '' !== $data[ $field ] ) {
				continue;
			}

			if ( ! isset( $attrs[ $field ] ) || ! is_string( $attrs[ $field ] ) ) {
				continue;
			}

			$value = trim( $attrs[ $field ] );

			if ( '' !== $value ) {
				$data[ $field ] = $value;
			}
		}

		$this->collectStrings( $attrs['tools'] ?? null, $data['tools'] );
		$this->collectStrings( $attrs['materials'] ?? null, $data['materials'] );
	}

	/**
	 * Append unique trimmed strings to a collected list.
	 *
	 * Matching is case insensitive on the trimmed value, so Tool and
	 * tool do not print twice. The list caps at 100 entries.
	 *
	 * @param mixed              $raw       Incoming list value.
	 * @param array<int, string> $collected Collected values.
	 */
	private function collectStrings( mixed $raw, array &$collected ): void {
		if ( ! is_array( $raw ) ) {
			return;
		}

		foreach ( $raw as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}

			$value = trim( $item );

			if ( '' === $value ) {
				continue;
			}

			$seen = false;

			foreach ( $collected as $existing ) {
				if ( strtolower( $existing ) === strtolower( $value ) ) {
					$seen = true;
					break;
				}
			}

			if ( $seen ) {
				continue;
			}

			$collected[] = $value;

			if ( count( $collected ) >= 100 ) {
				return;
			}
		}
	}

	/**
	 * Collect valid rows from one HowTo block.
	 *
	 * Rows with both an empty title and empty text are dropped, even
	 * when an image is present, which mirrors the render callback and
	 * the payload path exactly.
	 *
	 * @param array<string, mixed>                                          $block HowTo block.
	 * @param array<int, array{title: string, text: string, image: string}> $rows  Collected rows.
	 */
	private function collectRows( array $block, array &$rows ): void {
		$attrs = $block['attrs'] ?? [];

		if ( ! is_array( $attrs ) ) {
			return;
		}

		$steps = $attrs['steps'] ?? [];

		if ( ! is_array( $steps ) ) {
			return;
		}

		foreach ( $steps as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$title = isset( $row['title'] ) ? trim( (string) $row['title'] ) : '';
			$text  = isset( $row['text'] ) ? trim( (string) $row['text'] ) : '';

			if ( '' === $title && '' === $text ) {
				continue;
			}

			$image = isset( $row['image'] ) ? self::safeImageUrl( (string) $row['image'] ) : '';

			$rows[] = [
				'title' => $title,
				'text'  => $text,
				'image' => $image,
			];
		}
	}

	/**
	 * Valid steps from the payload plus the blocks.
	 *
	 * @param Context $ctx Request context.
	 * @return array<int, array{title: string, text: string, image: string}>
	 */
	private function steps( Context $ctx ): array {
		return $this->howto( $ctx )['steps'];
	}

	/**
	 * Image URL safe for schema output, empty when unsafe.
	 *
	 * Trims the value, rejects script capable schemes before WP sees
	 * them, then runs the WP raw escaper when available so stored URLs
	 * keep their safe shape. Unit test doubles emulate the escaper as
	 * a trim, which is why the explicit scheme check runs both before
	 * and after it.
	 *
	 * @param string $url Raw image value.
	 * @return string The result.
	 */
	private static function safeImageUrl( string $url ): string {
		$clean = trim( $url );

		if ( '' === $clean ) {
			return '';
		}

		if ( 1 === preg_match( '#^\s*(?:javascript|data|vbscript)\s*:#i', $clean ) ) {
			return '';
		}

		if ( function_exists( 'esc_url_raw' ) ) {
			$clean = trim( (string) esc_url_raw( $clean ) );
		}

		if ( '' === $clean ) {
			return '';
		}

		if ( 1 === preg_match( '#^\s*(?:javascript|data|vbscript)\s*:#i', $clean ) ) {
			return '';
		}

		return $clean;
	}

	/**
	 * Whether a total time value matches the ISO 8601 duration shape.
	 *
	 * Accepts week, date, and time designs such as P1W, P1DT2H, PT30M,
	 * and PT1H. Anything else stays out of the schema, and the editor
	 * hint shows the expected shape.
	 *
	 * @param string $value Raw total time value.
	 * @return bool The result.
	 */
	private static function validDuration( string $value ): bool {
		return 1 === preg_match( '/^P(?!$)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(?=\d)(\d+H)?(\d+M)?(\d+S)?)?$/', $value );
	}

	/**
	 * Plain text for schema fields, all markup removed.
	 *
	 * Block level tags become spaces before stripping, so words in
	 * separate paragraphs never merge. Entities are decoded and
	 * whitespace collapses to single spaces, so the schema carries
	 * clean text. Uses the native WP filter when available and falls
	 * back to strip_tags in contexts without WP loaded.
	 *
	 * @param string $text Raw value.
	 * @return string The result.
	 */
	private static function plainText( string $text ): string {
		$spaced = (string) preg_replace( '#<(?:br\s*/?|/(?:p|div|li|h[1-6]|td|tr|blockquote))\s*>#i', ' ', $text );

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$clean = wp_strip_all_tags( $spaced );
		} else {
			$clean = strip_tags( $spaced ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Fallback for contexts without WP loaded, where wp_strip_all_tags is unavailable.
		}

		$clean = html_entity_decode( (string) $clean, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$clean = (string) preg_replace( '/\s+/u', ' ', $clean );

		return trim( $clean );
	}
}
