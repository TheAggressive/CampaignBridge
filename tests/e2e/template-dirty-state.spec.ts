import { expect, test, type Page, type Request } from './support/fixtures';

const EDITOR_PATH = '/wp-admin/admin.php?page=campaignbridge-editor';
const UNSAVED_RESTORE = 'Save your changes before restoring a revision.';
const UNSAVED_DUPLICATE = 'Save your changes before duplicating this template.';
const AUTOSAVE_SETTLE_MS = 3500;

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

/** Native single-revision payload fetched by the unsaved-edit restore. */
function revisionRoute(templateId: number): RegExp {
  return new RegExp(
    `/wp/v2/cb_templates/${templateId}/revisions/\\d+(?:[?&]|$)`
  );
}

function autosaveRoute(templateId: number): RegExp {
  return new RegExp(`/wp/v2/cb_templates/${templateId}/autosaves(?:[?&]|$)`);
}

function canonicalRoute(templateId: number): RegExp {
  return new RegExp(`/wp/v2/cb_templates/${templateId}(?:[?&]|$)`);
}

function track(page: Page, method: string, route: RegExp): Request[] {
  const requests: Request[] = [];
  page.on('request', request => {
    if (matches(request, method, route)) {
      requests.push(request);
    }
  });
  return requests;
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

/**
 * Create a published template whose saves produced WordPress revisions of
 * Version A and Version B, and open it in the editor.
 */
async function openTemplateWithHistory(
  page: Page,
  created: number[]
): Promise<{ id: number; title: string }> {
  await page.goto(EDITOR_PATH);
  await expect(page.locator('.cb-editor__header')).toBeVisible();

  const title = `Dirty State ${Date.now()}`;
  const { id } = await apiFetch<{ id: number }>(page, {
    path: '/wp/v2/cb_templates',
    method: 'POST',
    data: { title, status: 'publish', content: blockContent('Initial') },
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

/** Replace the text block content through the rendered RichText field. */
async function editText(page: Page, templateId: number, text: string) {
  const block = textBlock(page);
  await block.click();
  await page.keyboard.press('ControlOrMeta+a');
  await page.keyboard.type(text);
  await expect(block).toHaveText(text);
  expect(await isDirty(page, templateId)).toBe(true);
}

async function saveTemplate(page: Page, templateId: number) {
  const saved = page.waitForResponse(response =>
    matches(response.request(), 'POST', canonicalRoute(templateId))
  );
  await page.locator('.cb-editor__save-button').click();
  expect((await saved).status()).toBe(200);
  await expect(page.locator('.cb-editor__save-button')).toHaveText('Saved');
  expect(await isDirty(page, templateId)).toBe(false);
}

/** Wait for the published template's autosave; core-data stays dirty. */
async function expectAutosaveKeepsTemplateDirty(
  page: Page,
  templateId: number
) {
  const autosave = await page.waitForResponse(response =>
    matches(response.request(), 'POST', autosaveRoute(templateId))
  );
  expect(autosave.status()).toBe(200);
  await expect(page.locator('.cb-editor__save-button')).toHaveText('Save');
  expect(await isDirty(page, templateId)).toBe(true);
}

test.describe('CampaignBridge unsaved editor state (E2E)', () => {
  const created: number[] = [];

  test.afterEach(async ({ page }) => {
    if (page.isClosed()) {
      return;
    }
    for (const id of created.splice(0)) {
      await apiFetch(page, {
        path: `/wp/v2/cb_templates/${id}?force=true`,
        method: 'DELETE',
      }).catch(() => undefined);
    }
  });

  test('A: a dirty template cannot have a revision restored', async ({
    page,
  }) => {
    const { id } = await openTemplateWithHistory(page, created);
    const revisionGets = track(page, 'GET', revisionRoute(id));

    await editText(page, id, 'Unsaved edit');
    await expectAutosaveKeepsTemplateDirty(page, id);

    // History can still be inspected while dirty.
    await page.locator('.cb-editor__history-button').click();
    const items = page.locator('.cb-editor__revision-item');
    await expect(items).toHaveCount(2);
    const unsavedNotice =
      'You have unsaved changes. Save them before restoring a revision.';
    // The notice also carries WordPress's visually hidden "Warning notice" label.
    await expect(page.locator('.cb-editor__revision-unsaved')).toContainText(
      unsavedNotice
    );
    // The WordPress notice is also announced to screen readers.
    await expect(page.locator('#a11y-speak-polite')).toContainText(
      unsavedNotice
    );

    await items.nth(1).getByRole('button', { name: 'Restore' }).click();
    await page.locator('.cb-editor__revision-restore-confirm').click();

    await expect(
      page.getByRole('alert').filter({ hasText: UNSAVED_RESTORE })
    ).toBeVisible();
    expect(revisionGets).toHaveLength(0);

    await page.getByRole('button', { name: 'Close' }).click();
    await expect(textBlock(page)).toHaveText('Unsaved edit');
    expect(await isDirty(page, id)).toBe(true);
    expect((await getTemplate(page, id)).content.raw).toContain('Version B');
  });

  test('B: after Save, restore applies unsaved changes until an explicit save', async ({
    page,
  }) => {
    const { id } = await openTemplateWithHistory(page, created);
    const revisionGets = track(page, 'GET', revisionRoute(id));

    await editText(page, id, 'Saved edit');
    await saveTemplate(page, id);

    await page.locator('.cb-editor__history-button').click();
    const items = page.locator('.cb-editor__revision-item');
    // Newest first: Saved edit, Version B, Version A.
    await expect(items).toHaveCount(3);
    await items.nth(2).getByRole('button', { name: 'Restore' }).click();

    const restored = page.waitForResponse(response =>
      matches(response.request(), 'GET', revisionRoute(id))
    );
    await page.locator('.cb-editor__revision-restore-confirm').dblclick();
    expect((await restored).status()).toBe(200);
    await expect(page.locator('.cb-editor__revision-items')).toBeHidden();

    // The restored revision is an unsaved change: the canonical template
    // keeps the explicitly saved edit until an explicit Save.
    await expect(textBlock(page)).toHaveText('Version A');
    expect(await isDirty(page, id)).toBe(true);
    await expect(page.locator('.cb-editor__save-button')).toHaveText('Save');
    expect((await getTemplate(page, id)).content.raw).toContain('Saved edit');

    // An explicit Save makes the restored revision canonical.
    await saveTemplate(page, id);
    expect((await getTemplate(page, id)).content.raw).toContain('Version A');

    // No delayed autosave can write anything over the saved state.
    await page.waitForTimeout(AUTOSAVE_SETTLE_MS);
    expect(revisionGets).toHaveLength(1);
    await expect(textBlock(page)).toHaveText('Version A');
    expect((await getTemplate(page, id)).content.raw).toContain('Version A');
  });

  test('C: a dirty template cannot be duplicated', async ({ page }) => {
    const { id } = await openTemplateWithHistory(page, created);
    const createRequests = track(page, 'POST', createRoute);

    await editText(page, id, 'Unsaved copy edit');
    await expectAutosaveKeepsTemplateDirty(page, id);

    await page.locator('.cb-editor__duplicate-button').click();

    await expect(snackbar(page, UNSAVED_DUPLICATE)).toBeVisible();
    expect(createRequests).toHaveLength(0);
    expect(new URL(page.url()).searchParams.get('post_id')).toBe(String(id));
    await expect(textBlock(page)).toHaveText('Unsaved copy edit');
    expect(await isDirty(page, id)).toBe(true);
  });

  test('D: after Save, duplicate creates exactly one copy of the saved state', async ({
    page,
  }) => {
    const { id, title } = await openTemplateWithHistory(page, created);
    const createRequests = track(page, 'POST', createRoute);

    await editText(page, id, 'Saved for copy');
    await saveTemplate(page, id);

    const createdResponse = page.waitForResponse(response =>
      matches(response.request(), 'POST', createRoute)
    );
    await page.locator('.cb-editor__duplicate-button').dblclick();
    const response = await createdResponse;
    expect(response.status()).toBe(201);
    const copyId = ((await response.json()) as { id: number }).id;
    created.push(copyId);

    await expect(page).toHaveURL(new RegExp(`post_id=${copyId}(?:&|$)`));
    await expect(textBlock(page)).toHaveText('Saved for copy');
    expect(createRequests).toHaveLength(1);

    const copy = await getTemplate(page, copyId);
    expect(copy.status).toBe('draft');
    expect(copy.title.raw).toBe(`${title} (Copy)`);
    expect(copy.content.raw).toContain('Saved for copy');
  });
});
