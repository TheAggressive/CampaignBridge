import { expect, test, type Locator, type Page } from '@playwright/test';

const EDITOR_PATH = '/wp-admin/admin.php?page=campaignbridge-editor';

type ApiFetch = <T>(
  // eslint-disable-next-line no-unused-vars -- Documents the callable WordPress API contract.
  options: {
    path: string;
    method?: string;
    data?: Record<string, unknown>;
  }
) => Promise<T>;

async function createTemplate(page: Page): Promise<number> {
  await page.goto(EDITOR_PATH);
  await expect(page.locator('.cb-editor__header')).toBeVisible();

  return page.evaluate(async title => {
    const apiFetch = (
      globalThis as typeof globalThis & { wp: { apiFetch: ApiFetch } }
    ).wp.apiFetch;
    const result = await apiFetch<{ id: number }>({
      path: '/wp/v2/cb_templates',
      method: 'POST',
      data: {
        title,
        status: 'draft',
      },
    });

    return result.id;
  }, `CampaignBridge E2E ${Date.now()}`);
}

async function deleteTemplate(page: Page, templateId: number): Promise<void> {
  await page.evaluate(async id => {
    const apiFetch = (
      globalThis as typeof globalThis & { wp: { apiFetch: ApiFetch } }
    ).wp.apiFetch;
    await apiFetch({
      path: `/wp/v2/cb_templates/${id}?force=true`,
      method: 'DELETE',
    });
  }, templateId);
}

async function openTemplate(page: Page, templateId: number): Promise<void> {
  await page.goto(`${EDITOR_PATH}&post_id=${templateId}`);
  await expect(page.locator('iframe[name="editor-canvas"]')).toBeVisible();
  await expect(
    page
      .frameLocator('iframe[name="editor-canvas"]')
      .locator('body.editor-styles-wrapper')
  ).toBeVisible();
}

async function lastVisible(locator: Locator): Promise<Locator> {
  for (let index = (await locator.count()) - 1; index >= 0; index -= 1) {
    const candidate = locator.nth(index);
    if (await candidate.isVisible()) {
      return candidate;
    }
  }

  throw new Error('No visible block appender was found.');
}

test('loads core iframe assets without unrelated CampaignBridge form assets', async ({
  page,
}) => {
  const warnings: string[] = [];
  page.on('console', message => {
    if (message.type() === 'warning') {
      warnings.push(message.text());
    }
  });

  const templateId = await createTemplate(page);
  try {
    const restRequests: string[] = [];
    page.on('request', request => {
      if (request.url().includes('/wp-json/')) {
        restRequests.push(request.url());
      }
    });
    await openTemplate(page, templateId);
    await expect(
      page
        .frameLocator('iframe[name="editor-canvas"]')
        .locator('#wp-edit-blocks-css')
    ).toHaveCount(1);
    await expect(
      page.locator('#campaignbridge-admin-form-styles-css')
    ).toHaveCount(0);
    await expect(
      page.locator('#campaignbridge-encrypted-fields-js')
    ).toHaveCount(0);
    expect(
      warnings.some(message =>
        message.includes('template prop in useInnerBlocksProps')
      )
    ).toBe(false);
    expect(
      restRequests.some(url => url.includes('/wp/v2/cb_templates?per_page=100'))
    ).toBe(false);
    expect(
      restRequests.some(url => url.includes('/wp/v2/types?context=view'))
    ).toBe(false);
    expect(
      restRequests.some(url =>
        url.includes(`/wp/v2/cb_templates/${templateId}?context=edit`)
      )
    ).toBe(false);
    expect(
      restRequests.some(url =>
        url.includes('/campaignbridge/v1/editor-settings')
      )
    ).toBe(false);
  } finally {
    await deleteTemplate(page, templateId);
  }
});

