import { expect, test, type Locator, type Page } from './support/fixtures';

const EDITOR_PATH = '/wp-admin/admin.php?page=campaignbridge-editor';

type ApiFetch = <T>(
  // eslint-disable-next-line no-unused-vars -- Documents the callable WordPress API contract.
  options: {
    path: string;
    method?: string;
    data?: Record<string, unknown>;
  }
) => Promise<T>;

async function box(locator: Locator) {
  const bounds = await locator.boundingBox();
  expect(bounds).not.toBeNull();

  return bounds!;
}

async function apiFetch<T>(
  page: Page,
  options: Parameters<ApiFetch>[0]
): Promise<T> {
  return page.evaluate(
    requestOptions =>
      (
        globalThis as typeof globalThis & { wp: { apiFetch: ApiFetch } }
      ).wp.apiFetch<T>(requestOptions),
    options
  );
}

test.beforeEach(async ({ page }) => {
  await page.goto(EDITOR_PATH);
  await expect(page.locator('.cb-editor__header')).toBeVisible();
});

test('centers a compact template toolbar in the editor header', async ({
  page,
}) => {
  // Exact centering is only possible when both side groups fit in equal
  // tracks beside the toolbar; narrower headers are covered below.
  await page.setViewportSize({ width: 1920, height: 1080 });
  await page.goto(EDITOR_PATH);
  await expect(page.locator('.cb-editor__header')).toBeVisible();

  const header = await box(page.locator('.cb-editor__header'));
  const center = await box(page.locator('.cb-editor__header-center'));
  const selector = await box(page.locator('.cb-editor__templates-select'));

  expect(selector.width).toBeLessThanOrEqual(261);
  expect(
    Math.abs(center.x + center.width / 2 - (header.x + header.width / 2))
  ).toBeLessThanOrEqual(1);
  await expect(page.locator('.cb-editor__new-template')).toBeVisible();
});

test('keeps draft header actions clickable beside the template toolbar', async ({
  page,
}) => {
  const templateId = (
    await apiFetch<{ id: number }>(page, {
      path: '/wp/v2/cb_templates',
      method: 'POST',
      data: { title: `Header Layout ${Date.now()}`, status: 'draft' },
    })
  ).id;

  try {
    await page.goto(`${EDITOR_PATH}&post_id=${templateId}`);
    const publishButton = page.locator('.cb-editor__publish-button');
    await expect(publishButton).toBeVisible();

    const left = await box(page.locator('.cb-editor__header-left'));
    const center = await box(page.locator('.cb-editor__header-center'));
    const actions = await box(page.locator('.cb-editor__header-actions'));

    expect(center.x).toBeGreaterThanOrEqual(left.x + left.width);
    expect(actions.x).toBeGreaterThanOrEqual(center.x + center.width);
    // A trial click fails if another element intercepts the pointer.
    await publishButton.click({ trial: true });
    await page.locator('.cb-editor__duplicate-button').click({ trial: true });
  } finally {
    if (!page.isClosed()) {
      await apiFetch(page, {
        path: `/wp/v2/cb_templates/${templateId}?force=true`,
        method: 'DELETE',
      });
    }
  }
});

test('stacks the centered template toolbar without narrow-screen overlap', async ({
  page,
}) => {
  await page.setViewportSize({ width: 600, height: 800 });
  await page.goto(EDITOR_PATH);

  const header = await box(page.locator('.cb-editor__header'));
  const left = await box(page.locator('.cb-editor__header-left'));
  const center = await box(page.locator('.cb-editor__header-center'));
  const actions = await box(page.locator('.cb-editor__header-actions'));

  expect(center.y).toBeGreaterThanOrEqual(
    Math.max(left.y + left.height, actions.y + actions.height)
  );
  expect(
    Math.abs(center.x + center.width / 2 - (header.x + header.width / 2))
  ).toBeLessThanOrEqual(1);
});
