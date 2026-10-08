import { randomBytes } from 'node:crypto';
import AxeBuilder from '@axe-core/playwright';
import { BASE_URL } from './support/environment';
import { expect, test, type Page } from './support/fixtures';

const CAMPAIGNS_PATH = '/wp-admin/admin.php?page=campaignbridge-campaigns';

type ApiFetch = <T>(
  // eslint-disable-next-line no-unused-vars -- Documents the callable WordPress API contract.
  options: { path: string; method?: string; data?: unknown }
) => Promise<T>;

interface Checklist {
  steps: Array<{ id: string; done: boolean; optional: boolean }>;
  complete: boolean;
  dismissed: boolean;
  visible: boolean;
}

function api<T>(
  page: Page,
  options: { path: string; method?: string; data?: unknown }
): Promise<T> {
  return page.evaluate(
    request =>
      (
        globalThis as typeof globalThis & { wp: { apiFetch: ApiFetch } }
      ).wp.apiFetch(request),
    options
  ) as Promise<T>;
}

test('a new operator follows the checklist to a first campaign', async ({
  page,
  browser,
}) => {
  await page.goto(CAMPAIGNS_PATH);
  const me = await api<{ id: number }>(page, { path: '/wp/v2/users/me' });
  const title = `E2E onboarding template ${Date.now()}`;
  const template = await api<{ id: number }>(page, {
    path: '/wp/v2/cb_templates',
    method: 'POST',
    data: {
      title,
      status: 'publish',
      content: '<!-- wp:campaignbridge/container /-->',
    },
  });
  // A disposable site starts on the default Brand Kit; choosing a colour is the step.
  const kit = await api<{ source: string; slots: Array<{ color: string }> }>(
    page,
    { path: '/campaignbridge/v1/brand-kit' }
  );
  if (kit.source === 'defaults') {
    await api(page, {
      path: '/campaignbridge/v1/brand-kit',
      method: 'PUT',
      data: { id: 'text', color: kit.slots[0].color },
    });
  }

  // A brand-new administrator owns no campaigns, so their checklist is open.
  const suffix = Date.now().toString(36);
  const password = randomBytes(18).toString('base64url');
  const operator = await api<{ id: number }>(page, {
    path: '/wp/v2/users',
    method: 'POST',
    data: {
      username: `e2e-onboarding-${suffix}`,
      email: `e2e-onboarding-${suffix}@example.test`,
      password,
      roles: ['administrator'],
    },
  });
  const context = await browser.newContext({
    baseURL: BASE_URL,
    storageState: { cookies: [], origins: [] },
  });
  const operatorPage = await context.newPage();
  let campaignId: string | null = null;

  try {
    await operatorPage.goto('/wp-login.php');
    await operatorPage.locator('#user_login').fill(`e2e-onboarding-${suffix}`);
    await operatorPage.locator('#user_pass').fill(password);
    await operatorPage.locator('#wp-submit').click();
    await expect(operatorPage.locator('#wpadminbar')).toBeVisible();

    await operatorPage.goto(CAMPAIGNS_PATH);
    const checklist = operatorPage.getByRole('region', { name: 'Get set up' });
    await expect(checklist).toBeVisible();

    // Every rendered step says what the server derived from stored state.
    const state = await api<Checklist>(operatorPage, {
      path: '/campaignbridge/v1/onboarding',
    });
    expect(state.steps.find(step => step.id === 'campaign')?.done).toBe(false);
    expect(state.steps.find(step => step.id === 'template')?.done).toBe(true);
    expect(state.steps.find(step => step.id === 'brand')?.done).toBe(true);
    const steps = checklist.getByRole('listitem');
    await expect(steps).toHaveCount(state.steps.length);
    for (const [index, step] of state.steps.entries()) {
      await expect(steps.nth(index)).toContainText(
        step.done ? '(done)' : '(to do)'
      );
    }
    await expect(
      checklist.getByRole('button', { name: 'Dismiss checklist' })
    ).toHaveCount(0);

    const accessibility = await new AxeBuilder({ page: operatorPage })
      .include('.campaignbridge-campaigns__onboarding')
      .analyze();
    expect(
      accessibility.violations.filter(violation =>
        ['critical', 'serious'].includes(violation.impact ?? '')
      )
    ).toEqual([]);

    await checklist.getByRole('button', { name: 'Create a campaign' }).click();
    const dialog = operatorPage.getByRole('dialog', { name: 'New campaign' });
    await dialog.getByLabel('Email template').selectOption({ label: title });
    await dialog.getByLabel('Delivery').selectOption({ value: '' });
    await dialog.getByRole('button', { name: 'Create campaign' }).click();
    await expect(operatorPage).toHaveURL(/&campaign=campaign-/);
    campaignId = new URL(operatorPage.url()).searchParams.get('campaign');

    await operatorPage.goto(CAMPAIGNS_PATH);
    const done = operatorPage.getByRole('region', { name: 'You’re set up' });
    await expect(done).toBeVisible();
    await expect(
      done.getByRole('listitem').filter({ hasText: 'first campaign' })
    ).toContainText('(done)');
    await done.getByRole('button', { name: 'Dismiss checklist' }).click();
    await expect(done).toHaveCount(0);
    await operatorPage.reload();
    await expect(
      operatorPage.locator('.campaignbridge-campaigns__onboarding')
    ).toHaveCount(0);

    // Losing a prerequisite brings the checklist back.
    await api(page, {
      path: `/wp/v2/cb_templates/${template.id}`,
      method: 'DELETE',
    });
    const published = await api<Array<unknown>>(page, {
      path: '/wp/v2/cb_templates?status=publish&per_page=1',
    });
    if (published.length === 0) {
      await operatorPage.reload();
      await expect(
        operatorPage.getByRole('region', { name: 'Get set up' })
      ).toContainText('no longer complete');
    }
  } finally {
    if (campaignId) {
      await api(operatorPage, {
        path: `/campaignbridge/v1/campaigns/${campaignId}/archive`,
        method: 'POST',
        data: { expected_version: 1 },
      }).catch(() => undefined);
    }
    await context.close();
    await api(page, {
      path: `/wp/v2/cb_templates/${template.id}?force=true`,
      method: 'DELETE',
    });
    await api(page, {
      path: `/wp/v2/users/${operator.id}?force=true&reassign=${me.id}`,
      method: 'DELETE',
    });
  }
});
