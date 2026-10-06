import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from './support/fixtures';

const CAMPAIGNS_PATH = '/wp-admin/admin.php?page=campaignbridge-campaigns';

type ApiFetch = <T>(
  // eslint-disable-next-line no-unused-vars -- Documents the callable WordPress API contract.
  options: { path: string; method?: string; data?: unknown }
) => Promise<T>;

async function api<T>(
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

test('operators create a campaign from a template and archive it from the list', async ({
  page,
}) => {
  const title = `E2E campaign template ${Date.now()}`;
  await page.goto(CAMPAIGNS_PATH);
  await expect(
    page.getByRole('button', { name: 'New campaign' })
  ).toBeVisible();

  const template = await api<{ id: number }>(page, {
    path: '/wp/v2/cb_templates',
    method: 'POST',
    data: {
      title,
      status: 'publish',
      content: '<!-- wp:campaignbridge/container /-->',
    },
  });

  const screen = page.locator('#campaignbridge-campaigns-root');
  try {
    await page.reload();
    await page.getByRole('button', { name: 'New campaign' }).click();
    const dialog = page.getByRole('dialog', { name: 'New campaign' });
    // Bundled DataViews keeps its own id counter; every label must still
    // point at exactly one control, including when the list has pages.
    await dialog.getByLabel('Email template').waitFor();
    expect(
      await page.evaluate(() => {
        const ids = [
          ...globalThis.document.querySelectorAll(
            '[id^="inspector-"], [id^="campaignbridge-"]'
          ),
        ].map(element => element.id);
        return ids.filter((id, index) => ids.indexOf(id) !== index);
      })
    ).toEqual([]);
    await dialog.getByLabel('Email template').selectOption({ label: title });
    await dialog.getByLabel('Delivery').selectOption({ value: '' });
    await dialog.getByRole('button', { name: 'Create campaign' }).click();

    // A new campaign opens on its review page; the list links back to it.
    await expect(page).toHaveURL(/&campaign=campaign-/);
    await screen.getByRole('link', { name: 'All campaigns' }).click();
    const row = page.getByRole('row').filter({ hasText: title });
    await expect(row.getByRole('link', { name: title })).toBeVisible();
    await expect(row).toHaveCount(1);
    await expect(row).toContainText('Draft');
    await expect(row).toContainText('HTML export');

    const accessibility = await new AxeBuilder({ page })
      .include('#campaignbridge-campaigns-root')
      .analyze();
    expect(
      accessibility.violations.filter(violation =>
        ['critical', 'serious'].includes(violation.impact ?? '')
      )
    ).toEqual([]);

    await row.getByRole('button', { name: 'Actions' }).click();
    await page.getByRole('menuitem', { name: 'Archive' }).click();
    const confirm = page.getByRole('dialog', { name: 'Archive campaign' });
    await confirm.getByRole('button', { name: 'Archive' }).click();

    await expect(screen.getByText('Campaign archived.')).toBeVisible();
    await expect(row).toContainText('Archived');
    await row.getByRole('button', { name: 'Actions' }).click();
    await expect(page.getByRole('menuitem', { name: 'Archive' })).toHaveCount(
      0
    );
    await expect(
      page.getByRole('menuitem', { name: 'Duplicate' })
    ).toBeVisible();
  } finally {
    await api(page, {
      path: `/wp/v2/cb_templates/${template.id}?force=true`,
      method: 'DELETE',
    });
  }
});
