# GH-13 WordPress Coding Standards Compliance: Final Report

Bringing the RankKernel plugin into compliance with the finalized WordPress Coding Standards ruleset, using the Hybrid path the owner approved: full WordPress formatting, security, i18n, and documentation enforcement, with the documented PSR-4 naming decision preserved and the naming migration deferred.

Date: 2026-09-14
Branch: `GH-13`, 4 commits, not pushed, working tree clean
Base: `main` at `348e1e3`

## Repository State

- Starting branch: `GH-12` (merged to main, `348e1e3`). New work branch `GH-13` created from `main` following the repository `GH-<n>` convention.
- Final branch: `GH-13`
- Working tree: clean

## Configuration

- `phpcs.xml` now uses the finalized ruleset exactly: the full `WordPress` standard, `testVersion` 8.2-, `minimum_supported_wp_version` 6.5, text domain `rankkernel`, `<file>.</file>`, PHP extensions only, and exclusions for `*/vendor/*`, `*/node_modules/*`, `*/build/*`, and `tests/bootstrap.php`.
- Four documented project naming exceptions were added, scoped to `src` and `tests`, as the owner approved: `WordPress.Files.FileName` (PSR-4 file names), `WordPress.NamingConventions.ValidVariableName` (camelCase variables), `WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid` (camelCase methods), and `Universal.Arrays.DisallowShortArraySyntax` (short arrays). These preserve the Composer PSR-4 autoloader and the existing internal API. Every other WordPress sniff is enforced.
- No broad exclusion was added for `src/` or `tests/`, and no security, i18n, or DB sniff was disabled.
- `composer.json` now provides `composer lint` and `composer phpcs` (both run `phpcs --standard=phpcs.xml`), `composer phpcbf`, plus the existing `stan` and `test`.
- `docs/coding-standards.md` was rewritten to state that RankKernel follows the WordPress Coding Standards, that `phpcs.xml` is the source of truth, the exclusions, that production and test code must comply, that PHPCBF may be used for safe fixes, that the four naming exceptions are temporary pending the naming and autoloader migration, and that PHPCS passing is a quality gate and not by itself proof of WordPress.org acceptance.

## Violations

- Initial baseline with the full `WordPress` standard: 44,491 violations in 79 files. Auto-fixable: 39,523. Not auto-fixable: about 4,968.
- After applying the four documented naming exceptions, the residual to fix was 1,870 (1,543 errors, 327 warnings).
- PHPCBF fixed 35,796 violations across 104 files (tabs in place of spaces, function call inner spacing, control structure spacing, Yoda where fixable, cast and operator spacing, alignment).
- The remaining 1,870 were fixed by hand across these categories: documentation (function comments 943, `@var` 145, param tags 129, missing short 97, class comments 59, and more), unused function parameters 196, security InputNotSanitized 72 and MissingUnslash 22, reserved keyword parameter names 45, WordPress alternative functions (strip_tags 32, json_encode 11, serialize 9, others), short ternary 18, hook name underscores 12, global variable overrides 6, and direct custom table queries with caching 8.
- Two genuine security fixes were made: `wp_unslash` added to the `rankkernel_modules` and `authors_exclude_roles` array reads whose loops sanitize but never unslashed.
- Line level `phpcs:ignore` with a specific reason was used only where a sniff cannot understand legitimate code: interface and hook required parameters, prepared custom table queries, WordPress globals that must be read, established public hook names, and stored format fixtures. No sniff was disabled globally.
- Final result: `vendor/bin/phpcs --standard=phpcs.xml` reports zero errors and zero warnings, exit 0.

## Tests

Exact commands and results:

```text
composer test  -> OK (830 tests, 3023 assertions)
composer lint  -> phpcs exit 0, no output
composer stan  -> level 6, no errors
node --check assets/js/redirects-admin.js -> pass
node --check assets/js/monitor-admin.js   -> pass
```

No existing test was deleted or weakened. No public hook, REST namespace, option key, meta key, or text domain was renamed.

## Git

- Branch: `GH-13`
- Commits (4), newest first:
  - `155ebba` GH-13: Adopt the finalized WordPress ruleset and standards scripts (#13)
  - `d8f4d98` GH-13: Refresh coding standards doc for the finalized ruleset (#13)
  - `aa24f86` GH-13: Bring tests into WordPress Coding Standards compliance (#13)
  - `6898ce9` GH-13: Bring src and uninstall into WordPress Coding Standards compliance (#13)
- Pushed: no. Merged: no. Force-push: none. No destructive git commands used.

## GitHub

- GitHub CLI (`gh`) is not installed and not authenticated in this environment, so the issue and the pull request were not created.
- Manual steps remaining:
  1. Create the issue titled `chore: bring RankKernel into strict WordPress Coding Standards compliance` with the objective, scope, out of scope, and acceptance criteria from the task.
  2. Push `GH-13`: `git push -u origin GH-13`.
  3. Open a PR titled `chore: enforce strict WordPress coding standards` with the validation results above and a note that this PR establishes the code quality portion of WordPress.org readiness and is not by itself full WordPress.org approval.

## Remaining Work and Owner Decisions

1. PHP version policy mismatch: the plugin declares `Requires PHP: 8.1` (header and `composer.json` `>=8.1`), while the finalized ruleset sets `testVersion` to `8.2-`. The ruleset was kept as provided per instruction. Decide whether to raise the plugin's declared minimum to 8.2 or set `testVersion` to `8.1-`.
2. Naming migration deferred as approved: converting to WordPress `class-*.php` file names requires switching the Composer autoloader from PSR-4 to a classmap, plus renaming methods and variables to snake_case and converting short arrays to `array()`. This should be its own phase with a plan and approval, because it changes the file layout and internal API.
3. Generated docblock prose is accurate but generic in places. It can be made more descriptive later.
4. This compliance work establishes the coding standards portion of WordPress.org readiness. It is not a full WordPress.org review.
