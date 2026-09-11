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

The project standard is PSR4 file names, camelCase methods and variables, and short array syntax. `phpcs.xml` carries scoped exclusions with justification comments for `WordPress.Files.FileName`, `WordPress.NamingConventions.ValidVariableName`, `WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid`, and `Universal.Arrays.DisallowShortArraySyntax` over `src/` and `tests/` for exactly this reason. Never rename a file or a symbol to satisfy a naming sniff. Never reintroduce a global severity kill.

## 3. Whitespace

New and touched files use WordPress whitespace: real tabs for indentation, inner spacing in control structures (`if ( ... )`) and function calls (`function_call( ... )`), spacing around `=` in assignments, and multiline layout for multi item associative arrays.

Deliberate no mass reformat policy. Files outside the enforcement map keep their current style until a task brings them into compliance. Never run repo wide `phpcbf`. Never reformat a file you did not otherwise change.

## 4. Progressive enforcement map

PSR12 is the default for `src/` and `tests/`. WordPress Extra additionally covers exactly the paths below. Both rules must never cover the same file, because WordPress tabs and inner spacing directly contradict PSR12 spaces, so every path added to the WordPress Extra include list must be mirrored in the PSR12 exclude list in the same commit.

WordPress Extra enforced today:

* `rankkernel.php` and `uninstall.php` (plugin root, enforced from the start)
* `src/Admin/SchemaMetabox.php`
* `src/Admin/SchemaSettingsPage.php`
* `src/Admin/SitemapSettingsPage.php`
* `src/Admin/SettingsPage.php`
* `src/Modules/Metadata/MetaPayload.php`
* `src/Modules/Sitemaps/Provider/PostsProvider.php`
* `src/Modules/Sitemaps/Provider/AuthorsProvider.php`
* `src/Modules/Schema/SchemaModule.php`

Rule for extending the map: the rebuild agents add block files (`FaqBlock.php`, `HowtoBlock.php`, block subfolders) one file per commit, each commit fixing every WordPress Extra finding in that file (real fixes first, line level `phpcs:ignore` with a WordPress specific reason only for safe flows the sniff cannot trace, such as Settings API saves after nonce verification and prepared queries built through argument unpacking), then converting that file to WordPress whitespace, then keeping all three gates green.

Deferred, explicitly out of scope for the PHP pass:

* WordPress Docs enforcement (currently reports 122 findings in 45 files, stays excluded until a dedicated docs pass).
* JS and CSS under `src/blocks` plus `assets/js` (currently unscanned or vacuously scanned). Recommended path: eslint with the WordPress preset for editor scripts plus stylelint for editor styles, recorded here so a later pass can adopt it.

## 5. Block category convention

There is exactly one block category: slug `rankkernel`, title `RankKernel`, single icon, registered centrally in `SchemaModule::boot` through `SchemaModule::addCategory`. Individual blocks must not register their own category copies. (History: `FaqBlock` and `HowtoBlock` each carried an `addCategory` with the same slug but divergent icons. The central registration lands first, the rebuild pass removes the copies.)

## 6. Sanitization convention

Rich text that authors can format (FAQ answers, HowTo step text) filters through `wp_kses_post`, with a `strip_tags` fallback only for contexts without WP loaded. Plain strings (titles, names, costs, URLs aside) keep `sanitize_text_field` or the escaping function matching their context. The payload path (`MetaPayload`) and the block piece path (`FaqPiece`, `HowtoPiece`) must use the same filter for the same field, so one FAQ keeps its formatting regardless of authoring source. Script neutralization is part of the contract and covered by `test_answer_and_step_text_neutralize_script_tags`.

## 7. Gates

Run all three from the plugin root before claiming done:

* `composer lint` (PHPCS over `rankkernel.php`, `uninstall.php`, `src/`, `tests/`)
* `composer stan` (PHPStan level 6 over `src/`)
* `composer test` (PHPUnit, baseline 408 tests and 1702 assertions, report the exact numbers after every run)

Triage helper for scoping new files (read only, changes nothing):

* `vendor/bin/phpcs --standard=WordPress-Extra -s <file>` shows what the next file needs.
* `vendor/bin/phpcbf --standard=phpcs.xml <file>` converts one file after its semantic fixes land. Scope it to listed files only.
