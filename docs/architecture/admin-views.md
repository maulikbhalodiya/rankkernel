# Admin views

How RankKernel renders admin screens. Read this before adding or changing an admin page.

## The rule

Every substantial admin menu or page has a dedicated PHP view template.

```text
src/Admin/
├── SettingsPage.php          controller, logic, view state
├── SitemapSettingsPage.php
├── SchemaSettingsPage.php
├── RedirectsPage.php
├── NotFoundPage.php
├── SchemaMetabox.php
├── AdminMenu.php             menu registration, no HTML of its own
└── Views/
    ├── settings.php
    ├── sitemap-settings.php
    ├── schema-settings.php
    ├── redirects.php
    ├── not-found.php
    └── schema-metabox.php
```

New substantial admin pages must add a `NewPage` class plus `src/Admin/Views/new-page.php`. A new admin screen whose body is a long sequence of `echo` statements is not accepted.

## Responsibility split

The flow is one direction only.

```text
request
  ↓
admin page class      capability, nonce, save, validate, redirect, load, compute
  ↓
prepare variables
  ↓
view template         HTML, labels, controls, tables, escaped output
  ↓
HTML
```

The class owns everything that is not HTML: capability checks, nonce verification, request handling, form processing, validation, redirects, data loading, service calls, value computation, and view state preparation.

The template owns HTML structure and presentation-only PHP: markup, labels, form controls, tables, buttons, notices, accessibility attributes, and escaped dynamic output.

The template must not become the new home for logic. No database queries, no validation, no permission checks, no request processing, no redirects, no service orchestration, no `$this`, and no static calls on plugin classes. If a value needs computing, compute it in the class and pass plain data.

## Loading a template

Prepare explicit variables, then require the template. Nothing more.

```php
public function render(): void {
	$filters   = $this->listFilters();
	$rules     = $this->getRules( $filters );
	$pageUrls  = $this->paginationUrls( $filters );

	require __DIR__ . '/Views/redirects.php';
}
```

There is no template engine, no view factory, and no view-model abstraction. Plain PHP is the template language.

## Template shape

```php
<?php
/**
 * Redirects view.
 *
 * Presentation only. RedirectsPage prepares every variable used below, and owns
 * capability checks, nonce verification, request handling, validation and
 * redirects.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var array<string, mixed> $filters Current list filters.
 * @var array<int, mixed>    $rules   Redirect rows.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
?>
<div class="alignleft actions">
	<label for="rk-filter-status" class="screen-reader-text"><?php echo esc_html__( 'Filter by status', 'rankkernel' ); ?></label>
	<select name="rk_status" id="rk-filter-status">
		<option value="all"<?php echo selected( $filters['status'], 'all', false ); ?>><?php echo esc_html__( 'All statuses', 'rankkernel' ); ?></option>
	</select>
</div>
```

Notes on the shape:

* The guard follows `declare(strict_types=1);` because a template has no namespace, matching `src/Modules/Breadcrumbs/template-tags.php`.
* The `@var` lines are what keep PHPStan level 6 clean, since the analyser sees each template as a standalone file.
* Variables are camelCase, matching the rest of `src/`.
* No closing `?>` is required at the end of the file, and none is added.

## Landmines

These are the mistakes that break the build.

**Only call WordPress functions the renderer already called.** Unit tests use Brain Monkey and stub exactly the functions the original code used. `esc_html_e`, `esc_attr_e` and `_e` are not stubbed anywhere, so calling them raises `Call to undefined function`. Echo the `__` form instead:

```php
<?php echo esc_html__( 'Text', 'rankkernel' ); ?>
```

**Use the three argument form of `selected()` and `checked()`.** The test stubs return a string and do not echo, so the two argument echo form produces nothing and the attribute silently disappears.

```php
<?php echo selected( $current, $value, false ); ?>
<?php echo checked( $flag, true, false ); ?>
```

**Never use a WordPress global name as a template variable.** PHPCS blocks assigning to names such as `$type`, `$post`, `$id`, `$page`, `$paged`, `$args`, `$content`, `$term`, `$taxonomy`, `$format`, `$date`, `$title`, `$hook_suffix`, `$menu` and `$submenu`. Rename to something specific, for example `$schemaType` or `$ruleRow`.

**Move `phpcs:ignore` comments with the code they protect.** Superglobal reads carry an ignore comment listing exact sniffs and a reason. Dropping a sniff from the list turns into a PHPCS error.

**Prepare row data in the class.** Row URLs (`wp_nonce_url`), status labels, per row aria label text, and CSS class lists are computed in the class and passed as array keys. The template reads keys and escapes them.

## What stays inline

Not every echo statement needs a template. A compact metabox fragment or a single row of markup stays inline. The rule targets substantial page rendering, where a screen's whole body is a long `echo` sequence.

Templates are page level. Never add `label.php`, `select.php`, `button.php`, `form.php` or `table.php`. One template per substantial screen.

## Preserving behaviour

This architecture is a refactor boundary, not a licence to change output. Preserve element ids, CSS classes, form field names, hidden inputs, query parameters, action URLs, nonce fields, translated strings, escaping functions, accessibility attributes, label `for` pairing, `data-*` attributes, table structure, and every id the JavaScript reads.

Readable multi-line template markup is expected, so whitespace-only differences from the old echo output are fine. Anything meaningful is not.

## Related

* `docs/coding-standards.md` section 8 states the rule as a project invariant.
* `docs/architecture/404-monitor.md`, `docs/architecture/redirects.md` and the other per feature notes cover the behaviour of the screens themselves.
