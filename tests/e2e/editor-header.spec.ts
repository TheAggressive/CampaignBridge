import { expect, test, type Locator } from '@playwright/test';

const EDITOR_PATH = '/wp-admin/admin.php?page=campaignbridge-editor';

async function box(locator: Locator) {
  const bounds = await locator.boundingBox();
  expect(bounds).not.toBeNull();

  return bounds!;
}

test.beforeEach(async ({ page }) => {
  await page.goto(EDITOR_PATH);
  await expect(page.locator('.cb-editor__header')).toBeVisible();
});

test('centers a compact template toolbar in the editor header', async ({
  page,
}) => {
  const header = await box(page.locator('.cb-editor__header'));
  const center = await box(page.locator('.cb-editor__header-center'));
  const selector = await box(page.locator('.cb-editor__templates-select'));

  expect(selector.width).toBeLessThanOrEqual(261);
  expect(
    Math.abs(center.x + center.width / 2 - (header.x + header.width / 2))
  ).toBeLessThanOrEqual(1);
  await expect(page.locator('.cb-editor__new-template')).toBeVisible();
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
