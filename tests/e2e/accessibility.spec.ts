import AxeBuilder from '@axe-core/playwright';
import type { AxeResults, Result } from 'axe-core';
import { dismissEditorWelcomeGuide } from './support/editor';
import { expect, test, type Page } from './support/fixtures';

const NEW_TEMPLATE_PATH = '/wp-admin/post-new.php?post_type=cb_templates';
const BLOCKING_SEVERITIES = ['critical', 'serious'] as const;

type ApiFetch = <T>(
  // eslint-disable-next-line no-unused-vars -- Documents the callable WordPress API contract.
  options: { path: string; method?: string }
) => Promise<T>;

function blockingViolations(results: AxeResults): Result[] {
  return results.violations.filter(violation =>
    (BLOCKING_SEVERITIES as readonly string[]).includes(violation.impact ?? '')
  );
}

function formatViolations(violations: Result[]): string {
  return violations
    .map(violation => {
      const targets = violation.nodes
        .slice(0, 5)
        .map(node => node.target.join(' '))
        .join(', ');
      return `[${violation.impact ?? 'unknown'}] ${violation.id}: ${targets}`;
    })
    .join('\n');
}

async function waitForNativeEditor(page: Page): Promise<number> {
  await page.waitForFunction(() => {
    const wp = (globalThis as typeof globalThis & { wp?: any }).wp;
    return (
      wp?.data?.select('core/editor')?.getCurrentPostType?.() ===
        'cb_templates' && wp?.blocks?.getBlockType?.('campaignbridge/container')
    );
  });
  await dismissEditorWelcomeGuide(page);

  return page.evaluate(() => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    return Number(wp.data.select('core/editor').getCurrentPostId());
  });
}

async function deleteTemplate(page: Page, postId: number): Promise<void> {
  await page.evaluate(async id => {
    const apiFetch = (
      globalThis as typeof globalThis & { wp: { apiFetch: ApiFetch } }
    ).wp.apiFetch;
    await apiFetch({
      path: `/wp/v2/cb_templates/${id}?force=true`,
      method: 'DELETE',
    });
  }, postId);
}

async function openEmailPreview(page: Page): Promise<void> {
  await page.getByRole('button', { name: /^(Preview|View)$/ }).click();
  await page.getByRole('menuitem', { name: 'Email Preview' }).click();
  await expect(
    page.getByRole('dialog', { name: 'Email Preview' })
  ).toBeVisible();
}

test.describe('CampaignBridge accessibility (WCAG 2.1 AA)', () => {
  let cleanupPostId = 0;

  test.afterEach(async ({ page }) => {
    if (cleanupPostId > 0 && !page.isClosed()) {
      await deleteTemplate(page, cleanupPostId);
    }
    cleanupPostId = 0;
  });

  test('native template settings expose labelled keyboard controls', async ({
    page,
  }) => {
    await page.goto(NEW_TEMPLATE_PATH);
    cleanupPostId = await waitForNativeEditor(page);

    const templateSettings = page.getByRole('button', {
      name: 'Template Settings',
    });
    if (!(await templateSettings.isVisible())) {
      await page.getByRole('button', { name: 'Settings' }).click();
    }

    const subject = page.getByRole('textbox', { name: 'Subject Line' });
    if (!(await subject.isVisible())) {
      await templateSettings.click();
    }
    await expect(subject).toBeVisible();
    await subject.focus();
    await expect(subject).toBeFocused();
    await expect(
      page.getByRole('combobox', { name: 'Category' })
    ).toBeVisible();
  });

  test('compiled preview has no blocking accessibility violations', async ({
    page,
  }) => {
    await page.goto(NEW_TEMPLATE_PATH);
    cleanupPostId = await waitForNativeEditor(page);
    await openEmailPreview(page);

    const results = await new AxeBuilder({ page })
      .include('.cb-editor__preview-modal-frame')
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();
    const blocking = blockingViolations(results);

    expect(
      blocking,
      `CampaignBridge preview accessibility violations:\n${formatViolations(
        blocking
      )}`
    ).toHaveLength(0);
  });

  test('preview announces status and traps keyboard focus', async ({
    page,
  }) => {
    await page.goto(NEW_TEMPLATE_PATH);
    cleanupPostId = await waitForNativeEditor(page);
    await openEmailPreview(page);

    const status = page.locator('.cb-editor__preview-status');
    await expect(status).toHaveAttribute('role', 'status');
    await expect(status).toHaveAttribute('aria-live', 'polite');

    for (let step = 0; step < 8; step += 1) {
      await page.keyboard.press('Tab');
      const insideModal = await page.evaluate(() =>
        Boolean(
          globalThis.document.activeElement?.closest(
            '.cb-editor__preview-modal-frame'
          )
        )
      );
      expect(insideModal, `Tab stop ${step + 1} escaped the preview`).toBe(
        true
      );
    }

    await page.keyboard.press('Shift+Tab');
    const insideModal = await page.evaluate(() =>
      Boolean(
        globalThis.document.activeElement?.closest(
          '.cb-editor__preview-modal-frame'
        )
      )
    );
    expect(insideModal).toBe(true);
  });

  test('CampaignBridge settings page has no blocking violations', async ({
    page,
  }) => {
    await page.goto('/wp-admin/admin.php?page=campaignbridge-settings');
    await expect(page.locator('.campaignbridge-screen')).toBeVisible();

    const results = await new AxeBuilder({ page })
      .include('.campaignbridge-screen')
      .exclude('.nav-tab')
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();
    const blocking = blockingViolations(results);

    expect(
      blocking,
      `CampaignBridge settings accessibility violations:\n${formatViolations(
        blocking
      )}`
    ).toHaveLength(0);
  });
});
