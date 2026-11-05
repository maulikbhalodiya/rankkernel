# RankKernel — Final Test Report (`fc5c641`)

| Gate | Command | Result |
|---|---|---|
| PHPUnit | `vendor/bin/phpunit` | OK — 2375 tests / 9150 assertions |
| WPCS | `composer lint` | exit 0 |
| PHPStan L6 | `vendor/bin/phpstan analyse --level=6 src/` | [OK] No errors |
| JavaScript | `composer test:js` | 352 pass / 0 fail |
| JS syntax | `node --check` assets/js/*.js | clean |
| Composer validate | `composer validate --strict` | valid |
| Composer audit | `composer audit --locked` | no advisories |
| Plugin Check | exact final ZIP on WP 7.1.4 | 0 errors / 223 warnings |
| CI | PR #421 PHP 8.3/8.4, Psalm, Psalm Security, breadcrumb KSES | pass |

Plugin Check warnings: 203 template-local prefix false positives; 20 custom-table prepared-SQL warnings.

Not independently re-run in this final pass: WP 6.5 staging, multisite, real LiteSpeed. WP 7.1.4 staging and Apache .htaccess testing were performed in prior controlled passes.
