# RankKernel — AI Assistance Disclosure

Per official 2026 WordPress.org Plugin Directory guidelines (WordPress AI Guidelines v0), meaningful AI assistance during development must be documented per release in a standard format.

## Reconciliation note

- Base: #387 (keeps `Tested up to: 7.1`; standard disclosure format; no regression of the verified release).
- Superseded (not adopted): #377, #380, #391 (attempted to lower `Tested up to` and/or replace the disclosure wording; closed as superseded per owner direction).
- Unique content preserved from #377: Jules/Anthropic Claude tooling, boilerplate/i18n/unit-test stub generation, and the developer verification / responsibility statements.

## Release Disclosures

### v0.1.0
- **AI assistance:** Yes
- **Tool(s):** Claude, GitHub Copilot, Jules (AI Software Engineering Agent)
- **Used for:** Code refactoring, test suite coverage expansion, documentation alignment, WordPress.org guideline compliance checks, codebase compliance auditing, i18n string formatting, unit test stubs, and pre-submission checklist verification.
- **Developer Verification:** Every line of generated code, documentation, and configuration has been audited, tested, and verified for GPLv2+ license compatibility, security (sanitization, escaping, nonces, capabilities), performance, and adherence to WordPress Coding Standards.
- **Responsibility Statement:** The RankKernel development team maintains full responsibility for all submitted code, documentation, and architecture.
- **License compliance:** All AI-generated and AI-assisted code adheres strictly to the GPL-2.0-or-later license. The development team maintains full ownership and responsibility for every line of code committed.
