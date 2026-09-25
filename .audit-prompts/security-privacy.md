# Audit: Security & Privacy

You are auditing RankKernel, a free WordPress SEO plugin, for security vulnerabilities, privacy problems and WordPress.org plugin-directory guideline violations. Treat all input as hostile, and treat a missing check as a real vulnerability rather than a style issue.

The plugin code is in `src/` and `assets/`. The bootstrap is `rankkernel.php` and the uninstaller is `uninstall.php`. Scan the target directories recursively and read the bootstrap files.

Write findings to the output file `.opencode/audit-security_privacy.jsonl` in JSON Lines format.

## What to Check

### Capability and nonce on every write

Every state change must verify both the capability and a nonce before it acts.

**Bad:**
```php
update_option( 'rankkernel_settings', $_POST['rankkernel_settings'] );
```

**Good:**
```php
if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

check_admin_referer( 'rankkernel_settings' );

$partial = isset( $_POST['rankkernel_settings'] ) ? wp_unslash( (array) $_POST['rankkernel_settings'] ) : [];
$store->set( $partial );
```

Check every `update_option`, `update_post_meta`, `update_term_meta`, `update_user_meta`, `$wpdb->insert`, `$wpdb->update`, `$wpdb->query`, file write, and remote request. Confirm the checks happen before the write, and that each form action has its own nonce action rather than reusing one across unrelated forms.

A REST route must use a `permission_callback` that returns a `WP_Error` with a 403 for an unauthorised caller. Flag a `permission_callback` that returns true unconditionally, or one that only checks `is_user_logged_in()` where an administrator capability is required. CSRF for a cookie-authenticated REST request is core's `wp_rest` nonce carried in the `X-WP-Nonce` header, so do not flag its absence as a missing manual nonce, but do flag a hand-rolled `wp_verify_nonce()` that replaces the core model.

Flag any suppression of the nonce sniffs (`phpcs:ignore WordPress.Security.NonceVerification`) whose justification does not name the caller that genuinely verifies the nonce, or that reads a superglobal on a write path.

### Input sanitization and output escaping

Sanitize on input, escape on output, never trust a superglobal.

**Bad:**
```php
echo '<a href="' . $_GET['url'] . '">' . $_GET['label'] . '</a>';
```

**Good:**
```php
echo '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
```

Require the correct escaper for the context: `esc_html` for text, `esc_attr` for attributes, `esc_url` for URLs, `esc_js` for inline JavaScript, `wp_kses_post` for limited HTML. Flag `esc_html` applied to a URL or an attribute, and flag any unescaped variable in a template, including inside `printf`, `sprintf` and concatenated strings. Flag a value echoed from a database row without escaping, because stored data is untrusted.

### SQL injection

Every query must use `$wpdb->prepare()` with placeholders.

**Bad:**
```php
$wpdb->get_results( "SELECT * FROM {$wpdb->prefix}rankkernel_log WHERE url = '{$url}'" );
```

**Good:**
```php
$wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}rankkernel_log WHERE url = %s", $url ) );
```

Table and column names cannot be parameterized, so confirm they come from a constant or an allowlist and never from input. `$wpdb->prepare()` does not escape `LIKE` wildcards: flag a `LIKE` query whose user value was not passed through `$wpdb->esc_like()`, because an unescaped `%` becomes a wildcard. Flag a dynamically built `ORDER BY` or `LIMIT` that takes its direction or its column from input.

### Unserialization, evaluation and execution

Flag `unserialize()` and `maybe_unserialize()` on a value that can come from a request, a file or an option written from a request, because object injection is possible. `json_decode( $raw, true )` is the safe alternative for structured data. Flag `eval()`, `create_function()`, `assert()` with a string, `system`, `exec`, `shell_exec`, `passthru`, `popen`, `proc_open`, backtick execution, and any `include` or `require` built from a variable. Flag `extract()` in any scope that reaches a template or a query.

### Remote requests and SSRF

Every outbound request must target a known host.

**Bad:**
```php
wp_remote_get( $user_supplied_url );
```

**Good:**
```php
if ( ! in_array( $host, $allowed_hosts, true ) ) {
	return;
}

wp_safe_remote_post( $url, $args );
```

Prefer `wp_safe_remote_*` over `wp_remote_*`, because the safe variants block private and reserved ranges. Flag a request to a URL the user controls. Flag a request that follows redirects when the destination host was not validated, because a redirect can move it off the allowlisted host; `'redirection' => 0` is the correct guard. Flag a missing timeout, and flag a non-blocking request whose failure is never handled.

### Secrets and private data

Flag a real credential committed to the repository: an API key, an authentication token, a password, a private key, a licence key, a session secret, a webhook secret, a database connection string with a password in it, an OAuth client secret, or a private key block. Also flag a local socket path or an absolute local filesystem path, because those leak the developer's environment.

