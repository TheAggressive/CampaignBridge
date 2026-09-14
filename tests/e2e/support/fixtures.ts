import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import { test as base } from '@playwright/test';
import { AUTH_STATE_PATH } from './environment';

export { expect } from '@playwright/test';
export type { Locator, Page, Request } from '@playwright/test';

/**
 * Transient written by Rate_Limiter::check_rate_limit_authenticated(
 * 'editor_settings' ): Rest_Constants::CACHE_KEY_PREFIX_GENERAL, the endpoint
 * name, and the authenticated user ID.
 */
const EDITOR_SETTINGS_RATE_LIMIT_PREFIX =
  'campaignbridge_rate_limit_editor_settings_';

let adminUserId: string | undefined;

/** Run WP-CLI against the disposable E2E site named by CB_E2E_WP_CLI. */
function wpCli(args: string[]): string {
  let command: unknown;
  try {
    command = JSON.parse(process.env.CB_E2E_WP_CLI ?? '');
  } catch {
    command = undefined;
  }
  if (
    !Array.isArray(command) ||
    command.length === 0 ||
    !command.every(part => typeof part === 'string')
  ) {
    throw new Error(
      'E2E isolation requires CB_E2E_WP_CLI as a JSON array WP-CLI command.'
    );
  }

  const [executable, ...prefix] = command as string[];
  return execFileSync(executable, [...prefix, ...args], {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim();
}

/** Resolve the authenticated E2E administrator from the saved session. */
function resolveAdminUserId(): string {
  if (adminUserId) {
    return adminUserId;
  }

  const state = JSON.parse(fs.readFileSync(AUTH_STATE_PATH, 'utf8')) as {
    cookies?: { name: string; value: string }[];
  };
  const cookie = state.cookies?.find(({ name }) =>
    name.startsWith('wordpress_logged_in_')
  );
  const login = cookie ? decodeURIComponent(cookie.value).split('|')[0] : '';
  if (!login) {
    throw new Error('E2E isolation requires an authenticated session.');
  }

  const id = wpCli(['user', 'get', login, '--field=ID']);
  if (!/^\d+$/u.test(id)) {
    throw new Error('E2E isolation could not resolve the administrator.');
  }
  adminUserId = id;
  return id;
}

/**
 * Every test starts with a clean editor-settings request counter, so the
 * suite's cumulative editor loads cannot exhaust the production rate limit
 * for the shared administrator. The limiter itself is unchanged.
 */
export const test = base.extend<{ editorSettingsRateLimit: void }>({
  editorSettingsRateLimit: [
    // eslint-disable-next-line no-empty-pattern -- Playwright requires a destructured fixtures argument.
    async ({}, use) => {
      wpCli([
        'transient',
        'delete',
        `${EDITOR_SETTINGS_RATE_LIMIT_PREFIX}${resolveAdminUserId()}`,
      ]);
      await use();
    },
    { auto: true },
  ],
});
