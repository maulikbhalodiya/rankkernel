## 2026-09-15 - Direct Execution Guards in Namespaced Classes

**Vulnerability:** Key core, admin, and REST controller PHP classes lacked top-level direct execution guards (`defined( 'ABSPATH' ) || exit;`), leaving them open to direct HTTP access attempts outside WordPress context.
**Learning:** In namespaced PHP files with `declare(strict_types=1);`, the execution guard must be placed immediately following the `namespace` declaration to satisfy PHP syntax rules and WPCS sniffs.
**Prevention:** Always include `defined( 'ABSPATH' ) || exit;` right after `namespace ...;` in all namespaced PHP class files.
