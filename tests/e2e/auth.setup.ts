import fs from 'node:fs';
import path from 'node:path';
import { expect, test as setup } from '@playwright/test';
import { AUTH_STATE_PATH, BASE_URL } from './support/environment';

setup('authenticate an administrator', async ({ page }) => {
  const autoLoginUrl = process.env.CB_E2E_AUTO_LOGIN_URL;

  if (autoLoginUrl) {
    const loginUrl = new URL(autoLoginUrl);
    const siteUrl = new URL(BASE_URL);

    if (loginUrl.origin !== siteUrl.origin) {
      throw new Error('Studio auto-login URL must match the configured site.');
    }

    try {
      await page.goto(loginUrl.href, { waitUntil: 'domcontentloaded' });
    } catch {
      throw new Error('Studio auto-login navigation failed.');
    }
  } else {
    const username = process.env.CB_E2E_ADMIN_USER;
    const password = process.env.CB_E2E_ADMIN_PASSWORD;

    if (!username || !password) {
      throw new Error(
        'E2E authentication requires Studio auto-login or admin credentials.'
      );
    }

    await page.goto('/wp-login.php');
    await page.locator('#user_login').fill(username);
    await page.locator('#user_pass').fill(password);
    await page.locator('#wp-submit').click();
  }

  await page.goto('/wp-admin/');
  await expect(page.locator('#wpadminbar')).toBeVisible();
  await expect(page).not.toHaveURL(/wp-login\.php/u);

  fs.mkdirSync(path.dirname(AUTH_STATE_PATH), { recursive: true });
  await page.context().storageState({ path: AUTH_STATE_PATH });
});
