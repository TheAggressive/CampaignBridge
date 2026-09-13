import { expect, test, type Page, type Request } from '@playwright/test';

const EDITOR_PATH = '/wp-admin/admin.php?page=campaignbridge-editor';
const AUTOSAVE_DELAY_MS = 2000;
// Long enough for a pending debounce to fire and its request to start; used
// only to prove that no further autosave request happens.
const AUTOSAVE_SETTLE_MS = AUTOSAVE_DELAY_MS + 1500;
const SUBJECT = 'campaignbridge_subject';

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
  meta: Record<string, unknown>;
}

interface EditorMetaState {
  hasEdits: boolean;
  editedSubject: unknown;
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
function matchesRoute(url: string, route: RegExp): boolean {
  return route.test(decodeURIComponent(url));
}

function isAutosavePost(request: Request, templateId: number): boolean {
  return (
    request.method() === 'POST' &&
    matchesRoute(
      request.url(),
      new RegExp(`/wp/v2/cb_templates/${templateId}/autosaves(?:[?&]|$)`)
    )
  );
}

// wp.apiFetch sends PUT as POST with an X-HTTP-Method-Override header.
function isCanonicalWrite(request: Request, templateId: number): boolean {
  return (
    ['POST', 'PUT'].includes(request.method()) &&
    matchesRoute(
      request.url(),
      new RegExp(`/wp/v2/cb_templates/${templateId}(?:[?&]|$)`)
    )
  );
}

async function withTemplate(
  page: Page,
  status: 'draft' | 'publish',
  // eslint-disable-next-line no-unused-vars -- Documents the callback contract.
  body: (templateId: number) => Promise<void>
): Promise<void> {
  await page.goto(EDITOR_PATH);
  await expect(page.locator('.cb-editor__header')).toBeVisible();
  const templateId = (
    await apiFetch<{ id: number }>(page, {
      path: '/wp/v2/cb_templates',
      method: 'POST',
      data: {
        title: `Template Meta ${status} ${Date.now()}`,
        status,
        meta: { [SUBJECT]: 'Original subject' },
      },
    })
  ).id;

  let primaryError: unknown = null;
  try {
    await body(templateId);
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
}

async function getTemplate(
  page: Page,
  templateId: number
): Promise<TemplateRecord> {
  return apiFetch<TemplateRecord>(page, {
    path: `/wp/v2/cb_templates/${templateId}?context=edit`,
  });
}

async function getEditorMetaState(
  page: Page,
  templateId: number
): Promise<EditorMetaState> {
  return page.evaluate(
    ({ id, key }) => {
      const core = (
        globalThis as typeof globalThis & {
          wp: { data: { select: Function } };
        }
      ).wp.data.select('core');

      return {
        hasEdits: core.hasEditsForEntityRecord('postType', 'cb_templates', id),
        editedSubject: core.getEditedEntityRecord(
          'postType',
          'cb_templates',
          id
        )?.meta?.[key],
      };
    },
    { id: templateId, key: SUBJECT }
  );
}

async function openTemplateSettings(
  page: Page,
  templateId: number
): Promise<void> {
  await page.goto(`${EDITOR_PATH}&post_id=${templateId}`);
  await expect(page.locator('.cb-editor__header')).toBeVisible();
  await page.getByRole('tab', { name: 'Document' }).click();
  await expect(page.getByLabel('Subject Line')).toHaveValue('Original subject');
  expect((await getEditorMetaState(page, templateId)).hasEdits).toBe(false);
}

async function editSubject(
  page: Page,
  templateId: number,
  subject: string
): Promise<void> {
  await page.getByLabel('Subject Line').fill(subject);

  const state = await getEditorMetaState(page, templateId);
  expect(state.hasEdits).toBe(true);
  expect(state.editedSubject).toBe(subject);
}

test.describe('CampaignBridge template metadata autosave (E2E)', () => {
  test('draft: autosave does not persist meta, so the edit stays dirty until Save', async ({
    page,
  }) => {
    await withTemplate(page, 'draft', async templateId => {
      await openTemplateSettings(page, templateId);
      const autosavePosts: Request[] = [];
      page.on('request', request => {
        if (isAutosavePost(request, templateId)) {
          autosavePosts.push(request);
        }
      });
      const autosaveResponse = page.waitForResponse(response =>
        isAutosavePost(response.request(), templateId)
      );

      await editSubject(page, templateId, 'Draft subject edit');

      const response = await autosaveResponse;
      expect(response.status()).toBe(200);
      // WordPress applies a draft autosave to post fields only.
      const autosaved = (await response.json()) as TemplateRecord;
      expect(autosaved.id).toBe(templateId);
      expect(autosaved.meta[SUBJECT]).toBe('Original subject');

      await page.waitForTimeout(AUTOSAVE_SETTLE_MS);
      expect(autosavePosts).toHaveLength(1);

      // The operator's edit is neither lost nor falsely shown as saved.
      const state = await getEditorMetaState(page, templateId);
      expect(state.hasEdits).toBe(true);
      expect(state.editedSubject).toBe('Draft subject edit');
      await expect(page.getByLabel('Subject Line')).toHaveValue(
        'Draft subject edit'
      );
      const saveButton = page.locator('.cb-editor__save-button');
      await expect(saveButton).toHaveText('Save');
      await expect(saveButton).toBeEnabled();
      expect((await getTemplate(page, templateId)).meta[SUBJECT]).toBe(
        'Original subject'
      );

      const saveResponse = page.waitForResponse(candidate =>
        isCanonicalWrite(candidate.request(), templateId)
      );
      await saveButton.click();
      expect((await saveResponse).status()).toBe(200);
      await expect(saveButton).toHaveText('Saved');
      expect((await getTemplate(page, templateId)).meta[SUBJECT]).toBe(
        'Draft subject edit'
      );
      expect((await getEditorMetaState(page, templateId)).hasEdits).toBe(false);
    });
  });

  test('published: autosave stores recovery meta without changing canonical meta', async ({
    page,
  }) => {
    await withTemplate(page, 'publish', async templateId => {
      await openTemplateSettings(page, templateId);
      const autosavePosts: Request[] = [];
      page.on('request', request => {
        if (isAutosavePost(request, templateId)) {
          autosavePosts.push(request);
        }
      });
      const autosaveResponse = page.waitForResponse(response =>
        isAutosavePost(response.request(), templateId)
      );

      await editSubject(page, templateId, 'Published subject edit');

      const response = await autosaveResponse;
      expect(response.status()).toBe(200);
      const autosave = (await response.json()) as TemplateRecord & {
        parent: number;
      };
      expect(autosave.id).not.toBe(templateId);
      expect(autosave.parent).toBe(templateId);
      expect(autosave.meta[SUBJECT]).toBe('Published subject edit');

      await page.waitForTimeout(AUTOSAVE_SETTLE_MS);
      expect(autosavePosts).toHaveLength(1);

      const canonical = await getTemplate(page, templateId);
      expect(canonical.status).toBe('publish');
      expect(canonical.meta[SUBJECT]).toBe('Original subject');

      const state = await getEditorMetaState(page, templateId);
      expect(state.hasEdits).toBe(true);
      expect(state.editedSubject).toBe('Published subject edit');
      await expect(page.locator('.cb-editor__save-button')).toBeEnabled();
    });
  });
});
