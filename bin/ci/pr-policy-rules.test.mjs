import { strict as assert } from 'node:assert';
import test from 'node:test';

import {
  classify,
  decide,
  isDependabot,
  KNOWN_TYPES,
  LABEL_DEFINITIONS,
  MANAGED_LABELS,
  parseTitle,
  REQUIRED_CHECKS,
} from './pr-policy-rules.mjs';

const HUMAN = 'TheAggressive';
const GREEN_CHECKS = Object.fromEntries(
  REQUIRED_CHECKS.map(context => [context, 'SUCCESS'])
);

function classification(title, files, author = HUMAN) {
  return classify({ title, files, author });
}

function verdict(result, overrides = {}) {
  return decide({
    classification: result,
    author: HUMAN,
    labels: [],
    mergeStateStatus: 'CLEAN',
    checks: GREEN_CHECKS,
    trustedAuthors: [HUMAN],
    ...overrides,
  });
}

test('parses scoped and unscoped Conventional Commit titles', () => {
  assert.equal(parseTitle('feat: add approvals').type, 'feat');
  assert.equal(parseTitle('fix(api): reject invalid data').scope, 'api');
});

test('recognizes breaking changes', () => {
  assert.equal(parseTitle('feat!: change the contract').breaking, true);
  assert.equal(parseTitle('feat(api)!: change the contract').breaking, true);
});

test('rejects malformed, unknown, empty, and punctuated titles', () => {
  assert.equal(parseTitle('Update dependencies').valid, false);
  assert.equal(parseTitle('wip: unfinished').valid, false);
  assert.equal(parseTitle('').valid, false);
  assert.equal(parseTitle('fix: trailing punctuation.').valid, false);
});

test('accepts Dependabot title shapes without a special parser', () => {
  assert.equal(
    parseTitle('chore(deps): bump the wordpress group with 4 updates').valid,
    true
  );
  assert.ok(KNOWN_TYPES.includes('chore'));
});

test('recognizes both Dependabot identities', () => {
  assert.equal(isDependabot('dependabot[bot]'), true);
  assert.equal(isDependabot('app/dependabot'), true);
  assert.equal(isDependabot('dependabot'), false);
});

test('classifies documentation-only changes as low risk', () => {
  const result = classification('docs: explain approvals', [
    'docs/approvals.md',
    'README.md',
  ]);

  assert.equal(result.risk, 'risk:low');
  assert.equal(result.prose, true);
  assert.ok(result.labels.includes('type:docs'));
});

test('classifies tests-only changes as low risk', () => {
  const result = classification('test: cover approval refusal', [
    'tests/Unit/Approval_Test.php',
  ]);

  assert.equal(result.risk, 'risk:low');
  assert.ok(result.labels.includes('area:tests'));
  assert.ok(result.labels.includes('area:php'));
});

test('classifies blocks and compiler files by area', () => {
  const result = classification('feat: add an email block', [
    'src/blocks/columns/edit.tsx',
    'includes/Services/Email/Renderer/Columns_Renderer.php',
  ]);

  assert.equal(result.risk, 'risk:medium');
  assert.ok(result.labels.includes('area:blocks'));
  assert.ok(result.labels.includes('area:email'));
  assert.ok(result.labels.includes('area:frontend'));
  assert.ok(result.labels.includes('area:php'));
});

test('classifies provider code and treats it as high risk', () => {
  const result = classification('feat: add provider drafts', [
    'includes/Providers/Mailchimp/Draft_Client.php',
  ]);

  assert.equal(result.risk, 'risk:high');
  assert.ok(result.labels.includes('area:providers'));
});

test('treats workflow and ruleset changes as high risk', () => {
  for (const file of [
    '.github/workflows/ci.yml',
    '.github/rulesets/release-branches.json',
    '.github/dependabot.yml',
    '.github/CODEOWNERS',
  ]) {
    assert.equal(
      classification('ci: harden automation', [file]).risk,
      'risk:high',
      file
    );
  }
});

test('treats release, CI, security, workflow, and uninstall code as high risk', () => {
  for (const file of [
    'bin/release/package.sh',
    'bin/ci/check-action-pins.sh',
    'includes/Core/Encryption.php',
    'includes/REST/Form_Rest_Controller.php',
    'includes/Workflow/Email/Artifact_Fingerprinter.php',
    'campaignbridge.php',
    'uninstall.php',
  ]) {
    assert.equal(
      classification('fix: adjust behavior', [file]).risk,
      'risk:high',
      file
    );
  }
});

test('treats a breaking title as high risk', () => {
  const result = classification('feat!: change the compiler contract', [
    'docs/email-block-architecture.md',
  ]);

  assert.equal(result.risk, 'risk:high');
  assert.match(result.riskReasons.join('\n'), /breaking change/u);
});

test('fails closed when changed files cannot be determined', () => {
  const result = classification('fix: unknown change', []);

  assert.equal(result.risk, 'risk:high');
  assert.match(result.riskReasons.join('\n'), /no changed files/u);
});

test('treats a human manifest edit as high risk', () => {
  const result = classification('chore: change the runtime floor', [
    'package.json',
    'pnpm-lock.yaml',
  ]);

  assert.equal(result.risk, 'risk:high');
});

test('allows routine Dependabot manifest changes to remain medium risk', () => {
  const result = classification(
    'chore(deps): bump the js-tooling group with 3 updates',
    ['package.json', 'pnpm-lock.yaml'],
    'dependabot[bot]'
  );

  assert.equal(result.risk, 'risk:medium');
  assert.ok(result.labels.includes('dependencies'));
});

