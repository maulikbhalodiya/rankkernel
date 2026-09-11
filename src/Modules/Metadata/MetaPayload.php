<?php
/**
 * Meta payload, defaults, sanitization, REST schema.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Metadata;

use RankKernel\Modules\Schema\SchemaTypes;

/**
 * Static, pure helper for the _rankkernel_meta_data payload.
 *
 * Schema payload contract (binds all later schema tasks).
 *
 * Fresh rows hold an empty schema list. The first save carrying
 * schema input normalizes it to the object shape below, so readers
 * must accept both shapes and read defensively with null coalescing.
 * Unknown top level schema keys are dropped on save.
 *
 * Object shape: type (supported type name, unknown values fall back
 * to the automatic type and are never emitted raw), fields (string
 * map, max 50 entries, each value max 2000 chars), faq.questions
 * (question and answer pairs, rows with an empty question dropped,
 * max 100 rows), howto (name, steps of title, text and image with
 * rows lacking both title and text dropped and max 100 steps, plus
 * totalTime and cost strings), custom (raw JSON object, JSON safe
 * scalars and arrays only, max depth 5, max 200 keys), carousel and
 * items (ready made node lists, JSON safe scalars only, max 50 items
 * with each item capped at 50 keys, invalid entries dropped).
 */
final class MetaPayload {
	/**
	 * Get default payload shape.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			'title'          => '',
			'description'    => '',
			'canonical'      => '',
			'robots'         => [
				'index'             => true,
				'follow'            => true,
				'noarchive'         => false,
				'noimageindex'      => false,
				'nosnippet'         => false,
				'max_snippet'       => null,
				'max_image_preview' => null,
				'max_video_preview' => null,
			],
			'og'             => [
				'title'       => '',
				'description' => '',
				'image'       => '',
				'image_id'    => 0,
				'type'        => '',
			],
			'twitter'        => [
				'card'        => 'summary_large_image',
				'title'       => '',
				'description' => '',
				'image'       => '',
				'image_id'    => 0,
			],
			'focus_keywords' => [],
			'schema'         => [],
			'flags'          => [
				'pillar'           => false,
				'cornerstone'      => false,
				'breadcrumb_title' => '',
			],
		];
	}

	/**
	 * Decode a stored meta value to a payload array.
	 *
	 * Object typed meta is serialized by core on write, older rows may
	 * hold JSON, so both shapes decode. Anything unreadable yields an
	 * empty array (callers fail open).
	 *
	 * @param mixed $raw Stored meta value.
	 * @return array<string, mixed> Payload array or empty array.
	 */
	public static function decodeMetaValue( mixed $raw ): array {
		if ( is_array( $raw ) ) {
			return $raw;
		}

		if ( ! is_string( $raw ) || '' === $raw ) {
			return [];
		}

		if ( str_starts_with( $raw, 'a:' ) || str_starts_with( $raw, 'O:' ) ) {
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- decodes legacy serialized rows with object creation disabled, JSON handled below.
			$unserialized = unserialize( $raw, [ 'allowed_classes' => false ] );

			if ( false !== $unserialized && is_array( $unserialized ) ) {
				return $unserialized;
			}

			return [];
		}

		$decoded = json_decode( $raw, true );

		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		return [];
	}

