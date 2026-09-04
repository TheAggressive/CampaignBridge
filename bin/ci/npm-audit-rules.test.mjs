import { strict as assert } from 'node:assert';
import test from 'node:test';

import {
  classifyAttempt,
  countFindings,
  decide,
  dependencyCounts,
  OUTCOMES,
  parseReport,
  severitiesAtOrAbove,
} from './npm-audit-rules.mjs';

const LEVEL = 'moderate';

function report(vulnerabilities, advisories = {}) {
  return JSON.stringify({
    advisories,
    metadata: { vulnerabilities },
  });
}

/** Shape pnpm emits under --json: advisories already filtered to the level. */
function advisory(id, severity) {
  return [id, { github_advisory_id: id, severity, module_name: 'pkg' }];
}

const NO_VULNERABILITIES = {
  info: 0,
  low: 0,
  moderate: 0,
  high: 0,
  critical: 0,
};

function attempt(overrides = {}) {
  return classifyAttempt({
    exitCode: 0,
    stdout: '',
    stderr: '',
    level: LEVEL,
    ...overrides,
  });
}

function verdict(classification, overrides = {}) {
  return decide({
    ...classification,
    attempts: 3,
    isCI: false,
    level: LEVEL,
    ...overrides,
  });
}

test('the threshold includes its own severity and everything above it', () => {
  assert.deepEqual(severitiesAtOrAbove('moderate'), [
    'moderate',
    'high',
    'critical',
  ]);
  assert.deepEqual(severitiesAtOrAbove('critical'), ['critical']);
  assert.throws(() => severitiesAtOrAbove('catastrophic'));
});

test('a report is recovered even when surrounded by progress output', () => {
  const noisy = `Progress: resolved 12\n${report(NO_VULNERABILITIES)}\ndone`;

  assert.equal(parseReport(noisy)?.metadata.vulnerabilities.high, 0);
  assert.equal(parseReport(''), null);
  assert.equal(parseReport('not json at all'), null);
  assert.equal(parseReport('{ broken'), null);
});

test('advisories are counted in preference to the metadata summary', () => {
  // pnpm drops ignored advisories from `advisories` but leaves `metadata`
  // counting them, so trusting metadata would fail on an ignored finding.
  const counts = countFindings(
    {
      advisories: Object.fromEntries([advisory('GHSA-a', 'high')]),
      metadata: { vulnerabilities: { ...NO_VULNERABILITIES, high: 3 } },
    },
    LEVEL
  );

  assert.equal(counts.total, 1);
  assert.deepEqual(counts.bySeverity, { high: 1 });
});

test('an empty advisory set is clean even when metadata disagrees', () => {
  const counts = countFindings(
    {
      advisories: {},
      metadata: { vulnerabilities: { ...NO_VULNERABILITIES, critical: 9 } },
    },
    LEVEL
  );

  assert.equal(counts.total, 0);
});

test('findings below the threshold do not count', () => {
  const counts = countFindings(
    {
      advisories: Object.fromEntries([
        advisory('GHSA-a', 'low'),
        advisory('GHSA-b', 'moderate'),
        advisory('GHSA-c', 'critical'),
      ]),
    },
    LEVEL
  );

  assert.equal(counts.total, 2);
  assert.deepEqual(counts.bySeverity, { moderate: 1, critical: 1 });
});

test('metadata is used when a report carries no advisory map', () => {
  const counts = countFindings(
    {
      metadata: { vulnerabilities: { ...NO_VULNERABILITIES, low: 4, high: 2 } },
    },
    LEVEL
  );

  assert.equal(counts.total, 2);
  assert.deepEqual(counts.bySeverity, { high: 2 });
});

test('an unrecognized report summary yields no counts', () => {
  assert.equal(countFindings({}, LEVEL), null);
  assert.equal(countFindings({ metadata: {} }, LEVEL), null);
  assert.equal(
    countFindings(
      { metadata: { vulnerabilities: { moderate: 'lots' } } },
      LEVEL
    ),
    null
  );
});

