# RankKernel: Brutal QA and Correction Pass

## FAQ Block, HowTo Block, Gutenberg UX, WordPress Coding Standards, PHPCS Enforcement, Existing Code Cleanup

This is a **correction and quality enforcement pass** on RankKernel.

Do not treat this as a small UI task.

The goal is to ensure that the current FAQ and HowTo Gutenberg blocks are actually usable by a normal WordPress administrator and that the entire RankKernel codebase follows the project's required WordPress coding standards consistently.

The current implementation already has FAQ and HowTo blocks, but the editor UX must be reviewed critically instead of assuming that "block exists" means "feature is complete."

The standard is:

> **If a real WordPress user would find the block awkward, confusing, visually poor, or significantly worse than the established Rank Math experience, it is not finished.**

And:

> **If the code technically works but violates the project's coding standards, it is not finished.**

---

# 1. IMPORTANT: DO NOT START CODING IMMEDIATELY

First inspect:

* current FAQ block implementation
* current HowTo block implementation
* block.json files
* editor JavaScript
* frontend/server rendering
* schema extraction
* saved block attributes
* block registration
* block assets
* CSS
* build system
* existing tests
* PHPCS configuration
* composer scripts
* existing source files
* existing tests

Also inspect the current Git diff and branch.

Do not assume the previous implementation is correct.

---

# 2. PRIMARY USER COMPLAINT

The current FAQ and HowTo blocks have an unacceptable editor experience.

The user has specifically observed that the editor presentation behaves like content simply being added in new rows without a polished structured editor experience.

This has already been raised multiple times.

Do NOT ignore this requirement again.

Do NOT respond with:

```text
"The block works."
```

That is not sufficient.

The requirement is:

> **The FAQ and HowTo Gutenberg blocks must have a polished, structured, easy-to-use editing experience comparable in usability and interaction quality to Rank Math's corresponding blocks.**

This means the implementation must be reviewed visually and functionally.

---

# 3. CLEAN ROOM COMPETITOR REQUIREMENT

Inspect the current public Rank Math FAQ and HowTo block behavior and documentation.

Use it only for:

* UX expectations
* interaction patterns
* user workflow
* feature expectations
* information architecture

Do NOT copy:

* Rank Math source code
* class names
* proprietary implementation
* CSS source
* JavaScript source
* internal identifiers

We want:

> **Equivalent or better user experience, independently implemented.**

Not copied code.

---

# 4. FAQ BLOCK UX REQUIREMENTS

The FAQ block should feel like a dedicated FAQ editor, not a collection of unrelated text rows.

The editor should clearly communicate:

```text
FAQ
Question
Answer
```

Each FAQ item should be visually grouped.

For example, conceptually:

```text
┌───────────────────────────────────────────┐
│ FAQ                                        │
│                                           │
│ Question 1                                │
│ [ What is WordPress SEO?              ]   │
│                                           │
│ Answer                                    │
│ [ WordPress SEO is...                 ]   │
│ [                                      ]   │
│                                           │
│                         Remove FAQ         │
├───────────────────────────────────────────┤
│ Question 2                                │
│ [ How does RankKernel work?           ]   │
│                                           │
│ Answer                                    │
│ [ RankKernel provides...              ]   │
│                                           │
│                         Remove FAQ         │
├───────────────────────────────────────────┤
│ + Add FAQ                                 │
└───────────────────────────────────────────┘
```

This is only an interaction concept.

Use WordPress Gutenberg-native components where practical.

---

# 5. FAQ MUST SUPPORT MULTIPLE ITEMS

The block must allow:

* one FAQ
* two FAQs
* many FAQs

Adding another FAQ must be obvious.

The user should not have to understand block internals.

Use a clear:

```text
Add FAQ
```

action.

Each FAQ should have its own:

* question
* answer
* remove/delete action

If reordering is useful and can be implemented cleanly, support reordering.

Do not add unnecessary complexity merely for visual decoration.

---

# 6. FAQ VISUAL GROUPING

Every FAQ item must be visually distinguishable.

