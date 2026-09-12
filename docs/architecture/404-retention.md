# 404 Retention and Growth

Two independent, configurable limits bound the `wp_rankkernel_404_log`
table. Both live in the `rankkernel_404_settings` option and both
clamp into their allowed ranges, so empty or zero values can never
allow uncontrolled growth.

| Setting | Default | Minimum | Maximum |
|---|---|---|---|
| Retention days | 30 | 1 | 365 |
| Maximum rows | 1000 | 100 | 10000 |

## Automatic pruning

`Pruner::prune()` enforces both limits, age first, then count. It runs
on `shutdown` after a 404 insert (scheduled by `Logger` only for new
URIs) and can additionally run opportunistically on the admin list
view.

- Age rule: deletes rows last seen before the retention cutoff,
  bounded to 500 rows per pass (`AGE_BATCH`).
- Count rule: when the table holds more than the maximum, deletes the
  oldest rows, bounded to the excess plus a margin of 20 percent of
  the maximum, capped at 500 rows per pass (`COUNT_BATCH_CAP`). The
  margin amortizes cost so a sustained flood performs one small delete
  per request instead of repruning the full excess every time.
- Deterministic order: oldest first by `last_accessed`, then `id`.
- Never `TRUNCATE`. Never an unbounded `DELETE`. Every delete carries
  an explicit `LIMIT`.

## Manual clear

The Clear Log action on the 404 Monitor screen uses the same bounded
deletion path as pruning, looped until the log is empty or a pass cap
is hit (500 rows per pass, at most 20 passes per request). It requires
`manage_options` plus its own nonce, shows a JavaScript confirmation,
and redirects back with a success notice. Manual clearing is
independent of automatic pruning: it removes entries in bounded
batches without touching the pruning schedule or settings.

## Near-limit thresholds

The usage indicator shows the current count against the maximum as a
count and a bar (`742 / 1000`). `NotFoundPage::limitState()` maps the
usage percent to three states:

- Below 80 percent: normal, no notice.
- 80 percent to below 90 percent: informational notice.
- 90 percent and above: stronger warning notice.

The wording avoids `database full`: `Your 404 log is approaching its
configured entry limit. Oldest entries are automatically removed when
the limit is reached.` The notice renders above the list and the
settings card documents the same policy.

## Growth bounds

The 404 log is always bounded by the maximum rows setting, so it never
grows without limit. Average row size is small (URI text plus counters
plus two optional 255 character fields) and the indexes are narrow
(a 64 character hash plus a `last_accessed` key that keeps every prune
an indexed range instead of a full scan).
