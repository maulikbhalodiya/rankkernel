'use strict';

// The PHP bootstrap the parity test runs with `php -r`. It defines the small
// set of WordPress functions the pure engine calls, then runs the real
// Analyzer on each fixture and returns the full check payload. It contains no
// shell calls, which is why it is a JavaScript string and not a PHP file
// scanned by WPCS.
//
// The first statement defines ABSPATH. Every engine class opens with
// `defined( 'ABSPATH' ) || exit;`, and an undefined ABSPATH would make PHP
// exit silently with status 0 and zero bytes of output.
module.exports = [
	'define( "ABSPATH", getcwd() . "/" );',
	'$cwd = getcwd();',
	'if ( file_exists( $cwd . "/vendor/autoload.php" ) ) { require $cwd . "/vendor/autoload.php"; }',
	'if ( ! class_exists( "RankKernel\\\\Modules\\\\Analysis\\\\Analyzer" ) ) {',
	'	require $cwd . "/src/Modules/Analysis/TextStats.php";',
	'	require $cwd . "/src/Modules/Analysis/KeywordMatcher.php";',
	'	require $cwd . "/src/Modules/Analysis/Analyzer.php";',
	'}',
	'if ( ! function_exists( "__" ) ) { function __( $text, $domain = "default" ) { return $text; } }',
	'if ( ! function_exists( "number_format_i18n" ) ) { function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals ); } }',
	'if ( ! function_exists( "wp_parse_url" ) ) { function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); } }',
	'$GLOBALS["rankkernel_parity_strip_accents"] = true;',
	'function remove_accents( $text, $locale = "" ) {',
	'	if ( empty( $GLOBALS["rankkernel_parity_strip_accents"] ) ) { return $text; }',
	'	return strtr( (string) $text, array( "\u00e9" => "e", "\u00e8" => "e", "\u00ea" => "e", "\u00eb" => "e", "\u00e0" => "a", "\u00e1" => "a", "\u00e2" => "a", "\u00e4" => "a", "\u00e3" => "a", "\u00e5" => "a", "\u00ec" => "i", "\u00ed" => "i", "\u00ee" => "i", "\u00ef" => "i", "\u00f2" => "o", "\u00f3" => "o", "\u00f4" => "o", "\u00f6" => "o", "\u00f5" => "o", "\u00f9" => "u", "\u00fa" => "u", "\u00fb" => "u", "\u00fc" => "u", "\u00e7" => "c", "\u00f1" => "n", "\u00c9" => "E", "\u00c8" => "E", "\u00ca" => "E", "\u00c0" => "A", "\u00c1" => "A", "\u00c2" => "A", "\u00c4" => "A", "\u00cd" => "I", "\u00d3" => "O", "\u00da" => "U", "\u00c7" => "C", "\u00d1" => "N", "\u00e6" => "ae", "\u0153" => "oe", "\u00c6" => "AE", "\u0152" => "OE", "\u00df" => "s" ) );',
	'}',
	'$payload = json_decode( (string) stream_get_contents( STDIN ), true );',
	'$fixtures = isset( $payload["fixtures"] ) && is_array( $payload["fixtures"] ) ? $payload["fixtures"] : array();',
	'$out = array();',
	'$analyzer = new \\RankKernel\\Modules\\Analysis\\Analyzer();',
	'foreach ( $fixtures as $fixture ) {',
	'	$input = isset( $fixture["input"] ) && is_array( $fixture["input"] ) ? $fixture["input"] : array();',
	'	$GLOBALS["rankkernel_parity_strip_accents"] = ! array_key_exists( "stripAccents", $fixture ) || false !== $fixture["stripAccents"];',
	'	$mapped = array(',
	'		"keywords" => isset( $input["keywords"] ) ? $input["keywords"] : array(),',
	'		"html" => isset( $input["html"] ) ? $input["html"] : "",',
	'		"title" => isset( $input["title"] ) ? $input["title"] : "",',
	'		"description" => isset( $input["description"] ) ? $input["description"] : "",',
	'		"slug" => isset( $input["slug"] ) ? $input["slug"] : "",',
	'		"site_url" => isset( $input["site_url"] ) ? $input["site_url"] : "",',
	'		"featured_alt" => isset( $input["featured_alt"] ) ? $input["featured_alt"] : ""',
	'	);',
	'	if ( array_key_exists( "used_keywords", $input ) ) { $mapped["used_keywords"] = $input["used_keywords"]; }',
	'	$result = $analyzer->analyze( $mapped );',
	'	$out[ (string) $fixture["id"] ] = array( "score" => $result["score"], "band" => $result["band"], "checks" => $result["checks"], "keywords" => $result["keywords"] );',
	'}',
	'$word = \\RankKernel\\Modules\\Analysis\\TextStats::words( "\u041f\u0440\u0438\u0432\u0435\u0442 \u043c\u0438\u0440" );',
	'$last = end( $word );',
	'$probes = array(',
	'	"rulesVersion" => \\RankKernel\\Modules\\Analysis\\Analyzer::RULES_VERSION,',
	'	"numberFormat" => array( number_format( 1.005, 2 ), number_format( 1.015, 2 ), number_format( 2.5, 2 ) ),',
	'	"wordEdgeTrim" => array( "count" => count( $word ), "last_hex" => bin2hex( (string) $last ), "utf8_valid" => mb_check_encoding( (string) $last, "UTF-8" ) ),',
	'	"entities" => array( bin2hex( html_entity_decode( "&#0;", ENT_QUOTES | ENT_HTML5, "UTF-8" ) ), bin2hex( html_entity_decode( "&#xD800;", ENT_QUOTES | ENT_HTML5, "UTF-8" ) ), bin2hex( html_entity_decode( "&#65535;", ENT_QUOTES | ENT_HTML5, "UTF-8" ) ) ),',
	');',
	'echo json_encode( array( "fixtures" => $out, "probes" => $probes ) );'
].join( '\n' );
