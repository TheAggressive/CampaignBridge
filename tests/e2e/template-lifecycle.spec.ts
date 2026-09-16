import { expect, test, type Page, type Request } from './support/fixtures';

const EDITOR_PATH = '/wp-admin/admin.php?page=campaignbridge-editor';
const SUBJECT = 'campaignbridge_subject';
const AUDIENCE = 'campaignbridge_audience_tags';

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
  meta: Record<string, unknown>;
}

interface RevisionRecord {
  id: number;
  slug: string;
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

function canonicalRoute(templateId: number): RegExp {
  return new RegExp(`/wp/v2/cb_templates/${templateId}(?:[?&]|$)`);
}

function autosaveRoute(templateId: number): RegExp {
  return new RegExp(`/wp/v2/cb_templates/${templateId}/autosaves(?:[?&]|$)`);
}

function waitFor(page: Page, method: string, route: RegExp) {
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

/** Normal revisions newest first, straight from core's REST collection. */
async function normalRevisions(
  page: Page,
  templateId: number
): Promise<RevisionRecord[]> {
  const revisions = await apiFetch<RevisionRecord[]>(page, {
    path: `/wp/v2/cb_templates/${templateId}/revisions?context=edit&per_page=100`,
  });
  return revisions.filter(
    revision => !revision.slug.includes(`${templateId}-autosave`)
  );
}

async function autosaveIds(page: Page, templateId: number): Promise<number[]> {
  const autosaves = await apiFetch<{ id: number }[]>(page, {
    path: `/wp/v2/cb_templates/${templateId}/autosaves`,
  });
  return autosaves.map(autosave => autosave.id);
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
  const saved = waitFor(page, 'POST', canonicalRoute(templateId));
  await page.locator('.cb-editor__save-button').click();
  expect((await saved).status()).toBe(200);
  await expect(page.locator('.cb-editor__save-button')).toHaveText(
    (await getTemplate(page, templateId)).status === 'publish'
      ? 'Saved'
      : 'Saved'
  );
  expect(await isDirty(page, templateId)).toBe(false);
}

test.describe('CampaignBridge template lifecycle (E2E)', () => {
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

  test('a template moves through create, autosave, save, publish, restore, and duplicate', async ({
    page,
  }) => {
    test.setTimeout(120_000);
    const saveButton = page.locator('.cb-editor__save-button');
    const badge = page.locator('.cb-editor__status-badge');
    const title = `Lifecycle ${Date.now()}`;

    // CREATE through the New Template flow.
    await page.goto(EDITOR_PATH);
    await expect(page.locator('.cb-editor__header')).toBeVisible();
    await page.locator('.cb-editor__new-template').click();
    await page.getByLabel('Template name').fill(title);
    const createdResponse = waitFor(page, 'POST', createRoute);
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    expect((await createdResponse).status()).toBe(201);
    // Creating reloads the editor for the new template, which discards the
    // create response body, so the new ID is read from the reloaded URL.
    await page.waitForURL(/[?&]post_id=\d+(?:&|$)/);
    const id = Number(new URL(page.url()).searchParams.get('post_id'));
    expect(id).toBeGreaterThan(0);
    created.push(id);
    await expect(badge).toHaveText('Draft');
    await expect(saveButton).toHaveText('Saved');
    expect(await isDirty(page, id)).toBe(false);
    let template = await getTemplate(page, id);
    expect(template.status).toBe('draft');
    expect(template.title.raw).toBe(title);
    expect(template.meta[AUDIENCE]).toBe('');

    // Give the new draft an editable text block, definition meta, and
    // campaign targeting through a canonical core REST save.
    await apiFetch(page, {
      path: `/wp/v2/cb_templates/${id}`,
      method: 'PUT',
      data: {
        content: blockContent('First version'),
        meta: { [SUBJECT]: 'Lifecycle subject', [AUDIENCE]: 'launch-list' },
      },
    });
    await page.goto(`${EDITOR_PATH}&post_id=${id}`);
    await expect(textBlock(page)).toHaveText('First version');
    expect(await isDirty(page, id)).toBe(false);

    // EDIT → AUTOSAVE (draft): core applies it to the draft and cleans the editor.
    let autosaved = waitFor(page, 'POST', autosaveRoute(id));
    await editText(page, id, 'Autosaved draft');
    expect((await autosaved).status()).toBe(200);
    await expect.poll(() => isDirty(page, id)).toBe(false);
    await expect(saveButton).toHaveText('Saved');
    template = await getTemplate(page, id);
    expect(template.status).toBe('draft');
    expect(template.content.raw).toContain('Autosaved draft');

    // MANUAL SAVE creates a revision of the saved state.
    const revisionsBeforeSave = (await normalRevisions(page, id)).length;
    await editText(page, id, 'Saved draft');
    await saveTemplate(page, id);
    expect((await getTemplate(page, id)).content.raw).toContain('Saved draft');
    expect((await normalRevisions(page, id)).length).toBe(
      revisionsBeforeSave + 1
    );

    // PUBLISH.
    const published = waitFor(page, 'POST', canonicalRoute(id));
    await page.locator('.cb-editor__publish-button').click();
    expect((await published).status()).toBe(200);
    await expect(badge).toHaveText('Published');
    expect(await isDirty(page, id)).toBe(false);
    expect((await getTemplate(page, id)).status).toBe('publish');

    // EDIT PUBLISHED → AUTOSAVE: a separate recovery record; canonical
    // state is untouched and the editor stays dirty until Save.
    autosaved = waitFor(page, 'POST', autosaveRoute(id));
    await editText(page, id, 'Published edit');
    const autosaveResponse = await autosaved;
    expect(autosaveResponse.status()).toBe(200);
    const autosave = (await autosaveResponse.json()) as { id: number };
    expect(await autosaveIds(page, id)).toContain(autosave.id);
    template = await getTemplate(page, id);
    expect(template.status).toBe('publish');
    expect(template.content.raw).not.toContain('Published edit');
    expect(await isDirty(page, id)).toBe(true);
    await expect(saveButton).toHaveText('Save');

    // SAVE the published edit.
    await saveTemplate(page, id);
    template = await getTemplate(page, id);
    expect(template.status).toBe('publish');
    expect(template.content.raw).toContain('Published edit');

    // VIEW HISTORY: every normal revision, never the autosave.
    const history = await normalRevisions(page, id);
    await page.locator('.cb-editor__history-button').click();
    const items = page.locator('.cb-editor__revision-item');
    await expect(items).toHaveCount(history.length);
    const listed = await items.evaluateAll(rows =>
      rows.map(row => Number((row as HTMLElement).dataset.revisionId))
    );
    expect(listed).toEqual(history.map(revision => revision.id));
    expect(listed).not.toContain(autosave.id);

    // RESTORE the manual draft Save as unsaved changes.
    const savedDraft = history.find(revision =>
      revision.content.raw.includes('Saved draft')
    );
    expect(savedDraft).toBeDefined();
    await page
      .locator(
        `.cb-editor__revision-item[data-revision-id="${savedDraft?.id}"]`
      )
      .getByRole('button', { name: /Restore/ })
      .click();
    const restored = waitFor(
      page,
      'GET',
      new RegExp(
        `/wp/v2/cb_templates/${id}/revisions/${savedDraft?.id}(?:[?&]|$)`
      )
    );
    await page.locator('.cb-editor__revision-restore-confirm').click();
    expect((await restored).status()).toBe(200);
    await expect(page.locator('.cb-editor__revision-items')).toBeHidden();

    // The editor shows the restored revision as unsaved changes only.
    await expect(textBlock(page)).toHaveText('Saved draft');
    expect(await isDirty(page, id)).toBe(true);
    await expect(saveButton).toHaveText('Save');
    await expect(badge).toHaveText('Published');

    // The canonical template keeps the published edit until an explicit Save.
    let source = await getTemplate(page, id);
    expect(source.content.raw).toContain('Published edit');
    expect(source.status).toBe('publish');

    // An explicit Save makes the restored revision canonical and keeps the
    // organizational/targeting meta from the revision snapshot.
    await saveTemplate(page, id);
    source = await getTemplate(page, id);
    expect(source.content.raw).toContain('Saved draft');
    expect(source.status).toBe('publish');
    expect(source.meta[SUBJECT]).toBe('Lifecycle subject');
    expect(source.meta[AUDIENCE]).toBe('launch-list');
    const afterRestore = await normalRevisions(page, id);
    expect(afterRestore.length).toBe(history.length + 1);
    expect(afterRestore[0].content.raw).toContain('Saved draft');

    // DUPLICATE the clean, restored template.
    const copyResponse = waitFor(page, 'POST', createRoute);
    await page.locator('.cb-editor__duplicate-button').click();
    const duplicateResponse = await copyResponse;
    expect(duplicateResponse.status()).toBe(201);
    const copyId = ((await duplicateResponse.json()) as { id: number }).id;
    created.push(copyId);
    await expect(page).toHaveURL(new RegExp(`post_id=${copyId}(?:&|$)`));
    await expect(textBlock(page)).toHaveText('Saved draft');
    await expect(badge).toHaveText('Draft');
    expect(await isDirty(page, copyId)).toBe(false);
    let copy = await getTemplate(page, copyId);
    expect(copy.status).toBe('draft');
    expect(copy.title.raw).toBe(`${title} (Copy)`);
    expect(copy.content.raw).toBe(source.content.raw);
    expect(copy.meta[SUBJECT]).toBe('Lifecycle subject');
    expect(copy.meta[AUDIENCE]).toBe('');
    expect(await normalRevisions(page, copyId)).toEqual([]);
    expect(await autosaveIds(page, copyId)).toEqual([]);

    // EDIT and SAVE the duplicate; the source stays as it was.
    await editText(page, copyId, 'Copy edit');
    await saveTemplate(page, copyId);
    copy = await getTemplate(page, copyId);
    expect(copy.content.raw).toContain('Copy edit');
    const sourceAfter = await getTemplate(page, id);
    expect(sourceAfter.content.raw).toBe(source.content.raw);
    expect(sourceAfter.status).toBe('publish');
    expect(sourceAfter.meta).toEqual(source.meta);
    expect((await normalRevisions(page, id)).map(r => r.id)).toEqual(
      afterRestore.map(r => r.id)
    );
  });
});
