<?php
/**
 * MetaPayload tests.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RankKernel\Modules\Metadata\MetaPayload;

/**
 * Meta Payload Test.
 */
final class MetaPayloadTest extends TestCase {
	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();

		// Realistic sanitizers.
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => trim( strip_tags( $v ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- test asserts plain strip_tags behavior, WordPress is not loaded in unit tests.
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $v ): string => filter_var( $v, FILTER_SANITIZE_URL ) ? filter_var( $v, FILTER_SANITIZE_URL ) : '' );
		Functions\when( 'absint' )->alias( static fn ( mixed $v ): int => abs( (int) $v ) );
	}

	/**
	 * Tear down the test fixture.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test sanitize strips script from title.
	 */
	public function test_sanitize_strips_script_from_title(): void {
		$payload = [ 'title' => '<script>alert(1)</script>Hello' ];
		$clean   = MetaPayload::sanitize( $payload );

		$this->assertStringNotContainsString( '<script>', $clean['title'] );
		$this->assertSame( 'alert(1)Hello', $clean['title'] );
	}

	/**
	 * Test sanitize canonical esc url raw.
	 */
	public function test_sanitize_canonical_esc_url_raw(): void {
		$payload = [ 'canonical' => 'https://example.com/page?q=1' ];
		$clean   = MetaPayload::sanitize( $payload );

		$this->assertSame( 'https://example.com/page?q=1', $clean['canonical'] );
	}

	/**
	 * Test sanitize image id absint.
	 */
	public function test_sanitize_image_id_absint(): void {
		$payload = [ 'og' => [ 'image_id' => '-42' ] ];
		$clean   = MetaPayload::sanitize( $payload );

		$this->assertSame( 42, $clean['og']['image_id'] );
	}

	/**
	 * Test sanitize drops unknown top level key.
	 */
	public function test_sanitize_drops_unknown_top_level_key(): void {
		$payload = [
			'evil'  => 'x',
			'title' => 'Hello',
		];
		$clean   = MetaPayload::sanitize( $payload );

		$this->assertArrayNotHasKey( 'evil', $clean );
		$this->assertSame( 'Hello', $clean['title'] );
	}

	/**
	 * Test sanitize fills missing keys from defaults.
	 */
	public function test_sanitize_fills_missing_keys_from_defaults(): void {
		$clean = MetaPayload::sanitize( [] );

		$this->assertSame( MetaPayload::defaults(), $clean );
	}

	/**
	 * Test sanitize robots max snippet null or int.
	 */
	public function test_sanitize_robots_max_snippet_null_or_int(): void {
		$cleanNull = MetaPayload::sanitize( [ 'robots' => [ 'max_snippet' => null ] ] );
		$this->assertNull( $cleanNull['robots']['max_snippet'] );

		$cleanInt = MetaPayload::sanitize( [ 'robots' => [ 'max_snippet' => '120' ] ] );
		$this->assertSame( 120, $cleanInt['robots']['max_snippet'] );

		$cleanEmpty = MetaPayload::sanitize( [ 'robots' => [ 'max_snippet' => '' ] ] );
		$this->assertNull( $cleanEmpty['robots']['max_snippet'] );
	}

	/**
	 * Test sanitize schema json safe.
	 */
	public function test_sanitize_schema_json_safe(): void {
		$payload = [
			'schema' => [
				[ 'type' => 'Article' ],
				'not-an-array',
				[ 'type' => 'Breadcrumb' ],
				123,
			],
		];
		$clean   = MetaPayload::sanitize( $payload );

		$this->assertCount( 2, $clean['schema'] );
		$this->assertSame( [ 'type' => 'Article' ], $clean['schema'][0] );
		$this->assertSame( [ 'type' => 'Breadcrumb' ], $clean['schema'][1] );
	}

	/**
	 * Test rest schema shape sanity.
	 */
	public function test_rest_schema_shape_sanity(): void {
		$schema = MetaPayload::restSchema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertFalse( $schema['additionalProperties'] );
		$this->assertArrayHasKey( 'title', $schema['properties'] );
		$this->assertArrayHasKey( 'robots', $schema['properties'] );
		$this->assertArrayHasKey( 'og', $schema['properties'] );
		$this->assertArrayHasKey( 'twitter', $schema['properties'] );
		$this->assertArrayHasKey( 'focus_keywords', $schema['properties'] );
		$this->assertArrayHasKey( 'schema', $schema['properties'] );
		$this->assertArrayHasKey( 'flags', $schema['properties'] );
		$this->assertFalse( $schema['properties']['robots']['additionalProperties'] );
	}

	/**
	 * Test defaults shape matches spec.
	 */
	public function test_defaults_shape_matches_spec(): void {
		$defaults = MetaPayload::defaults();

		$this->assertSame( '', $defaults['title'] );
		$this->assertSame( '', $defaults['description'] );
		$this->assertSame( '', $defaults['canonical'] );
		$this->assertTrue( $defaults['robots']['index'] );
		$this->assertTrue( $defaults['robots']['follow'] );
		$this->assertFalse( $defaults['robots']['noarchive'] );
		$this->assertNull( $defaults['robots']['max_snippet'] );
		$this->assertSame( '', $defaults['og']['image'] );
		$this->assertSame( 0, $defaults['og']['image_id'] );
		$this->assertSame( 'summary_large_image', $defaults['twitter']['card'] );
		$this->assertSame( [], $defaults['focus_keywords'] );
		$this->assertSame( [], $defaults['schema'] );
		$this->assertFalse( $defaults['flags']['pillar'] );
	}

	/**
	 * A malformed budget string normalizes to null, never 0.
	 *
	 * 0 is the explicit "no snippet" budget, so coercing an empty or non
	 * numeric value to it would silently change meaning. This locks the
	 * authority contract for both integer budgets.
	 */
	public function test_sanitize_robots_budgets_treat_malformed_as_null(): void {
		$cases = [
			[ null, null ],
			[ '', null ],
			[ 'abc', null ],
			[ '   ', null ],
			[ '120', 120 ],
			[ '0', 0 ],
			[ 45, 45 ],
			[ 0, 0 ],
		];

		foreach ( $cases as $index => $case ) {
			$snippet = MetaPayload::sanitize( [ 'robots' => [ 'max_snippet' => $case[0] ] ] );
			$video   = MetaPayload::sanitize( [ 'robots' => [ 'max_video_preview' => $case[0] ] ] );

			$this->assertSame( $case[1], $snippet['robots']['max_snippet'], 'max_snippet case ' . $index );
			$this->assertSame( $case[1], $video['robots']['max_video_preview'], 'max_video_preview case ' . $index );
		}
	}

	/**
	 * Max image preview keeps its string values and normalizes blanks to null.
	 */
	public function test_sanitize_robots_max_image_preview_values(): void {
		$cases = [
			[ null, null ],
			[ '', null ],
			[ '   ', null ],
			[ 'none', 'none' ],
			[ 'standard', 'standard' ],
			[ 'large', 'large' ],
			[ 120, 120 ],
		];

		foreach ( $cases as $index => $case ) {
			$clean = MetaPayload::sanitize( [ 'robots' => [ 'max_image_preview' => $case[0] ] ] );

			$this->assertSame( $case[1], $clean['robots']['max_image_preview'], 'max_image_preview case ' . $index );
		}
	}

	/**
	 * The declared schema accepts every value the client actually sends.
	 *
	 * The REST layer validates the payload against this schema before the
	 * sanitize callback runs, so a type the schema rejects hard blocks the
	 * whole save. This exercises the real declared type list for the three
	 * robots budgets with the client values and with the defensive empty
	 * and malformed strings, so the save blocking bug cannot return.
	 */
	public function test_rest_schema_accepts_every_client_robots_value(): void {
		$robots = MetaPayload::restSchema()['properties']['robots']['properties'];

		$clientValues = [
			'max_snippet'       => [ null, 0, 120, '', 'abc', '120' ],
			'max_video_preview' => [ null, 0, 30, '', 'abc', '30' ],
			'max_image_preview' => [ null, '', 'none', 'standard', 'large', 120 ],
		];

		foreach ( $clientValues as $field => $values ) {
			$this->assertArrayHasKey( $field, $robots );

			foreach ( $values as $index => $value ) {
				$this->assertTrue(
					$this->accepts_type( $value, $robots[ $field ]['type'] ),
					$field . ' must accept client value #' . $index
				);
			}
		}

		// A genuinely wrong shape stays rejected, so the widening did not
		// turn the schema into a blanket pass.
		$this->assertFalse( $this->accepts_type( [ 'nope' ], $robots['max_snippet']['type'] ) );
		$this->assertFalse( $this->accepts_type( [ 'nope' ], $robots['max_video_preview']['type'] ) );
		$this->assertFalse( $this->accepts_type( [ 'nope' ], $robots['max_image_preview']['type'] ) );
	}

	/**
	 * Whether a declared REST type list accepts a JSON value.
	 *
	 * Mirrors the subset of WordPress REST schema type checking the payload
	 * uses, so the test exercises the real declared types without booting
	 * WordPress.
	 *
	 * @param mixed              $value Value a client may send.
	 * @param array<int, string> $types Allowed type words.
	 * @return bool The result.
	 */
	private function accepts_type( mixed $value, array $types ): bool {
		foreach ( $types as $type ) {
			if ( 'null' === $type && null === $value ) {
				return true;
			}

			if ( 'integer' === $type && is_int( $value ) ) {
				return true;
			}

			if ( 'number' === $type && ( is_int( $value ) || is_float( $value ) ) ) {
				return true;
			}

			if ( 'string' === $type && is_string( $value ) ) {
				return true;
			}

			if ( 'boolean' === $type && is_bool( $value ) ) {
				return true;
			}

			if ( 'array' === $type && is_array( $value ) ) {
				return true;
			}

			if ( 'object' === $type && is_array( $value ) && ! array_is_list( $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Test user prefs schema is permissive.
	 */
	public function test_user_prefs_schema_is_permissive(): void {
		$schema = MetaPayload::userPrefsSchema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertIsArray( $schema['additionalProperties'] );
		// Must allow scalars and array-of-scalars; not strict false.
		$this->assertNotFalse( $schema['additionalProperties'] );
		// Ensure oneOf includes scalar types.
		$oneOf = $schema['additionalProperties']['oneOf'] ?? [];
		$types = array_column( $oneOf, 'type' );
		$this->assertContains( 'string', $types );
		$this->assertContains( 'boolean', $types );
		// Must be different from restSchema's strict false.
		$strict = MetaPayload::restSchema();
		$this->assertFalse( $strict['additionalProperties'] );
	}
}
