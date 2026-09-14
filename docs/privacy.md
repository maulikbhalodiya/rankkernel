# RankKernel Privacy: Redirects and 404 Monitor

## What is stored

Redirect rules store the normalized source, destination, match type,
status code, hit counter, active flag, and timestamps. No personal
data.

The 404 log stores the normalized URI, its SHA256 hash, hit counts,
and first and last seen timestamps.

## What is never stored

- No IP address is stored by the 404 log, ever. No IP field is read
  anywhere in the Logger, FloodGuard, repository, or admin screen.
- No telemetry, no external requests, no GeoIP, no third party calls.
- Flood protection is per site and time windowed, never per visitor.

## Advanced fields

Referer and user agent capture is optional and off by default. When
the administrator enables it, values truncate to 255 characters and
are treated as untrusted on display (escaped like any other stored
input). Enabling the setting affects new entries; previously stored
rows keep their empty values.

## Retention and control

- Retention is bounded and configurable: 30 days and 1000 rows by
  default, clamped to 1 to 365 days and 100 to 10000 rows. Empty or
  zero values are clamped into range, so the log can never grow
  without limit.
- Automatic pruning removes the oldest entries first, in bounded
  batches, never with a full truncate.
- Manual clear is always available on the 404 Monitor screen with a
  confirmation step, independent of automatic pruning.
- Uninstall with the purge setting on removes every `rankkernel_*`
  option and every `wp_rankkernel_*` table. See
  `docs/architecture/redirects-404-lifecycle.md`.