	/**
	 * Deep-sanitize a payload array.
	 *
	 * Unknown top-level keys are dropped; missing keys are filled from defaults.
	 *
	 * @param array<string, mixed> $payload Raw payload.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $payload ): array {
		$defaults = self::defaults();
		$out      = $defaults;

		if ( isset( $payload['title'] ) ) {
			$out['title'] = sanitize_text_field( (string) $payload['title'] );
		}

		if ( isset( $payload['description'] ) ) {
			$out['description'] = sanitize_text_field( (string) $payload['description'] );
		}

		if ( isset( $payload['canonical'] ) ) {
			$out['canonical'] = esc_url_raw( (string) $payload['canonical'] );
		}

		if ( isset( $payload['robots'] ) && is_array( $payload['robots'] ) ) {
			$robots = $payload['robots'];

			if ( array_key_exists( 'index', $robots ) ) {
				$out['robots']['index'] = (bool) $robots['index'];
			}

			if ( array_key_exists( 'follow', $robots ) ) {
				$out['robots']['follow'] = (bool) $robots['follow'];
			}

			if ( array_key_exists( 'noarchive', $robots ) ) {
				$out['robots']['noarchive'] = (bool) $robots['noarchive'];
			}

			if ( array_key_exists( 'noimageindex', $robots ) ) {
				$out['robots']['noimageindex'] = (bool) $robots['noimageindex'];
			}

			if ( array_key_exists( 'nosnippet', $robots ) ) {
				$out['robots']['nosnippet'] = (bool) $robots['nosnippet'];
			}

			if ( array_key_exists( 'max_snippet', $robots ) ) {
				$val                          = $robots['max_snippet'];
				$out['robots']['max_snippet'] = ( null === $val || '' === $val ) ? null : (int) $val;
			}

			if ( array_key_exists( 'max_image_preview', $robots ) ) {
				$val = $robots['max_image_preview'];
				// max_image_preview may be string like "large", keep as string or null.
				if ( null === $val || '' === $val ) {
					$out['robots']['max_image_preview'] = null;
				} elseif ( is_numeric( $val ) ) {
					$out['robots']['max_image_preview'] = (int) $val;
				} else {
					$out['robots']['max_image_preview'] = sanitize_text_field( (string) $val );
				}
			}

			if ( array_key_exists( 'max_video_preview', $robots ) ) {
				$val                                = $robots['max_video_preview'];
				$out['robots']['max_video_preview'] = ( null === $val || '' === $val ) ? null : (int) $val;
			}
		}

		if ( isset( $payload['og'] ) && is_array( $payload['og'] ) ) {
			$og = $payload['og'];

			if ( array_key_exists( 'title', $og ) ) {
				$out['og']['title'] = sanitize_text_field( (string) $og['title'] );
			}

			if ( array_key_exists( 'description', $og ) ) {
				$out['og']['description'] = sanitize_text_field( (string) $og['description'] );
			}

			if ( array_key_exists( 'image', $og ) ) {
				$out['og']['image'] = esc_url_raw( (string) $og['image'] );
			}

			if ( array_key_exists( 'image_id', $og ) ) {
				$out['og']['image_id'] = absint( $og['image_id'] );
			}

			if ( array_key_exists( 'type', $og ) ) {
				$out['og']['type'] = sanitize_text_field( (string) $og['type'] );
			}
		}

		if ( isset( $payload['twitter'] ) && is_array( $payload['twitter'] ) ) {
			$tw = $payload['twitter'];

			if ( array_key_exists( 'card', $tw ) ) {
				$out['twitter']['card'] = sanitize_text_field( (string) $tw['card'] );
			}

			if ( array_key_exists( 'title', $tw ) ) {
				$out['twitter']['title'] = sanitize_text_field( (string) $tw['title'] );
			}

			if ( array_key_exists( 'description', $tw ) ) {
				$out['twitter']['description'] = sanitize_text_field( (string) $tw['description'] );
			}

			if ( array_key_exists( 'image', $tw ) ) {
				$out['twitter']['image'] = esc_url_raw( (string) $tw['image'] );
			}

			if ( array_key_exists( 'image_id', $tw ) ) {
				$out['twitter']['image_id'] = absint( $tw['image_id'] );
			}
		}

		if ( isset( $payload['focus_keywords'] ) && is_array( $payload['focus_keywords'] ) ) {
			$keywords = [];

			foreach ( $payload['focus_keywords'] as $kw ) {
				$clean = sanitize_text_field( (string) $kw );
				if ( '' !== $clean ) {
					$keywords[] = $clean;
				}
			}

			$out['focus_keywords'] = $keywords;
		}

		if ( isset( $payload['schema'] ) && is_array( $payload['schema'] ) ) {
			$out['schema'] = self::sanitizeSchema( $payload['schema'] );
		}

		if ( isset( $payload['flags'] ) && is_array( $payload['flags'] ) ) {
			$flags = $payload['flags'];

			if ( array_key_exists( 'pillar', $flags ) ) {
				$out['flags']['pillar'] = (bool) $flags['pillar'];
			}

			if ( array_key_exists( 'cornerstone', $flags ) ) {
				$out['flags']['cornerstone'] = (bool) $flags['cornerstone'];
			}

			if ( array_key_exists( 'breadcrumb_title', $flags ) ) {
				$out['flags']['breadcrumb_title'] = sanitize_text_field( (string) $flags['breadcrumb_title'] );
			}
		}

		return $out;
	}

	/**
	 * Sanitize the schema payload.
	 *
	 * Empty input stays an empty list so fresh rows stay lean. A legacy
	 * flat list keeps array rows only. Any other non empty array is
	 * normalized to the object shape from the class docblock.
	 *
	 * @param array<int|string, mixed> $raw Raw schema value.
	 * @return array<string, mixed>|array<int, mixed>
	 */
	private static function sanitizeSchema( array $raw ): array {
		if ( [] === $raw ) {
			return [];
		}

		if ( array_is_list( $raw ) ) {
			$schema = [];

			foreach ( $raw as $item ) {
				if ( is_array( $item ) ) {
					$schema[] = $item;
				}
			}

			return $schema;
		}

		$faq = $raw['faq'] ?? [];

		if ( ! is_array( $faq ) ) {
			$faq = [];
		}

		return [
			'type'     => SchemaTypes::normalizeOrEmpty( $raw['type'] ?? null ),
			'disabled' => ! empty( $raw['disabled'] ),
			'fields'   => self::sanitizeSchemaFields( $raw['fields'] ?? [] ),
			'faq'      => [
				'questions' => self::sanitizeFaqQuestions( $faq['questions'] ?? [] ),
			],
			'howto'    => self::sanitizeHowto( $raw['howto'] ?? [] ),
			'custom'   => self::sanitizeCustom( $raw['custom'] ?? [] ),
			'carousel' => self::sanitizeNodeList( $raw['carousel'] ?? [] ),
			'items'    => self::sanitizeNodeList( $raw['items'] ?? [] ),
		];
	}

