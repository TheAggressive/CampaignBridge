import { expect, test, type Page, type Request } from './support/fixtures';

const EDITOR_PATH = '/wp-admin/admin.php?page=campaignbridge-editor';
const RAW_SERVER_ERROR = 'SQLSTATE[HY000]: simulated database failure';

const MESSAGES = {
  saved: 'Template saved.',
  published: 'Template published.',
  saveFailed: 'Template changes could not be saved. Please try again.',
  publishFailed: 'Template could not be published. Please try again.',
  duplicateFailed: 'This template could not be duplicated.',
  restoreFailed: 'This revision could not be restored. Please try again.',
};

type ApiFetch = <T>(
  // eslint-disable-next-line no-unused-vars -- Documents the callable WordPress API contract.
  options: {
    path: string;
    method?: string;
    data?: Record<string, unknown>;
  }
) => Promise<T>;

interface TemplateRecord {
  id: number;
  status: string;
  title: { raw: string };
  content: { raw: string };
}

function blockContent(text: string): string {
  return (
    '<!-- wp:campaignbridge/container -->\n' +
    '<!-- wp:campaignbridge/section -->\n' +
    `<!-- wp:campaignbridge/text {"content":"${text}"} /-->\n` +
    '<!-- /wp:campaignbridge/section -->\n' +
    '<!-- /wp:campaignbridge/container -->'
  );
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

// Sites without pretty permalinks (CI) send REST routes URL-encoded in
// `?rest_route=`, so routes are matched against the decoded URL.
function matches(request: Request, method: string, route: RegExp): boolean {
  return (
    request.method() === method && route.test(decodeURIComponent(request.url()))
  );
}

const createRoute = /\/wp\/v2\/cb_templates(?:[?&]|$)/;
// The editor restores by fetching the native single-revision payload.
const singleRevisionRoute =
  /\/wp\/v2\/cb_templates\/\d+\/revisions\/\d+(?:[?&]|$)/;

function canonicalRoute(templateId: number): RegExp {
  return new RegExp(`/wp/v2/cb_templates/${templateId}(?:[?&]|$)`);
}

/** Fail matching requests with a server error that must never be shown. */
async function failRequests(page: Page, method: string, route: RegExp) {
  await page.route(
    url => route.test(decodeURIComponent(url.href)),
    async interceptedRoute => {
      if (interceptedRoute.request().method() !== method) {
        await interceptedRoute.continue();
        return;
      }
      await interceptedRoute.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({
          code: 'db_error',
          message: RAW_SERVER_ERROR,
          data: { status: 500, trace: '/var/www/wp-includes/class-wpdb.php' },
        }),
      });
    }
  );
}

function waitForResponse(page: Page, method: string, route: RegExp) {
  return page.waitForResponse(response =>
    matches(response.request(), method, route)
  );
}

async function getTemplate(
  page: Page,
  templateId: number
): Promise<TemplateRecord> {
  return apiFetch<TemplateRecord>(page, {
    path: `/wp/v2/cb_templates/${templateId}?context=edit`,
  });
}

async function isDirty(page: Page, templateId: number): Promise<boolean> {
  return page.evaluate(
    id =>
      (
        globalThis as typeof globalThis & {
          wp: { data: { select: Function } };
        }
      ).wp.data
        .select('core')
        .hasEditsForEntityRecord('postType', 'cb_templates', id),
    templateId
  );
}

function textBlock(page: Page) {
  return page
    .frameLocator('iframe[name="editor-canvas"]')
    .locator('[data-type="campaignbridge/text"][contenteditable="true"]')
    .first();
}

function snackbar(page: Page, text: string) {
  return page.locator('.components-snackbar', { hasText: text });
}

async function expectNoRawServerText(page: Page) {
  await expect(page.locator('body')).not.toContainText('SQLSTATE');
  await expect(page.locator('body')).not.toContainText('class-wpdb.php');
}

/**
 * Create a template whose saves produced WordPress revisions of Version A and
 * Version B, and open it in the editor.
 */
async function openTemplate(
  page: Page,
  created: number[],
  status: 'draft' | 'publish'
): Promise<{ id: number; title: string }> {
  await page.goto(EDITOR_PATH);
  await expect(page.locator('.cb-editor__header')).toBeVisible();

  const title = `Editor Failure ${Date.now()}`;
  const { id } = await apiFetch<{ id: number }>(page, {
    path: '/wp/v2/cb_templates',
    method: 'POST',
    data: { title, status, content: blockContent('Initial') },
  });
  created.push(id);
  for (const text of ['Version A', 'Version B']) {
    await apiFetch(page, {
      path: `/wp/v2/cb_templates/${id}`,
      method: 'PUT',
      data: { content: blockContent(text) },
    });
  }

  await page.goto(`${EDITOR_PATH}&post_id=${id}`);
  await expect(textBlock(page)).toHaveText('Version B');
  expect(await isDirty(page, id)).toBe(false);

  return { id, title };
}

