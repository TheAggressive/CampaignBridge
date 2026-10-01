import type { Page } from '@playwright/test';

/** Dismiss WordPress's first-run editor guide when the test user is new. */
export async function dismissEditorWelcomeGuide(page: Page): Promise<void> {
  const guide = page.getByRole('dialog', { name: 'Welcome to the editor' });

  if (await guide.isVisible()) {
    await guide.getByRole('button', { name: 'Close' }).click();
    await guide.waitFor({ state: 'hidden' });
  }
}

/**
 * Wait until the template canvas accepts sections.
 *
 * Block types register before the container's inner-block list settings.
 * Inserting in that window makes `insertBlocks()` a silent no-op, so tests
 * must wait for the rendered container and a positive insertion check.
 */
export async function waitForInsertableCanvas(page: Page): Promise<void> {
  await page
    .frameLocator('iframe[name="editor-canvas"]')
    .locator('[data-type="campaignbridge/container"]')
    .waitFor();
  await page.waitForFunction(() => {
    const wp = (globalThis as typeof globalThis & { wp?: any }).wp;
    const editor = wp?.data?.select('core/block-editor');
    const root = editor?.getBlocks?.()[0];
    return (
      root?.name === 'campaignbridge/container' &&
      editor.canInsertBlockType('campaignbridge/section', root.clientId)
    );
  });
}
