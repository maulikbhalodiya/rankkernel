# Audit: Performance & Efficiency

You are auditing RankKernel, a free WordPress SEO plugin whose stated principle is zero bloat: no per-request query tax, no wasted work, no unnecessary dependency. Treat a performance problem as a correctness problem, because on a shared host every wasted query is paid on every page view.

The plugin code is in `src/` and `assets/`. Scan the target directories recursively.

Write findings to the output file `.opencode/audit-performance_efficiency.jsonl` in JSON Lines format.

## What to Check

### Queries per request

The plugin's core promise is a single option read per request and no query tax on the front end.

- Flag a `get_option()` that runs inside a loop, a filter callback, or a method that a front-end request can reach more than once. Options must be read once and held, the way `ModuleEnableMap` holds its single read.
- Flag any query, `get_option`, `get_post_meta`, `get_term_meta`, `get_user_meta` or `get_transient` on the front end that is inside a `foreach` over posts or terms, because that is the classic N+1 pattern. The fix is a single primed lookup, `update_meta_cache()`, `update_object_term_cache()`, or one query with an `IN` clause.
- Flag a meta query or an `WP_Query` with `meta_query` where a taxonomy query or a direct primed cache read would do.
- Flag `posts_per_page => -1` or an unbounded `get_posts()`, `get_terms()` or `$wpdb` select that returns every row. Any read that can grow with content must be paginated or bounded.
- Flag a `$wpdb` query with no `LIMIT` in a request path that can be reached with a large table.

### Indexes and schema

For every custom table, confirm the indexes match the real queries.

- Flag a query filtered or ordered by a column that has no index in the table's `CREATE TABLE`.
- Flag a redundant index, meaning one that duplicates the leading columns of another index.
- Flag a `TEXT` or `LONGTEXT` column used in a `WHERE` equality or a `LIKE` prefix match where a bounded `VARCHAR` would be faster, and flag a `LIKE '%term%'` pattern that cannot use an index at all.
- Flag an index added without evidence that a real query needs it, because every index costs on write.
- Confirm timestamps are stored consistently, and that a datetime comparison compares against the same timezone the column stores.

### Option autoload

The `alloptions` array is loaded on every request, so anything in it is paid on every page view.

- Flag a large or unbounded value stored with autoload on, such as a log, a cache, a list that grows, or a serialized payload of rows.
- Flag a settings option written with the autoload argument omitted where the value is not tiny, because the default can be autoload yes.
- Flag a per-request value stored in an option where the object cache or a transient belongs.

### Caching

- Flag an expensive read that is repeated and could be held for the request or cached in the object cache keyed by a validator.
- Flag a cache written without a version or validator, so a code change cannot retire it.
- Flag a cache read that could return a stale value after a write in the same request. When a write bypasses the cache layer, the memo must be retired in the same call.
- Flag a remote request made on a page render rather than on a state change, and flag a remote request in a path a visitor can trigger.
- Flag a network call repeated for the same input within one request, where a memo would do.

### Hooks and work on every request

- Flag work attached to a hook that fires very often (`init`, `wp`, `template_redirect`, `wp_head`, `the_content`, `admin_init`, `admin_enqueue_scripts`) that does more than register, and specifically any query, filesystem read or remote call there.
- Flag a shortcode or a content filter that queries on every invocation without caching the result for the post.
- Flag a module or service constructed on every request when it is only needed on one screen or only when the module is enabled. A disabled module must cost nothing: no hooks, no construction, no query.
- Flag an `assets` enqueue that is not gated on its own screen, so another admin screen pays for CSS or JS it does not use.
- Flag a stylesheet or script registered with no version, because it defeats browser caching, and flag a version that is not the single plugin version constant.

### Filesystem and remote

- Flag a filesystem read or write on a request path that runs on every page view, and flag a directory scan of the uploads or plugin directory.
- Flag a `file_get_contents` of a URL, which bypasses the WordPress HTTP API and its caching and error handling.

### Assets

- Flag a font, image or script loaded from an external host, because the plugin ships assets locally and must make no external request from a page render.
- Flag an icon or font set bundled in full where a subset would do, given the plugin markets itself as lightweight.
- Flag a script or style registered on a screen that does not use it.

## Severity Guide

- **critical**: work on every front-end request that grows with content, an unbounded query on a public path, an autoloaded value that grows without limit, an external request from a page render.
- **important**: an N+1 in an admin list, a missing index for a real query, a cache that can go stale, missing screen gating for an asset.
- **minor**: a micro-optimization with no measurable effect, a redundant index, a naming or documentation clarity issue.

## Output Format

Write findings to the output file in JSON Lines format:

```jsonl
{"type":"summary","text":"Audited {target_dir}. Found X issues."}
{"type":"issue","severity":"critical|important|minor","file":"relative/path","line":42,"message":"What the issue is","suggestion":"How to fix it","inline":false}
```

Every `issue` record carries a real relative file path and a real line number. Do not report a finding you cannot point at. One record per root cause.

For each finding, state the measurable cost and the concrete fix: "one query per post in the loop, replace with a single primed `update_meta_cache`", "no index on the `created` column used by the `ORDER BY`, add `KEY created (created)`", "autoload on a value that grows, pass the third argument false". Do not report a micro-optimization as if it were urgent.
