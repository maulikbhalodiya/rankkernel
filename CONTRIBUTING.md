# Contributing to RankKernel

Thank you for contributing! This project follows a strict workflow with two lanes, issue-backed work and issue-free maintenance, so every change is traceable.

## The workflow (no exceptions)

1. **Pick the lane first.** Every change is exactly one of two lanes, and the lane decides the branch name and the PR title.
   - **Issue-backed work: every feature and every fix.** The work starts as a GitHub issue with a clear title, e.g. `Add XML sitemaps module`. One issue = one feature/fix. The branch name is `GH-<issue-number>` (issue `#1` → branch `GH-1`), the PR title carries the `GH-<number>` prefix, and the PR body carries a `Closes #<number>` line. `GH-<number>` in a title is not itself an issue link, so the `Closes` line is what makes GitHub link and auto-close the issue.
   - **Issue-free maintenance: CI, chore and documentation changes.** Do not create a fake issue merely to satisfy a naming rule. Use the conventional branch and title exception: the branch carries a conventional name with no issue id, and the PR title carries a conventional type and no issue id, for example `ci(audit): stop treating high entropy as evidence of a secret` or `docs(contributing): document the PR title convention`.
2. **Branch from the latest `main`.** Whether the change is issue-backed or issue-free, the branch starts at the latest `main` and nothing else.
3. **Commit with context.** Commit messages must describe the *why*, not just the *what*. An issue-backed commit references its issue, and an issue-free commit uses a conventional message with no issue reference:
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
- Issue-backed branch names follow `GH-*` only (e.g., `GH-1`, `GH-42`). Issue-free maintenance branches use a conventional name with no issue id, as the workflow section describes.
- Keep one issue = one branch = one PR for issue-backed work, and one concern = one branch = one PR for issue-free maintenance. No mixed-concern branches.

## PR title rules

- A pull request that has an issue begins its title with the issue id, then a conventional description:
  ```
  GH-88: feat(instant-indexing) custom log table, REST, and retry
  GH-42: fix(sitemaps) scope the takeover notice to RankKernel screens
  ```
- The title carries the issue id, and the description carries a `Closes #88` line. The title satisfies
  the naming rule; the `Closes` line is what makes GitHub link and auto-close the issue. `GH-88` in a
  title is not itself an issue link, so the body line is still required.
- A change that has no issue, such as a CI or chore fix, uses a conventional title with no issue id:
  ```
  ci(audit): stop treating high entropy as evidence of a secret
  chore(deps): bump the reviewer action to the pinned release
  ```
- Never invent an issue just to satisfy the prefix. If the work is genuinely a chore, title it as one.

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
