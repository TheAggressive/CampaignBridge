/**
 * Pure pull-request classification and automation decisions.
 *
 * This module is deliberately independent of GitHub. The controller may only
 * act when every uncertain state resolves to "wait" or "skip" here.
 */

const TYPE_LABELS = Object.freeze({
  feat: 'type:feature',
  fix: 'type:fix',
  perf: 'type:refactor',
  refactor: 'type:refactor',
  docs: 'type:docs',
  test: 'type:chore',
  build: 'type:chore',
  ci: 'type:chore',
  chore: 'type:chore',
  style: 'type:chore',
  revert: 'type:fix',
});

export const KNOWN_TYPES = Object.freeze(Object.keys(TYPE_LABELS));

export const REQUIRED_CHECKS = Object.freeze([
  'CI summary',
  'Analyze JavaScript and TypeScript',
  'Actionlint',
  'Zizmor',
  'PR Title',
]);

export const LABEL_DEFINITIONS = Object.freeze({
  'type:feature': ['1d76db', 'New capability'],
  'type:fix': ['d73a4a', 'Corrects a defect'],
  'type:refactor': ['6f42c1', 'Restructures without changing behavior'],
  'type:chore': ['8b949e', 'Tooling, tests, CI, or build work'],
  'type:docs': ['0075ca', 'Documentation-only intent'],
  dependencies: ['0366d6', 'Dependency updates'],
  'area:release': ['b60205', 'Release or publishing behavior'],
  'area:ci': ['5319e7', 'Continuous-integration policy or tooling'],
  'area:blocks': ['7057ff', 'WordPress email blocks'],
  'area:email': ['0e8a16', 'Email compiler or campaign workflow'],
  'area:tests': ['bfd4f2', 'Automated tests or test configuration'],
  'area:frontend': ['f1e05a', 'Frontend source or Node tooling'],
  'area:php': ['4f5d95', 'PHP or Composer code'],
  'area:providers': ['96588a', 'Provider integrations or delivery boundaries'],
  'risk:low': ['0e8a16', 'Narrow change eligible for opted-in automation'],
  'risk:medium': ['fbca04', 'Normal product code'],
  'risk:high': ['b60205', 'Security or release sensitive; never auto-merged'],
  automerge: ['5319e7', 'Opt in to native squash auto-merge'],
  'needs-attention': [
    'd93f0b',
    'Automation stopped; maintainer action required',
  ],
  'dependency-major': ['d93f0b', 'Major dependency update; review required'],
});

