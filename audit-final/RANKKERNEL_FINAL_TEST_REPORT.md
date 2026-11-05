# RankKernel — Final Test Report

Source `fc5c6416e9499f3ed93106c1445c9016393d57a8`  
Final ZIP `rankkernel-0.1.0.zip` — 901,122 bytes, 239 files, SHA-256 `0d891a9fc91007a92f4e867dfaaf00acb688ad48a7533e1c5a9db6ecf315bb39`  
Manifest SHA-256 `3c20b8eb73ad1c49414725e0fbce67e9ffc658e9539fe2f3b90bfe89bbfa11d5`

## Automated gates

| Gate | Command | Result |
|---|---|---|
| PHPUnit | `composer test` | **2375 tests / 9150 assertions, OK** |
| PHPCS/WPCS | `composer lint` | exit 0 |
| PHPStan | `composer stan` / `vendor/bin/phpstan analyse --level=6 src/` | `[OK] No errors` |
| JavaScript tests | `composer test:js` | **352 pass / 0 fail** |
| JS syntax | `node --check` all `assets/js/*.js` | clean |
| Composer validation | `composer validate --strict` | valid |
| Composer audit | `composer audit --locked` | no advisories |
| Psalm | CI | pass |
| Psalm Security | CI | pass |
| PHP 8.3 CI | PR #421 | WPCS/PHPStan/PHPUnit pass |
| PHP 8.4 CI | PR #421 | WPCS/PHPStan/PHPUnit pass |
| Breadcrumb KSES integration | CI job | pass |
| Plugin Check, exact final ZIP | WP 7.1.4 + Plugin Check 2.1.0 | **0 errors / 223 warnings** |

## Plugin Check warnings

- 203 `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound`: template-local variables in included views, not globals.
- 20 `PluginCheck.Security.DirectDB.UnescapedDBParameter`: custom-table SQL, values prepared, IDs int-cast, identifiers trusted/validated.

No broad suppression was added.

## Staging

- WordPress 7.1.4, PHP 8.2.29: exact ZIP installed; admin pages load with zero console errors; `debug.log` empty.
- WordPress 6.5.14: breadcrumb KSES integration and prior release flows passed.
- Real Apache 2.4.58: `.htaccess` managed block, custom-rule preservation, 301 interception, backup/restore, deactivation and fail-closed behavior tested earlier.
- LiteSpeed unavailable; not tested.

## Not independently re-run in this pass

- WP 6.5.14 exact final ZIP smoke (earlier release candidate and KSES integration passed).
- Real Apache after the final breadcrumb/schema changes (PHP-only output changes; earlier Apache package tested).
- Multisite and full uninstall purge.

No test result above was fabricated; tests marked CI were run by GitHub Actions on the same commit.
