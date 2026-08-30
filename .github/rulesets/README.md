# GitHub rulesets

`release-branches.json` is the reviewable source of truth for protection on
`master`. GitHub does not synchronize committed ruleset files automatically;
the live `release-branches` ruleset must be updated through the API or repository
settings whenever this file changes.

The contract is:

- pull-request-only changes with no bypass actors;
- squash-only, linear, signed history and no deletion or force pushes;
- resolved review threads, with zero required approvals while there is only one
  maintainer;
- strict, up-to-date `CI summary`, CodeQL workflow, Actionlint, Zizmor, and
  `PR Title` checks;
- native CodeQL enforcement for critical security findings and error-severity
  quality findings.

The code-scanning threshold is intentionally staged at `critical`. CampaignBridge
had eight pre-existing high-severity JavaScript alerts when this ruleset was
introduced. Raising `security_alerts_threshold` to `high_or_higher` before those
findings are fixed would make every pull request permanently unmergeable. Fix
the alerts, verify they are closed on `master`, then strengthen the committed and
live rule together.

The matching repository settings are squash-only merges, pull-request titles as
squash subjects, native auto-merge enabled, and automatic head-branch deletion.
Default Actions permissions remain read-only. CampaignBridge's release workflow
creates a signed version-sync pull request rather than pushing release metadata
through this protection.

## Applying and verifying

Resolve the live ruleset by name so repository recreation does not depend on a
hard-coded ID:

```sh
RULESET_ID="$(gh api repos/TheAggressive/CampaignBridge/rulesets \
  --jq '.[] | select(.name == "release-branches") | .id')"

if [ -n "${RULESET_ID}" ]; then
  gh api --method PUT \
    "repos/TheAggressive/CampaignBridge/rulesets/${RULESET_ID}" \
    --input .github/rulesets/release-branches.json
else
  gh api --method POST repos/TheAggressive/CampaignBridge/rulesets \
    --input .github/rulesets/release-branches.json
fi
```

Run the same comparison used by the weekly drift workflow:

```sh
GITHUB_REPOSITORY=TheAggressive/CampaignBridge \
  node bin/ci/ruleset-drift.mjs
```

The scheduled workflow uses the existing repository-scoped CI App credentials
with administration read permission. It can report drift but cannot alter the
ruleset it audits.
