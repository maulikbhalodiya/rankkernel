# Agent instructions

RankKernel is a free WordPress SEO plugin. This file tells automated agents, including Jules,
what is in scope for a change.

## In scope: the shipped plugin

- `rankkernel.php`, `uninstall.php`
- `src/`
- `assets/`
- `tests/`
- `readme.txt`
- `composer.json`, `phpcs.xml`, `phpstan.neon`, `phpstan-baseline.neon`, `phpunit.xml`

## Out of scope: docs/

`docs/` holds internal working material only. Audits, the roadmap, the parity matrix, the
clean-room policy, research notes and session handoff state. It is not shipped and it is not
reviewed.

Do not open a pull request that only edits `docs/`. Do not add, rewrite or "verify" anything in
`docs/` as part of a code change. Documentation nits in that folder are not findings and are not
worth a commit, a review round or a pull request.

If a code change genuinely makes a document wrong, describe it in the pull request description and
leave the file alone. The owner updates `docs/`.

## Workflow

Follow `CONTRIBUTING.md`. One issue equals one branch (`GH-<n>`) equals one pull request. Branch
from the latest `main`. Never push to `main`. The owner merges.

## Gates

All three must pass before a pull request is reviewable:

    composer lint   # phpcs, WordPress ruleset
    composer stan   # PHPStan level 6
    composer test   # PHPUnit

Check every JavaScript file individually, because `node --check` accepts one file per run:

    for f in assets/js/*.js; do node --check "$f"; done

Report the real command output. Do not claim a gate passed if it did not run.

## Code rules

- `src/` classes: PSR-12, PHP 8.1 typed, `declare(strict_types=1)`, namespace `RankKernel\`.
- `rankkernel.php` and `uninstall.php`: WordPress Coding Standards.
- Prefix functions, hooks, options, transients and tables with `rankkernel` or `RankKernel`.
- Text domain is exactly `rankkernel`.
- Escape all output, sanitize all input, and check nonces and capabilities on every write.
- `docs/coding-standards.md` has the detail.

## Writing style

Zero standalone dashes. No em dashes, en dashes or hyphens used as pauses. Separate clauses with
commas or full stops. Hyphens appear only inside compound words and identifiers.
