<?php
/**
 * Real WordPress KSES integration smoke test for breadcrumb output.
 *
 * Run with `wp eval-file tests/Integration/breadcrumb-kses.php` after
 * activating RankKernel. This intentionally uses WordPress KSES and
 * WP_HTML_Tag_Processor instead of test doubles.
 *
 * @package RankKernel
 */

if ( ! function_exists( 'RankKernel\Modules\Breadcrumbs\sanitize_breadcrumb_html' ) ) {
	require_once dirname( __DIR__, 2 ) . '/src/Modules/Breadcrumbs/functions.php';
}

if ( ! function_exists( 'RankKernel\Modules\Breadcrumbs\sanitize_breadcrumb_html' ) ) {
	$message = 'BREADCRUMB_KSES_INTEGRATION_FAIL: sanitizer not loaded';
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::error( $message );
	}
	fwrite( STDERR, $message . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI fallback outside WP-CLI.
	exit( 1 );
}

$failures = [];

/**
 * Assert a condition and record a failure.
 *
 * @param bool   $condition Condition.
 * @param string $label     Failure label.
 * @param string $html      Output for diagnostics.
 * @return void
 */
$assert = static function ( bool $condition, string $label, string $html ) use ( &$failures ): void {
	if ( ! $condition ) {
		$failures[] = $label . ' :: ' . substr( $html, 0, 500 );
	}
};

$safe     = '<nav class="rk-breadcrumbs" aria-label="Breadcrumbs" style="--rk-breadcrumb-separator:&quot;/&quot;;"><ol class="rk-breadcrumbs-list"><li class="rk-breadcrumbs-item"><a href="/about/">About</a></li><li class="rk-breadcrumbs-item"><a href="../up">Up</a></li><li class="rk-breadcrumbs-item"><a href="?q=1">Query</a></li><li class="rk-breadcrumbs-item"><a href="#anchor">Anchor</a></li></ol></nav>';
$safe_out = \RankKernel\Modules\Breadcrumbs\sanitize_breadcrumb_html( $safe );

$assert( false !== strpos( $safe_out, '<nav' ), 'safe nav missing', $safe_out );
$assert( false !== strpos( $safe_out, 'class="rk-breadcrumbs"' ), 'safe nav class missing', $safe_out );
$assert( false !== strpos( $safe_out, '--rk-breadcrumb-separator:&quot;/&quot;' ), 'safe separator style missing', $safe_out );
$assert( false !== strpos( $safe_out, '--rk-breadcrumb-separator' ), 'safe style missing', $safe_out );
$assert( false !== strpos( $safe_out, 'href="/about/"' ), 'safe absolute path missing', $safe_out );
$assert( false !== strpos( $safe_out, 'href="../up"' ), 'safe relative path missing', $safe_out );
$assert( false !== strpos( $safe_out, 'href="?q=1"' ), 'safe query missing', $safe_out );
$assert( false !== strpos( $safe_out, 'href="#anchor"' ), 'safe anchor missing', $safe_out );

$unsafe     = '<nav class="rk-breadcrumbs"><ol class="rk-breadcrumbs-list"><li><a href="javascript:alert(1)" onclick="alert(2)">Click</a><a href="//evil.example/x">External</a><script>alert(3)</script><img src="x" onerror="alert(4)"><span style="color:red">Bad</span></li></ol></nav>';
$unsafe_out = \RankKernel\Modules\Breadcrumbs\sanitize_breadcrumb_html( $unsafe );

$assert( false === strpos( $unsafe_out, '<script' ), 'script tag survived', $unsafe_out );
$assert( false === strpos( $unsafe_out, '<img' ), 'img tag survived', $unsafe_out );
$assert( false === strpos( $unsafe_out, 'onerror' ), 'onerror survived', $unsafe_out );
$assert( false === strpos( $unsafe_out, 'onclick' ), 'onclick survived', $unsafe_out );
$assert( false === strpos( $unsafe_out, 'javascript:' ), 'javascript URL survived', $unsafe_out );
$assert( false === strpos( $unsafe_out, '//evil.example' ), 'protocol-relative URL survived', $unsafe_out );
$assert( false === strpos( $unsafe_out, 'color:red' ), 'unsafe style survived', $unsafe_out );

if ( [] !== $failures ) {
	$message = 'BREADCRUMB_KSES_INTEGRATION_FAIL: ' . implode( ' | ', $failures );
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::error( $message );
	}
	fwrite( STDERR, $message . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI fallback outside WP-CLI.
	exit( 1 );
}

echo "BREADCRUMB_KSES_INTEGRATION_OK\n";
