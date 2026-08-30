# Pull-request automation

CampaignBridge uses the same guarded model as the Aggressive WordPress
repositories: automation classifies and routes every pull request, but GitHub's
native ruleset remains the only authority that can permit a merge.

## Maintainer interface

| Pull request                               | Result                                                                            |
| ------------------------------------------ | --------------------------------------------------------------------------------- |
| Dependabot patch, minor, or grouped update | Classified and registered for native auto-merge after every required check passes |
| Dependabot major update                    | `dependency-major` and `needs-attention`; waits for review                        |
| Maintainer PR with `automerge`             | Eligible only when the author is trusted and risk is not high                     |
| Maintainer PR without `automerge`          | Classified and left open                                                          |
| Any high-risk PR                           | `risk:high` and `needs-attention`; never auto-merged by policy                    |

The `automerge` label is an opt-in request, not authorization. The author must
also appear in the comma-separated `CB_AUTOMERGE_ACTORS` repository variable;
when that variable is absent, only the repository owner is trusted.

## Classification

The workflow derives type labels from the Conventional Commit title and area
labels from the changed files. Documentation and tests-only changes are low
risk. Normal product changes are medium risk.

High-risk paths include:

- workflows, rulesets, CODEOWNERS, Dependabot, release, and CI enforcement;
- static-analysis and test configuration;
- REST authorization, encryption, provider, repository, and campaign workflow
  boundaries;
- the plugin bootstrap/runtime contract and uninstall behavior;
- dependency manifests edited by a person;
- any breaking-change title or pull request whose file list cannot be read.

`risk:high` always overrides `automerge`. This is a tested invariant: a label a
person can apply must never authorize a workflow or security-boundary change to
merge itself.

## Trust boundaries

`PR Title` runs on `pull_request` with a read-only token. Classification and
label changes run from `pull_request_target` or `workflow_run`, always checking
out protected `master`; pull-request code is never executed with a write token.
The controller re-reads the author, title, files, labels, checks, reviews, merge
state, and head SHA from GitHub's API.

The policy refuses missing required checks, pending or failed optional checks,
unknown merge states, conflicts, requested changes, untrusted authors, and
drafts. A stale branch is updated with an expected-head compare-and-swap, then
left for fresh checks. A merge decision only calls native
`gh pr merge --auto --squash`; it never calls the merge API directly.

## Required checks

The committed and live ruleset require these exact contexts:

- `CI summary`
- `Analyze JavaScript and TypeScript`
- `Actionlint`
- `Zizmor`
- `PR Title`

The controller requires the same list before it can register auto-merge, so a
missing live ruleset cannot turn bootstrap timing into an unguarded merge.

## Implementation

- `.github/workflows/pr-policy.yml` separates read-only validation from
  write-capable routing.
- `bin/ci/pr-policy-rules.mjs` is pure, fail-closed decision logic.
- `bin/ci/pr-policy-rules.test.mjs` covers the safety and classification matrix.
- `bin/ci/pr-policy.mjs` is the narrow GitHub API controller.
- `bin/ci/check-pr-title.mjs` shares the same title parser.

The controller creates missing policy labels from committed definitions before
it applies them. It only removes labels explicitly owned by the policy.

## Release synchronization

Semantic release may create tags and GitHub releases, but it does not push a
release commit through protected `master`. The accepted version metadata is
carried into a signed `chore/version-sync` pull request using the repository CI
App. That PR starts the normal checks and registers native squash auto-merge;
the release automation has no ruleset bypass.
