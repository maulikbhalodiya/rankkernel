# Audit: Error Handling & Resilience

You are auditing RankKernel, a free WordPress SEO plugin, for error handling, failure modes and resilience. A WordPress plugin runs inside a shared process on an untrusted host: a fatal error takes the whole site down, and a swallowed error hides a real bug for months. Judge every finding by what happens on the worst day.

The plugin code is in `src/` and `assets/`. Scan the target directories recursively.

Write findings to the output file `.opencode/audit-error_handling_resilience.jsonl` in JSON Lines format.

## What to Check

### No fatal error on any path

A fatal is the worst outcome, because it takes down the whole site rather than one feature.

- Flag a call to a function or method that may not exist without a guard. The plugin supports a test environment without WordPress loaded, so a WordPress function reached from a class that a unit test constructs must be guarded with `function_exists()`.
- Flag an undefined variable used before assignment on a reachable path, an array access without an existence check where the key comes from a stored or remote value, and a property access on a value that may be null.
- Flag a type error waiting to happen: a method declared to take `int` called with a value that can be a string, a non-nullable property assigned a nullable value, a cast that would fail on an array.
- Flag recursion without a depth bound, and a loop over input whose length is not bounded.

**Bad:**
```php
$row = $wpdb->get_row( $sql );
echo $row->url;
```

**Good:**
```php
$row = $wpdb->get_row( $sql );

if ( ! is_object( $row ) || ! isset( $row->url ) ) {
	return '';
}

return (string) $row->url;
```

### Guarding a dependency

A WordPress function, an object cache, a database handle, a filesystem and a remote call can all be absent or disabled.

- Flag a call to `$wpdb` without checking that it exists and that `$wpdb->prefix` is set, because the unit test environment does not provide it.
- Flag a database write whose failure is not checked. `$wpdb->insert()` returns false on failure, and `$wpdb->last_error` holds the reason. A silent failure that loses a row is worse than a logged one.
- Flag a `dbDelta` or a table creation whose result is ignored, and flag a table read that assumes the table exists without checking first.
- Flag an object cache call that assumes a persistent cache, and flag a cache read that assumes the value exists.

### Catching correctly

- Flag an empty `catch` block, and flag a `catch` that logs nothing and returns as if it succeeded, because that converts a bug into silence.
- Flag a `catch ( \Throwable )` that is overly broad where a narrower type would do, and flag one that swallows a programming error together with an expected failure.
- Flag a `catch` whose handler logs the exception message on every request, because a repeated log line on a shared host is a performance problem as well as noise.
- Flag a failure that is logged but never surfaced to the person who can act, where the surface is the admin screen that triggered the action.

**Bad:**
```php
try {
	$this->client()->submit( $urls );
} catch ( \Throwable $error ) {
	// Nothing.
}
```

**Good:**
```php
try {
	return $this->client()->submit( $urls, $source );
} catch ( \Throwable $error ) {
	foreach ( $list as $url ) {
		$this->settings()->logEntry( $url, 0, $source, $error->getMessage() );
	}

	return self::emptyResult();
}
```

### Remote call failure

A remote call can fail in four distinct ways, and each needs a defined outcome: a transport error returned as `WP_Error`, a non-success status code, a slow response, and a success whose body is not what was expected.

- Flag a request whose `WP_Error` is not distinguished from a status code, because an `instanceof \WP_Error` check and a `wp_remote_retrieve_response_code` read are different paths.
- Flag a status code that the code does not map, where an unknown code is treated as success by default. An unknown code must fall to a safe branch, not the happy one.
- Flag a missing timeout, and flag a timeout so long that a slow host holds the request open.
- Flag a response body used without checking its type or shape, for example `json_decode()` whose result is used before it is confirmed to be an array.

### Data integrity

- Flag a write that can leave the store half-updated, where a partial failure leaves invalid state.
- Flag a read that trusts a stored shape. A stored value can be a legacy shape from an older version, a value written by another plugin, or a value a user edited directly in the database. Confirm the read normalises what it finds and never assumes.
- Flag a delete that is not idempotent, meaning a second run behaves differently from the first, and flag a delete with no confirmation on an admin action that cannot be undone.
- Flag a cache that can be stale after a write in the same request. When a write bypasses the cache layer, the memo must be retired in the same call, or a later read in that request returns the old value.
- Flag a value whose length is not clamped before a write to a bounded column, because strict SQL mode drops the row rather than truncating it.

### Admin action failure

- Flag a form handler that does not redirect after a successful write, because a refresh then repeats the action.
- Flag a handler that continues to the next action after a rejected precondition instead of stopping, so a rejected request can still mutate state.
- Flag a partial success reported as a total success, where some input was rejected and the message does not say so.
- Flag a user-facing message that claims more than happened. "Saved" is not the same as "submitted", and "submitted" never means "indexed".

### Scheduled and background work

- Flag an event scheduled without a guard against double scheduling, and flag one whose handler does not handle being run twice.
- Flag a handler that does more work than it can finish inside one request, and flag work that should be chunked.
- Flag a failure in a background job that is never recorded, so a silent job failure is invisible.

## Severity Guide

- **critical**: a reachable fatal, an unchecked write that can lose data, a swallowed error that hides a broken feature, a partial update left in an invalid state, a failure reported as a success.
- **important**: a missing `WP_Error` branch, an unmapped status code falling to success, a missing timeout, a non-idempotent delete, a cache that can go stale, an unclamped value written to a bounded column.
- **minor**: an over-broad catch with a correct handler, a log line that is louder than it needs to be, a missing guard on a value that cannot currently be null.

## Output Format

Write findings to the output file in JSON Lines format:

```jsonl
{"type":"summary","text":"Audited {target_dir}. Found X issues."}
{"type":"issue","severity":"critical|important|minor","file":"relative/path","line":42,"message":"What the issue is","suggestion":"How to fix it","inline":false}
```

Every `issue` record carries a real relative file path and a real line number. Do not report a finding you cannot point at. One record per root cause, at the earliest line where it appears.

For each finding, state the trigger and the outcome: "if `$wpdb` is absent, this call fatals the request", "a 500 response falls to the accepted branch and is logged as success", "`file_put_contents` returns false and the row is lost silently". A finding that does not name the trigger and the outcome is not actionable.
