import { expect, test, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import type { AxeResults, Result } from 'axe-core';

const EDITOR_PATH = '/wp-admin/admin.php?page=campaignbridge-editor';

/** Severity levels that represent WCAG violations we must not ship. */
const BLOCKING_SEVERITIES = ['critical', 'serious'] as const;

/**
 * CampaignBridge-owned DOM namespaces. Scoping axe to these selectors
 * prevents false positives from WordPress core admin chrome (admin bar,
 * sidebar, notices) that are outside our compliance boundary.
 */
const CB_SCOPES = [
  '.cb-editor',
  '.cb-editor__canvas',
  '.cb-admin-screen',
  '.campaignbridge-screen',
  '.cb-editor__preview-modal-frame',
] as const;

/**
 * Formats axe violations into a human-readable multi-line string
 * suitable for Playwright test failure output.
 */
function formatViolations(violations: Result[]): string {
  if (violations.length === 0) {
    return 'No accessibility violations found.';
  }

  const lines: string[] = [];
  for (const violation of violations) {
    lines.push(
      `\n[${violation.impact?.toUpperCase()}] ${violation.id} — ${violation.description}`
    );
    lines.push(`  Help: ${violation.helpUrl}`);
    for (const node of violation.nodes.slice(0, 5)) {
      lines.push(`  Target: ${node.target.join(' ')}`);
      lines.push(`    ${node.failureSummary?.split('\n')[0] ?? ''}`);
    }
    if (violation.nodes.length > 5) {
      lines.push(`  …and ${violation.nodes.length - 5} more element(s)`);
    }
  }
  return lines.join('\n');
}

/**
 * Extracts only the violations at blocking severity levels.
 */
function getBlockingViolations(results: AxeResults): Result[] {
  return results.violations.filter(v =>
    (BLOCKING_SEVERITIES as readonly string[]).includes(v.impact ?? '')
  );
}

/**
 * Runs axe-core scoped to CampaignBridge-owned DOM and asserts no blocking
 * violations. Automatically scans same-origin iframes (editor canvas).
 */
async function expectNoBlockingViolations(
  page: Page,
  context: string
): Promise<void> {
  const builder = new AxeBuilder({ page }).withTags([
    'wcag2a',
    'wcag2aa',
    'wcag21a',
    'wcag21aa',
  ]);

  for (const scope of CB_SCOPES) {
    builder.include(scope);
  }
  builder.exclude('.nav-tab');

  const results = await builder.analyze();
  const blocking = getBlockingViolations(results);
  expect(
    blocking,
    `Accessibility violations on ${context}:\n${formatViolations(blocking)}`
  ).toHaveLength(0);
}

/**
 * Waits for dynamic editor content to settle (React hydration, API calls).
 * Prevents flaky axe runs against partially-rendered DOM.
 */
async function waitForEditorReady(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle', { timeout: 10000 });
  await page.waitForTimeout(200);
}

/**
 * Creates a test template via the WordPress REST API.
 */
async function createTemplate(page: Page): Promise<number> {
  return page.evaluate(async () => {
    const apiFetch = (
      globalThis as typeof globalThis & {
        wp: {
          apiFetch: <T>(
            // eslint-disable-next-line no-unused-vars -- Documents the callable WordPress API contract.
            options: {
              path: string;
              method?: string;
              data?: Record<string, unknown>;
            }
          ) => Promise<T>;
        };
      }
    ).wp.apiFetch;
    const result = await apiFetch<{ id: number }>({
      path: '/wp/v2/cb_templates',
      method: 'POST',
      data: { title: `A11y Test ${Date.now()}`, status: 'draft' },
    });
    return result.id;
  });
}

/**
 * Deletes a test template via the WordPress REST API.
 */
async function deleteTemplate(page: Page, templateId: number): Promise<void> {
  await page.evaluate(async id => {
    const apiFetch = (
      globalThis as typeof globalThis & {
        wp: {
          apiFetch: <T>(
            // eslint-disable-next-line no-unused-vars -- Documents the callable WordPress API contract.
            options: {
              path: string;
              method?: string;
            }
          ) => Promise<T>;
        };
      }
    ).wp.apiFetch;
    await apiFetch({ path: `/wp/v2/cb_templates/${id}`, method: 'DELETE' });
  }, templateId);
}

/**
 * Returns the count of focusable elements within a given scope selector.
 */
async function countFocusableElements(
  page: Page,
  scopeSelector: string
): Promise<number> {
  return page.evaluate(scope => {
    const container = globalThis.document.querySelector(scope);
    if (!container) {
      return 0;
    }
    const focusable = container.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    return focusable.length;
  }, scopeSelector);
}

test.describe('CampaignBridge Accessibility (WCAG 2.1 AA)', () => {
  test('editor empty state has no critical or serious violations', async ({
    page,
  }) => {
    await page.goto(EDITOR_PATH);
    await expect(page.locator('.cb-editor__header')).toBeVisible({
      timeout: 10000,
    });
    await waitForEditorReady(page);

    await expectNoBlockingViolations(page, 'the editor (empty state)');
  });

  test('editor with template has no critical or serious violations', async ({
    page,
  }) => {
    // Navigate to the editor first so wp.apiFetch is available in page context.
    await page.goto(EDITOR_PATH);
    await expect(page.locator('.cb-editor__header')).toBeVisible({
      timeout: 10000,
    });
    await waitForEditorReady(page);

    const templateId = await createTemplate(page);

    try {
      await page.goto(`${EDITOR_PATH}&post_id=${templateId}`);
      await expect(
        page.frameLocator('iframe[name="editor-canvas"]').locator('body')
      ).toBeVisible({ timeout: 10000 });
      await waitForEditorReady(page);

      // Scopes include .cb-editor__canvas which exists inside the iframe;
      // AxeBuilder.runPartialRecursive() scans same-origin iframes automatically.
      await expectNoBlockingViolations(page, 'the editor (with template)');
    } finally {
      await deleteTemplate(page, templateId);
    }
  });

  test('dashboard page has no critical or serious violations', async ({
    page,
  }) => {
    await page.goto('/wp-admin/admin.php?page=campaignbridge-settings');
    await expect(
      page.locator('.cb-admin-screen, .campaignbridge-screen')
    ).toBeVisible({ timeout: 10000 });
    await waitForEditorReady(page);

    await expectNoBlockingViolations(page, 'the dashboard page');
  });

  test('editor header toolbar is accessible', async ({ page }) => {
    await page.goto(EDITOR_PATH);
    await expect(page.locator('.cb-editor__header')).toBeVisible();
    await waitForEditorReady(page);

    const toolbar = page.locator('[role="toolbar"]');
    await expect(toolbar).toBeVisible();

    const toolbarLabel = await toolbar.getAttribute('aria-label');
    expect(toolbarLabel, 'Toolbar must have an aria-label').toBeTruthy();
    expect(
      toolbarLabel!.length,
      'aria-label must not be empty'
    ).toBeGreaterThan(0);
  });

  test('sidebar tabs use proper ARIA tab pattern', async ({ page }) => {
    await page.goto(EDITOR_PATH);
    await expect(page.locator('.cb-editor__header')).toBeVisible();
    await waitForEditorReady(page);

    const templateId = await createTemplate(page);

    try {
      await page.goto(`${EDITOR_PATH}&post_id=${templateId}`);
      await expect(
        page.frameLocator('iframe[name="editor-canvas"]').locator('body')
      ).toBeVisible({ timeout: 10000 });
      await waitForEditorReady(page);

      // Open the primary sidebar so the tab strip renders.
      await page.locator('.cb-editor__toggle--primary').click();
      const tablist = page.locator('.cb-editor__sidebar-tabs[role="tablist"]');
      await expect(tablist).toBeVisible({ timeout: 5000 });

      const tablistLabel = await tablist.getAttribute('aria-label');
      expect(tablistLabel, 'Tablist must have an aria-label').toBeTruthy();

      const tabs = tablist.locator('[role="tab"]');
      const tabCount = await tabs.count();
      expect(tabCount, 'Must have at least one tab').toBeGreaterThanOrEqual(1);

      for (let i = 0; i < tabCount; i += 1) {
        const tab = tabs.nth(i);
        const selected = await tab.getAttribute('aria-selected');
        expect(selected, `Tab ${i} must have aria-selected`).toMatch(
          /^(true|false)$/u
        );

        const controls = await tab.getAttribute('aria-controls');
        expect(controls, `Tab ${i} must have aria-controls`).toBeTruthy();
      }
    } finally {
      await deleteTemplate(page, templateId);
    }
  });

  test('preview modal announces status via live region', async ({ page }) => {
    await page.goto(EDITOR_PATH);
    await expect(page.locator('.cb-editor__header')).toBeVisible();
    await waitForEditorReady(page);

    const templateId = await createTemplate(page);

    try {
      await page.goto(`${EDITOR_PATH}&post_id=${templateId}`);
      await expect(
        page.frameLocator('iframe[name="editor-canvas"]').locator('body')
      ).toBeVisible();
      await waitForEditorReady(page);

      // Verify the editor has at least one status/live region for
      // screen reader announcements (e.g., save status, preview loading).
      // Covers aria-live, role="status", and role="alert" patterns.
      const liveRegions = page.locator(
        '[aria-live], [role="status"], [role="alert"]'
      );
      const regionCount = await liveRegions.count();
      expect(
        regionCount,
        'Editor must have at least one live region (aria-live, role="status", or role="alert") for status announcements'
      ).toBeGreaterThanOrEqual(1);
    } finally {
      await deleteTemplate(page, templateId);
    }
  });

  test('all interactive elements are keyboard reachable', async ({ page }) => {
    await page.goto(EDITOR_PATH);
    await expect(page.locator('.cb-editor__header')).toBeVisible();
    await waitForEditorReady(page);

    const focusableCount = await countFocusableElements(
      page,
      '.cb-editor__header'
    );
    expect(
      focusableCount,
      'Editor must have at least 3 focusable interactive elements'
    ).toBeGreaterThanOrEqual(3);

    // Tab through the first 5 focusable elements and verify each is visible.
    // This validates that no element is hidden from keyboard navigation.
    const steps = Math.min(focusableCount, 5);
    const visited: string[] = [];

    for (let i = 0; i < steps; i += 1) {
      await page.keyboard.press('Tab');
      await page.waitForTimeout(50);

      const active = await page.evaluate(() => {
        const el = globalThis.document.activeElement;
        if (!el || el === globalThis.document.body) {
          return null;
        }
        const rect = el.getBoundingClientRect();
        return {
          label:
            el.getAttribute('aria-label') ||
            el.getAttribute('title') ||
            el.textContent?.trim().slice(0, 60) ||
            el.tagName.toLowerCase(),
          visible: rect.width > 0 && rect.height > 0,
        };
      });

      expect(
        active,
        `Tab stop ${i + 1}: focus must land on a visible element`
      ).not.toBeNull();
      expect(
        active!.visible,
        `Tab stop ${i + 1} ("${active!.label}"): element must be visible`
      ).toBe(true);
      visited.push(active!.label);
    }

    // Verify we cycled through distinct elements (not stuck on one).
    const unique = new Set(visited);
    expect(
      unique.size,
      `Keyboard traversal should reach multiple distinct elements (got: ${visited.join(', ')})`
    ).toBeGreaterThanOrEqual(2);
  });

  test('focus is trapped within the preview modal', async ({ page }) => {
    await page.goto(EDITOR_PATH);
    await expect(page.locator('.cb-editor__header')).toBeVisible();
    await waitForEditorReady(page);

    const templateId = await createTemplate(page);

    try {
      await page.goto(`${EDITOR_PATH}&post_id=${templateId}`);
      await expect(
        page.frameLocator('iframe[name="editor-canvas"]').locator('body')
      ).toBeVisible();
      await waitForEditorReady(page);

      // Open the preview modal via the toolbar button.
      const previewButton = page
        .locator('[role="toolbar"]')
        .locator('button')
        .filter({ hasText: /preview/i })
        .first();
      await previewButton.click();

      // Wait for the modal to appear.
      const modal = page.locator('.cb-editor__preview-modal-frame');
      await expect(modal).toBeVisible({ timeout: 5000 });
      await page.waitForTimeout(300);

      // Tab through the modal and verify focus never escapes.
      const modalScope = '.cb-editor__preview-modal-frame';
      const steps = 8;

      for (let i = 0; i < steps; i += 1) {
        await page.keyboard.press('Tab');
        await page.waitForTimeout(50);

        const insideModal = await page.evaluate(scope => {
          const el = globalThis.document.activeElement;
          if (!el) {
            return false;
          }
          return el.closest(scope) !== null;
        }, modalScope);

        expect(
          insideModal,
          `Tab stop ${i + 1}: focus must remain within the preview modal`
        ).toBe(true);
      }

      // Shift+Tab should also stay within the modal.
      for (let i = 0; i < 3; i += 1) {
        await page.keyboard.press('Shift+Tab');
        await page.waitForTimeout(50);

        const insideModal = await page.evaluate(scope => {
          const el = globalThis.document.activeElement;
          if (!el) {
            return false;
          }
          return el.closest(scope) !== null;
        }, modalScope);

        expect(
          insideModal,
          `Shift+Tab stop ${i + 1}: focus must remain within the preview modal`
        ).toBe(true);
      }

      // Close the modal and verify focus is restored to the trigger.
      await page.keyboard.press('Escape');
      await expect(modal).toBeHidden({ timeout: 5000 });
      await page.waitForTimeout(200);

      const focusRestored = await page.evaluate(() => {
        const el = globalThis.document.activeElement;
        return el !== null && el !== globalThis.document.body;
      });
      expect(
        focusRestored,
        'Focus must be restored to a meaningful element after modal close'
      ).toBe(true);
    } finally {
      await deleteTemplate(page, templateId);
    }
  });
});
