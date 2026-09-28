<?php
/**
 * Autoloading contract for the plugin's own namespace.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the autoload mapping the plugin registers for itself.
 *
 * Rankkernel.php registers a PSR-4 autoloader for the RankKernel namespace
 * pointing at src/, so the plugin loads on a site where the files were
 * deployed without running composer install. These tests protect that
 * contract: the mapping must hold for every class file, and the registration
 * must stay in place, because removing either one brings back a class not
 * found fatal on activation for every copy, zip and git deploy.
 */
final class PluginAutoloadTest extends TestCase {
	/**
	 * Plugin root directory.
	 *
	 * @return string The result.
	 */
	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Every class file must sit where the plugin's own PSR-4 mapping expects it.
	 *
	 * The fallback autoloader turns RankKernel\A\B into src/A/B.php. A file
	 * whose namespace or class name does not match its location would load
	 * under Composer's classmap in development and then vanish in production,
	 * so this walks the tree and asserts the mapping holds for real.
	 */
	public function test_every_class_file_matches_the_plugin_psr4_mapping(): void {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $this->root() . '/src', \FilesystemIterator::SKIP_DOTS )
		);

		$checked = 0;
		$skipped = 0;

		foreach ( $iterator as $file ) {
			if ( ! $file instanceof \SplFileInfo || 'php' !== $file->getExtension() ) {
				continue;
			}

			$source = (string) file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads a local plugin source file, not a remote URL.

			if ( 1 !== preg_match( '/^namespace\s+([^;]+);/m', $source, $ns ) ) {
				++$skipped;
				continue;
			}

			if ( 1 !== preg_match( '/^(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+([A-Za-z0-9_]+)/m', $source, $decl ) ) {
				++$skipped;
				continue;
			}

			$fqcn = trim( $ns[1] ) . '\\' . $decl[1];

			$expected = $this->root() . '/src/' . str_replace( '\\', '/', substr( $fqcn, strlen( 'RankKernel\\' ) ) ) . '.php';
			$actual   = (string) realpath( $file->getPathname() );

			$this->assertStringStartsWith(
				'RankKernel\\',
				$fqcn,
				'only the plugin namespace is autoloaded by the fallback mapping.'
			);
			$this->assertSame(
				(string) realpath( $expected ),
				$actual,
				"{$fqcn} must live at the path the plugin's own PSR-4 mapping derives, or it loads in development and disappears without composer."
			);

			++$checked;
		}

		$this->assertGreaterThan( 100, $checked, 'the walk must actually reach the class files.' );
		$this->assertGreaterThan( 0, $skipped, 'function only files are expected and are skipped.' );
	}

	/**
	 * Rankkernel.php must register its own autoloader, not only Composer's.
	 *
	 * This is the guard against the exact regression that broke activation:
	 * relying solely on vendor/autoload.php means a deploy without composer
	 * registers no autoloader at all and fatals on the first class reference.
	 */
	public function test_rankkernel_php_registers_its_own_autoloader_before_composer(): void {
		$source = (string) file_get_contents( $this->root() . '/rankkernel.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads the plugin entry file, not a remote URL.

		$fallback = strpos( $source, 'spl_autoload_register' );
		$composer = strpos( $source, "'vendor/autoload.php'" );

		$this->assertNotFalse( $fallback, 'rankkernel.php must register a fallback autoloader for its own namespace.' );
		$this->assertNotFalse( $composer, 'rankkernel.php must still load the Composer autoloader when it is present.' );
		$this->assertLessThan(
			$composer,
			$fallback,
			'the fallback must be registered before the Composer require so a missing vendor directory cannot leave the plugin without any autoloader.'
		);
	}

	/**
	 * The plugin must declare no Composer runtime dependency.
	 *
	 * The fallback is only correct while the plugin needs nothing from vendor/
	 * at runtime. If a runtime requirement is ever added, this test fails and
	 * forces a deliberate decision, because then a vendor-less deploy really
	 * would be incomplete.
	 */
	public function test_composer_declares_no_runtime_dependency(): void {
		$raw      = (string) file_get_contents( $this->root() . '/composer.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads the plugin manifest, not a remote URL.
		$composer = json_decode( $raw, true );

		$this->assertIsArray( $composer );
		$this->assertArrayHasKey( 'require', $composer );

		$require = is_array( $composer['require'] ) ? $composer['require'] : [];

		$this->assertSame(
			[ 'php' ],
			array_keys( $require ),
			'the fallback autoloader is only safe while "require" holds nothing but the PHP version.'
		);

		$this->assertSame(
			[ 'RankKernel\\' => 'src/' ],
			$composer['autoload']['psr-4'] ?? [],
			'the PSR-4 mapping the fallback mirrors must stay exactly this.'
		);
	}
}