	/**
	 * Sanitize the free form string map for per type overrides.
	 *
	 * String keys only, scalar values cast to string, max 50 entries
	 * with each value capped at 2000 chars. Anything else is dropped.
	 *
	 * @param mixed $raw Raw fields value.
	 * @return array<string, string>
	 */
	private static function sanitizeSchemaFields( mixed $raw ): array {
		if ( ! is_array( $raw ) || [] === $raw ) {
			return [];
		}

		$fields = [];

		foreach ( $raw as $key => $value ) {
			if ( count( $fields ) >= 50 ) {
				break;
			}

			$name = sanitize_text_field( (string) $key );

			if ( '' === $name ) {
				continue;
			}

			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$fields[ $name ] = self::truncate( (string) $value, 2000 );
		}

		return $fields;
	}

	/**
	 * Sanitize FAQ rows.
	 *
	 * Each row keeps a question and answer pair. Rows with an empty
	 * question are dropped. Kept rows cap at 100. The answer keeps
	 * safe rich HTML through the same wp_kses_post path the block
	 * output uses, so formatting survives regardless of source.
	 *
	 * @param mixed $raw Raw questions value.
	 * @return array<int, array{question: string, answer: string}>
	 */
	private static function sanitizeFaqQuestions( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$rows = [];

		foreach ( $raw as $row ) {
			if ( count( $rows ) >= 100 ) {
				break;
			}

			if ( ! is_array( $row ) ) {
				continue;
			}

			$question = isset( $row['question'] ) ? sanitize_text_field( (string) $row['question'] ) : '';

			if ( '' === trim( $question ) ) {
				continue;
			}

			$answer = isset( $row['answer'] ) ? self::kses( (string) $row['answer'] ) : '';

			$rows[] = [
				'question' => $question,
				'answer'   => $answer,
			];
		}

		return array_values( $rows );
	}

	/**
	 * Sanitize the HowTo block.
	 *
	 * Steps keep title, text, and image (image via esc_url_raw). Rows
	 * with both an empty title and empty text are dropped. Kept steps
	 * cap at 100. Name, totalTime, and cost stay plain strings. Step
	 * text keeps safe rich HTML through the same wp_kses_post path
	 * the block output uses, so formatting survives regardless of
	 * source.
	 *
	 * @param mixed $raw Raw howto value.
	 * @return array{name: string, steps: array<int, mixed>, totalTime: string, cost: string}
	 */
	private static function sanitizeHowto( mixed $raw ): array {
		$out = [
			'name'      => '',
			'steps'     => [],
			'totalTime' => '',
			'cost'      => '',
		];

		if ( ! is_array( $raw ) || array_is_list( $raw ) ) {
			return $out;
		}

		if ( isset( $raw['name'] ) ) {
			$out['name'] = sanitize_text_field( (string) $raw['name'] );
		}

		if ( isset( $raw['steps'] ) && is_array( $raw['steps'] ) ) {
			$steps = [];

			foreach ( $raw['steps'] as $row ) {
				if ( count( $steps ) >= 100 ) {
					break;
				}

				if ( ! is_array( $row ) ) {
					continue;
				}

				$title = isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '';
				$text  = isset( $row['text'] ) ? self::kses( (string) $row['text'] ) : '';

				if ( '' === trim( $title ) && '' === trim( $text ) ) {
					continue;
				}

				$image = isset( $row['image'] ) ? esc_url_raw( (string) $row['image'] ) : '';

				$steps[] = [
					'title' => $title,
					'text'  => $text,
					'image' => $image,
				];
			}

			$out['steps'] = array_values( $steps );
		}

		if ( isset( $raw['totalTime'] ) ) {
			$out['totalTime'] = sanitize_text_field( (string) $raw['totalTime'] );
		}

		if ( isset( $raw['cost'] ) ) {
			$out['cost'] = sanitize_text_field( (string) $raw['cost'] );
		}

		return $out;
	}

