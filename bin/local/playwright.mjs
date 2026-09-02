#!/usr/bin/env node

import { execFileSync, spawnSync } from 'node:child_process';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const repositoryRoot = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  '../..'
);
const sitePath = path.resolve(
  repositoryRoot,
  process.env.CB_E2E_SITE_PATH ?? '../../..'
);

let status;
try {
  status = JSON.parse(
    execFileSync('studio', ['status', '--path', sitePath, '--format', 'json'], {
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore'],
    })
  );
} catch {
  console.error('Unable to read the local WordPress Studio site.');
  process.exit(1);
}

let siteUrl;
let autoLoginUrl;
try {
  siteUrl = new URL(status.siteUrl ?? '');
  autoLoginUrl = new URL(status.autoLoginUrl ?? '');
} catch {
  console.error('Studio returned invalid site connection details.');
  process.exit(1);
}

if (siteUrl.origin !== autoLoginUrl.origin) {
  console.error('Studio returned mismatched site and auto-login origins.');
  process.exit(1);
}

const result = spawnSync(
  'pnpm',
  ['exec', 'playwright', 'test', ...process.argv.slice(2)],
  {
    cwd: repositoryRoot,
    env: {
      ...process.env,
      CB_E2E_AUTO_LOGIN_URL: autoLoginUrl.href,
      CB_E2E_BASE_URL: siteUrl.href,
    },
    stdio: 'inherit',
  }
);

if (result.error) {
  console.error('Unable to start Playwright.');
  process.exit(1);
}

process.exit(result.status ?? 1);
