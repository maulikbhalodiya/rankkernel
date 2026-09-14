# Redirect CSV Import and Export Contract

Free in RankKernel, and a differentiator: rival CSV import and export
is premium only. One documented contract is used in both directions,
implemented by `CsvHandler` with storage in `RedirectRepository`.

## Columns

Exact columns in exact order. The header row is required on import and
always emitted on export:

```text
source,target,code,match_type,active,hits,last_accessed
```

| Column | Required | Default | Notes |
|---|---|---|---|
| source | yes | | Full or relative URL as entered, normalized on import |
| target | yes, unless code is 410 or 451 | | Destination URL, validated like the admin form |
| code | no | 301 | One of 301, 302, 307, 410, 451 |
| match_type | no | exact | One of exact, prefix, contains, suffix, wildcard, regex |
| active | no | yes | `yes` or `no` |
| hits | export only | | Ignored on import |
| last_accessed | export only | | Ignored on import |

## Format rules

- UTF-8. An optional BOM is tolerated on read and never emitted on
  write.
- Unix or Windows line endings tolerated on read, Unix on write.
- Comma delimiter, double quote wrapping, doubled quotes for escapes.
- Export leaves in stable id order with the header row first.
- Export reads in bounded batches of 500 (`EXPORT_BATCH`) through
  `export_count` plus `export_batch`, and the admin download streams
  batches straight to the response, so export memory stays flat
  however many rules exist. The string export returns identical bytes.

## Validation

Every imported row passes the same pipeline as the admin form before
its own write: normalization, length caps (2000 characters for source
and target), code and match type checks, destination policy via
`DestinationValidator`, regex compile test mirroring the matcher
length cap (200 characters) and delimiter handling, duplicate handling
on match type plus source hash, loop rejection, then chain warning.

- Malformed rows, invalid URLs, invalid regex, and invalid status
  codes are rejected per row and reported, never fatal. One bad row
  cannot corrupt the rows around it.
- The error report is a summary of created, updated, and skipped
  counts plus a per row error list with the file row number.
- Overlapping patterns produce a warning entry, not a hard failure.

## Duplicate handling

Identical `(match_type, source_hash)` rows update the existing row
when the importer runs with update enabled, or are skipped otherwise.
Duplicates are never inserted twice: the `UNIQUE (match_type,
source_hash)` constraint backs the application check.

## Limits

- Maximum accepted upload size is 2 MB (`MAX_FILE_SIZE`).
- Maximum data rows accepted from one file is 5000 (`MAX_ROWS`),
  configurable per run through the `$maxRows` parameter.
- Rows stream in bounded batches of 200 (`BATCH_SIZE`) with a time
  budget reset between batches, so a large file stays within memory.
- Validation happens before insert, so a partial duplicate is never
  left behind.

## Injection defenses

- Formula injection: imported cells are neutralized by
  `CsvHandler::sanitize_cell()` and exported cells by
  `CsvHandler::escape_cell()`. A leading `=`, `+`, `-`, `@`, tab, or
  carriage return is prefixed with a single quote, so opening the
  file in a spreadsheet can never run a formula.
- Malicious uploads: per row validation, bounded batches, and no code
  execution. The file is parsed as plain CSV and each row passes the
  full redirect validation before any write.