test('keeps Dependabot workflow changes high risk', () => {
  const result = classification(
    'chore(deps): bump actions/checkout from 7.0.0 to 7.0.1',
    ['.github/workflows/ci.yml'],
    'dependabot[bot]'
  );

  assert.equal(result.risk, 'risk:high');
});

test('high risk always defeats an automerge label', () => {
  const result = classification('ci: change a workflow', [
    '.github/workflows/ci.yml',
  ]);

  assert.equal(verdict(result, { labels: ['automerge'] }).action, 'skip');
});

test('human pull requests require explicit automerge opt-in', () => {
  const result = classification('docs: explain a setting', ['docs/x.md']);

  assert.equal(verdict(result).action, 'skip');
  assert.equal(verdict(result, { labels: ['automerge'] }).action, 'merge');
});

test('an automerge label cannot authorize an untrusted author', () => {
  const result = classification('docs: explain a setting', ['docs/x.md']);
  const decision = verdict(result, {
    author: 'external-contributor',
    labels: ['automerge'],
  });

  assert.equal(decision.action, 'skip');
  assert.match(decision.reason, /not permitted/u);
});

test('Dependabot patch and grouped updates need no automerge label', () => {
  const result = classification(
    'chore(deps): bump eslint from 9.0.0 to 9.0.1',
    ['package.json', 'pnpm-lock.yaml'],
    'dependabot[bot]'
  );

  assert.equal(verdict(result, { author: 'dependabot[bot]' }).action, 'merge');
});

test('Dependabot major updates never auto-merge', () => {
  const result = classification(
    'chore(deps): bump eslint from 9.0.0 to 10.0.0',
    ['package.json', 'pnpm-lock.yaml'],
    'dependabot[bot]'
  );

  assert.equal(
    verdict(result, {
      author: 'dependabot[bot]',
      labels: ['dependency-major'],
    }).action,
    'skip'
  );
});

test('updates a stale branch and refuses a conflicting branch', () => {
  const result = classification('docs: explain a setting', ['docs/x.md']);

  assert.equal(
    verdict(result, {
      labels: ['automerge'],
      mergeStateStatus: 'BEHIND',
    }).action,
    'update-branch'
  );
  assert.equal(
    verdict(result, {
      labels: ['automerge'],
      mergeStateStatus: 'DIRTY',
    }).action,
    'skip'
  );
});

test('waits for blocked and unknown mergeability', () => {
  const result = classification('docs: explain a setting', ['docs/x.md']);

  assert.equal(
    verdict(result, {
      labels: ['automerge'],
      mergeStateStatus: 'BLOCKED',
    }).action,
    'wait'
  );
  assert.equal(
    verdict(result, {
      labels: ['automerge'],
      mergeStateStatus: 'UNKNOWN',
    }).action,
    'wait'
  );
});

test('refuses an unrecognized merge state', () => {
  const result = classification('docs: explain a setting', ['docs/x.md']);

  assert.equal(
    verdict(result, {
      labels: ['automerge'],
      mergeStateStatus: 'NEW_STATE',
    }).action,
    'skip'
  );
});

test('requires every protected context to report', () => {
  const result = classification('docs: explain a setting', ['docs/x.md']);
  const checks = { ...GREEN_CHECKS };
  delete checks['PR Title'];
  const decision = verdict(result, { labels: ['automerge'], checks });

  assert.equal(decision.action, 'wait');
  assert.match(decision.reason, /PR Title/u);
});

test('waits for any reported pending or failed check', () => {
  const result = classification('docs: explain a setting', ['docs/x.md']);

  assert.equal(
    verdict(result, {
      labels: ['automerge'],
      checks: { ...GREEN_CHECKS, 'Optional audit': 'PENDING' },
    }).action,
    'wait'
  );
  assert.equal(
    verdict(result, {
      labels: ['automerge'],
      checks: { ...GREEN_CHECKS, 'Optional audit': 'FAILURE' },
    }).action,
    'wait'
  );
});

test('allows skipped and neutral non-required checks', () => {
  const result = classification('docs: explain a setting', ['docs/x.md']);
  const decision = verdict(result, {
    labels: ['automerge'],
    checks: {
      ...GREEN_CHECKS,
      Release: 'SKIPPED',
      'Optional audit': 'NEUTRAL',
    },
  });

  assert.equal(decision.action, 'merge');
});

test('drafts and requested changes never auto-merge', () => {
  const result = classification('docs: explain a setting', ['docs/x.md']);

  assert.equal(
    verdict(result, { labels: ['automerge'], draft: true }).action,
    'skip'
  );
  assert.equal(
    verdict(result, {
      labels: ['automerge'],
      reviewDecision: 'CHANGES_REQUESTED',
    }).action,
    'skip'
  );
});

test('invalid titles never auto-merge', () => {
  const result = classification('Update docs', ['docs/x.md']);

  assert.equal(verdict(result, { labels: ['automerge'] }).action, 'skip');
});

test('every managed and classifier-owned label has a definition', () => {
  for (const label of MANAGED_LABELS) {
    assert.ok(LABEL_DEFINITIONS[label], label);
  }

  const allAreas = classification('feat: cover every area', [
    'src/blocks/x/edit.tsx',
    'includes/Services/Email/X.php',
    'includes/Providers/X.php',
    'tests/Unit/X.php',
    '.github/workflows/ci.yml',
    'bin/release/package.sh',
  ]);
  for (const label of allAreas.labels) {
    assert.ok(LABEL_DEFINITIONS[label], label);
  }
});

test('the policy has a bounded readable label vocabulary', () => {
  assert.ok(MANAGED_LABELS.length <= 18);
  assert.equal(new Set(MANAGED_LABELS).size, MANAGED_LABELS.length);
});