	/**
	 * Filter rich text through wp_kses_post.
	 *
	 * Mirrors the block piece path so the same content keeps its
	 * formatting regardless of authoring source. Falls back to
	 * strip_tags when WP is not loaded, as in unit tests.
	 *
	 * @param string $text Raw text.
	 */
	private static function kses( string $text ): string {
		if ( function_exists( 'wp_kses_post' ) ) {
			$clean = wp_kses_post( $text );

			if ( is_string( $clean ) ) {
				return $clean;
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- fallback for contexts without WP loaded, where wp_strip_all_tags is also unavailable.
		return strip_tags( $text );
	}

	/**
	 * Sanitize the raw JSON object for advanced users.
	 *
	 * Keeps JSON safe scalars (plus null) and arrays. PHP objects,
	 * resources, and other shapes are dropped. Nesting deeper than 5
	 * levels is cut, total keys cap at 200. Invalid content becomes
	 * an empty array.
	 *
	 * @param mixed $raw Raw custom value.
	 * @return array<string, mixed>
	 */
	private static function sanitizeCustom( mixed $raw ): array {
		if ( ! is_array( $raw ) || [] === $raw ) {
			return [];
		}

		$keys = 0;

		return self::sanitizeCustomArray( $raw, 1, $keys );
	}

	/**
	 * Recurse into a custom value level.
	 *
	 * @param array<int|string, mixed> $raw   Raw level.
	 * @param int                      $depth Current depth, starts at 1.
	 * @param int                      $keys  Running total of kept keys.
	 * @return array<string, mixed>
	 */
	private static function sanitizeCustomArray( array $raw, int $depth, int &$keys ): array {
		$out = [];

		if ( $depth > 5 ) {
			return [];
		}

		foreach ( $raw as $key => $value ) {
			if ( $keys >= 200 ) {
				break;
			}

			if ( is_array( $value ) ) {
				$out[ $key ] = self::sanitizeCustomArray( $value, $depth + 1, $keys );
				++$keys;
			} elseif ( is_scalar( $value ) || null === $value ) {
				$out[ $key ] = $value;
				++$keys;
			}
		}

		return $out;
	}

	/**
	 * Sanitize a ready made node list for carousel and item list output.
	 *
	 * Keeps array entries only, each capped at maxKeys keys with JSON
	 * safe scalars (plus null) and nested arrays kept to depth 5. PHP
	 * objects, resources, and other shapes are dropped. List caps at
	 * maxItems entries. Invalid content becomes an empty array. Lives
	 * here so the Metadata module never depends on Schema classes.
	 *
	 * @param mixed $raw      Raw list value.
	 * @param int   $maxItems Max kept entries.
	 * @param int   $maxKeys  Max kept keys per entry level.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitizeNodeList( mixed $raw, int $maxItems = 50, int $maxKeys = 50 ): array {
		if ( ! is_array( $raw ) || [] === $raw ) {
			return [];
		}

		$out = [];

		foreach ( $raw as $item ) {
			if ( count( $out ) >= $maxItems ) {
				break;
			}

			if ( ! is_array( $item ) ) {
				continue;
			}

			$clean = self::sanitizeNodeLevel( $item, 1, $maxKeys );

			if ( [] !== $clean ) {
				$out[] = $clean;
			}
		}

		return array_values( $out );
	}

	/**
	 * Recurse into one node level, dropping non JSON safe values.
	 *
	 * @param array<int|string, mixed> $raw     Raw level.
	 * @param int                      $depth   Current depth, starts at 1.
	 * @param int                      $maxKeys Max kept keys at this level.
	 * @return array<string, mixed>
	 */
	private static function sanitizeNodeLevel( array $raw, int $depth, int $maxKeys ): array {
		$out = [];

		if ( $depth > 5 ) {
			return [];
		}

		foreach ( $raw as $key => $value ) {
			if ( count( $out ) >= $maxKeys ) {
				break;
			}

			if ( is_array( $value ) ) {
				$out[ $key ] = self::sanitizeNodeLevel( $value, $depth + 1, $maxKeys );
			} elseif ( is_scalar( $value ) || null === $value ) {
				$out[ $key ] = $value;
			}
		}

		return $out;
	}

	/**
	 * Cap a string at a max length, multibyte safe when available.
	 *
	 * @param string $value Raw string.
	 * @param int    $max   Max length in chars.
	 */
	private static function truncate( string $value, int $max ): string {
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $value ) <= $max ) {
				return $value;
			}

			$cut = mb_substr( $value, 0, $max );

			return $cut;
		}