Avoid an editor that looks like:

```text
Question
Answer

Question
Answer

Question
Answer
```

with no grouping.

Use appropriate WordPress editor components such as:

* Panel
* PanelBody
* Card
* CardBody
* TextControl
* TextareaControl
* RichText
* Button
* Placeholder
* Notice

where appropriate.

Do not blindly use every component.

Choose the simplest native Gutenberg components that produce a professional UX.

---

# 7. FAQ QUESTION FIELD

Question must be visually and semantically distinct.

Requirements:

* clear label
* editable value
* proper placeholder
* accessible label
* no unnecessary duplicate controls

Do not store presentation text inside the actual question value.

---

# 8. FAQ ANSWER FIELD

Answer should support normal content appropriately.

Determine whether the existing implementation should use:

* RichText
* InnerBlocks
* TextareaControl

based on the current architecture.

Do not make the answer artificially limited if the competitor-quality UX clearly supports richer content.

However:

Do not introduce massive nested-block complexity if it is unnecessary.

The final saved representation must remain predictable and safe for schema extraction.

---

# 9. FAQ ADD / REMOVE UX

The user must immediately understand how to:

* add another FAQ
* remove an FAQ
* edit an FAQ

Buttons must have clear labels.

Avoid icon-only controls unless they have proper accessible labels and tooltips.

Do not make destructive actions ambiguous.

---

# 10. FAQ EMPTY STATE

If the block is inserted with no FAQ entries, show a useful empty state.

Example concept:

```text
Add your frequently asked questions.

[ Add FAQ ]
```

Do not show a broken-looking empty container.

---

# 11. FAQ BLOCK FRONTEND

The editor UI and frontend output are separate concerns.

Do not allow editor UX improvements to accidentally alter the intended frontend HTML/schema behavior.

Verify:

* frontend content remains readable
* questions/answers render correctly
* schema extraction still works
* multiple FAQ items work
* empty items do not generate invalid schema

---

# 12. FAQ SCHEMA VALIDATION

For each FAQ item:

```text
Question -> valid question
Answer   -> valid answer
```

Do not output:

```text
Question = ""
Answer = ""
```

Do not output malformed FAQPage schema.

The generated FAQPage node must correspond to actual visible content.

---

# 13. HOWTO BLOCK UX

Apply the same level of scrutiny to the HowTo block.

The HowTo block must feel like a dedicated structured HowTo editor.

It should support a clear hierarchy such as:

```text
How To
  Title
  Description
  Total Time
  Estimated Cost
  Tools
  Materials
  Steps
```

Only include fields that are actually part of the current RankKernel schema model and roadmap.

Do not invent unnecessary fields.

---

# 14. HOWTO STEPS MUST BE STRUCTURED

The biggest requirement:

Steps must NOT appear as an unstructured collection of new rows.

Each step should be visually grouped.

Conceptually:

```text
┌───────────────────────────────────────────┐
│ Step 1                                    │
│                                           │
│ Title                                     │
│ [ Prepare the environment              ]  │
│                                           │
│ Description                               │
│ [ Install the required package...       ] │
│                                           │
│                              Remove Step   │
├───────────────────────────────────────────┤
│ Step 2                                    │
│                                           │
│ Title                                     │
│ [ Configure WordPress                   ] │
│                                           │
│ Description                               │
│ [ Open the settings...                  ] │
│                                           │
│                              Remove Step   │
├───────────────────────────────────────────┤
│ + Add Step                                 │
└───────────────────────────────────────────┘
```

Again, this is a UX concept, not an instruction to copy any competitor UI literally.

---

# 15. HOWTO STEP ORDER

Steps are inherently ordered.

The editor must make the order obvious.

If drag-and-drop reordering can be implemented cleanly using WordPress-native functionality, evaluate it.

If drag-and-drop introduces unnecessary complexity, provide reliable move controls.

At minimum the user must be able to control:

```text
1
2
3
4
```

and understand that this order is preserved in schema.

---

# 16. HOWTO ADD / REMOVE UX

Provide clear:

