#!/usr/bin/env bash
#
# Guard against a pull request silently removing work, and against coverage
# being deleted along with the code it covered.
#
# PR #183 was titled "Memoize sitemap IndexBuilder set page counts". A branch
# commit on it, titled "Add @return void docblock tag and per_page filter
# invalidation unit test", deleted 6,820 lines across 80 files. Every removed
# line had been added three commits earlier by GH-176. GitHub's squash-merge
# carried those deletions onto main under the tidy one-line PR title, and CI
# stayed green because the tests that would have noticed were deleted in the
# same commit.
#
# Two properties separate that from ordinary work, and this checks both:
#
#   1. Files deleted outright, test files among them. A deleted test file is
#      a permanently lost assertion, and the suite still passes without it.
#   2. A net-destructive diff, where deletions dwarf additions. Rewrites and
#      restructures delete lines too, so an absolute line budget is not the
#      signal; the ratio is. Measured here: #183 deletes 6.36x what it adds,
#      while ordinary fixes sit at 0.14 or below.
#
# Either condition fails the check until a human adds the acknowledgement
# label, which makes the removal a recorded decision rather than a side effect.

set -euo pipefail

ACK_LABEL="${RK_DELETION_LABEL:-large-deletion}"
RATIO="${RK_DELETION_RATIO:-3}"

base="${RK_BASE_SHA:?RK_BASE_SHA is required}"
head="${RK_HEAD_SHA:?RK_HEAD_SHA is required}"

if ! git cat-file -e "${base}^{commit}" 2>/dev/null; then
	echo "removed-work guard: base ${base} is not available, skipping" >&2
	exit 0
fi

# Three-dot diff, so this measures what the branch itself changes.
numstat="$(git diff --numstat "${base}" "${head}" 2>/dev/null || true)"
namestatus="$(git diff --name-status "${base}" "${head}" 2>/dev/null || true)"

adds=0
dels=0
while read -r add del path; do
	[ -n "${path:-}" ] || continue
	case "${add}" in
	'' | *[!0-9]*) ;;
	*) adds=$(( adds + add )) ;;
	esac
	case "${del}" in
	'' | *[!0-9]*) ;;
	*) dels=$(( dels + del )) ;;
	esac
done <<<"${numstat}"

removed_files="$(printf '%s\n' "${namestatus}" | awk '$1=="D"{print $2}' || true)"
removed_count=0
removed_tests=0
if [ -n "${removed_files}" ]; then
	removed_count="$(printf '%s\n' "${removed_files}" | grep -c . || true)"
	removed_tests="$(printf '%s\n' "${removed_files}" | grep -cE '(^|/)(tests?|spec)/' || true)"
fi

echo "removed-work guard: +${adds} -${dels}, ${removed_count} file(s) removed (${removed_tests} test file(s)) versus ${base}"

problems=""

if [ "${removed_count}" -gt 0 ]; then
	problems="${problems}  - ${removed_count} whole file(s) removed, ${removed_tests} of them test files:
$(printf '%s\n' "${removed_files}" | sed 's/^/        /')
"
fi

# Compared as a ratio rather than an absolute budget, so an ordinary
# rewrite is not mistaken for a removal. The 50 line floor keeps a tiny
# one-line diff from tripping the ratio.
if [ "${dels}" -gt 50 ] && [ "$(( dels * 1 ))" -gt "$(( adds * RATIO ))" ]; then
	problems="${problems}  - net destructive: ${dels} deletions against ${adds} additions, over the ${RATIO}:1 budget
"
fi

if [ -z "${problems}" ]; then
	echo "removed-work guard: no unexplained removal, nothing to confirm"
	exit 0
fi

echo "removed-work guard: FAILED" >&2
printf '%s\n' "${problems}" >&2

if [ -z "${GITHUB_TOKEN:-}" ] || [ "${PR_NUMBER:-0}" = "0" ]; then
	echo "  Cannot read pull request labels, so this is failing." >&2
	echo "  Confirm the removals are intended and add the '${ACK_LABEL}'" >&2
	echo "  label to the pull request." >&2
	exit 1
fi

labels="$(gh api "repos/${GITHUB_REPOSITORY}/issues/${PR_NUMBER}/labels" \
	--jq '.[].name' 2>/dev/null || echo '')"

if printf '%s\n' "${labels}" | grep -Fxq "${ACK_LABEL}"; then
	echo "removed-work guard: '${ACK_LABEL}' present, removals acknowledged by a human"
	exit 0
fi

cat >&2 <<EOF

removed-work guard: FAILED

$(printf '%s\n' "${problems}")

  This is the shape PR #183 had when it reverted 6,820 lines of work that
  had landed three commits earlier, taking 3 test files and the shared
  CountingFakeWpdb helper with it. The suite still passed afterwards,
  because the assertions that would have noticed were gone too.

  If these removals are intended:
    Add the '${ACK_LABEL}' label, and say in the description what went.

  If they are not:
    Rebase onto the current main. A branch cut before a large merge, then
    squashed, deletes that merge's work.

EOF

exit 1