test('a clean report is classified clean', () => {
  assert.equal(
    attempt({ stdout: report(NO_VULNERABILITIES) }).outcome,
    OUTCOMES.CLEAN
  );
});

test('a report with findings is vulnerable even on a zero exit code', () => {
  const result = attempt({
    exitCode: 0,
    stdout: report(
      { ...NO_VULNERABILITIES, high: 1 },
      Object.fromEntries([advisory('GHSA-a', 'high')])
    ),
  });

  assert.equal(result.outcome, OUTCOMES.VULNERABLE);
  assert.equal(result.findings.total, 1);
});

test('a timeout is unavailable, never clean', () => {
  const result = attempt({
    exitCode: 1,
    stdout: '',
    stderr: 'TimeoutError: The operation was aborted due to timeout',
  });

  assert.equal(result.outcome, OUTCOMES.UNAVAILABLE);
  assert.match(result.reason, /unreachable/);
});

test('an unparseable payload is unavailable rather than assumed safe', () => {
  const result = attempt({ exitCode: 0, stdout: '<html>502 Bad Gateway' });

  assert.equal(result.outcome, OUTCOMES.UNAVAILABLE);
});

test('findings fail everywhere, and are not blamed on the network', () => {
  const classification = attempt({
    stdout: report(
      { ...NO_VULNERABILITIES, critical: 2 },
      Object.fromEntries([
        advisory('GHSA-a', 'critical'),
        advisory('GHSA-b', 'critical'),
      ])
    ),
  });

  for (const isCI of [true, false]) {
    const decision = verdict(classification, { isCI });

    assert.equal(decision.exitCode, 1);
    assert.equal(decision.severity, 'error');
    assert.match(decision.lines.join('\n'), /not a network failure/);
  }
});

test('an unreachable service fails closed in CI and warns locally', () => {
  const classification = attempt({
    exitCode: 1,
    stderr: 'ENOTFOUND registry.npmjs.org',
  });

  const ci = verdict(classification, { isCI: true });
  assert.equal(ci.exitCode, 1);
  assert.equal(ci.severity, 'error');
  assert.match(ci.lines.join('\n'), /Failing closed in CI/);

  const local = verdict(classification, { isCI: false });
  assert.equal(local.exitCode, 0);
  assert.equal(local.severity, 'warn');
  assert.match(
    local.lines.join('\n'),
    /proves nothing about dependency safety/
  );
});

test('a clean audit is the only outcome that reports success', () => {
  const decision = verdict(attempt({ stdout: report(NO_VULNERABILITIES) }));

  assert.equal(decision.exitCode, 0);
  assert.equal(decision.severity, 'ok');
});

test('production dependencies count both runtime sections', () => {
  assert.deepEqual(
    dependencyCounts({
      dependencies: { a: '1' },
      optionalDependencies: { b: '1' },
      devDependencies: { c: '1', d: '1' },
    }),
    { production: 2, development: 2 }
  );
  assert.deepEqual(dependencyCounts({}), { production: 0, development: 0 });
  assert.deepEqual(dependencyCounts(undefined), {
    production: 0,
    development: 0,
  });
});

test('an empty production set skips the request without claiming safety', () => {
  const decision = decide({
    outcome: OUTCOMES.EMPTY,
    counts: { production: 0, development: 61 },
    attempts: 0,
    isCI: true,
    level: LEVEL,
  });

  assert.equal(decision.exitCode, 0);
  assert.equal(decision.severity, 'warn');
  const text = decision.lines.join('\n');
  // The operator must be told what was not checked.
  assert.match(text, /61 devDependencies are NOT covered/);
  assert.doesNotMatch(text, /no .*advisories/);
});

test('an empty production set behaves identically in CI and locally', () => {
  const base = {
    outcome: OUTCOMES.EMPTY,
    counts: { production: 0, development: 3 },
    attempts: 0,
    level: LEVEL,
  };

  assert.deepEqual(
    decide({ ...base, isCI: true }),
    decide({ ...base, isCI: false })
  );
});