```text
Add Step
Remove Step
```

actions.

If tools/materials are repeatable fields, apply the same UX quality.

Do not create confusing nested controls.

---

# 17. HOWTO EMPTY STATE

If no steps exist:

```text
Add the first step to your HowTo.

[ Add Step ]
```

The block should never look broken.

---

# 18. RESPONSIVE ADMIN UX

The Gutenberg editor can operate at different widths.

Check that:

* fields do not overflow
* buttons remain usable
* labels remain readable
* nested groups do not become excessively narrow
* long content does not destroy layout

Do not use fixed widths unnecessarily.

---

# 19. ACCESSIBILITY

Treat accessibility as part of correctness.

Audit:

* labels
* button names
* keyboard navigation
* focus behavior
* aria labels where necessary
* color contrast
* heading hierarchy
* screen-reader meaningful controls

Do not rely on icons alone.

Do not create inaccessible custom controls when native Gutenberg controls solve the problem.

WordPress accessibility expectations must be followed.

---

# 20. DO NOT USE EXCESSIVE CUSTOM CSS

Prefer WordPress/Gutenberg native components and styles.

Only add custom CSS where necessary.

Avoid:

* giant CSS files
* global selectors
* theme-specific selectors
* aggressive !important
* styling that leaks outside the block
* selectors that interfere with Gutenberg

Scope custom styles to the RankKernel block.

---

# 21. BLOCK DATA MODEL

Before changing the editor UI, inspect the current saved attribute structure.

Do NOT change the data model unnecessarily.

If the current data structure can support the improved UX:

> Keep it.

If the current structure prevents a good UX:

> Design the smallest compatible improvement.

Do not create duplicate data representations.

---

# 22. BLOCK BACKWARD COMPATIBILITY

Existing RankKernel FAQ/HowTo blocks already stored in post_content must continue to work.

Test:

* existing block
* one FAQ
* multiple FAQ
* existing HowTo
* multiple steps
* missing optional fields
* older attribute shape if applicable

Do not silently destroy existing content.

---

# 23. BLOCK EDITOR JAVASCRIPT QUALITY

Audit current JavaScript.

Check for:

* repeated logic
* uncontrolled state
* mutation bugs
* stale state
* unnecessary rerenders
* missing dependencies
* incorrect attribute updates
* invalid React/Gutenberg patterns
* missing keys
* unsafe HTML

Do not introduce React patterns that are incompatible with WordPress's supported editor environment.

---

# 24. IMPORTANT: GLOBAL WORDPRESS CODING STANDARDS

This is a project-wide requirement.

Do not only fix the files you touch.

RankKernel must consistently follow:

* WordPress PHP Coding Standards
* WordPress JavaScript Coding Standards
* WordPress CSS Coding Standards
* WordPress HTML Coding Standards
* WordPress inline documentation standards where applicable
* project PHPCS configuration
* WordPress.org plugin expectations

The current project already runs PHPCS/lint tooling.

Make the standard enforceable globally.

---

# 25. PHP WHITESPACE

The following style is NOT acceptable:

```php
if($a==b){
```

Use WordPress style:

```php
if ( $a == $b ) {
```

Likewise:

```php
$result=$value;
```

is not acceptable.

Use:

```php
$result = $value;
```

Operators require appropriate spacing.

Control structure parentheses require WordPress spacing.

Function arguments must follow WordPress spacing rules.

Do not manually guess formatting.

Use PHPCS as the authoritative automated check.

---

# 26. INDENTATION

WordPress PHP coding standards use real tabs for indentation.

Do NOT convert the entire project to four-space indentation.

Use:

```text
TAB
TAB
TAB
```

for logical indentation.

Spaces may still be used for alignment inside lines where appropriate.

The same principle applies to WordPress HTML indentation.

Do not blindly run a generic formatter that converts WordPress indentation into four spaces.

---

# 27. EXISTING FILES MUST ALSO BE AUDITED

This requirement is extremely important.

Do NOT only make newly written code compliant.

Audit existing RankKernel files.

Check:

```text
src/
tests/
admin/
assets/
```

and all PHP/JS/CSS/HTML files that are part of RankKernel.

If existing code violates the project's selected WordPress standards:

* fix it
* run PHPCS
* run tests
* review the diff

Do not leave known style violations simply because "the file existed before."

---

# 28. BUT DO NOT MASS-REFORMAT BLINDLY

Do NOT run a formatter across the entire repository and create a giant meaningless diff.

Use this process:

1. Run PHPCS.
2. Identify actual violations.
3. Group related violations.
4. Fix them deliberately.
5. Run PHPCS again.
6. Run tests.
7. Review diff.

Do not change working code semantics while fixing style.

---

# 29. PHPCS MUST BE AUTHORITATIVE

The final code must pass:

```text
composer lint
```

if that is the project's lint command.

Also run:

```text
composer stan
composer test
```

The project currently uses:

```text
PHPStan level 6
```

Do not weaken PHPStan.

Do not exclude files merely to make the command pass.

Do not add PHPCS ignore rules merely to hide bad code unless there is a documented legitimate WordPress-specific exception.

---

# 30. CHECK PHPCS CONFIGURATION ITSELF

Inspect:

* phpcs.xml
* phpcs.xml.dist
* composer.json
* scripts
* installed WordPress Coding Standards ruleset
* exclusions
* ignores

Determine whether the project is actually enforcing the intended WordPress standards.

If the current PHPCS configuration does not adequately enforce the required standards, fix the configuration.

Do not assume:

```text
composer lint = WordPress Coding Standards
```

until verified.

---

# 31. GLOBAL CODING RULE

Add or update project-level developer documentation/configuration so future implementation work follows the same standard.

The rule should effectively be:

> All RankKernel code must follow the repository's configured WordPress Coding Standards. New code and modified existing code must pass PHPCS before completion.

This should become a project rule, not a reminder that must be repeated manually every time.

---

# 32. WORDPRESS ORG COMPLIANCE

Treat WordPress.org compatibility as a design requirement from now onward.

Audit for:

* GPL compatibility
* correct plugin headers
* text domain
* internationalization
* escaping
* sanitization
* capability checks
* nonce checks
* no hidden telemetry
* no external requests without justified user action
* no obfuscated code
* no remote executable code
* no artificial PRO gates
* no upsells
* no spammy admin notices
* no unnecessary tracking
* proper plugin uninstall behavior

RankKernel is:

> 100 percent free forever.

Never introduce PRO gating.

Never introduce telemetry.

Never introduce advertising.

---

# 33. INTERNATIONALIZATION

Any user-facing string introduced or modified must be reviewed for:

* translation functions
* correct text domain
* translator comments where needed
* no concatenated untranslated fragments
* no hard-coded user-facing English when translation is expected

Check existing block strings too.

---

# 34. JAVASCRIPT CODING STANDARDS

Do not apply PHP formatting rules blindly to JavaScript.

Use the repository's WordPress JavaScript standard.

Check:

* spacing
* naming
* semicolons
* function style
* imports
* React/Gutenberg conventions
* escaping
* translation APIs

The goal is:

> WordPress-native code quality, not generic JavaScript style.

---

# 35. CSS CODING STANDARDS

Audit block CSS.

Check:

* selector scope
* formatting
* indentation
* property spacing
* unnecessary duplication
* !important usage
* editor/frontend separation
* CSS leakage

Do not add CSS merely because a visual issue can technically be solved with CSS.

Prefer correct Gutenberg components first.

---

# 36. BLOCK ASSET LOADING

FAQ and HowTo editor assets should only load where appropriate.

Audit:

* editor script
* editor CSS
* frontend CSS
* block registration
* dependency loading
* versioning

Do not load RankKernel block assets globally across wp-admin.

---

# 37. TESTING THE BLOCK UX

Unit tests alone are not enough.

Test the actual editor behavior where the repository/tooling allows.

At minimum verify:

### FAQ

