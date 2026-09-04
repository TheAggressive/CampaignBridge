/**
 * Decision rules for the npm dependency audit.
 *
 * `pnpm audit` conflates two very different outcomes in its exit code: a real
 * advisory against a production dependency, and a failure to reach the npm
 * advisory service at all. Only the first is a statement about this repository.
 * These rules separate them so the second can never be reported as "clean".
 *
 * Pure functions only, so the policy is testable without network access.
 */

/** Severity ordering used by the npm advisory report, least to most severe. */
export const SEVERITY_ORDER = Object.freeze([
  'info',
  'low',
  'moderate',
  'high',
  'critical',
]);

/**
 * What a single audit attempt proved.
 *
 * - `clean`       a report was returned and nothing meets the threshold
 * - `vulnerable`  a report was returned and something meets the threshold
 * - `unavailable` no usable report; the audit proved nothing either way
 */
export const OUTCOMES = Object.freeze({
  CLEAN: 'clean',
  VULNERABLE: 'vulnerable',
  UNAVAILABLE: 'unavailable',
  EMPTY: 'empty',
});

const TRANSPORT_HINTS = Object.freeze([
  'EAI_AGAIN',
  'ECONNREFUSED',
  'ECONNRESET',
  'ENOTFOUND',
  'ERR_SOCKET_TIMEOUT',
  'ETIMEDOUT',
  'TimeoutError',
  'aborted due to timeout',
  'request to https',
  'socket hang up',
]);

/**
 * Count the dependencies `--prod` actually audits.
 *
 * A WordPress plugin ships PHP and compiled assets, so every npm package here
 * may be a devDependency. `pnpm audit --prod` then has an empty input set and
 * cannot report a finding however it is invoked, which makes the network call
 * pure cost: it proves nothing when it succeeds and blocks a merge when the
 * advisory service is unreachable.
 *
 * @param {object} manifest Parsed package.json.
 * @return {{ production: number, development: number }} Dependency counts.
 */
export function dependencyCounts(manifest) {
  const count = section => Object.keys(manifest?.[section] ?? {}).length;

  return {
    production: count('dependencies') + count('optionalDependencies'),
    development: count('devDependencies'),
  };
}

/**
 * Severities at or above the configured threshold.
 *
 * @param {string} level Lowest severity that should fail the audit.
 * @return {string[]} Severity names, most severe last.
 */
export function severitiesAtOrAbove(level) {
  const index = SEVERITY_ORDER.indexOf(level);

  if (index === -1) {
    throw new Error(`Unknown audit level: ${level}`);
  }

  return SEVERITY_ORDER.slice(index);
}

/**
 * Extract the advisory report from audit stdout.
 *
 * @param {string} stdout Raw stdout from the audit command.
 * @return {object|null} Parsed report, or null when stdout is not a report.
 */
export function parseReport(stdout) {
  const text = String(stdout ?? '').trim();

  if (!text) {
    return null;
  }

  // pnpm prints progress lines around the JSON document on some terminals.
  const start = text.indexOf('{');
  const end = text.lastIndexOf('}');

  if (start === -1 || end <= start) {
    return null;
  }

  try {
    const report = JSON.parse(text.slice(start, end + 1));

    return report && typeof report === 'object' ? report : null;
  } catch {
    return null;
  }
}

/**
 * Count advisories at or above the threshold in a parsed report.
 *
 * `advisories` is authoritative and is what pnpm's own exit code is derived
 * from: under `--json` pnpm has already filtered it to `--audit-level` and
 * removed anything listed in `auditConfig.ignoreGhsas`. `metadata` is only a
 * fallback for an older report shape, and deliberately over-reports, because
 * pnpm leaves those counts undecremented when advisories are ignored.
 *
 * @param {object} report Parsed advisory report.
 * @param {string} level  Lowest severity that should fail the audit.
 * @return {{ total: number, bySeverity: Record<string, number> }|null}
 *         Counts, or null when the report does not carry a usable summary.
 */