**High entropy alone is NOT evidence of a secret.** This rule exists because a previous run of this audit flagged a public Google Fonts `.woff2` URL recorded in an asset provenance table, purely because the font filename hash looked random. That was a false positive and it cost real time. Do not repeat it.

Treat all of the following as NON-SECRETS unless there is separate contextual evidence that credential material is present:

- public CDN URLs, including `fonts.googleapis.com` and `fonts.gstatic.com`
- font file URLs, image URLs and any other public asset URL
- font hashes, asset fingerprints, and content hashes such as a woff2 filename segment
- public checksums and published digests, for example a integrity hash in a provenance table
- versioned asset identifiers and build identifiers
- provenance, attribution and licence records, including a sources table in a README
- public dependency metadata: a package name, a version, a licence name, a registry URL, a repository URL

Before reporting a hardcoded secret, require contextual evidence. Name the evidence in the finding. Acceptable evidence is any of:

- the value appears in an assignment with a credential-shaped key, such as `api_key`, `secret`, `token`, `password`, `client_secret`, `private_key`, `access_key`, or `auth`
- the value has a recognized credential prefix or shape, such as `ghp_`, `github_pat_`, `sk-`, `xoxb-`, `AKIA`, `AIza`, `eyJ` followed by a base64 JSON payload, or a `-----BEGIN ... PRIVATE KEY-----` block
- the value is used to authenticate a request, sign a payload, decrypt data, or unlock an API
- the value is read from an environment variable or a config file that is itself gitignored, and then committed by mistake

If the evidence is only "it looks random", do not report it. If a value is uncertain, report it as **minor** with the reasoning and the exact file and line, so a human can decide, rather than as critical.

Never propose rotating a value you have not confirmed is a credential. A rotation suggestion on a public URL is a false alarm that erodes trust in every other finding.

Check `.github/workflows/` as well: a secret interpolated into a `run:` step can leak through logs, so confirm secrets come from `${{ secrets.* }}` and are never echoed. Note that a public CDN or font URL in a workflow is not a secret.

Flag logging of a secret or of personal data. A debug log must not contain an API key, a nonce, a session token, an email address, or a stored key value.

### Privacy and data handling

- no IP address is stored
- no user agent or referer is stored unless a setting enables it, and then truncated
- no data leaves the site except a documented, user-initiated service call
- no telemetry, no phone-home, no usage tracking, no remote code loading
- uninstall removes the plugin's options, tables, post meta, term meta and user meta, and respects the stored purge preference

Flag a table or an option that `uninstall.php` does not clean up. A custom table must match the existing `wp_rankkernel_*` sweep pattern.

### File operations and bootstrap guards

Flag direct file writes whose path is built from input, and flag `unlink`, `rename`, `copy`, `file_put_contents` and `fopen` with a variable path. Flag a missing `ABSPATH` guard: `src/` uses `defined( 'ABSPATH' ) || exit;`, `rankkernel.php` uses the braced form, `uninstall.php` uses `defined( 'WP_UNINSTALL_PLUGIN' ) || exit;`.

### WordPress.org directory policy

Flag anything a plugin reviewer would reject: a bundled library without a GPL-compatible licence recorded in a provenance or dependency audit, an undisclosed external service call, a trialware or upsell gate, a licence check, an artificial limit, an admin notice that only promotes the plugin, or a `readme.txt` promise the code does not implement.

## Severity Guide

- **critical**: an exploitable vulnerability. Missing capability or nonce on a write, SQL injection, unescaped stored or reflected output, SSRF to an attacker-controlled host, a leaked secret, uninstall leaving private data behind.
- **important**: a real weakness needing a precondition. Missing sanitization that is currently unexploited, an unjustified suppressed sniff, a remote request without a timeout, missing uninstall coverage for a new table.
- **minor**: defence in depth and documentation. A missing guard on a value that cannot currently be reached, an unclear justification comment, a hardening opportunity.

## Output Format

Write findings to the output file in JSON Lines format:

```jsonl
{"type":"summary","text":"Audited {target_dir}. Found X issues."}
{"type":"issue","severity":"critical|important|minor","file":"relative/path","line":42,"message":"What the issue is","suggestion":"How to fix it","inline":false}
```

Every `issue` record carries a real relative file path and a real line number. Do not report a finding you cannot point at. Do not report one root cause twice: one record, at the earliest line where it appears.

Name the concrete WordPress API or sniff that applies, for example "use `$wpdb->esc_like()`", "use `esc_url()`", "restore the `check_admin_referer()` call", "add the table to the uninstall sweep". A suggestion the author cannot apply directly is not a suggestion.
