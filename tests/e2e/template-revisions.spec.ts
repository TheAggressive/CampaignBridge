import { expect, test, type Page, type Request } from './support/fixtures';

const EDITOR_PATH = '/wp-admin/admin.php?page=campaignbridge-editor';
const SUBJECT = 'campaignbridge_subject';

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
  parent: number;
  slug: string;
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

// wp.apiFetch sends PUT as POST with an X-HTTP-Method-Override header. Sites
// without pretty permalinks (CI) URL-encode the route in `?rest_route=`.
function isCanonicalWrite(request: Request, templateId: number): boolean {
  return (
    ['POST', 'PUT'].includes(request.method()) &&
    new RegExp(`/wp/v2/cb_templates/${templateId}(?:[?&]|$)`).test(
      decodeURIComponent(request.url())
    )
  );
}

async function getSubject(page: Page, templateId: number): Promise<unknown> {
  const template = await apiFetch<{ meta: Record<string, unknown> }>(page, {
    path: `/wp/v2/cb_templates/${templateId}?context=edit`,
  });
  return template.meta[SUBJECT];
}

async function hasEdits(page: Page, templateId: number): Promise<boolean> {
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

async function saveSubject(
  page: Page,
  templateId: number,
  subject: string
): Promise<void> {
  await page.getByLabel('Subject Line').fill(subject);
  const saveResponse = page.waitForResponse(response =>
    isCanonicalWrite(response.request(), templateId)
  );
  await page.locator('.cb-editor__save-button').click();
  expect((await saveResponse).status()).toBe(200);
  await expect(page.locator('.cb-editor__save-button')).toHaveText('Saved');
  expect(await getSubject(page, templateId)).toBe(subject);
}

test('restores revisioned metadata through the editor and hides autosaves from history', async ({
  page,
}) => {
  await page.goto(EDITOR_PATH);
  await expect(page.locator('.cb-editor__header')).toBeVisible();
  const templateId = (
    await apiFetch<{ id: number }>(page, {
      path: '/wp/v2/cb_templates',
      method: 'POST',
      data: {
        title: `Template Revisions ${Date.now()}`,
        status: 'publish',
        meta: { [SUBJECT]: 'Original subject' },
      },
    })
  ).id;

  let primaryError: unknown = null;
  try {
    await page.goto(`${EDITOR_PATH}&post_id=${templateId}`);
    await expect(page.locator('.cb-editor__header')).toBeVisible();
    await page.getByRole('tab', { name: 'Document' }).click();

    // Each operator Save creates a WordPress revision of the saved state.
    await saveSubject(page, templateId, 'Revision A subject');
    await saveSubject(page, templateId, 'Revision B subject');

    // WordPress timestamps have second precision; make recovery newer.
    await page.waitForTimeout(1100);
    // A real autosave of the published template holds unsaved recovery meta.
    const autosave = await apiFetch<RevisionRecord>(page, {
      path: `/wp/v2/cb_templates/${templateId}/autosaves`,
      method: 'POST',
      data: { meta: { [SUBJECT]: 'Unsaved recovery subject' } },
    });
    expect(autosave.parent).toBe(templateId);
    expect(await getSubject(page, templateId)).toBe('Revision B subject');

    const listed = await apiFetch<RevisionRecord[]>(page, {
      path: `/wp/v2/cb_templates/${templateId}/revisions?per_page=20&order=desc`,
    });
    // Core lists the autosave alongside the normal revisions.
    expect(listed.map(revision => revision.id)).toContain(autosave.id);
    const normalRevisions = listed.filter(
      revision => !revision.slug.includes(`${templateId}-autosave`)
    );
    expect(normalRevisions).toHaveLength(2);

    // Reload so the editor starts from the saved template.
    await page.goto(`${EDITOR_PATH}&post_id=${templateId}`);
    await page.getByRole('tab', { name: 'Document' }).click();
    await expect(page.getByLabel('Subject Line')).toHaveValue(
      'Revision B subject'
    );

    // Resolve the new recovery prompt before operating on canonical history.
    await page
      .getByRole('button', { name: 'Discard autosave', exact: true })
      .click();
    expect(await getSubject(page, templateId)).toBe('Revision B subject');

    await page.locator('.cb-editor__history-button').click();
    const items = page.locator('.cb-editor__revision-item');
    await expect(items).toHaveCount(normalRevisions.length);

    // Newest first: restore the older operator save (Revision A).
    await items.nth(1).getByRole('button', { name: 'Restore' }).click();
    await page.locator('.cb-editor__revision-restore-confirm').click();
    await expect(page.locator('.cb-editor__revision-items')).toBeHidden();

    await expect(page.getByLabel('Subject Line')).toHaveValue(
      'Revision A subject'
    );
    expect(await getSubject(page, templateId)).toBe('Revision A subject');
    expect(await hasEdits(page, templateId)).toBe(false);
    await expect(page.locator('.cb-editor__save-button')).toHaveText('Saved');
  } catch (error) {
    primaryError = error;
  }

  // A timed-out test closes the page; cleanup errors must never replace the
  // primary assertion failure.
  if (!page.isClosed()) {
    try {
      await apiFetch(page, {
        path: `/wp/v2/cb_templates/${templateId}?force=true`,
        method: 'DELETE',
      });
    } catch (cleanupError) {
      if (!primaryError) {
        throw cleanupError;
      }
    }
  }

  if (primaryError) {
    throw primaryError;
  }
});
