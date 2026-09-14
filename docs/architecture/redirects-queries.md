# Redirect Query Handling, Status Codes, and Destinations

## Query string policy

- Source matching ignores the query string. The matcher and the hash
  operate on the normalized path only, so `/old?x=1` matches a rule
  with source `/old`.
- A source entered with a query string is stored without it:
  `/old?x=1` normalizes to `/old` before hashing, so the stored row
  and every later match agree.
- Destination query preservation defaults to on. The
  `preserve_query` setting in `rankkernel_redirects_settings`
  defaults to `true` and is editable on the Redirect Manager screen.
- When preservation is on, the incoming query string is appended to
  the destination only when the destination carries no query of its
  own and the rule is a redirect code. A destination query always
  wins over the incoming query.
- When preservation is off, the incoming query string is dropped and
  a destination query still sends untouched.
- The incoming query appends verbatim. Multiple parameters,
  percent encoded bytes, and repeated keys pass through exactly as
  received with no re encoding, no parsing, and no reordering, so
  `/old?a=1&b=2`, `/old?q=%2Fb%20x`, and `/old?a=1&a=2` arrive
  intact.
- Fragments never travel. Browsers do not send fragments, the
  dispatcher strips any fragment defensively before appending, and
  the validator strips destination fragments at save and again at
  send, so a stored `/new#section` sends `/new`.
- Terminal codes 410 and 451 send no `Location` header at all, so
  query preservation does not apply to them.
- Regex rules match on the path only, exactly like every other
  matcher, and the incoming query handling is identical. Capture
  references such as `$1` in a regex target are never substituted:
  the destination sends literally, which the save time loop check
  reports as inconclusive so an administrator verifies it manually.
- The admin form states the policy next to the active flag:
  matching ignores the query string, and the preserved query can be
  changed under Redirect Settings.

## Status codes and destination requirements

| Code | Type | Destination required | Hit counter | Traversal | Headers |
|---|---|---|---|---|---|
| 301 | permanent redirect | yes | yes | has outgoing edge | `Location`, cacheable |
| 302 | temporary redirect | yes | yes | has outgoing edge | `Location`, no cache |
| 307 | temporary redirect, method preserved | yes | yes | has outgoing edge | `Location`, no cache |
| 410 | terminal gone | no | yes | terminal, no outgoing edge | status only, no `Location` |
| 451 | terminal legal | no | yes | terminal, no outgoing edge | status only, no `Location` |

Sending uses `wp_redirect()` with a validated destination for 301,
302, and 307, and `status_header()` plus a minimal translated body for
410 and 451. Temporary codes plus 410 and 451 send
`Cache-Control: no-cache`.

## Destination validation

`DestinationValidator::validate()` runs both at save time (admin form,
CSV import) and again at the send boundary inside
`Redirector::buildDestination()`, so a stored row that predates a
policy change or bypassed the form can never be sent raw.

- Terminal codes accept an empty target. Redirect codes require a
  concrete destination.
- Control characters (CR, LF, NUL through US, DEL) are rejected.
- Schemes pass the WordPress allowed protocols list (`http`, `https`
  in practice). Unsafe schemes such as `javascript:`, `data:`,
  `file:`, and `vbscript:` are rejected.
- Relative destinations normalize to a clean path with the query
  kept and the fragment stripped.
- Absolute URLs on the home host fold to a relative destination.
- Any other host must appear in the `rankkernel/redirect/allowed_hosts`
  filter allowlist (empty by default). External destinations are
  permitted for legitimate SEO use once allowlisted, and the policy is
  documented rather than silently open.
- No server side fetch of the destination ever happens: the response
  is a browser redirect only, so there is no SSRF surface.

The dispatcher sends only the cleaned destination returned by the
validator, never the raw stored input.
