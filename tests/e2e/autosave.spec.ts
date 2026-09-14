import { expect, test, type Page, type Request } from './support/fixtures';

const EDITOR_PATH = '/wp-admin/admin.php?page=campaignbridge-editor';

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
  content: { raw: string; rendered: string };
}

async function createTemplate(
  page: Page,
  title: string,
  content?: string,
  status: 'draft' | 'publish' = 'draft'
): Promise<number> {
  await page.goto(EDITOR_PATH);
  await expect(page.locator('.cb-editor__header')).toBeVisible();
  return page.evaluate(
    async ({ title, content, status }) => {
      const apiFetch = (
        globalThis as typeof globalThis & { wp: { apiFetch: ApiFetch } }
      ).wp.apiFetch;
      const result = await apiFetch<{ id: number }>({
        path: '/wp/v2/cb_templates',
        method: 'POST',
        data: {
          title,
          status,
          ...(content ? { content } : {}),
        },
      });
      return result.id;
    },
    { title, content, status }
  );
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

async function getTemplate(
  page: Page,
  templateId: number
): Promise<TemplateRecord> {
  return page.evaluate(async id => {
    const apiFetch = (
      globalThis as typeof globalThis & { wp: { apiFetch: ApiFetch } }
    ).wp.apiFetch;
    return apiFetch<TemplateRecord>({
      path: `/wp/v2/cb_templates/${id}?context=edit`,
      method: 'GET',
    });
  }, templateId);
}

async function getAutosaves(
  page: Page,
  templateId: number
): Promise<Array<{ id: number }>> {
  return page.evaluate(async id => {
    const apiFetch = (
      globalThis as typeof globalThis & { wp: { apiFetch: ApiFetch } }
    ).wp.apiFetch;
    return apiFetch<Array<{ id: number }>>({
      path: `/wp/v2/cb_templates/${id}/autosaves`,
      method: 'GET',
    });
  }, templateId);
}

async function createAutosave(
  page: Page,
  templateId: number,
  content: string
): Promise<number> {
  return page.evaluate(
    async ({ templateId, content }) => {
      const apiFetch = (
        globalThis as typeof globalThis & { wp: { apiFetch: ApiFetch } }
      ).wp.apiFetch;
      const result = await apiFetch<{ id: number }>({
        path: `/wp/v2/cb_templates/${templateId}/autosaves`,
        method: 'POST',
        data: { content },
      });
      return result.id;
    },
    { templateId, content }
  );
}

test.describe('CampaignBridge Autosave REST API', () => {
  test('draft template: autosave updates post in-place and preserves draft status', async ({
    page,
  }) => {
    const templateId = await createTemplate(
      page,
      `Autosave Draft ${Date.now()}`,
      '<!-- wp:campaignbridge/container -->\n<!-- wp:campaignbridge/section -->\n<!-- /wp:campaignbridge/section -->\n<!-- /wp:campaignbridge/container -->'
    );
    try {
      const originalContent = (await getTemplate(page, templateId)).content.raw;

      // WordPress updates draft posts in-place for the author (no separate
      // autosave record). The returned ID equals the template ID.
      const autosaveId = await createAutosave(
        page,
        templateId,
        '<!-- wp:campaignbridge/container -->\n<!-- wp:campaignbridge/section -->\n<!-- wp:campaignbridge/spacer {"height":24} /-->\n<!-- /wp:campaignbridge/section -->\n<!-- /wp:campaignbridge/container -->'
      );
      expect(autosaveId).toBe(templateId);

      // Verify canonical post is still draft and content was updated in-place.
      const canonical = await getTemplate(page, templateId);
      expect(canonical.status).toBe('draft');
      expect(canonical.content.raw).not.toBe(originalContent);
      expect(canonical.content.raw).toContain('spacer');
    } finally {
      await deleteTemplate(page, templateId);
    }
  });

  test('published template: autosave creates separate record and preserves publish status', async ({
    page,
  }) => {
    const templateId = await createTemplate(
      page,
      `Autosave Publish ${Date.now()}`,
      '<!-- wp:campaignbridge/container -->\n<!-- wp:campaignbridge/section -->\n<!-- /wp:campaignbridge/section -->\n<!-- /wp:campaignbridge/container -->',
      'publish'
    );
    try {
      const autosaveId = await createAutosave(
        page,
        templateId,
        '<!-- wp:campaignbridge/container -->\n<!-- wp:campaignbridge/section -->\n<!-- wp:campaignbridge/spacer {"height":24} /-->\n<!-- /wp:campaignbridge/section -->\n<!-- /wp:campaignbridge/container -->'
      );

      // Published posts get a separate autosave record with a different ID.
      expect(autosaveId).not.toBe(templateId);

      const canonical = await getTemplate(page, templateId);
      expect(canonical.status).toBe('publish');

      const autosaves = await getAutosaves(page, templateId);
      expect(autosaves.length).toBeGreaterThanOrEqual(1);
      expect(autosaves.some(a => a.id === autosaveId)).toBe(true);
    } finally {
      await deleteTemplate(page, templateId);
    }
  });

  test('published template: autosave does not modify canonical post', async ({
    page,
  }) => {
    const templateId = await createTemplate(
      page,
      `Autosave Distinguish ${Date.now()}`,
      '<!-- wp:campaignbridge/container -->\n<!-- wp:campaignbridge/section -->\n<!-- /wp:campaignbridge/section -->\n<!-- /wp:campaignbridge/container -->',
      'publish'
    );
    try {
      const before = await getTemplate(page, templateId);

      const autosaveId = await createAutosave(
        page,
        templateId,
        '<!-- wp:campaignbridge/container -->\n<!-- wp:campaignbridge/section -->\n<!-- wp:campaignbridge/spacer {"height":24} /-->\n<!-- /wp:campaignbridge/section -->\n<!-- /wp:campaignbridge/container -->'
      );

      // Autosave creates a separate record, not a revision.
      expect(autosaveId).not.toBe(templateId);

      // Canonical content is unchanged.
      const after = await getTemplate(page, templateId);
      expect(after.content.raw).toBe(before.content.raw);
      expect(after.status).toBe('publish');
    } finally {
      await deleteTemplate(page, templateId);
    }
  });

  test('published template: second autosave overwrites first for same author', async ({
    page,
  }) => {
    const templateId = await createTemplate(
      page,
      `Autosave Multi ${Date.now()}`,
      '<!-- wp:campaignbridge/container -->\n<!-- wp:campaignbridge/section -->\n<!-- /wp:campaignbridge/section -->\n<!-- /wp:campaignbridge/container -->',
      'publish'
    );
    try {
      const autosave1Id = await createAutosave(
        page,
        templateId,
        '<!-- wp:campaignbridge/container -->\n<!-- wp:campaignbridge/section -->\n<!-- wp:campaignbridge/spacer {"height":24} /-->\n<!-- /wp:campaignbridge/section -->\n<!-- /wp:campaignbridge/container -->'
      );
      const autosave2Id = await createAutosave(
        page,
        templateId,
        '<!-- wp:campaignbridge/container -->\n<!-- wp:campaignbridge/section -->\n<!-- wp:campaignbridge/spacer {"height":48} /-->\n<!-- /wp:campaignbridge/section -->\n<!-- /wp:campaignbridge/container -->'
      );

      // Both autosaves have different IDs from the canonical post.
      expect(autosave1Id).not.toBe(templateId);
      expect(autosave2Id).not.toBe(templateId);

      // WordPress keeps one autosave per author: second overwrites the first.
      expect(autosave2Id).toBe(autosave1Id);

      const autosaves = await getAutosaves(page, templateId);
      expect(autosaves.length).toBe(1);
      expect(autosaves[0].id).toBe(autosave1Id);
    } finally {
      await deleteTemplate(page, templateId);
    }
  });

  test('failed autosave returns error and does not create record', async ({
    page,
  }) => {
    const templateId = await createTemplate(
      page,
      `Autosave Failure ${Date.now()}`,
      '<!-- wp:campaignbridge/container -->\n<!-- wp:campaignbridge/section -->\n<!-- /wp:campaignbridge/section -->\n<!-- /wp:campaignbridge/container -->',
      'publish'
    );
    try {
      // Mock wp.apiFetch to reject autosave POST requests.
      await page.evaluate(() => {
        const wp = (
          globalThis as typeof globalThis & { wp: { apiFetch: ApiFetch } }
        ).wp;
        (globalThis as Record<string, unknown>).__originalApiFetch =
          wp.apiFetch;
        wp.apiFetch = (options: {
          path: string;
          method?: string;
          data?: Record<string, unknown>;
        }) => {
          if (
            options.path.includes('/autosaves') &&
            options.method === 'POST'
          ) {
            return Promise.reject(new Error('500 Internal Server Error'));
          }
          return (
            (globalThis as Record<string, unknown>)
              .__originalApiFetch as ApiFetch
          )(options);
        };
      });

      let error = false;
      try {
        await createAutosave(
          page,
          templateId,
          '<!-- wp:campaignbridge/container -->\n<!-- wp:campaignbridge/section -->\n<!-- wp:campaignbridge/spacer {"height":24} /-->\n<!-- /wp:campaignbridge/section -->\n<!-- /wp:campaignbridge/container -->'
        );
      } catch {
        error = true;
      }
      expect(error).toBe(true);

      // Restore the original wp.apiFetch.
      await page.evaluate(() => {
        const wp = (
          globalThis as typeof globalThis & { wp: { apiFetch: ApiFetch } }
        ).wp;
        const original = (globalThis as Record<string, unknown>)
          .__originalApiFetch;
        if (original) {
          wp.apiFetch = original as ApiFetch;
          delete (globalThis as Record<string, unknown>).__originalApiFetch;
        }
      });

      const autosaves = await getAutosaves(page, templateId);
      expect(autosaves.length).toBe(0);

      const canonical = await getTemplate(page, templateId);
      expect(canonical.status).toBe('publish');
      expect(canonical.content.raw).toContain('campaignbridge');
    } finally {
      await deleteTemplate(page, templateId);
    }
  });
});

// ---------------------------------------------------------------------------
// Editor-triggered autosave E2E tests
//
// These tests prove that a real operator edit in the editor canvas makes the
// core-data entity dirty and that useTemplateEditor then triggers native
// WordPress autosave through save({ isAutosave: true }). They complement the
// REST API tests above (which verify server-side semantics) by covering the
// editor-to-server round trip: debounce, request shape, conflict with manual
// Save/Publish, failure recovery, notices, and dirty-state tracking.
// ---------------------------------------------------------------------------

const AUTOSAVE_DELAY_MS = 2000;
// Long enough for a pending debounce to fire and its request to start; used
// only to prove that no further autosave request happens.
const AUTOSAVE_SETTLE_MS = AUTOSAVE_DELAY_MS + 1500;
// Autosave is background recovery, so its failure copy differs from Save.
const SAFE_SAVE_ERROR =
  'Your recovery copy could not be saved. Your changes are still in the editor.';

const TEXT_BLOCK_CONTENT =
  '<!-- wp:campaignbridge/container -->\n' +
  '<!-- wp:campaignbridge/section -->\n' +
  '<!-- wp:campaignbridge/text {"content":"Hello"} /-->\n' +
  '<!-- /wp:campaignbridge/section -->\n' +
  '<!-- /wp:campaignbridge/container -->';

interface EditorEntityState {
  hasEdits: boolean;
  editedContent: string;
  persistedContent: string;
}

// Sites without pretty permalinks (CI) send REST routes URL-encoded in
// `?rest_route=`, so routes are matched against the decoded URL.
function matchesRoute(url: string, route: RegExp): boolean {
  return route.test(decodeURIComponent(url));
}

function autosaveRoute(templateId: number): RegExp {
  return new RegExp(`/wp/v2/cb_templates/${templateId}/autosaves(?:[?&]|$)`);
}

function canonicalRoute(templateId: number): RegExp {
  return new RegExp(`/wp/v2/cb_templates/${templateId}(?:[?&]|$)`);
}

function isAutosavePost(request: Request, templateId: number): boolean {
  return (
    request.method() === 'POST' &&
    matchesRoute(request.url(), autosaveRoute(templateId))
  );
}

// wp.apiFetch sends PUT as POST with an X-HTTP-Method-Override header.
function isCanonicalWrite(request: Request, templateId: number): boolean {
  return (
    ['POST', 'PUT'].includes(request.method()) &&
    matchesRoute(request.url(), canonicalRoute(templateId))
  );
}

function trackAutosavePosts(page: Page, templateId: number): Request[] {
  const requests: Request[] = [];
  page.on('request', request => {
    if (isAutosavePost(request, templateId)) {
      requests.push(request);
    }
  });
  return requests;
}

async function withTemplate(
  page: Page,
  title: string,
  status: 'draft' | 'publish',
  // eslint-disable-next-line no-unused-vars -- Documents the callback contract.
  body: (templateId: number) => Promise<void>
): Promise<void> {
  const templateId = await createTemplate(
    page,
    title,
    TEXT_BLOCK_CONTENT,
    status
  );
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
      await page.unrouteAll({ behavior: 'ignoreErrors' });
      await deleteTemplate(page, templateId);
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

async function getEditorEntityState(
  page: Page,
  templateId: number
): Promise<EditorEntityState> {
  return page.evaluate(id => {
    const wp = (
      globalThis as typeof globalThis & {
        wp: {
          blocks: { serialize: Function };
          data: { select: Function };
        };
      }
    ).wp;
    const core = wp.data.select('core');
    const edited = core.getEditedEntityRecord('postType', 'cb_templates', id);
    const persisted = core.getRawEntityRecord('postType', 'cb_templates', id);
    let editedContent = '';
    if (Array.isArray(edited?.blocks)) {
      editedContent = wp.blocks.serialize(edited.blocks);
    } else if (typeof edited?.content === 'string') {
      editedContent = edited.content;
    }

    return {
      hasEdits: core.hasEditsForEntityRecord('postType', 'cb_templates', id),
      editedContent,
      persistedContent:
        typeof persisted?.content === 'string' ? persisted.content : '',
    };
  }, templateId);
}

async function openEditorForTemplate(
  page: Page,
  templateId: number
): Promise<void> {
  await page.goto(`${EDITOR_PATH}&post_id=${templateId}`);
  await expect(page.locator('.cb-editor__header')).toBeVisible();
  await expect(textBlock(page)).toBeVisible();

  // Opening a template is not an edit.
  expect((await getEditorEntityState(page, templateId)).hasEdits).toBe(false);
}

function textBlock(page: Page) {
  return page
    .frameLocator('iframe[name="editor-canvas"]')
    .locator('[data-type="campaignbridge/text"][contenteditable="true"]')
    .first();
}

/**
 * Replace the text block's content through the rendered RichText field and
 * prove the live core-data entity observed by useTemplateEditor became dirty.
 */
async function editTextBlock(
  page: Page,
  templateId: number,
  text: string
): Promise<void> {
  const block = textBlock(page);
  await block.click();
  await page.keyboard.press('ControlOrMeta+a');
  await page.keyboard.type(text);
  await expect(block).toHaveText(text);

  const state = await getEditorEntityState(page, templateId);
  expect(state.hasEdits).toBe(true);
  expect(state.editedContent).toContain(text);
  expect(state.editedContent).not.toBe(state.persistedContent);
}

function snackbar(page: Page, text: string) {
  return page.locator('.components-snackbar', { hasText: text });
}

test.describe('CampaignBridge Editor Autosave (E2E)', () => {
  test('editor autosave (draft): native autosave keeps draft status and cleans the editor', async ({
    page,
  }) => {
    await withTemplate(
      page,
      `Editor Autosave Draft ${Date.now()}`,
      'draft',
      async templateId => {
        await openEditorForTemplate(page, templateId);
        const autosavePosts = trackAutosavePosts(page, templateId);
        const autosaveResponse = page.waitForResponse(response =>
          isAutosavePost(response.request(), templateId)
        );

        await editTextBlock(page, templateId, 'Autosave draft content');

        expect((await autosaveResponse).status()).toBe(200);

        // WordPress applies a draft author's autosave to the post itself and
        // core-data clears the edits the server now holds.
        await expect
          .poll(
            async () => (await getEditorEntityState(page, templateId)).hasEdits
          )
          .toBe(false);
        await expect(page.locator('.cb-editor__save-button')).toHaveText(
          'Saved'
        );
        await expect(page.locator('.cb-editor__status-badge')).toHaveText(
          'Draft'
        );

        const canonical = await getTemplate(page, templateId);
        expect(canonical.status).toBe('draft');
        expect(canonical.content.raw).toContain('Autosave draft content');

        await page.waitForTimeout(AUTOSAVE_SETTLE_MS);
        expect(autosavePosts).toHaveLength(1);
        // A background autosave is not the operator's Save.
        await expect(snackbar(page, 'Template saved.')).toHaveCount(0);
      }
    );
  });

  test('editor autosave (published): separate autosave, canonical unchanged, editor stays dirty', async ({
    page,
  }) => {
    await withTemplate(
      page,
      `Editor Autosave Published ${Date.now()}`,
      'publish',
      async templateId => {
        await openEditorForTemplate(page, templateId);
        const autosavePosts = trackAutosavePosts(page, templateId);
        const autosaveResponse = page.waitForResponse(response =>
          isAutosavePost(response.request(), templateId)
        );

        await editTextBlock(page, templateId, 'Published autosave content');

        const response = await autosaveResponse;
        expect(response.status()).toBe(200);
        const autosave = (await response.json()) as {
          id: number;
          parent: number;
        };
        expect(autosave.id).not.toBe(templateId);
        expect(autosave.parent).toBe(templateId);

        const canonical = await getTemplate(page, templateId);
        expect(canonical.status).toBe('publish');
        expect(canonical.content.raw).not.toContain(
          'Published autosave content'
        );
        const autosaves = await getAutosaves(page, templateId);
        expect(autosaves.map(record => record.id)).toContain(autosave.id);

        await page.waitForTimeout(AUTOSAVE_SETTLE_MS);

        // The canonical post is unchanged, so WordPress keeps the edits: the
        // operator must still Save to publish them.
        const state = await getEditorEntityState(page, templateId);
        expect(state.hasEdits).toBe(true);
        expect(state.editedContent).toContain('Published autosave content');
        const saveButton = page.locator('.cb-editor__save-button');
        await expect(saveButton).toHaveText('Save');
        await expect(saveButton).toBeEnabled();
        await expect(page.locator('.cb-editor__status-badge')).toHaveText(
          'Published'
        );

        // Remaining dirty must not re-arm autosave without a new edit.
        expect(autosavePosts).toHaveLength(1);
        await expect(snackbar(page, 'Template saved.')).toHaveCount(0);
      }
    );
  });

  test('manual Save before debounce: canonical save wins, no autosave follows', async ({
    page,
  }) => {
    await withTemplate(
      page,
      `Editor Save Conflict ${Date.now()}`,
      'draft',
      async templateId => {
        await openEditorForTemplate(page, templateId);
        const autosavePosts = trackAutosavePosts(page, templateId);

        await editTextBlock(page, templateId, 'Save conflict test');
        expect(autosavePosts).toHaveLength(0);

        const saveResponse = page.waitForResponse(response =>
          isCanonicalWrite(response.request(), templateId)
        );
        const saveButton = page.locator('.cb-editor__save-button');
        await saveButton.click();

        expect((await saveResponse).status()).toBe(200);
        await expect(saveButton).toHaveText('Saved');
        await expect(snackbar(page, 'Template saved.')).toBeVisible();

        await page.waitForTimeout(AUTOSAVE_SETTLE_MS);
        expect(autosavePosts).toHaveLength(0);
        expect((await getEditorEntityState(page, templateId)).hasEdits).toBe(
          false
        );

        const template = await getTemplate(page, templateId);
        expect(template.content.raw).toContain('Save conflict test');
        expect(template.status).toBe('draft');
      }
    );
  });

  test('Publish before debounce: final status is publish, no stale autosave', async ({
    page,
  }) => {
    await withTemplate(
      page,
      `Editor Publish Conflict ${Date.now()}`,
      'draft',
      async templateId => {
        await openEditorForTemplate(page, templateId);
        const autosavePosts = trackAutosavePosts(page, templateId);

        await editTextBlock(page, templateId, 'Publish conflict test');
        expect(autosavePosts).toHaveLength(0);

        const publishResponse = page.waitForResponse(response =>
          isCanonicalWrite(response.request(), templateId)
        );
        await page.locator('.cb-editor__publish-button').click();

        expect((await publishResponse).status()).toBe(200);
        await expect(page.locator('.cb-editor__status-badge')).toHaveText(
          'Published'
        );

        await page.waitForTimeout(AUTOSAVE_SETTLE_MS);
        expect(autosavePosts).toHaveLength(0);
        expect((await getEditorEntityState(page, templateId)).hasEdits).toBe(
          false
        );

        const template = await getTemplate(page, templateId);
        expect(template.status).toBe('publish');
        expect(template.content.raw).toContain('Publish conflict test');
        expect(await getAutosaves(page, templateId)).toHaveLength(0);
      }
    );
  });

  test('autosave failure through editor: safe visible error, no retry loop, edits recoverable', async ({
    page,
  }) => {
    await withTemplate(
      page,
      `Editor Autosave Fail ${Date.now()}`,
      'draft',
      async templateId => {
        await openEditorForTemplate(page, templateId);
        const autosavePosts = trackAutosavePosts(page, templateId);

        await page.route(
          url => matchesRoute(url.href, autosaveRoute(templateId)),
          route =>
            route.request().method() === 'POST'
              ? route.fulfill({
                  status: 500,
                  contentType: 'application/json',
                  body: JSON.stringify({
                    code: 'internal_error',
                    message: 'Simulated server failure',
                  }),
                })
              : route.continue()
        );
        const failedResponse = page.waitForResponse(response =>
          isAutosavePost(response.request(), templateId)
        );

        await editTextBlock(page, templateId, 'Failure test content');

        expect((await failedResponse).status()).toBe(500);
        await expect(snackbar(page, SAFE_SAVE_ERROR)).toBeVisible();
        await expect(
          page.locator('.components-snackbar-list')
        ).not.toContainText('Simulated server failure');
        await expect(snackbar(page, 'Template saved.')).toHaveCount(0);

        // A failed autosave must not hammer the server without a new edit.
        await page.waitForTimeout(AUTOSAVE_SETTLE_MS);
        expect(autosavePosts).toHaveLength(1);

        // The operator's edits survive the failure.
        const state = await getEditorEntityState(page, templateId);
        expect(state.hasEdits).toBe(true);
        expect(state.editedContent).toContain('Failure test content');
        await expect(textBlock(page)).toHaveText('Failure test content');
        expect((await getTemplate(page, templateId)).content.raw).not.toContain(
          'Failure test content'
        );

        // ...and can still be saved once the server recovers.
        await page.unrouteAll({ behavior: 'ignoreErrors' });
        const saveResponse = page.waitForResponse(response =>
          isCanonicalWrite(response.request(), templateId)
        );
        const saveButton = page.locator('.cb-editor__save-button');
        await expect(saveButton).toBeEnabled();
        await saveButton.click();
        expect((await saveResponse).status()).toBe(200);
        await expect(saveButton).toHaveText('Saved');
        expect((await getTemplate(page, templateId)).content.raw).toContain(
          'Failure test content'
        );
      }
    );
  });

  test('dirty state (draft): autosave cleans, a new edit re-dirties, Save persists', async ({
    page,
  }) => {
    await withTemplate(
      page,
      `Editor Dirty State ${Date.now()}`,
      'draft',
      async templateId => {
        await openEditorForTemplate(page, templateId);
        const saveButton = page.locator('.cb-editor__save-button');

        const firstAutosave = page.waitForResponse(response =>
          isAutosavePost(response.request(), templateId)
        );
        await editTextBlock(page, templateId, 'Dirty state first edit');
        await expect(saveButton).toHaveText('Save');
        expect((await firstAutosave).status()).toBe(200);
        await expect(saveButton).toHaveText('Saved');
        await expect(saveButton).toBeDisabled();

        await editTextBlock(page, templateId, 'Dirty state second edit');
        await expect(saveButton).toHaveText('Save');
        await expect(saveButton).toBeEnabled();

        const saveResponse = page.waitForResponse(response =>
          isCanonicalWrite(response.request(), templateId)
        );
        await saveButton.click();
        expect((await saveResponse).status()).toBe(200);
        await expect(saveButton).toHaveText('Saved');

        expect((await getEditorEntityState(page, templateId)).hasEdits).toBe(
          false
        );
        const template = await getTemplate(page, templateId);
        expect(template.content.raw).toContain('Dirty state second edit');
        expect(template.status).toBe('draft');
      }
    );
  });
});