const HIGH_RISK_PATTERNS = Object.freeze([
  { re: /^\.github\/workflows\//u, why: 'workflow definition' },
  { re: /^\.github\/rulesets\//u, why: 'branch protection ruleset' },
  { re: /^\.github\/CODEOWNERS$/u, why: 'code owners' },
  { re: /^\.github\/dependabot\.yml$/u, why: 'dependency update policy' },
  { re: /^\.github\/actions\//u, why: 'composite action' },
  { re: /^\.releaserc\.json$/u, why: 'release configuration' },
  { re: /^bin\/release\//u, why: 'release machinery' },
  { re: /^bin\/ci\//u, why: 'CI contract or enforcement script' },
  {
    re: /^(phpcs\.xml\.dist|phpstan\.neon(?:\.dist)?|eslint\.config\..*|tsconfig\.json)$/u,
    why: 'static-analysis configuration',
  },
  {
    re: /^phpunit.*\.xml(?:\.dist)?$/u,
    why: 'test configuration',
  },
  { re: /^includes\/Core\/(?:Encryption|Security)/u, why: 'security boundary' },
  { re: /^includes\/REST\//u, why: 'REST authorization boundary' },
  { re: /^includes\/Providers\//u, why: 'external provider boundary' },
  { re: /^includes\/Repository\//u, why: 'persistence boundary' },
  { re: /^includes\/Workflow\//u, why: 'campaign state transition' },
  { re: /^uninstall\.php$/u, why: 'destructive uninstall' },
  { re: /^campaignbridge\.php$/u, why: 'plugin header and runtime floor' },
]);

const MANIFEST_PATTERNS = Object.freeze([
  /^composer\.json$/u,
  /^package\.json$/u,
]);

const AREA_PATTERNS = Object.freeze([
  { label: 'area:release', re: /^(bin\/release\/|\.releaserc\.json$)/u },
  { label: 'area:ci', re: /^(\.github\/|bin\/ci\/)/u },
  { label: 'area:blocks', re: /^src\/blocks\//u },
  {
    label: 'area:email',
    re: /^(includes\/(?:Domain|Services|Workflow)\/Email\/|docs\/email-|src\/scripts\/editor\/)/u,
  },
  {
    label: 'area:tests',
    re: /^(tests\/|.*\.test\.mjs$|phpunit.*\.xml)/u,
  },
  {
    label: 'area:frontend',
    re: /^(src\/|webpack\..*\.mjs$|.*\.(?:js|jsx|ts|tsx|css)$)/u,
  },
  {
    label: 'area:php',
    re: /^(includes\/|templates\/|.*\.php$|composer\.json$|phpcs\.xml\.dist$|phpstan\.neon)/u,
  },
  { label: 'area:providers', re: /^includes\/Providers\//u },
]);

const PROSE_RE = /^(docs\/|.*\.md$|LICENSE$|\.github\/ISSUE_TEMPLATE\/)/u;

/** Parse a Conventional Commit pull-request title. */
export function parseTitle(title) {
  if (typeof title !== 'string' || title.trim() === '') {
    return {
      valid: false,
      type: null,
      scope: null,
      breaking: false,
      problem: 'the title is empty',
    };
  }

  const match = /^([a-z]+)(?:\(([^)]+)\))?(!)?:\s+(.+)$/u.exec(title.trim());
  if (match === null) {
    return {
      valid: false,
      type: null,
      scope: null,
      breaking: false,
      problem:
        'use Conventional Commit form, for example "fix(api): reject invalid input"',
    };
  }

  const [, type, scope = null, bang, subject] = match;
  if (!Object.hasOwn(TYPE_LABELS, type)) {
    return {
      valid: false,
      type,
      scope,
      breaking: bang === '!',
      problem: `"${type}" is not one of: ${KNOWN_TYPES.join(', ')}`,
    };
  }

  if (subject.trim().endsWith('.')) {
    return {
      valid: false,
      type,
      scope,
      breaking: bang === '!',
      problem: 'the subject must not end in a full stop',
    };
  }

  return {
    valid: true,
    type,
    scope,
    breaking: bang === '!',
    problem: null,
  };
}

/** Return whether a login is one of GitHub's Dependabot identities. */
export function isDependabot(login) {
  return login === 'app/dependabot' || login === 'dependabot[bot]';
}

/** Classify one pull request using API-verified facts. */
export function classify({ title, author, files }) {
  const paths = Array.isArray(files) ? files.filter(Boolean) : [];
  const parsed = parseTitle(title);
  const labels = new Set();

  if (parsed.valid) labels.add(TYPE_LABELS[parsed.type]);
  if (isDependabot(author)) labels.add('dependencies');

  for (const { label, re } of AREA_PATTERNS) {
    if (paths.some(file => re.test(file))) labels.add(label);
  }

  const riskReasons = [];
  for (const file of paths) {
    const match = HIGH_RISK_PATTERNS.find(({ re }) => re.test(file));
    if (match) riskReasons.push(`${file} — ${match.why}`);

    if (
      !isDependabot(author) &&
      MANIFEST_PATTERNS.some(pattern => pattern.test(file))
    ) {
      riskReasons.push(`${file} — dependency or runtime version contract`);
    }
  }

  if (parsed.breaking) riskReasons.push('the title declares a breaking change');
  if (paths.length === 0)
    riskReasons.push('no changed files could be determined');

  const prose = paths.length > 0 && paths.every(file => PROSE_RE.test(file));
  let risk = 'risk:medium';

  if (riskReasons.length > 0) {
    risk = 'risk:high';
  } else if (
    prose ||
    paths.every(
      file => /^(tests\/|languages\/)/u.test(file) || PROSE_RE.test(file)
    )
  ) {
    risk = 'risk:low';
  }

  labels.add(risk);

  return {
    labels: [...labels].sort(),
    risk,
    riskReasons,
    prose,
    title: parsed,
  };
}

export const MANAGED_LABELS = Object.freeze([
  ...new Set(Object.values(TYPE_LABELS)),
  ...AREA_PATTERNS.map(area => area.label),
  'risk:low',
  'risk:medium',
  'risk:high',
  'needs-attention',
]);

/**
 * Decide what automation may request. GitHub's native auto-merge and ruleset
 * remain authoritative even when this returns merge.
 */
export function decide({
  classification,
  author,
  labels = [],
  mergeStateStatus,
  draft = false,
  checks = {},
  reviewDecision = '',
  trustedAuthors = [],
}) {
  const has = label => labels.includes(label);

  if (draft) return { action: 'skip', reason: 'the pull request is a draft' };

  if (classification?.risk === 'risk:high') {
    return {
      action: 'skip',
      reason: 'high-risk pull requests are never merged by policy automation',
    };
  }

  if (!classification?.title?.valid) {
    return {
      action: 'skip',
      reason: 'the title is not a valid Conventional Commit',
    };
  }

  if (isDependabot(author)) {
    if (has('dependency-major')) {
      return {
        action: 'skip',
        reason: 'a major dependency update needs review',
      };
    }
  } else if (!has('automerge')) {
    return { action: 'skip', reason: 'no automerge label was requested' };
  } else if (!trustedAuthors.includes(author)) {
    return {
      action: 'skip',
      reason: `${author} is not permitted to request automatic merging`,
    };
  }

  if (reviewDecision === 'CHANGES_REQUESTED') {
    return { action: 'skip', reason: 'a reviewer requested changes' };
  }

  switch (mergeStateStatus) {
    case 'DIRTY':
      return {
        action: 'skip',
        reason: 'the pull request conflicts with its base',
      };
    case 'BLOCKED':
      return { action: 'wait', reason: 'GitHub reports the merge is blocked' };
    case 'BEHIND':
      return {
        action: 'update-branch',
        reason: 'the branch is behind; fresh checks must decide',
      };
    case 'UNKNOWN':
      return { action: 'wait', reason: 'GitHub is computing mergeability' };
    case 'CLEAN':
    case 'HAS_HOOKS':
    case 'UNSTABLE':
      break;
    default:
      return {
        action: 'skip',
        reason: `unrecognized merge state ${mergeStateStatus || '(none)'}`,
      };
  }

  const missing = REQUIRED_CHECKS.filter(context => !(context in checks));
  if (missing.length > 0) {
    return {
      action: 'wait',
      reason: `required checks have not reported: ${missing.join(', ')}`,
    };
  }

  const unfinished = Object.entries(checks)
    .filter(([, state]) => !['SUCCESS', 'SKIPPED', 'NEUTRAL'].includes(state))
    .map(([context, state]) => `${context}=${state}`);
  if (unfinished.length > 0) {
    return {
      action: 'wait',
      reason: `checks not green: ${unfinished.sort().join(', ')}`,
    };
  }

  return {
    action: 'merge',
    reason: 'every required and reported check passed',
  };
}
