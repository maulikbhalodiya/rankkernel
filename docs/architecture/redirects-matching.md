# Redirect Matching and Normalization

## Matcher precedence

`Matcher::match()` resolves a normalized request path in a fixed order.
The order is both the precedence and the performance strategy: cheap
string tiers run before the expensive regex tier.

1. Exact. Hash equality on `(match_type, source_hash)`. One indexed
   lookup through `RedirectRepository::lookup()`.
2. Prefix. Longest source wins (`str_starts_with` over the candidate
   set).
3. Wildcard. One glob per rule, translated to a bounded expression
   internally, never user supplied regex.
4. Contains. Plain `strpos` substring search.
5. Suffix. Plain `str_ends_with` comparison.
6. Regex. Last. Capped count, capped length, fail closed.

The final tiebreak inside every tier is the lowest rule id. There is no
stored priority or order column: specificity plus id keeps the outcome
deterministic. Overlapping patterns across tiers are allowed and
produce a warning only, never a hard failure.

Exact duplicates are impossible by construction: the table carries
`UNIQUE KEY match_source (match_type, source_hash)`, so identical
normalized duplicates are rejected by the database even under a race.
`RedirectRepository::prepareRow()` normalizes and hashes before every
insert and update, and the admin save plus CSV import check
`lookup()` first to report a friendly duplicate message.

Candidate loading: exact resolves with one indexed query. Pattern rules
(prefix, wildcard, contains, suffix, regex) load through
`RedirectRepository::all_patterns()` as one bounded cached list for in
memory matching, at most `MAX_PATTERNS` (500) rows served from the
`RedirectCache` pattern slot when fresh, otherwise read with an
explicit `LIMIT` and stored. This avoids `LIKE` scans on the hot path
and keeps a cold miss to one small indexed read. Writes that would
grow the active pattern set past the cap are refused with a zero or
false return so the admin reports the limit instead of silently
exceeding it.

## Normalization rules

`Normalizer::normalize()` is the single shared normalizer. Rule sources
at create time, request paths at dispatch time, loop and chain targets,
CSV rows, cache keys, and 404 URIs all pass through it, so hashing and
comparison stay consistent everywhere. Regex rule sources are the one
exception: `Normalizer::normalizeSource()` stores pattern bodies
verbatim (trimmed only), because path normalization would corrupt them
by cutting at question marks, collapsing slashes, or forcing a leading
slash. The hash covers the stored form, so identical patterns under
the same matcher stay one row.

## Normalization rules

`Normalizer::normalize()` is the single shared normalizer. Rule sources
at create time, request paths at dispatch time, loop and chain targets,
CSV rows, cache keys, and 404 URIs all pass through it, so hashing and
comparison stay consistent everywhere.

In order:

- Trim the input. Empty input becomes `/`.
- Full URLs fold to their path part via `wp_parse_url`. A bare host
  with no path becomes `/`.
- The query string and fragment are dropped for matching identity.
- Duplicate slashes collapse to one.
- Percent encoding canonicalizes: uppercase hex, unreserved characters
  (letters, digits, hyphen, underscore, dot, tilde) decoded, reserved
  bytes kept encoded.
- Unicode folds to NFC when intl is available, otherwise bytes pass
  through untouched. Hashing always runs over the resulting raw bytes.
- The subdirectory home path from `home_url` parsing is stripped, so
  subdirectory installs match relative to the home directory.
- Exactly one leading slash is enforced.
- The trailing slash is trimmed except for the root path `/`.
- Case is sensitive by default. Lowercasing applies only when the per
  rule ignore case flag is set (future surface; the hash accepts the
  case folding flag today).

Homepage and bare domain are blocked as redirect sources:
both normalize to `/`, and `Normalizer::isBlockedSource()` rejects `/`
at create time and skips it at dispatch time, so a rule can never lock
visitors out of the front page.

## Hash basis

`Normalizer::hash( $matchType, $normalizedPath )` returns SHA256 hex
over `match_type . "|" . casefolded_path`. The query string is excluded
from the hash in v1, matching the default source query ignored
semantics. The match type is folded into the hash so identical paths
under different matchers are distinct rows.
