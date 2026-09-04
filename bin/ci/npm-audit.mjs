/**
 * Run the npm production dependency audit under an explicit failure policy.
 *
 * Replaces a bare `pnpm audit --prod --audit-level moderate` so that an
 * unreachable advisory service is reported as unknown rather than as either a
 * vulnerability or a pass. See `npm-audit-rules.mjs` for the policy itself.
 */

import { spawnSync } from 'node:child_process';
import process from 'node:process';

import { classifyAttempt, decide, OUTCOMES } from './npm-audit-rules.mjs';

const AUDIT_LEVEL = process.env.CB_AUDIT_LEVEL || 'moderate';
const MAX_ATTEMPTS = Number(process.env.CB_AUDIT_ATTEMPTS || 3);
const BACKOFF_MS = Number(process.env.CB_AUDIT_BACKOFF_MS || 5000);
const ATTEMPT_TIMEOUT_MS = Number(process.env.CB_AUDIT_TIMEOUT_MS || 45000);

// GitHub Actions and most hosted runners set CI=true.
const isCI = ['1', 'true'].includes(String(process.env.CI).toLowerCase());

/** Block the process without pulling in a timer dependency. */
function sleepSync(ms) {
  Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, ms);
}

function runAudit() {
  const result = spawnSync(
    'pnpm',
    ['audit', '--prod', '--audit-level', AUDIT_LEVEL, '--json'],
    {
      encoding: 'utf8',
      maxBuffer: 64 * 1024 * 1024,
      // pnpm retries a dropped request internally for over a minute. This
      // wrapper owns the retry policy, so bound each attempt instead.
      timeout: ATTEMPT_TIMEOUT_MS,
      killSignal: 'SIGKILL',
      env: { ...process.env, npm_config_fetch_retries: '0' },
    }
  );

  const stdout = result.stdout ?? '';
  const stderr = result.stderr ?? '';

  // A killed attempt reports no status; say so rather than leaking exit 1.
  if (result.signal || result.error) {
    const detail = result.signal
      ? `ETIMEDOUT after ${ATTEMPT_TIMEOUT_MS}ms`
      : String(result.error.message);

    return { exitCode: 1, stdout, stderr: `${stderr}\n${detail}` };
  }

  return { exitCode: result.status ?? 1, stdout, stderr };
}

let classification = { outcome: OUTCOMES.UNAVAILABLE, reason: 'not attempted' };
let attempts = 0;

for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt += 1) {
  attempts = attempt;
  classification = classifyAttempt({ ...runAudit(), level: AUDIT_LEVEL });

  // Only an unreachable service is worth retrying; a finding is stable.
  if (classification.outcome !== OUTCOMES.UNAVAILABLE) {
    break;
  }

  if (attempt < MAX_ATTEMPTS) {
    process.stderr.write(
      `npm audit attempt ${attempt}/${MAX_ATTEMPTS} failed: ${classification.reason}\n`
    );
    sleepSync(BACKOFF_MS * attempt);
  }
}

const decision = decide({
  ...classification,
  attempts,
  isCI,
  level: AUDIT_LEVEL,
});

const prefix = { ok: '', warn: 'WARNING: ', error: 'ERROR: ' }[
  decision.severity
];
const stream = decision.severity === 'ok' ? process.stdout : process.stderr;

stream.write(`${prefix}${decision.lines.join('\n')}\n`);
process.exit(decision.exitCode);