export function countFindings(report, level) {
  const included = new Set(severitiesAtOrAbove(level));
  const bySeverity = {};
  let total = 0;

  const advisories = report?.advisories;

  if (advisories && typeof advisories === 'object') {
    for (const advisory of Object.values(advisories)) {
      const severity = advisory?.severity;

      if (included.has(severity)) {
        bySeverity[severity] = (bySeverity[severity] ?? 0) + 1;
        total += 1;
      }
    }

    return { total, bySeverity };
  }

  const vulnerabilities = report?.metadata?.vulnerabilities;

  if (!vulnerabilities || typeof vulnerabilities !== 'object') {
    return null;
  }

  for (const severity of included) {
    const count = Number(vulnerabilities[severity] ?? 0);

    if (!Number.isFinite(count) || count < 0) {
      return null;
    }

    if (count > 0) {
      bySeverity[severity] = count;
      total += count;
    }
  }

  return { total, bySeverity };
}

/**
 * Classify one audit attempt.
 *
 * An attempt only proves something when it produced a parseable report with a
 * severity summary. Anything else — a timeout, a proxy error, an unrecognized
 * payload — is `unavailable`, never `clean`.
 *
 * @param {{ exitCode: number, stdout: string, stderr: string, level: string }} attempt Attempt result.
 * @return {{ outcome: string, findings?: object, reason?: string }} Classification.
 */
export function classifyAttempt({ exitCode, stdout, stderr, level }) {
  const report = parseReport(stdout);
  const findings = report ? countFindings(report, level) : null;

  if (findings) {
    return findings.total > 0
      ? { outcome: OUTCOMES.VULNERABLE, findings }
      : { outcome: OUTCOMES.CLEAN, findings };
  }

  const noise = `${stdout ?? ''}\n${stderr ?? ''}`;
  const hint = TRANSPORT_HINTS.find(pattern => noise.includes(pattern));

  return {
    outcome: OUTCOMES.UNAVAILABLE,
    reason: hint
      ? `advisory service unreachable (${hint})`
      : `audit produced no usable report (exit code ${exitCode})`,
  };
}

/**
 * Turn a final outcome into an exit code and an operator-facing message.
 *
 * Findings always fail. An unreachable advisory service fails in CI, where a
 * rerun is cheap and the gate is authoritative, and warns locally, where a
 * red gate nobody can fix trains developers to bypass every check. A skipped
 * audit is never presented as a passing one.
 *
 * @param {{ outcome: string, findings?: object, reason?: string, isCI: boolean, level: string, attempts: number, counts?: object }} input Final state.
 * @return {{ exitCode: number, severity: string, lines: string[] }} Decision.
 */
export function decide({
  outcome,
  findings,
  reason,
  isCI,
  level,
  attempts,
  counts,
}) {
  if (outcome === OUTCOMES.EMPTY) {
    return {
      exitCode: 0,
      severity: 'warn',
      lines: [
        'npm audit skipped: this package declares no production dependencies.',
        `--prod audits nothing here, so no request was made. ${counts?.development ?? 0} devDependencies are NOT covered by this gate.`,
      ],
    };
  }

  if (outcome === OUTCOMES.CLEAN) {
    return {
      exitCode: 0,
      severity: 'ok',
      lines: [`npm audit: no ${level}-or-higher advisories in production deps`],
    };
  }

  if (outcome === OUTCOMES.VULNERABLE) {
    const summary = Object.entries(findings?.bySeverity ?? {})
      .map(([severity, count]) => `${count} ${severity}`)
      .join(', ');

    return {
      exitCode: 1,
      severity: 'error',
      lines: [
        `npm audit: ${findings?.total ?? 0} advisory finding(s) at ${level} or higher (${summary})`,
        'Run `pnpm audit --prod` for detail. This is a finding, not a network failure.',
      ],
    };
  }

  const context = [
    `npm audit did not run: ${reason}`,
    `Tried ${attempts} time(s). This proves nothing about dependency safety.`,
  ];

  return isCI
    ? {
        exitCode: 1,
        severity: 'error',
        lines: [...context, 'Failing closed in CI.'],
      }
    : {
        exitCode: 0,
        severity: 'warn',
        lines: [...context, 'Not failing your local gate; CI will enforce it.'],
      };
}