async function editText(page: Page, templateId: number, text: string) {
  const block = textBlock(page);
  await block.click();
  await page.keyboard.press('ControlOrMeta+a');
  await page.keyboard.type(text);
  await expect(block).toHaveText(text);
  expect(await isDirty(page, templateId)).toBe(true);
}

async function openHistoryAndConfirmVersionA(page: Page) {
  await page.locator('.cb-editor__history-button').click();
  const items = page.locator('.cb-editor__revision-item');
  // Newest first: Version B, Version A.
  await expect(items).toHaveCount(2);
  await items.nth(1).getByRole('button', { name: 'Restore' }).click();
  return page.locator('.cb-editor__revision-restore-confirm');
}

test.describe('CampaignBridge editor failure UX (E2E)', () => {
  const created: number[] = [];

  test.afterEach(async ({ page }) => {
    if (page.isClosed()) {
      return;
    }
    await page.unrouteAll({ behavior: 'ignoreErrors' });
    for (const id of created.splice(0)) {
      await apiFetch(page, {
        path: `/wp/v2/cb_templates/${id}?force=true`,
        method: 'DELETE',
      }).catch(() => undefined);
    }
  });

  test('A: a failed Save keeps the edit, shows safe copy, and can be retried', async ({
    page,
  }) => {
    const { id } = await openTemplate(page, created, 'publish');
    await editText(page, id, 'Edit that fails to save');

    await failRequests(page, 'POST', canonicalRoute(id));
    const failed = waitForResponse(page, 'POST', canonicalRoute(id));
    await page.locator('.cb-editor__save-button').click();
    expect((await failed).status()).toBe(500);

    await expect(snackbar(page, MESSAGES.saveFailed)).toBeVisible();
    await expect(snackbar(page, MESSAGES.saved)).toHaveCount(0);
    await expectNoRawServerText(page);
    await expect(textBlock(page)).toHaveText('Edit that fails to save');
    expect(await isDirty(page, id)).toBe(true);
    await expect(page.locator('.cb-editor__save-button')).toHaveText('Save');
    await expect(page.locator('.cb-editor__save-button')).toBeEnabled();
    expect((await getTemplate(page, id)).content.raw).toContain('Version B');

    await page.unrouteAll({ behavior: 'ignoreErrors' });
    const saved = waitForResponse(page, 'POST', canonicalRoute(id));
    await page.locator('.cb-editor__save-button').click();
    expect((await saved).status()).toBe(200);
    await expect(snackbar(page, MESSAGES.saved)).toBeVisible();
    expect(await isDirty(page, id)).toBe(false);
    expect((await getTemplate(page, id)).content.raw).toContain(
      'Edit that fails to save'
    );
  });

  test('B: a failed Publish never shows Published and can be retried', async ({
    page,
  }) => {
    const { id } = await openTemplate(page, created, 'draft');
    const badge = page.locator('.cb-editor__status-badge');
    await expect(badge).toHaveText('Draft');

    await failRequests(page, 'POST', canonicalRoute(id));
    const failed = waitForResponse(page, 'POST', canonicalRoute(id));
    await page.locator('.cb-editor__publish-button').click();
    expect((await failed).status()).toBe(500);

    await expect(snackbar(page, MESSAGES.publishFailed)).toBeVisible();
    await expect(snackbar(page, MESSAGES.published)).toHaveCount(0);
    await expectNoRawServerText(page);
    await expect(badge).toHaveText('Draft');
    await expect(page.locator('.cb-editor__publish-button')).toBeEnabled();
    // The failed publish left no status edit a later Save could send.
    const editedStatus = await page.evaluate(
      templateId =>
        (
          globalThis as typeof globalThis & {
            wp: { data: { select: Function } };
          }
        ).wp.data
          .select('core')
          .getEditedEntityRecord('postType', 'cb_templates', templateId).status,
      id
    );
    expect(editedStatus).toBe('draft');
    expect(await isDirty(page, id)).toBe(false);
    expect((await getTemplate(page, id)).status).toBe('draft');

    await page.unrouteAll({ behavior: 'ignoreErrors' });
    const published = waitForResponse(page, 'POST', canonicalRoute(id));
    await page.locator('.cb-editor__publish-button').click();
    expect((await published).status()).toBe(200);
    await expect(badge).toHaveText('Published');
    await expect(snackbar(page, MESSAGES.published)).toBeVisible();
    expect((await getTemplate(page, id)).status).toBe('publish');
  });

  test('C: a failed Duplicate stays on the template and creates nothing', async ({
    page,
  }) => {
    const { id, title } = await openTemplate(page, created, 'publish');

    await failRequests(page, 'POST', createRoute);
    const failed = waitForResponse(page, 'POST', createRoute);
    await page.locator('.cb-editor__duplicate-button').click();
    expect((await failed).status()).toBe(500);

    await expect(snackbar(page, MESSAGES.duplicateFailed)).toBeVisible();
    await expectNoRawServerText(page);
    expect(new URL(page.url()).searchParams.get('post_id')).toBe(String(id));
    await expect(textBlock(page)).toHaveText('Version B');
    await expect(page.locator('.cb-editor__duplicate-button')).toBeEnabled();

    await page.unrouteAll({ behavior: 'ignoreErrors' });
    const matchesTitle = await apiFetch<TemplateRecord[]>(page, {
      path: `/wp/v2/cb_templates?search=${encodeURIComponent(title)}&status=draft,publish&context=edit`,
    });
    expect(matchesTitle.map(template => template.id)).toEqual([id]);

    // A retry with a working server creates the copy and opens it.
    const createdResponse = waitForResponse(page, 'POST', createRoute);
    await page.locator('.cb-editor__duplicate-button').click();
    const response = await createdResponse;
    expect(response.status()).toBe(201);
    const copyId = ((await response.json()) as { id: number }).id;
    created.push(copyId);
    await expect(page).toHaveURL(new RegExp(`post_id=${copyId}(?:&|$)`));
  });

  test('D: a failed restore keeps history open and the editor unchanged', async ({
    page,
  }) => {
    const { id } = await openTemplate(page, created, 'publish');
    const confirm = await openHistoryAndConfirmVersionA(page);

    await failRequests(page, 'GET', singleRevisionRoute);
    const failed = waitForResponse(page, 'GET', singleRevisionRoute);
    await confirm.click();
    expect((await failed).status()).toBe(500);

    await expect(
      page.getByRole('alert').filter({ hasText: MESSAGES.restoreFailed })
    ).toBeVisible();
    await expectNoRawServerText(page);
    const items = page.locator('.cb-editor__revision-item');
    await expect(items).toHaveCount(2);
    // The failed fetch mutated no editor state.
    expect(await isDirty(page, id)).toBe(false);
    await expect(textBlock(page)).toHaveText('Version B');
    const retryRestore = items.nth(1).getByRole('button', { name: 'Restore' });
    await expect(retryRestore).toBeEnabled();
    expect((await getTemplate(page, id)).content.raw).toContain('Version B');

    // A retry with a working server applies the revision as unsaved edits only.
    await page.unrouteAll({ behavior: 'ignoreErrors' });
    await retryRestore.click();
    const restored = waitForResponse(page, 'GET', singleRevisionRoute);
    await page.locator('.cb-editor__revision-restore-confirm').click();
    expect((await restored).status()).toBe(200);
    await expect(page.locator('.cb-editor__revision-items')).toBeHidden();
    await expect(textBlock(page)).toHaveText('Version A');
    expect(await isDirty(page, id)).toBe(true);
    // Canonical content stays Version B until an explicit Save.
    expect((await getTemplate(page, id)).content.raw).toContain('Version B');
  });

  test('E: a successful restore applies the revision as unsaved changes only', async ({
    page,
  }) => {
    const { id } = await openTemplate(page, created, 'publish');
    const confirm = await openHistoryAndConfirmVersionA(page);
    const urlBefore = page.url();

    // The restore fetches the native single-revision payload.
    const restored = waitForResponse(page, 'GET', singleRevisionRoute);
    await confirm.click();
    expect((await restored).status()).toBe(200);
    await expect(page.locator('.cb-editor__revision-items')).toBeHidden();

    // The editor canvas shows the revision as unsaved changes.
    await expect(textBlock(page)).toHaveText('Version A');
    expect(await isDirty(page, id)).toBe(true);
    const saveButton = page.locator('.cb-editor__save-button');
    await expect(saveButton).toHaveText('Save');

    // The canonical saved template stays Version B until an explicit Save.
    expect((await getTemplate(page, id)).content.raw).toContain('Version B');

    // No full-page reload or reload prompt: same session and URL.
    expect(page.url()).toBe(urlBefore);
    await expect(
      page.getByRole('button', { name: 'Reload editor' })
    ).toHaveCount(0);
  });
});
