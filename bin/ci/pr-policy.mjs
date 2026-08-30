#!/usr/bin/env node
/**
 * GitHub controller for the tested pull-request policy.
 *
 * It reads every decision input from GitHub's API, reconciles only labels the
 * policy owns, and requests native auto-merge rather than merging directly.
 */

import { execFileSync } from 'node:child_process';

import {
  classify,
  decide,
  isDependabot,
  LABEL_DEFINITIONS,
  MANAGED_LABELS,
} from './pr-policy-rules.mjs';

const number = process.argv[2];
if (!/^[0-9]+$/u.test(number ?? '')) {
  console.error('pr-policy: expected a pull request number');
  process.exit(1);
}

const trustedAuthors = (process.env.CB_AUTOMERGE_ACTORS ?? '')
  .split(',')
  .map(login => login.trim())
  .filter(Boolean);
const dryRun = process.env.CB_PR_POLICY_DRY_RUN === '1';

function gh(args) {
  return execFileSync('gh', args, {
    encoding: 'utf8',
    maxBuffer: 16 * 1024 * 1024,
  });
}

function syncLabels() {
  const existing = new Set(
    JSON.parse(gh(['label', 'list', '--limit', '100', '--json', 'name'])).map(
      label => label.name
    )
  );

  for (const [name, [color, description]] of Object.entries(
    LABEL_DEFINITIONS
  )) {
    if (existing.has(name)) continue;

    gh([
      'label',
      'create',
      name,
      '--color',
      color,
      '--description',
      description,
      '--force',
    ]);
  }
}

const pr = JSON.parse(
  gh([
    'pr',
    'view',
    number,
    '--json',
    [
      'author',
      'title',
      'labels',
      'files',
      'isDraft',
      'mergeStateStatus',
      'reviewDecision',
      'statusCheckRollup',
      'headRefOid',
      'headRefName',
      'baseRefName',
    ].join(','),
  ])
);

if (pr.baseRefName !== 'master') {
  console.error(`pr-policy: refusing unexpected base ${pr.baseRefName}`);
  process.exit(1);
}

const author = pr.author?.login ?? '';
const files = (pr.files ?? []).map(file => file.path);
const labels = (pr.labels ?? []).map(label => label.name);
const classification = classify({ title: pr.title, author, files });

const major = (() => {
  const bump = /from\s+(\d+)\.\d+\.\d+\S*\s+to\s+(\d+)\.\d+\.\d+/u.exec(
    pr.title ?? ''
  );
  return bump !== null && bump[1] !== bump[2];
})();

const desired = new Set(classification.labels);
if (major && isDependabot(author)) desired.add('dependency-major');

const checks = {};
for (const check of pr.statusCheckRollup ?? []) {
  const context = check.name || check.context;
  if (!context) continue;

  checks[context] =
    check.conclusion || check.state || check.status || 'PENDING';
}

const verdict = decide({
  classification,
  author,
  labels: [...new Set([...labels, ...desired])],
  mergeStateStatus: pr.mergeStateStatus,
  draft: Boolean(pr.isDraft),
  checks,
  reviewDecision: pr.reviewDecision ?? '',
  trustedAuthors,
});

if (
  classification.risk === 'risk:high' ||
  !classification.title.valid ||
  desired.has('dependency-major') ||
  ['DIRTY'].includes(pr.mergeStateStatus) ||
  pr.reviewDecision === 'CHANGES_REQUESTED'
) {
  desired.add('needs-attention');
}

const managed = new Set([...MANAGED_LABELS, 'dependency-major']);
const toAdd = [...desired].filter(label => !labels.includes(label));
const toRemove = labels.filter(
  label => managed.has(label) && !desired.has(label)
);

console.log(`#${number} by ${author}`);
console.log(`  risk:   ${classification.risk}`);
for (const reason of classification.riskReasons) {
  console.log(`          ${reason}`);
}
console.log(`  labels: ${[...desired].sort().join(', ') || '(none)'}`);
console.log(`  action: ${verdict.action} — ${verdict.reason}`);

if (dryRun) process.exit(0);

syncLabels();

if (toAdd.length > 0) {
  gh(['pr', 'edit', number, ...toAdd.flatMap(label => ['--add-label', label])]);
}

if (toRemove.length > 0) {
  gh([
    'pr',
    'edit',
    number,
    ...toRemove.flatMap(label => ['--remove-label', label]),
  ]);
}

switch (verdict.action) {
  case 'update-branch':
    gh([
      'api',
      '--method',
      'PUT',
      `repos/${process.env.GH_REPO}/pulls/${number}/update-branch`,
      '-f',
      `expected_head_sha=${pr.headRefOid}`,
    ]);
    console.log('  branch updated; fresh checks will decide.');
    break;

  case 'merge':
    gh(['pr', 'merge', number, '--auto', '--squash', '--delete-branch']);
    console.log('  native squash auto-merge registered.');
    break;

  default:
    break;
}
