<?php
/**
 * Meta payload, defaults, sanitization, REST schema.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Metadata;

/**
 * Static, pure helper for the _rankkernel_meta_data payload.
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
    public static function decodeMetaValue(mixed $raw): array {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || '' === $raw) {
            return [];
        }

        if (str_starts_with($raw, 'a:') || str_starts_with($raw, 'O:')) {
            $unserialized = unserialize($raw, [ 'allowed_classes' => false ]);

            if (false !== $unserialized && is_array($unserialized)) {
                return $unserialized;
            }

            return [];
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
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
    public static function sanitize(array $payload): array {
        $defaults = self::defaults();
        $out      = $defaults;

        if (isset($payload['title'])) {
            $out['title'] = sanitize_text_field((string) $payload['title']);
        }

        if (isset($payload['description'])) {
            $out['description'] = sanitize_text_field((string) $payload['description']);
        }

        if (isset($payload['canonical'])) {
            $out['canonical'] = esc_url_raw((string) $payload['canonical']);
        }

        if (isset($payload['robots']) && is_array($payload['robots'])) {
            $robots = $payload['robots'];

            if (array_key_exists('index', $robots)) {
                $out['robots']['index'] = (bool) $robots['index'];
            }

            if (array_key_exists('follow', $robots)) {
                $out['robots']['follow'] = (bool) $robots['follow'];
            }

            if (array_key_exists('noarchive', $robots)) {
                $out['robots']['noarchive'] = (bool) $robots['noarchive'];
            }

            if (array_key_exists('noimageindex', $robots)) {
                $out['robots']['noimageindex'] = (bool) $robots['noimageindex'];
            }

            if (array_key_exists('nosnippet', $robots)) {
                $out['robots']['nosnippet'] = (bool) $robots['nosnippet'];
            }

            if (array_key_exists('max_snippet', $robots)) {
                $val = $robots['max_snippet'];
                $out['robots']['max_snippet'] = (null === $val || '' === $val) ? null : (int) $val;
            }

            if (array_key_exists('max_image_preview', $robots)) {
                $val = $robots['max_image_preview'];
                // max_image_preview may be string like "large", keep as string or null.
                if (null === $val || '' === $val) {
                    $out['robots']['max_image_preview'] = null;
                } elseif (is_numeric($val)) {
                    $out['robots']['max_image_preview'] = (int) $val;
                } else {
                    $out['robots']['max_image_preview'] = sanitize_text_field((string) $val);
                }
            }

            if (array_key_exists('max_video_preview', $robots)) {
                $val = $robots['max_video_preview'];
                $out['robots']['max_video_preview'] = (null === $val || '' === $val) ? null : (int) $val;
            }
        }

        if (isset($payload['og']) && is_array($payload['og'])) {
            $og = $payload['og'];

            if (array_key_exists('title', $og)) {
                $out['og']['title'] = sanitize_text_field((string) $og['title']);
            }

            if (array_key_exists('description', $og)) {
                $out['og']['description'] = sanitize_text_field((string) $og['description']);
            }

            if (array_key_exists('image', $og)) {
                $out['og']['image'] = esc_url_raw((string) $og['image']);
            }

            if (array_key_exists('image_id', $og)) {
                $out['og']['image_id'] = absint($og['image_id']);
            }

            if (array_key_exists('type', $og)) {
                $out['og']['type'] = sanitize_text_field((string) $og['type']);
            }
        }

        if (isset($payload['twitter']) && is_array($payload['twitter'])) {
            $tw = $payload['twitter'];

            if (array_key_exists('card', $tw)) {
                $out['twitter']['card'] = sanitize_text_field((string) $tw['card']);
            }

            if (array_key_exists('title', $tw)) {
                $out['twitter']['title'] = sanitize_text_field((string) $tw['title']);
            }

            if (array_key_exists('description', $tw)) {
                $out['twitter']['description'] = sanitize_text_field((string) $tw['description']);
            }

            if (array_key_exists('image', $tw)) {
                $out['twitter']['image'] = esc_url_raw((string) $tw['image']);
            }

            if (array_key_exists('image_id', $tw)) {
                $out['twitter']['image_id'] = absint($tw['image_id']);
            }
        }

        if (isset($payload['focus_keywords']) && is_array($payload['focus_keywords'])) {
            $keywords = [];

            foreach ($payload['focus_keywords'] as $kw) {
                $clean = sanitize_text_field((string) $kw);
                if ('' !== $clean) {
                    $keywords[] = $clean;
                }
            }

            $out['focus_keywords'] = $keywords;
        }

        if (isset($payload['schema']) && is_array($payload['schema'])) {
            $schema = [];

            foreach (array_values($payload['schema']) as $item) {
                if (is_array($item)) {
                    $schema[] = $item;
                }
            }

            $out['schema'] = $schema;
        }

        if (isset($payload['flags']) && is_array($payload['flags'])) {
            $flags = $payload['flags'];

            if (array_key_exists('pillar', $flags)) {
                $out['flags']['pillar'] = (bool) $flags['pillar'];
            }

            if (array_key_exists('cornerstone', $flags)) {
                $out['flags']['cornerstone'] = (bool) $flags['cornerstone'];
            }

            if (array_key_exists('breadcrumb_title', $flags)) {
                $out['flags']['breadcrumb_title'] = sanitize_text_field((string) $flags['breadcrumb_title']);
            }
        }

        return $out;
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
                    [ 'type' => 'object', 'additionalProperties' => true ],
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
                'canonical'      => [ 'type' => 'string', 'format' => 'uri' ],
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
                        'image'       => [ 'type' => 'string', 'format' => 'uri' ],
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
                        'image'       => [ 'type' => 'string', 'format' => 'uri' ],
                        'image_id'    => [ 'type' => 'integer' ],
                    ],
                ],
                'focus_keywords' => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'string' ],
                ],
                'schema'         => [
                    'type'  => 'array',
                    'items' => [ 'type' => 'object' ],
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
