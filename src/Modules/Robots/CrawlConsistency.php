<?php
/**
 * Robots.txt and llms.txt consistency warnings.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Robots;

defined( 'ABSPATH' ) || exit;

/**
 * Pure consistency warnings for the Crawl Signals screens.
 *
 * The job is to inform the site owner, never to choose a crawler policy for
 * them. Blocking a training crawler is the deliberate privacy default and
 * says nothing about llms.txt, so only the AI search crawlers, which build
 * the answers llms.txt exists to feed, can raise a warning. A crawler left
 * on Custom is the owner own rule and stays out of it.
 *
 * Nothing here reads an option, runs a query or caches, so a site with the
 * module off never pays for it.
 */
final class CrawlConsistency {
	/**
	 * Crawler purposes that actually consume llms.txt.
	 *
	 * Training and dataset crawlers collect content for models and never read
	 * llms.txt. User triggered fetchers may ignore robots.txt by design, so a
	 * block there is advisory rather than a conflict.
	 *
	 * @var string[]
	 */
	private const CONSUMER_PURPOSES = [ 'ai-search' ];

	/**
	 * Public route the llms.txt document is served from.
	 */
	private const LLMS_PATH = '/llms.txt';

	/**
	 * Collect actionable warnings for an inconsistent robots and llms setup.
	 *
	 * @param array<string, string> $policies    Crawler slug to policy map.
	 * @param bool                  $llmsEnabled Whether llms.txt is served.
	 * @param string                $robotsText  Effective robots.txt document.
	 * @return string[] Warnings, empty when the setup is consistent.
	 */
	public static function warnings( array $policies, bool $llmsEnabled, string $robotsText ): array {
		if ( ! $llmsEnabled ) {
			return [];
		}

		$warnings = [];
		$blocked  = self::blockedConsumers( $policies );

		if ( [] !== $blocked ) {
			$warnings[] = sprintf(
				/* translators: %s: comma separated list of AI search crawler names. */
				__( 'llms.txt is on, but these AI search crawlers are blocked in robots.txt, so they cannot read it: %s.', 'rankkernel' ),
				implode( ', ', $blocked )
			);
		}

		$disallowed = self::wildcardDisallows( $robotsText );

		if ( in_array( '/', $disallowed, true ) ) {
			$warnings[] = __( 'robots.txt disallows the whole site for the wildcard group, so no crawler can reach llms.txt.', 'rankkernel' );
		} elseif ( self::blocksPath( self::LLMS_PATH, $disallowed ) ) {
			$warnings[] = __( 'robots.txt disallows /llms.txt, so crawlers cannot read the file this screen publishes.', 'rankkernel' );
		}

		return $warnings;
	}

	/**
	 * AI search crawlers explicitly blocked in the policy map.
	 *
	 * A crawler left on Allow, on the default, or on Custom is not reported:
	 * the first two do not block it, and Custom is the owner own rule, which
	 * this class has no business second guessing.
	 *
	 * @param array<string, string> $policies Crawler slug to policy map.
	 * @return string[] Blocked crawler labels.
	 */
	private static function blockedConsumers( array $policies ): array {
		$blocked = [];

		foreach ( CrawlerPolicy::all() as $slug => $crawler ) {
			$slug   = (string) $slug;
			$policy = CrawlerPolicy::sanitizePolicy( $policies[ $slug ] ?? CrawlerPolicy::CUSTOM );

			if ( CrawlerPolicy::BLOCK !== $policy ) {
				continue;
			}

			if ( ! in_array( (string) $crawler['purpose'], self::CONSUMER_PURPOSES, true ) ) {
				continue;
			}

			$blocked[] = (string) $crawler['label'];
		}

		return $blocked;
	}

	/**
	 * Disallow paths declared inside the wildcard user-agent group.
	 *
	 * @param string $robotsText Robots.txt document.
	 * @return string[] Disallowed paths.
	 */
	private static function wildcardDisallows( string $robotsText ): array {
		$paths      = [];
		$inWildcard = false;

		foreach ( explode( "\n", $robotsText ) as $line ) {
			$line = trim( $line );

			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				continue;
			}

			$position = strpos( $line, ':' );

			if ( false === $position ) {
				continue;
			}

			$name  = strtolower( trim( substr( $line, 0, $position ) ) );
			$value = trim( substr( $line, $position + 1 ) );

			if ( 'user-agent' === $name ) {
				$inWildcard = ( '*' === $value );
				continue;
			}

			if ( $inWildcard && 'disallow' === $name && '' !== $value ) {
				$paths[] = $value;
			}
		}

		return $paths;
	}

	/**
	 * Whether a wildcard disallow rule covers a path.
	 *
	 * Wildcard disallow rules match by prefix, so a Disallow of /blog also
	 * covers /blog/post. A bare slash is handled by the caller, which reports
	 * the whole site case with its own wording.
	 *
	 * @param string   $path       Path to test.
	 * @param string[] $disallowed Wildcard disallow paths.
	 * @return bool The result.
	 */
	private static function blocksPath( string $path, array $disallowed ): bool {
		foreach ( $disallowed as $rule ) {
			if ( str_starts_with( $path, $rule ) ) {
				return true;
			}
		}

		return false;
	}
}
