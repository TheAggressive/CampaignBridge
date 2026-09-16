import { expect, test, type Page, type Request } from './support/fixtures';

const EDITOR_PATH = '/wp-admin/admin.php?page=campaignbridge-editor';
const SUBJECT = 'campaignbridge_subject';
const PER_PAGE = 20;

type ApiFetch = <T>(
  // eslint-disable-next-line no-unused-vars -- Documents the callable WordPress API contract.
  options: {
    path: string;
    method?: string;
    data?: Record<string, unknown>;
  }
) => Promise<T>;

interface RevisionRecord {
  id: number;
  slug: string;
}

interface TemplateRecord {
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
function isRevisionPage(request: Request, templateId: number, page: number) {
  const url = decodeURIComponent(request.url());
  return (
    request.method() === 'GET' &&
    new RegExp(`/wp/v2/cb_templates/${templateId}/revisions(?:[?&]|$)`).test(
      url
    ) &&
    new RegExp(`[?&]page=${page}(?:&|$)`).test(url)
  );
}

function textBlock(page: Page) {
  return page
    .frameLocator('iframe[name="editor-canvas"]')
    .locator('[data-type="campaignbridge/text"][contenteditable="true"]')
    .first();
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

/** Canonical saves through core REST, each creating one WordPress revision. */
async function saveVersions(
  page: Page,
  templateId: number,
  from: number,
  to: number
) {
  for (let version = from; version <= to; version++) {
    const label = String(version).padStart(2, '0');
    await apiFetch(page, {
      path: `/wp/v2/cb_templates/${templateId}`,
      method: 'PUT',
      data: {
        content: blockContent(`Version ${label}`),
        meta: { [SUBJECT]: `Subject ${label}` },
      },
    });
  }
}

/** Normal revision IDs newest first, straight from core's REST collection. */
async function canonicalRevisionIds(
  page: Page,
  templateId: number
): Promise<number[]> {
  const revisions = await apiFetch<RevisionRecord[]>(page, {
    path: `/wp/v2/cb_templates/${templateId}/revisions?per_page=100&order=desc&orderby=date&_fields=id,slug`,
  });
  return revisions
    .filter(revision => !revision.slug.includes(`${templateId}-autosave`))
    .map(revision => revision.id);
}

async function listedIds(page: Page): Promise<number[]> {
  return page
    .locator('.cb-editor__revision-item')
    .evaluateAll(items =>
      items.map(item => Number((item as HTMLElement).dataset.revisionId))
    );
}

async function createTemplate(
  page: Page,
  created: number[],
  versions: number
): Promise<number> {
  await page.goto(EDITOR_PATH);
  await expect(page.locator('.cb-editor__header')).toBeVisible();
  const { id } = await apiFetch<{ id: number }>(page, {
    path: '/wp/v2/cb_templates',
    method: 'POST',
    data: {
      title: `Revision History ${Date.now()}`,
      status: 'publish',
      content: blockContent('Version 00'),
      meta: { [SUBJECT]: 'Subject 00' },
    },
  });
  created.push(id);
  await saveVersions(page, id, 1, versions);
  return id;
}

async function openHistory(page: Page, templateId: number, newest: string) {
  await page.goto(`${EDITOR_PATH}&post_id=${templateId}`);
  await expect(textBlock(page)).toHaveText(newest);
  await page.locator('.cb-editor__history-button').click();
  await expect(page.locator('.cb-editor__revision-item')).toHaveCount(PER_PAGE);
}

test.describe('CampaignBridge revision history (E2E)', () => {
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

  test('A: a long history loads page by page without autosaves or duplicates', async ({
    page,
  }) => {
    const id = await createTemplate(page, created, 23);
    // A real recovery autosave must never appear or count as history.
    const autosave = await apiFetch<{ id: number }>(page, {
      path: `/wp/v2/cb_templates/${id}/autosaves`,
      method: 'POST',
      data: { content: blockContent('Unsaved recovery copy') },
    });
    const expected = await canonicalRevisionIds(page, id);
    expect(expected).toHaveLength(23);
    expect(expected).not.toContain(autosave.id);

    await openHistory(page, id, 'Version 23');
    const status = page.locator('.cb-editor__revision-status');
    const loadMore = page.locator('.cb-editor__revision-load-more');
    expect(await listedIds(page)).toEqual(expected.slice(0, PER_PAGE));
    await expect(status).toHaveText('Showing 20 of 23 revisions.');
    await expect(loadMore).toBeVisible();

    const pageTwoRequests: Request[] = [];
    page.on('request', request => {
      if (isRevisionPage(request, id, 2)) {
        pageTwoRequests.push(request);
      }
    });
    const pageTwo = page.waitForResponse(response =>
      isRevisionPage(response.request(), id, 2)
    );
    await loadMore.dblclick();
    expect((await pageTwo).status()).toBe(200);

    await expect(page.locator('.cb-editor__revision-item')).toHaveCount(23);
    expect(await listedIds(page)).toEqual(expected);
    expect(await listedIds(page)).not.toContain(autosave.id);
    expect(pageTwoRequests).toHaveLength(1);
    await expect(status).toHaveText('Showing 23 of 23 revisions.');
    await expect(loadMore).toHaveCount(0);
    // Focus moves to the first revision the request added.
    await expect(
      page
        .locator('.cb-editor__revision-item')
        .nth(PER_PAGE)
        .getByRole('button')
    ).toBeFocused();
  });

  test('B: Refresh reloads canonical history from page 1 and survives a failure', async ({
    page,
  }) => {
    const id = await createTemplate(page, created, 21);
    await openHistory(page, id, 'Version 21');
    const loadMore = page.locator('.cb-editor__revision-load-more');
    await loadMore.click();
    await expect(page.locator('.cb-editor__revision-item')).toHaveCount(21);

    // A failed refresh keeps the loaded history and stays retryable.
    await page.route(
      url =>
        /\/wp\/v2\/cb_templates\/\d+\/revisions/.test(
          decodeURIComponent(url.href)
        ),
      route =>
        route.fulfill({
          status: 500,
          contentType: 'application/json',
          body: JSON.stringify({
            code: 'db_error',
            message: 'SQLSTATE[HY000]',
          }),
        })
    );
    const refresh = page.locator('.cb-editor__revision-header-action');
    await refresh.click();
    await expect(
      page.getByRole('alert').filter({
        hasText: 'Revision history could not be loaded. Please try again.',
      })
    ).toBeVisible();
    await expect(page.locator('body')).not.toContainText('SQLSTATE');
    await expect(page.locator('.cb-editor__revision-item')).toHaveCount(21);
    await expect(refresh).toBeFocused();
    await page.unrouteAll({ behavior: 'ignoreErrors' });

    // Another canonical save lands while the history is open.
    await saveVersions(page, id, 22, 22);
    const expected = await canonicalRevisionIds(page, id);
    expect(expected).toHaveLength(22);

    await refresh.click();
    await expect(page.getByRole('alert')).toHaveCount(0);
    await expect(page.locator('.cb-editor__revision-item')).toHaveCount(
      PER_PAGE
    );
    expect(await listedIds(page)).toEqual(expected.slice(0, PER_PAGE));
    await expect(page.locator('.cb-editor__revision-status')).toHaveText(
      'Showing 20 of 22 revisions.'
    );
    await expect(refresh).toBeFocused();

    await loadMore.click();
    await expect(page.locator('.cb-editor__revision-item')).toHaveCount(22);
    expect(await listedIds(page)).toEqual(expected);
    await expect(loadMore).toHaveCount(0);
  });

  test('C: a restored revision stays unsaved until an explicit save', async ({
    page,
  }) => {
    const id = await createTemplate(page, created, 21);
    const before = await canonicalRevisionIds(page, id);
    await openHistory(page, id, 'Version 21');
    await page.locator('.cb-editor__revision-load-more').click();
    const items = page.locator('.cb-editor__revision-item');
    await expect(items).toHaveCount(21);

    // The oldest revision, on page 2, holds Version 01.
    const oldest = items.nth(20);
    expect(Number(await oldest.getAttribute('data-revision-id'))).toBe(
      before[20]
    );
    await oldest.getByRole('button', { name: /Restore/ }).click();
    await page.locator('.cb-editor__revision-restore-confirm').click();
    await expect(page.locator('.cb-editor__revision-items')).toBeHidden();

    // The editor shows the restored revision as unsaved changes only.
    await expect(textBlock(page)).toHaveText('Version 01');
    expect(await isDirty(page, id)).toBe(true);
    const saveButton = page.locator('.cb-editor__save-button');
    await expect(saveButton).toHaveText('Update');

    // The canonical template keeps Version 21 until an explicit Save.
    let template = await apiFetch<TemplateRecord>(page, {
      path: `/wp/v2/cb_templates/${id}?context=edit`,
    });
    expect(template.content.raw).toContain('Version 21');
    expect(template.meta[SUBJECT]).toBe('Subject 21');

    // An explicit Save makes the restored revision canonical.
    const saved = page.waitForResponse(
      response =>
        ['POST', 'PUT'].includes(response.request().method()) &&
        new RegExp(`/wp/v2/cb_templates/${id}(?:[?&]|$)`).test(
          decodeURIComponent(response.url())
        )
    );
    await saveButton.click();
    expect((await saved).status()).toBe(200);

    await expect(saveButton).toHaveText('Updated');
    expect(await isDirty(page, id)).toBe(false);
    template = await apiFetch<TemplateRecord>(page, {
      path: `/wp/v2/cb_templates/${id}?context=edit`,
    });
    expect(template.content.raw).toContain('Version 01');
    expect(template.meta[SUBJECT]).toBe('Subject 01');

    // Reopened history starts with the new revision of the restored state.
    const after = await canonicalRevisionIds(page, id);
    expect(after).toHaveLength(22);
    expect(after.slice(1)).toEqual(before);
    await page.locator('.cb-editor__history-button').click();
    await expect(items).toHaveCount(PER_PAGE);
    expect(await listedIds(page)).toEqual(after.slice(0, PER_PAGE));
    await expect(page.locator('.cb-editor__revision-status')).toHaveText(
      'Showing 20 of 22 revisions.'
    );
  });
});