* block inserts
* first FAQ appears correctly
* add FAQ works
* multiple FAQs work
* remove FAQ works
* data persists after save/reload
* schema persists
* empty values handled
* existing blocks remain compatible

### HowTo

* block inserts
* fields appear correctly
* add step works
* remove step works
* ordering works
* data persists
* schema persists
* existing blocks remain compatible

---

# 38. RENDERED SCHEMA REGRESSION TEST

After improving the editor, verify that frontend schema has not regressed.

For FAQ:

```text
FAQ block content
        ↓
saved block attributes/content
        ↓
server render
        ↓
schema extraction
        ↓
FAQPage graph node
```

For HowTo:

```text
HowTo block content
        ↓
saved block attributes/content
        ↓
server render
        ↓
schema extraction
        ↓
HowTo graph node
```

Test the complete chain.

---

# 39. REAL BROWSER / LOCAL SITE CHECK

The project has a LocalWP test site with approximately 30k posts.

Do not start or stop Local services from tooling.

Use the existing running environment.

Manually inspect the Gutenberg editor for:

1. FAQ block
2. multiple FAQ items
3. HowTo block
4. multiple HowTo steps
5. editing existing blocks
6. saving
7. reloading
8. reopening editor
9. frontend output

If browser automation is unavailable, state exactly what was and was not manually verified.

Do not claim visual verification without actually doing it.

---

# 40. COMPETITOR UX CHECK

Use current public Rank Math documentation/screenshots as a reference for expected workflow.

Rank Math's public documentation shows:

* dedicated FAQ block
* multiple FAQ entries
* explicit Add New FAQ workflow
* dedicated HowTo block
* structured HowTo information
* structured steps

RankKernel should meet or exceed that usability standard.

Do not copy their source code or exact visual assets.

---

# 41. "SAME TO SAME" INTERPRETATION

The requirement "same to same as Rank Math" means:

### Match or exceed:

* clarity
* discoverability
* grouping
* add/remove workflow
* structured editing
* step/question organization
* usability
* persistence
* schema correctness

### Do NOT copy:

* proprietary source
* exact CSS
* exact internal markup
* proprietary JS
* internal class names
* proprietary implementation

This is clean-room behavioral parity.

---

# 42. FINAL QUALITY LOOP

Before declaring completion:

## Pass 1

Run:

```text
composer lint
```

Fix every relevant violation.

## Pass 2

Run:

```text
composer stan
```

Fix every issue.

## Pass 3

Run:

```text
composer test
```

Fix every failure.

## Pass 4

Review the actual diff.

Look for:

* debug code
* TODOs
* temporary hacks
* commented-out code
* dead code
* accidental formatting changes
* unrelated modifications
* duplicate logic
* missing translations
* missing tests
* poor naming
* unnecessary CSS
* unnecessary JS

## Pass 5

Inspect actual FAQ and HowTo editor behavior.

## Pass 6

Inspect actual rendered JSON-LD.

## Pass 7

Run the complete quality gates again.

Do not stop after the first green run if later changes were made.

---

# 43. ACCEPTANCE CRITERIA

This task is NOT complete unless:

## FAQ

* [ ] FAQ block has a polished structured editor.
* [ ] Questions and answers are visually grouped.
* [ ] Multiple FAQ entries are easy to manage.
* [ ] Add FAQ is obvious.
* [ ] Remove FAQ is obvious.
* [ ] Empty state is good.
* [ ] Existing FAQ blocks remain compatible.
* [ ] Schema output remains correct.
* [ ] No invalid empty FAQ schema is produced.

## HowTo

* [ ] HowTo block has a polished structured editor.
* [ ] Steps are clearly grouped.
* [ ] Step ordering is obvious.
* [ ] Add Step is obvious.
* [ ] Remove Step is obvious.
* [ ] Existing HowTo blocks remain compatible.
* [ ] Schema output remains correct.
* [ ] Empty/invalid steps are handled safely.

## Coding Standards

