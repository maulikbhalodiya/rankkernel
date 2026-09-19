<?php
/**
 * AI crawler catalogue and policy semantics.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Robots;

defined( 'ABSPATH' ) || exit;

/**
 * Catalogued AI crawlers with purpose and default policy.
 *
 * A blanket block is wrong: training crawlers, AI search crawlers and
 * user triggered fetchers are separate tokens with separate effects. Each
 * crawler therefore carries a purpose and its own Allow, Block or Custom
 * policy.
 */
final class CrawlerPolicy {
	/**
	 * Emit an explicit allow for this crawler.
	 */
	public const ALLOW = 'allow';

	/**
	 * Emit a full block for this crawler.
	 */
	public const BLOCK = 'block';

	/**
	 * Leave the crawler alone, for the custom rules to decide.
	 */
	public const CUSTOM = 'custom';

	/**
	 * Purpose labels, also the display order in the UI.
	 *
	 * @var array<string, string>
	 */
	public const PURPOSES = [
		'training'   => 'AI training',
		'ai-search'  => 'AI search',
		'user-fetch' => 'User triggered',
		'dataset'    => 'Open dataset',
	];

	/**
	 * Crawler catalogue keyed by a stable slug.
	 *
	 * @var array<string, array{label: string, token: string, purpose: string, default: string, note: string}>
	 */
	private const CRAWLERS = [
		'gptbot'             => [
			'label'   => 'GPTBot (OpenAI)',
			'token'   => 'GPTBot',
			'purpose' => 'training',
			'default' => self::BLOCK,
			'note'    => 'Crawls content for OpenAI model training.',
		],
		'claudebot'          => [
			'label'   => 'ClaudeBot (Anthropic)',
			'token'   => 'ClaudeBot',
			'purpose' => 'training',
			'default' => self::BLOCK,
			'note'    => 'Crawls content for Anthropic model training.',
		],
		'google-extended'    => [
			'label'   => 'Google-Extended',
			'token'   => 'Google-Extended',
			'purpose' => 'training',
			'default' => self::BLOCK,
			'note'    => 'Controls Gemini training only. Blocking it does not affect Google Search or AI Overviews.',
		],
		'applebot-extended'  => [
			'label'   => 'Applebot-Extended',
			'token'   => 'Applebot-Extended',
			'purpose' => 'training',
			'default' => self::BLOCK,
			'note'    => 'Opts out of Apple Intelligence training. Pages stay in search results.',
		],
		'meta-externalagent' => [
			'label'   => 'meta-externalagent (Meta)',
			'token'   => 'meta-externalagent',
			'purpose' => 'training',
			'default' => self::BLOCK,
			'note'    => 'Meta AI training and direct indexing.',
		],
		'amazonbot'          => [
			'label'   => 'Amazonbot',
			'token'   => 'Amazonbot',
			'purpose' => 'training',
			'default' => self::BLOCK,
			'note'    => 'May be used to train Amazon AI models.',
		],
		'bytespider'         => [
			'label'   => 'Bytespider (ByteDance)',
			'token'   => 'Bytespider',
			'purpose' => 'training',
			'default' => self::BLOCK,
			'note'    => 'Training crawler. Compliance with robots.txt is not verified by the operator.',
		],
		'ccbot'              => [
			'label'   => 'CCBot (Common Crawl)',
			'token'   => 'CCBot',
			'purpose' => 'dataset',
			'default' => self::BLOCK,
			'note'    => 'Builds an open dataset widely reused for model training.',
		],
		'oai-searchbot'      => [
			'label'   => 'OAI-SearchBot (OpenAI)',
			'token'   => 'OAI-SearchBot',
			'purpose' => 'ai-search',
			'default' => self::ALLOW,
			'note'    => 'Builds the ChatGPT search index. Blocking it removes you from ChatGPT results.',
		],
		'claude-searchbot'   => [
			'label'   => 'Claude-SearchBot (Anthropic)',
			'token'   => 'Claude-SearchBot',
			'purpose' => 'ai-search',
			'default' => self::ALLOW,
			'note'    => 'Builds the Claude search index.',
		],
		'perplexitybot'      => [
			'label'   => 'PerplexityBot',
			'token'   => 'PerplexityBot',
			'purpose' => 'ai-search',
			'default' => self::ALLOW,
			'note'    => 'Builds Perplexity search results. Not used for training.',
		],
		'chatgpt-user'       => [
			'label'   => 'ChatGPT-User (OpenAI)',
			'token'   => 'ChatGPT-User',
			'purpose' => 'user-fetch',
			'default' => self::ALLOW,
			'note'    => 'Fetches a page when a user asks. May ignore robots.txt by design.',
		],
		'claude-user'        => [
			'label'   => 'Claude-User (Anthropic)',
			'token'   => 'Claude-User',
			'purpose' => 'user-fetch',
			'default' => self::ALLOW,
			'note'    => 'Fetches a page at a user request.',
		],
		'perplexity-user'    => [
			'label'   => 'Perplexity-User',
			'token'   => 'Perplexity-User',
			'purpose' => 'user-fetch',
			'default' => self::ALLOW,
			'note'    => 'Fetches a page at a user request.',
		],
	];

	/**
	 * Crawler catalogue, filterable for new bots.
	 *
	 * @return array<string, array{label: string, token: string, purpose: string, default: string, note: string}>
	 */
	public static function all(): array {
		/**
		 * Filter the catalogued AI crawlers.
		 *
		 * @param array<string, array{label: string, token: string, purpose: string, default: string, note: string}> $crawlers Crawlers keyed by slug.
		 */
		$filtered = apply_filters( 'rankkernel/robots/crawlers', self::CRAWLERS ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, part of the plugin API, must stay stable.

		return ( is_array( $filtered ) && [] !== $filtered ) ? $filtered : self::CRAWLERS;
	}

	/**
	 * Known crawler slugs.
	 *
	 * @return string[]
	 */
	public static function slugs(): array {
		return array_map( 'strval', array_keys( self::all() ) );
	}

	/**
	 * Get one crawler definition.
	 *
	 * @param string $slug Crawler slug.
	 * @return array{label: string, token: string, purpose: string, default: string, note: string}|null The result.
	 */
	public static function get( string $slug ): ?array {
		$all = self::all();

		return $all[ $slug ] ?? null;
	}

	/**
	 * Default policy map, one entry per crawler.
	 *
	 * @return array<string, string> The result.
	 */
	public static function defaults(): array {
		$out = [];

		foreach ( self::all() as $slug => $crawler ) {
			$out[ (string) $slug ] = (string) $crawler['default'];
		}

		return $out;
	}

	/**
	 * Normalise a single policy value.
	 *
	 * @param mixed $value Raw value.
	 * @return string The result.
	 */
	public static function sanitizePolicy( mixed $value ): string {
		$value = is_string( $value ) ? strtolower( $value ) : '';

		return in_array( $value, [ self::ALLOW, self::BLOCK, self::CUSTOM ], true ) ? $value : self::CUSTOM;
	}

	/**
	 * Normalise a policy map to known slugs, defaulting missing crawlers.
	 *
	 * @param mixed $value Raw map.
	 * @return array<string, string> The result.
	 */
	public static function sanitizeMap( mixed $value ): array {
		$stored = is_array( $value ) ? $value : [];
		$out    = [];

		foreach ( self::all() as $slug => $crawler ) {
			$slug         = (string) $slug;
			$out[ $slug ] = array_key_exists( $slug, $stored )
				? self::sanitizePolicy( $stored[ $slug ] )
				: (string) $crawler['default'];
		}

		return $out;
	}
}
