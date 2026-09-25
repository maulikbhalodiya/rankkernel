# Audit: Code Quality & Conventions

You are auditing RankKernel, a free WordPress SEO plugin, for code quality, standards compliance and WordPress.org plugin-directory review readiness. Report only findings an author can act on directly, and require the code you propose to be clean by the same standard you enforce.

The plugin code is in `src/` and `assets/`. The bootstrap is `rankkernel.php` and the uninstaller is `uninstall.php`. Scan the target directories recursively.

Write findings to the output file `.opencode/audit-code_quality_conventions.jsonl` in JSON Lines format.

## What to Check

### File and class structure

Every PHP file under `src/` must open with a file docblock carrying `@package RankKernel` and `@license GPL-2.0-or-later`, then `declare( strict_types=1 );`, then the `RankKernel\` namespace, then `defined( 'ABSPATH' ) || exit;`. One class per file, and the file name must match the class name.

**Bad:**
```php
namespace RankKernel\Modules\Foo;

class Bar {
```
The file is `Bar.php` but the class is in a folder that does not match, or the guard is missing.

**Good:**
```php
/**
 * Foo bar.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace RankKernel\Modules\Foo;

defined( 'ABSPATH' ) || exit;

final class Bar {
```

Flag a class that is not `final` unless it is genuinely designed for extension, and flag a file whose name and class disagree.

### Naming and prefixes

Every function, hook, option, transient, table, post meta key, term meta key and user meta key must carry the `rankkernel` or `RankKernel` prefix. The text domain is exactly `rankkernel`. Flag a global function without the prefix, a hook name that is not namespaced, and any stored key that does not use the prefix.

### Type declarations

The plugin targets PHP 8.1 with `declare(strict_types=1)`.

- Flag a method without a declared parameter type or return type where a type is knowable.
- Flag `mixed` used where a narrower type would do, and flag an `array` type without a shape docblock where the shape matters.
- Flag any type or error suppression: `@ts-ignore` equivalent, `@phpstan-ignore`, a widened baseline, a changed `phpstan.neon`, or `// phpcs:ignore` added to silence a real problem rather than a documented false positive. A suppression is only acceptable when it names the exact sniff and a justification that a reviewer can verify.
- Flag an empty `catch` block, a `catch` that swallows an error without logging or rethrowing, and a `catch ( \Throwable )` whose handler does nothing.
- Flag `defined()` guards missing around a WordPress function that is unavailable in the unit test environment, because the tests run without WordPress loaded.

### Docblocks

Every class, method, property and constant carries a WordPress docblock: a short description, and `@param`, `@return` and `@var` tags that match the real types.

**Bad:**
```php
/** Get the key. */
public function getKey() {
```

**Good:**
```php
/**
 * Get the stored key.
 *
 * @return string The result.
 */
public function getKey(): string {
```

Flag a docblock whose `@param` or `@return` contradicts the signature, which is worse than no docblock, and flag a docblock that describes behaviour the code does not have.

### WordPress standards that phpcs enforces

- one real tab per indentation level, never spaces
- WordPress spacing inside control structures `if ( $x ) {` and function calls `foo( $bar )`
- Yoda conditions, `true === $flag`
- `elseif` rather than `else if`
- strict comparison when the types are known
- single quotes for a string without interpolation
- no trailing whitespace
- aligned array arrows in a multi line array
- a blank line after the opening PHP tag

Report a violation with the exact sniff name, the file and the line, for example `WordPress.PHP.YodaConditions` at `src/Foo.php:42`. Never propose adding a suppression or editing `phpcs.xml`. Never propose lowering PHPStan below level 6, weakening a test, or running a repo wide formatter.

### WordPress API over raw PHP

- `wp_parse_url` rather than `parse_url`, `wp_remote_*` rather than `curl`, `wp_json_encode` rather than `json_encode`, `wp_rand` rather than `rand`, `wp_generate_password` rather than a hand-rolled generator
- the Filesystem API rather than raw `file_put_contents` in a plugin path
- `wp_kses_post` and the escaping helpers rather than manual string handling
- `$wpdb->prepare()` for every query

Flag a raw PHP call where the WordPress equivalent exists and is safe, and say which helper replaces it.

### Internationalisation

Every user-facing string must be translatable and use the exact `rankkernel` domain. Flag a hardcoded English string in rendered output, in an admin notice, in a button label, in a form label, in an `aria-label`, and in a `title` attribute. Flag an `sprintf` with a translator comment missing where a placeholder is present, and flag a translators comment whose placeholder does not match.

### Separator and punctuation style

The plugin forbids a standalone dash as a pause: no em dash, no en dash, and no hyphen used as a pause, in code, comments or strings. A hyphen is allowed inside a compound word and inside an identifier. Flag a standalone dash used as a pause in a comment, a docblock, a string or documentation.

### Accessibility

- every form control has a label, either a visible `<label for>` or a `screen-reader-text` label
- every button that carries only an icon has an `aria-label`
- an interactive control is reachable and operable by keyboard, and shows a visible focus state
- a status change is announced, using `role="status"` or `role="alert"` where appropriate
- a table has `scope` on its header cells
- colour is never the only carrier of meaning, so a status is a shape and a label as well as a colour

Flag a missing label, a missing `aria-label` on an icon-only control, a lost focus style, and a status communicated only by colour.

### Dead code and drift

Flag a class, method, constant or file with no caller, a constant that duplicates a value defined elsewhere, and two lists that must stay in sync with nothing proving they do. Flag a docblock that still describes removed behaviour, a comment that names a class or file that no longer exists, and an option or a table left behind after the code that used it was replaced.

### WordPress.org review readiness

Flag a `readme.txt` whose stable tag, tested-up-to version, or feature list disagrees with the code. Flag a feature described as complete that is a visual shell or is unreachable. Flag a file with a competing licence header, a bundled asset without provenance, or an external dependency that is not GPL compatible.

## Severity Guide

- **critical**: a broken contract. A missing `ABSPATH` guard, a docblock that contradicts the signature, a stored key without the prefix, a hardcoded user-facing string, a `readme.txt` promise the code does not keep.
- **important**: a standards violation that would fail the gate or the review, a duplicated constant with drift risk, an accessibility gap, a suppression without justification.
- **minor**: naming clarity, a docblock polish, a dead branch that cannot currently be reached, a style nit with no functional effect.

## Output Format

Write findings to the output file in JSON Lines format:

```jsonl
{"type":"summary","text":"Audited {target_dir}. Found X issues."}
{"type":"issue","severity":"critical|important|minor","file":"relative/path","line":42,"message":"What the issue is","suggestion":"How to fix it","inline":false}
```

Every `issue` record carries a real relative file path and a real line number. Do not report a finding you cannot point at. One record per root cause.

Name the exact sniff or the exact replacement API. A finding that says "improve the style" is not actionable and must not be reported. Any code you propose in a `suggestion` must itself satisfy the rules above: real tabs, WordPress spacing, Yoda conditions, a complete docblock, and the `rankkernel` text domain.