* [ ] PHP follows WordPress PHP Coding Standards.
* [ ] PHP indentation uses real tabs.
* [ ] Control structures use WordPress spacing.
* [ ] Operators use WordPress spacing.
* [ ] JS follows project WordPress JS standards.
* [ ] CSS follows project WordPress CSS standards.
* [ ] HTML follows project WordPress HTML standards.
* [ ] Documentation follows WordPress standards.
* [ ] Existing relevant files were audited.
* [ ] New code follows the same standards.
* [ ] PHPCS configuration is verified.
* [ ] No violations are hidden just to make CI green.

## Quality Gates

* [ ] composer lint passes.
* [ ] composer stan passes.
* [ ] composer test passes.
* [ ] Existing tests remain green.
* [ ] New behavior has tests.
* [ ] Rendered-output assertions exist.
* [ ] Actual editor behavior was checked.
* [ ] Final diff was reviewed.

## WordPress.org readiness

* [ ] No PRO gates.
* [ ] No upsells.
* [ ] No telemetry.
* [ ] No unnecessary external requests.
* [ ] Proper escaping.
* [ ] Proper sanitization.
* [ ] Proper capability checks.
* [ ] Proper nonce checks.
* [ ] Proper internationalization.
* [ ] GPL-compatible implementation.
* [ ] No obfuscated code.
* [ ] No hidden tracking.

---

# 44. DO NOT CHEAT THE ACCEPTANCE CRITERIA

The following do NOT count as completion:

```text
"It renders."

"It compiles."

"Tests pass."

"The block is registered."

"Rank Math has something similar."

"PHPCS only checks new files."

"The old files were already like that."

"The user can technically edit the fields."

"The schema is valid."
```

Completion requires:

> **Good UX + correct saved data + correct frontend output + correct schema + coding standards + tests + WordPress compatibility.**

---

# 45. FINAL REPORT

Return:

## A. Problems found

List the actual problems discovered.

## B. FAQ changes

List exact changes.

## C. HowTo changes

List exact changes.

## D. Coding standard changes

List:

* files changed
* violations fixed
* PHPCS configuration changes
* global project rule changes

## E. Tests

Report:

```text
Tests before:
Tests after:
Assertions before:
Assertions after:
composer lint:
composer stan:
composer test:
```

Use actual values.

Never invent numbers.

## F. Browser/editor verification

Clearly state what was actually verified.

## G. Schema verification

Clearly state what actual JSON-LD output was checked.

## H. Remaining limitations

Only genuine limitations.

## I. Files changed

Give the exact list.

## J. Git status

Confirm:

* branch
* working tree
* commits
* whether anything remains uncommitted

---

# 46. GIT DISCIPLINE

Follow project rules.

Every change must remain on the current issue branch unless the owner explicitly instructs otherwise.

Use atomic commits.

Before commit:

```text
composer lint
composer stan
composer test
```

Commit subject format:

```text
GH-<n>: <plain summary> (#<n>)
```

Use project-required commit body/footer conventions.

Do not merge.

The owner must manually verify before merge.

---

# 47. IMPORTANT PROJECT RULE

From this point forward, treat WordPress coding standards as a **global project invariant**, not a task-specific instruction.

Every future feature must:

1. Follow the configured WordPress standards.
2. Pass PHPCS.
3. Pass PHPStan.
4. Pass tests.
5. Avoid unnecessary database/storage growth.
6. Preserve module gating.
7. Include tests for changed behavior.
8. Review existing relevant code before adding new code.

Do not wait for the owner to remind you.

---

# 48. FINAL STANDARD

The goal is not:

> "Make the current code pass."

The goal is:

> **Make RankKernel code that a senior WordPress developer would be comfortable reviewing and that a normal WordPress administrator would genuinely enjoy using.**

For the FAQ and HowTo blocks specifically:

> **Do not ship a technically functional but visually primitive editor.**

For code quality:

> **Do not ship code that only happens to work while violating WordPress standards.**

For testing:

> **Do not trust unit tests alone when the user-facing editor behavior is part of the requirement.**

For WordPress.org:

> **Do not leave standards/compliance cleanup for the end of the project. Enforce it continuously from now onward.**