test('uses the core secondary-sidebar transition for list view', async ({
  page,
}) => {
  const templateId = await createTemplate(page);

  try {
    await openTemplate(page, templateId);
    const toggle = page.locator('.cb-editor__toggle--secondary');
    if ((await toggle.getAttribute('aria-pressed')) === 'true') {
      await toggle.click();
    }
    await expect(
      page.locator('.interface-interface-skeleton__secondary-sidebar')
    ).toHaveCount(0);

    await toggle.click();
    const sidebar = page.locator(
      '.interface-interface-skeleton__secondary-sidebar'
    );
    await sidebar.waitFor({ state: 'attached' });
    await expect(
      sidebar.locator('.interface-complementary-area__fill')
    ).toHaveCount(0);

    const startingWidth = (await sidebar.boundingBox())?.width ?? 0;
    await page.waitForTimeout(350);
    const finalWidth = (await sidebar.boundingBox())?.width ?? 0;

    expect(startingWidth).toBeLessThan(finalWidth);
    expect(finalWidth).toBeGreaterThan(100);

    await page.getByRole('button', { name: 'Close list view' }).click();
    await page.waitForTimeout(50);
    const closingWidth = (await sidebar.boundingBox())?.width ?? 0;

    expect(closingWidth).toBeGreaterThan(0);
    expect(closingWidth).toBeLessThan(finalWidth);
    await expect(sidebar).toHaveCount(0);
  } finally {
    await deleteTemplate(page, templateId);
  }
});

test('switches existing templates without reloading wp-admin', async ({
  page,
}) => {
  const firstTemplateId = await createTemplate(page);
  const secondTemplateId = await createTemplate(page);

  try {
    await openTemplate(page, firstTemplateId);
    const documentTimeOrigin = await page.evaluate(
      () => globalThis.performance.timeOrigin
    );
    const previousIframe = await page
      .locator('iframe[name="editor-canvas"]')
      .elementHandle();

    await page
      .locator('.cb-editor__templates-select select')
      .selectOption(String(secondTemplateId));
    await expect(page).toHaveURL(
      new RegExp(`post_id=${secondTemplateId}(?:&|$)`, 'u')
    );
    await expect
      .poll(() => previousIframe?.evaluate(element => element.isConnected))
      .toBe(false);
    await expect(
      page
        .frameLocator('iframe[name="editor-canvas"]')
        .locator('body.editor-styles-wrapper')
    ).toBeVisible();

    expect(await page.evaluate(() => globalThis.performance.timeOrigin)).toBe(
      documentTimeOrigin
    );
  } finally {
    await deleteTemplate(page, firstTemplateId);
    await deleteTemplate(page, secondTemplateId);
  }
});

test('keeps the bottom inserter popover visible and inside the editor', async ({
  page,
}) => {
  await page.setViewportSize({ width: 1440, height: 700 });
  const templateId = await createTemplate(page);

  try {
    await openTemplate(page, templateId);
    const frame = page.frameLocator('iframe[name="editor-canvas"]');
    const appender = await lastVisible(
      frame.locator(
        '.block-editor-button-block-appender, .block-list-appender button, .block-editor-default-block-appender button'
      )
    );

    await appender.evaluate(element => {
      element.style.marginTop = '1000px';
      const ownerWindow = element.ownerDocument.defaultView;
      ownerWindow?.scrollTo(
        0,
        element.ownerDocument.documentElement.scrollHeight
      );
    });
    await appender.click();

    const popover = page.locator('.block-editor-inserter__popover').first();
    await expect(popover).toBeVisible();

    const appenderBox = await appender.boundingBox();
    const popoverBox = await popover.boundingBox();
    const shellBox = await page.locator('.cb-editor-shell').boundingBox();
    expect(appenderBox).not.toBeNull();
    expect(popoverBox).not.toBeNull();
    expect(shellBox).not.toBeNull();

    const popoverBottom = popoverBox!.y + popoverBox!.height;
    const appenderBottom = appenderBox!.y + appenderBox!.height;
    const isOutsideAppender =
      popoverBottom <= appenderBox!.y || popoverBox!.y >= appenderBottom;
    expect(isOutsideAppender).toBe(true);
    expect(popoverBox!.y).toBeGreaterThanOrEqual(shellBox!.y);
    expect(popoverBottom).toBeLessThanOrEqual(shellBox!.y + shellBox!.height);

    const hasClippingAncestor = await popover.evaluate(element => {
      let ancestor = element.parentElement;
      while (ancestor) {
        const styles =
          element.ownerDocument.defaultView?.getComputedStyle(ancestor);
        if (!styles) {
          return false;
        }
        if (
          ['hidden', 'clip'].includes(styles.overflow) ||
          ['hidden', 'clip'].includes(styles.overflowY)
        ) {
          return true;
        }
        ancestor = ancestor.parentElement;
      }
      return false;
    });
    expect(hasClippingAncestor).toBe(false);
  } finally {
    await deleteTemplate(page, templateId);
  }
});
