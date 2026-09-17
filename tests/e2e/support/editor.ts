import type { Page } from '@playwright/test';

/** Dismiss WordPress's first-run editor guide when the test user is new. */
export async function dismissEditorWelcomeGuide(page: Page): Promise<void> {
  const guide = page.getByRole('dialog', { name: 'Welcome to the editor' });

  if (await guide.isVisible()) {
    await guide.getByRole('button', { name: 'Close' }).click();
    await guide.waitFor({ state: 'hidden' });
  }
}
