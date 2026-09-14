# RankKernel Coding Standards

Project invariant for every change on every branch. Read this before touching code.

## 1. Global invariant

All code follows the configured WordPress standards. Concretely, every change must satisfy each item below:

* New and modified code must pass PHPCS under `phpcs.xml` with zero errors and zero warnings.
* PHPStan level 6 over `src/` must stay clean. Never weaken the level or add ignores to silence new findings. Fix the code instead.
* Changed behavior ships with tests. Bug fixes add a failing first test, features add coverage for the new path.
* No unnecessary storage growth. New options, meta keys, or cache entries need a stated reason and a cleanup path.
* Module gating preserved. New output or new hooks stay behind the module enable map, and disabled modules stay silent.

## 2. Naming decision, encoded once

RankKernel follows the WordPress Coding Standards. `phpcs.xml` is the single source of truth: it applies the full WordPress ruleset to `rankkernel.php`, `uninstall.php`, `src/`, and `tests/`. Third party and generated paths stay out of the scan: `vendor`, `node_modules`, `build`, and `tests/bootstrap.php`.

The ruleset carries exactly four scoped exceptions, each documented in `phpcs.xml` and each limited to `src/` and `tests/`: `WordPress.Files.FileName` (PSR-4 file names for the Composer autoloader), `WordPress.NamingConventions.ValidVariableName` (camelCase variables), `WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid` (camelCase methods), and `Universal.Arrays.DisallowShortArraySyntax` (short array syntax). These four are the only exclusions, and they are temporary pending the planned naming and autoloader migration. Never add or broaden an exclusion in `phpcs.xml`. Never rename a public hook name, REST route namespace, option key, meta key, or the text domain to satisfy a sniff. Never reintroduce a global severity kill.

## 3. Whitespace

Global rule, mandatory for every new file and every new block of code: use one real tab per indentation level. Never use four spaces. Never mix tabs and spaces in the same file. This applies to PHP, and to HTML, JS, and CSS the project authors.

WordPress whitespace also applies to new and touched code: inner spacing in control structures (`if ( ... )`) and function calls (`function_call( ... )`), spacing around `=` in assignments, and multiline layout for multi item associative arrays.

Enforcement: `phpcs.xml` covers every PHP file under `src/` and `tests/` from the first line, so tab indentation and WordPress spacing apply to all new and touched code. PHPCBF may be used for safe automatic fixes (whitespace, alignment, Yoda order, and similar), scoped to files the change already touches.

Legacy migration: the existing `src/` tree predates this rule and is migrated progressively, file by file, as tasks touch it. Deliberate no mass reformat policy. Never run repo wide `phpcbf`. Never reformat a file you did not otherwise change.

## 4. Compliance expectations

Production and test code are both expected to comply: `vendor/bin/phpcs --standard=phpcs.xml` must report zero errors and zero warnings.

Fix real code first: proper docblocks with short descriptions plus `@param`, `@return`, and `@var` tags; `wp_unslash` with the matching sanitizer; WordPress function equivalents where behavior is identical. A single line `phpcs:ignore` with a specific documented reason is allowed only where a sniff genuinely cannot understand legitimate code, such as hook callback signatures that must keep unused parameters, public hook names that must stay stable, custom table queries the database sniff cannot recognize, test doubles mirroring WordPress signatures, and unit tests running without WordPress loaded. Never disable a sniff globally.

PHPCS passing is a code quality gate. It is not by itself proof of WordPress.org acceptance.

WordPress Docs enforcement applies across `src/` and `tests/`. JS and CSS under `src/blocks` plus `assets/js` remain outside PHPCS. Recommended path: eslint with the WordPress preset for editor scripts plus stylelint for editor styles, recorded here so a later pass can adopt it.

## 5. Block category convention

There is exactly one block category: slug `rankkernel`, title `RankKernel`, single icon, registered centrally in `SchemaModule::boot` through `SchemaModule::addCategory`. Individual blocks must not register their own category copies. (History: `FaqBlock` and `HowtoBlock` each carried an `addCategory` with the same slug but divergent icons. The central registration lands first, the rebuild pass removes the copies.)

## 6. Sanitization convention

Rich text that authors can format (FAQ answers, HowTo step text) filters through `wp_kses_post`, with a `strip_tags` fallback only for contexts without WP loaded. Plain strings (titles, names, costs, URLs aside) keep `sanitize_text_field` or the escaping function matching their context. The payload path (`MetaPayload`) and the block piece path (`FaqPiece`, `HowtoPiece`) must use the same filter for the same field, so one FAQ keeps its formatting regardless of authoring source. Script neutralization is part of the contract and covered by `test_answer_and_step_text_neutralize_script_tags`.

## 7. Gates

Run all three from the plugin root before claiming done:

* `composer lint` (PHPCS over `rankkernel.php`, `uninstall.php`, `src/`, `tests/`)
* `composer stan` (PHPStan level 6 over `src/`)
* `composer test` (PHPUnit, baseline 830 tests and 3023 assertions, report the exact numbers after every run)

Triage helper for scoping single files (read only, changes nothing):

* `vendor/bin/phpcs --standard=phpcs.xml -s <file>` shows what one file needs.
* `vendor/bin/phpcbf --standard=phpcs.xml <file>` applies safe automatic fixes after the semantic fixes land. Scope it to listed files only.
