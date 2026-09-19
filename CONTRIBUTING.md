# Contributing to RankKernel

Thank you for contributing! This project follows a strict issue-driven workflow so every change is traceable.

## The workflow (no exceptions)

1. **Issue first.** Every change starts as a GitHub issue with a clear title, e.g. `Add XML sitemaps module`. One issue = one feature/fix.
2. **Branch from the issue.** Branch name is `GH-<issue-number>`:
   - Issue `#1` → branch `GH-1`
   - Always branch from the latest `main`.
3. **Commit with context.** Commit messages must reference the issue and describe the *why*, not just the *what*:
   ```
   Add sitemap cache with validator-based invalidation (#2)

   Ships cache ON by default (competitor ships it OFF); invalidation is
   queued and flushed on shutdown to avoid mid-request rebuilds.
   ```
4. **Open a PR early.** PR description must include: what changed, why, how it was tested (gate output), and any blueprint deviations.
5. **Gates must be green before a PR is reviewable:**
   ```bash
   composer lint   # phpcs
   composer stan   # phpstan level 6
   composer test   # phpunit
   ```
6. **The repo owner merges.** PRs are never merged by the PR author. `main` is review-only, the owner (maulikbhalodiya) reviews and merges.

## Branch rules

- `main` is always releasable. Direct pushes to `main` are not allowed.
- Branch names follow `GH-*` only (e.g., `GH-1`, `GH-42`).
- Keep one issue = one branch = one PR. No mixed-concern branches.

## One active feature, one active PR

While a feature is under way in an open pull request, every remaining piece of that feature stays
in that same pull request until the feature is complete and verified. That includes implementation,
tests, review fixes, security fixes, UX fixes, architecture corrections, validation fixes, parity
fixes and any behaviour that belongs to the same feature.

Do not open a second pull request for a fragment of the same active functionality, and do not split
one feature across several pull requests merely because two earlier attempts overlap.

Open a new pull request only when the work is genuinely separate, can ship on its own, and is
intentionally split by the owner. The rule exists because splitting one feature fragments review,
produces duplicate implementations, leaves the branch state hard to read, and hides which pull
request holds the real functionality.

## Writing style

- Zero standalone dashes in all project text: no em dashes, en dashes, or hyphens used as pauses. Clauses are separated by commas or full stops. Hyphens appear only inside compound words (same-week) and identifiers (GH-1, rankkernel_modules).

## Coding standards

- Procedural entry files (`rankkernel.php`, `uninstall.php`): WordPress Coding Standards.
- `src/` classes: PSR-12, PHP 8.1 typed, `declare(strict_types=1)`.
- Every user-facing string uses the `rankkernel` text domain.
- No telemetry, no external requests, no competitor code, ever. See the clean-room policy in the project docs.

## Development setup

```bash
git clone https://github.com/maulikbhalodiya/rankkernel.git
cd rankkernel
composer install
composer test && composer lint && composer stan
```
