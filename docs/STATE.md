# RankKernel Session State (resume here)

Compact continuation record. The full audit lives in `technical-handoff.md`. Updated when phases complete.

## Where we are

- Branch `GH-11`, clean tree, unpushed. `origin/main` at `af9aab8` (PR #10 merged).
- Schema phase functionally complete, awaiting user verification before push, PR, merge.
- Gates green: 408 tests, 1702 assertions, phpcs clean, phpstan level 6 clean.
- Live proof DONE 2026-09-11 (post/page/home: 1 script each, unique @ids, no empties, refs resolve). Reviewer package: `docs/reviewer-handoff.md`.
- Next: push GH-11 → PR `GH-11: ...` → merge → redirects phase (new issue).

## Key paths

- Plugin (repo): `wp-content/plugins/rankkernel/`
- Plans and audits: `docs/` (blueprint at `docs/architecture/blueprint.md`, roadmap at `docs/ROADMAP.md`, compliance at `docs/wporg-compliance.md`, clean room at `docs/clean-room-policy.md`, competitor audits at `docs/competitor-analysis/`)
- This file replaces the old `/tmp` ledger (tmp storage gets wiped).

## Standing rules (never break)

1. Free forever. No Pro tier, upsells, or nags.
2. Disabled module means zero cost. Never claim zero-query in absolute terms.
3. No competitor code copied, ideas only.
4. Git: issue → `GH-<n>` branch → gates → `GH-<n>:` PR with description and `Closes #<n>` → merge only after user verifies. Main protected (pending setup).
5. Commits authored `maulikbhalodiya`. Token at `~/.config/rankkernel/.gh-token`, rotates ~Dec 2026. Pushes via SSH alias `github-maulik-repo`.
6. Zero-dash writing in all project text. Hyphens only inside compound words and identifiers.
7. Push branches for backup, merge only on user verified. Same functionality stays on one issue and branch.
8. Competitor parity research before each feature (match baseline minimum, then improve).
9. **All project MD files live under `docs/`** (inner structure allowed). Nothing outside that folder.
10. Verify identifiers with `grep -c`, never by eye. Test doubles leak across files in-suite: stub what you touch, never blanket-`when` beside strict `expect`.

## Do-not-touch list (working, heavily tested)

ModuleManager and EnableMap gating, MetaPayload sanitize shapes, sitemap cache validator protocol, rewrite rules, uninstall purge logic, Plugin::VERSION constant pattern.
