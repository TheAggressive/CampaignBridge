import { expect, test, type Page, type Request } from './support/fixtures';

const EDITOR_PATH = '/wp-admin/admin.php?page=campaignbridge-editor';

/** Reusable email definition fields a duplicate copies. */
const COPIED_META = {
  campaignbridge_subject: 'Launch subject',
  campaignbridge_preheader: 'Launch preheader',
  campaignbridge_sender_name: 'Launch Desk',
  campaignbridge_sender_email: 'launch@example.test',
  campaignbridge_view_online_enabled: true,
  campaignbridge_view_online_url: 'https://example.test/launch',
  campaignbridge_unsubscribe_url: 'https://example.test/unsubscribe',
  campaignbridge_address_html: '<p>1 Launch Street</p>',
  campaignbridge_utm_enabled: true,
  campaignbridge_utm_template: 'utm_source=launch',
  campaignbridge_footer_enabled: true,
  campaignbridge_footer_pattern: 'launch-footer',
};

/** Targeting and library fields a duplicate must not inherit. */
const EXCLUDED_META = {
  campaignbridge_template_category: 'newsletter',
  campaignbridge_audience_tags: 'launch-list',
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
  meta: Record<string, unknown>;
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

async function historyIds(
  page: Page,
  templateId: number,
  history: 'revisions' | 'autosaves'
): Promise<number[]> {
  const records = await apiFetch<{ id: number }[]>(page, {
    path: `/wp/v2/cb_templates/${templateId}/${history}?context=edit`,
  });
  return records.map(record => record.id);
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

async function createTemplate(
  page: Page,
  created: number[],
  data: Record<string, unknown>
): Promise<number> {
  await page.goto(EDITOR_PATH);
  await expect(page.locator('.cb-editor__header')).toBeVisible();
  const { id } = await apiFetch<{ id: number }>(page, {
    path: '/wp/v2/cb_templates',
    method: 'POST',
    data,
  });
  created.push(id);
  return id;
}

async function openTemplate(page: Page, templateId: number, text: string) {
  await page.goto(`${EDITOR_PATH}&post_id=${templateId}`);
  await expect(textBlock(page)).toHaveText(text);
  expect(await isDirty(page, templateId)).toBe(false);
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

/** Duplicate the open template and return the copy's ID after navigation. */
async function duplicateOpenTemplate(
  page: Page,
  created: number[]
): Promise<{ copyId: number; payload: Record<string, unknown> }> {
  const createdResponse = page.waitForResponse(response =>
    matches(response.request(), 'POST', createRoute)
  );
  await page.locator('.cb-editor__duplicate-button').click();
  const response = await createdResponse;
  expect(response.status()).toBe(201);
  const copyId = ((await response.json()) as { id: number }).id;
  created.push(copyId);

  await expect(page).toHaveURL(new RegExp(`post_id=${copyId}(?:&|$)`));
  return {
    copyId,
    payload: response.request().postDataJSON() as Record<string, unknown>,
  };
}

test.describe('CampaignBridge template duplication (E2E)', () => {
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

  test('A: a published template duplicates into a draft with only its reusable definition', async ({
    page,
  }) => {
    const title = `Duplication Source ${Date.now()}`;
    const id = await createTemplate(page, created, {
      title,
      status: 'draft',
      content: blockContent('Launch content'),
      meta: { ...COPIED_META, ...EXCLUDED_META },
    });
    await openTemplate(page, id, 'Launch content');

    // Publish through the editor.
    const badge = page.locator('.cb-editor__status-badge');
    const published = page.waitForResponse(response =>
      matches(response.request(), 'POST', canonicalRoute(id))
    );
    await page.locator('.cb-editor__publish-button').click();
    expect((await published).status()).toBe(200);
    await expect(badge).toHaveText('Published');

    // Give the source history of its own: a revision and a recovery autosave.
    await apiFetch(page, {
      path: `/wp/v2/cb_templates/${id}/autosaves`,
      method: 'POST',
      data: { content: blockContent('Source recovery copy') },
    });
    const source = await getTemplate(page, id);
    expect(source.status).toBe('publish');
    const sourceRevisions = await historyIds(page, id, 'revisions');
    const sourceAutosaves = await historyIds(page, id, 'autosaves');
    expect(sourceRevisions.length).toBeGreaterThan(0);
    expect(sourceAutosaves).toHaveLength(1);

    const creates = track(page, 'POST', createRoute);
    const { copyId, payload } = await duplicateOpenTemplate(page, created);

    // The create request carries only the reusable definition.
    expect(Object.keys(payload).sort()).toEqual([
      'content',
      'meta',
      'status',
      'title',
    ]);
    expect(payload.status).toBe('draft');
    expect(payload.meta).toEqual(COPIED_META);

    // The editor opens the copy as a draft of the saved content.
    await expect(textBlock(page)).toHaveText('Launch content');
    await expect(badge).toHaveText('Draft');
    expect(creates).toHaveLength(1);

    const copy = await getTemplate(page, copyId);
    expect(copy.status).toBe('draft');
    expect(copy.title.raw).toBe(`${title} (Copy)`);
    expect(copy.content.raw).toBe(source.content.raw);
    for (const [key, value] of Object.entries(COPIED_META)) {
      expect(copy.meta[key]).toEqual(value);
    }
    for (const key of Object.keys(EXCLUDED_META)) {
      expect(copy.meta[key]).toBe('');
    }
    expect(await historyIds(page, copyId, 'revisions')).toEqual([]);
    expect(await historyIds(page, copyId, 'autosaves')).toEqual([]);

    // The source keeps its status, definition, targeting, and history.
    const sourceAfter = await getTemplate(page, id);
    expect(sourceAfter.status).toBe('publish');
    expect(sourceAfter.content.raw).toBe(source.content.raw);
    expect(sourceAfter.meta).toEqual(source.meta);
    expect(await historyIds(page, id, 'revisions')).toEqual(sourceRevisions);
    expect(await historyIds(page, id, 'autosaves')).toEqual(sourceAutosaves);
  });

  test('B: a duplicate and its source are independent', async ({ page }) => {
    const id = await createTemplate(page, created, {
      title: `Independence Source ${Date.now()}`,
      status: 'draft',
      content: blockContent('Shared start'),
      meta: { campaignbridge_subject: 'Shared subject' },
    });
    await openTemplate(page, id, 'Shared start');

    const { copyId } = await duplicateOpenTemplate(page, created);
    await expect(textBlock(page)).toHaveText('Shared start');

    // Editing and saving the copy leaves the source unchanged.
    await editText(page, copyId, 'Copy edit');
    await saveTemplate(page, copyId);
    await apiFetch(page, {
      path: `/wp/v2/cb_templates/${copyId}`,
      method: 'PUT',
      data: { meta: { campaignbridge_subject: 'Copy subject' } },
    });
    let source = await getTemplate(page, id);
    expect(source.content.raw).toContain('Shared start');
    expect(source.content.raw).not.toContain('Copy edit');
    expect(source.meta.campaignbridge_subject).toBe('Shared subject');

    // Editing and saving the source leaves the copy unchanged.
    await openTemplate(page, id, 'Shared start');
    await editText(page, id, 'Source edit');
    await saveTemplate(page, id);
    const copy = await getTemplate(page, copyId);
    expect(copy.content.raw).toContain('Copy edit');
    expect(copy.content.raw).not.toContain('Source edit');
    expect(copy.meta.campaignbridge_subject).toBe('Copy subject');

    source = await getTemplate(page, id);
    expect(source.content.raw).toContain('Source edit');
    expect(source.meta.campaignbridge_subject).toBe('Shared subject');
  });
});