		if ( strlen( $value ) <= $max ) {
			return $value;
		}

		$cut = substr( $value, 0, $max );

		return $cut;
	}

	/**
	 * Get permissive schema for per-user prefs (_rankkernel_user_prefs).
	 *
	 * Allows arbitrary keys with scalar or array-of-scalar values.
	 *
	 * @return array<string, mixed>
	 */
	public static function userPrefsSchema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => [
				'oneOf' => [
					[ 'type' => 'string' ],
					[ 'type' => 'integer' ],
					[ 'type' => 'number' ],
					[ 'type' => 'boolean' ],
					[
						'type'  => 'array',
						'items' => [
							'oneOf' => [
								[ 'type' => 'string' ],
								[ 'type' => 'integer' ],
								[ 'type' => 'number' ],
								[ 'type' => 'boolean' ],
							],
						],
					],
					[
						'type'                 => 'object',
						'additionalProperties' => true,
					],
				],
			],
		];
	}

	/**
	 * Get REST schema for register_meta show_in_rest.
	 *
	 * @return array<string, mixed>
	 */
	public static function restSchema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => [
				'title'          => [ 'type' => 'string' ],
				'description'    => [ 'type' => 'string' ],
				'canonical'      => [
					'type'   => 'string',
					'format' => 'uri',
				],
				'robots'         => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => [
						'index'             => [ 'type' => 'boolean' ],
						'follow'            => [ 'type' => 'boolean' ],
						'noarchive'         => [ 'type' => 'boolean' ],
						'noimageindex'      => [ 'type' => 'boolean' ],
						'nosnippet'         => [ 'type' => 'boolean' ],
						'max_snippet'       => [ 'type' => [ 'integer', 'null' ] ],
						'max_image_preview' => [ 'type' => [ 'string', 'integer', 'null' ] ],
						'max_video_preview' => [ 'type' => [ 'integer', 'null' ] ],
					],
				],
				'og'             => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => [
						'title'       => [ 'type' => 'string' ],
						'description' => [ 'type' => 'string' ],
						'image'       => [
							'type'   => 'string',
							'format' => 'uri',
						],
						'image_id'    => [ 'type' => 'integer' ],
						'type'        => [ 'type' => 'string' ],
					],
				],
				'twitter'        => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => [
						'card'        => [ 'type' => 'string' ],
						'title'       => [ 'type' => 'string' ],
						'description' => [ 'type' => 'string' ],
						'image'       => [
							'type'   => 'string',
							'format' => 'uri',
						],
						'image_id'    => [ 'type' => 'integer' ],
					],
				],
				'focus_keywords' => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
				'schema'         => [
					'type'       => 'object',
					'properties' => [
						'type'     => [ 'type' => 'string' ],
						'fields'   => [
							'type'                 => 'object',
							'additionalProperties' => [ 'type' => 'string' ],
						],
						'faq'      => [
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => [
								'questions' => [
									'type'  => 'array',
									'items' => [
										'type'       => 'object',
										'additionalProperties' => false,
										'properties' => [
											'question' => [ 'type' => 'string' ],
											'answer'   => [ 'type' => 'string' ],
										],
									],
								],
							],
						],
						'howto'    => [
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => [
								'name'      => [ 'type' => 'string' ],
								'steps'     => [
									'type'  => 'array',
									'items' => [
										'type'       => 'object',
										'additionalProperties' => false,
										'properties' => [
											'title' => [ 'type' => 'string' ],
											'text'  => [ 'type' => 'string' ],
											'image' => [
												'type'   => 'string',
												'format' => 'uri',
											],
										],
									],
								],
								'totalTime' => [ 'type' => 'string' ],
								'cost'      => [ 'type' => 'string' ],
							],
						],
						'custom'   => [ 'type' => 'object' ],
						'carousel' => [
							'type'  => 'array',
							'items' => [ 'type' => 'object' ],
						],
						'items'    => [
							'type'  => 'array',
							'items' => [ 'type' => 'object' ],
						],
					],
				],
				'flags'          => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => [
						'pillar'           => [ 'type' => 'boolean' ],
						'cornerstone'      => [ 'type' => 'boolean' ],
						'breadcrumb_title' => [ 'type' => 'string' ],
					],
				],
			],
		];
	}
}
