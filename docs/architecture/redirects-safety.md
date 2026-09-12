# Redirect Loop Detection, Chain Detection, and Runtime Safety

Save time analysis lives in `Validator` and runs in the administrator
context only, never on the frontend, so it cannot be attacker
controlled. The admin form, CSV import, and slug watcher all run the
same pipeline.

## Definitions

- A cycle is a path from a rule back to itself, for example
  `/a -> /b -> /a`. Cycles are blocked at save.
- A chain is a path that ends elsewhere, for example
  `/a -> /b -> /c`. Chains save with a warning.

## Loop detection

`Validator::detect_loop()` builds the proposed rule in memory without
persisting it, then depth first searches from its target over the
active internal rules that carry a concrete target (terminal 410 and
451 codes are excluded because they carry no outgoing edge). An edge
from R1 to R2 exists when `Matcher::rule_matches()` reports that R2
matches the normalized target path of R1, so admin analysis uses
exactly the same semantics as frontend dispatch.

Traversal rules:

- Returning to any visited path is a conclusive cycle: the save is
  rejected and the notice renders the full path, for example
  `This redirect would create a redirect loop: /a -> /b -> /c -> /a.
  The rule was not saved.`
- Dynamic targets (regex or wildcard capture references such as `$1`,
  backslash numbered references) cannot be resolved statically: the
  branch is marked inconclusive and stops without blocking.
- External targets return no internal path and mark the branch
  inconclusive without blocking.
- Regex matchers on intermediate rules mark the branch inconclusive
  without blocking.
- Only conclusive cycles block. Inconclusive branches warn and allow
  the save, for example `This redirect may loop through a pattern
  rule. Please verify it manually.`

Limits:

- Maximum traversal depth 10 (`MAX_DEPTH`).
- Maximum rule examinations 50 (`MAX_NODES`).
- Hitting either cap marks the analysis inconclusive instead of
  blocking.

## Chain detection

`Validator::detect_chain()` uses the same traversal capped at 5 hops
(`MAX_CHAIN_HOPS`). Chains are never blocked.

- Traversal terminates at 410, 451, external destinations, inactive
  rules, and rules with no follower.
- Query strings are ignored during traversal (path only) to stay
  conservative.
- Deterministic chains produce a direct recommendation, for example
  `Redirect chain detected: /a -> /b -> /c. Consider pointing the
  source directly to /c.`
- Pattern, capture, external ambiguity, or hop cap overruns report
  inconclusive instead of a recommendation, for example
  `Chain analysis could not determine the final destination because
  the next rule uses a pattern matcher. Saved as entered.`
- Inconclusive analysis is stated explicitly and never blocks a save.

The slug watcher shortens avoidable chains at creation: when the new
address already redirects onward to a deterministic and policy clean
destination, the watcher points the old address straight at it.

## Runtime safety

Each redirect ends the request with a response sent to the browser.
RankKernel does not follow chains server side, so there is no internal
PHP infinite loop and no unbounded query cost. The real risk is a
browser bounce loop across requests (`A -> B -> A`), typically from a
slug watcher plus redirector interaction or from imported rules.

The runtime guard in `Redirector`:

- Sends exactly one redirect per request, then exits.
- A same request reentry attempt aborts the second send and fires the
  administrator only diagnostic action
  `rankkernel/redirect/reentry`.
- Cost bound per request: matcher checks over the bounded rule set,
  with exact resolving in one indexed lookup, plus one coalesced
  counter update at shutdown.
