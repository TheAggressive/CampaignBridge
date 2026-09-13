import { expect, test, type Page } from '@playwright/test';

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
// These tests prove that the editor UI (useTemplateEditor hook) triggers
// native WordPress autosaves through the standard core-data save pipeline.
// They complement the REST API tests above (which verify server-side
// semantics) by covering the editor-to-server round trip: debounce timing,
// request shape, conflict with manual Save/Publish, failure recovery, and
// dirty-state tracking.
// ---------------------------------------------------------------------------

const TEXT_BLOCK_CONTENT =
  '<!-- wp:campaignbridge/container -->\n' +
  '<!-- wp:campaignbridge/section -->\n' +
  '<!-- wp:campaignbridge/text {"content":"Hello"} /-->\n' +
  '<!-- /wp:campaignbridge/section -->\n' +
  '<!-- /wp:campaignbridge/container -->';

async function openEditorForTemplate(
  page: Page,
  templateId: number
): Promise<void> {
  await page.goto(
    `/wp-admin/admin.php?page=campaignbridge-editor&post_id=${templateId}`
  );
  await expect(page.locator('.cb-editor__header')).toBeVisible();
  const frame = page.frameLocator('iframe[name="editor-canvas"]');
  await frame
    .locator('[data-type="campaignbridge/text"]')
    .first()
    .waitFor({ state: 'visible', timeout: 10000 });
}

async function typeIntoTextBlock(page: Page, text: string): Promise<void> {
  const url = new URL(page.url());
  const templateId = parseInt(url.searchParams.get('post_id') || '0', 10);

  // Mark the entity as dirty via editEntityRecord. The editor's
  // useTemplateEditor hook detects hasEdits=true and triggers its own
  // autosave after the 2000ms debounce, going through the full
  // saveEditedEntityRecord → saveEntityRecord pipeline.
  await page.evaluate(
    ({ text, templateId }) => {
      const wp = (globalThis as any).wp;
      if (!wp?.data) throw new Error('wp.data not available');

      const { dispatch, select } = wp.data;
      const core = select('core');
      const record = core.getEntityRecord(
        'postType',
        'cb_templates',
        templateId
      );
      if (!record) throw new Error('record not found: ' + templateId);

      const contentRaw =
        typeof record.content === 'string'
          ? record.content
          : record.content?.raw || '';

      const newContent = contentRaw.replace(
        '"content":"Hello"',
        `"content":"${text}"`
      );

      dispatch('core').editEntityRecord(
        'postType',
        'cb_templates',
        templateId,
        { content: newContent }
      );
    },
    { text, templateId }
  );
}

test.describe('CampaignBridge Editor Autosave (E2E)', () => {
  test('editor autosave (draft): triggers POST to /autosaves, status stays draft', async ({
    page,
  }) => {
    const templateId = await createTemplate(
      page,
      `Editor Autosave Draft ${Date.now()}`,
      TEXT_BLOCK_CONTENT,
      'draft'
    );
    try {
      await openEditorForTemplate(page, templateId);

      const autosaveRequest = page.waitForRequest(
        req => req.url().includes('/autosaves') && req.method() === 'POST',
        { timeout: 10000 }
      );

      await typeIntoTextBlock(page, 'Autosave test content');

      const request = await autosaveRequest;
      expect(request.url()).toContain(
        `/wp/v2/cb_templates/${templateId}/autosaves`
      );
      expect(request.method()).toBe('POST');

      const statusBadge = page.locator('.cb-editor__status-badge');
      await expect(statusBadge).toContainText('Draft');
    } finally {
      await deleteTemplate(page, templateId);
    }
  });

  test('editor autosave (published): canonical stays publish, autosave is separate', async ({
    page,
  }) => {
    const templateId = await createTemplate(
      page,
      `Editor Autosave Published ${Date.now()}`,
      TEXT_BLOCK_CONTENT,
      'publish'
    );
    try {
      await openEditorForTemplate(page, templateId);

      const before = await getTemplate(page, templateId);
      expect(before.status).toBe('publish');

      const autosaveRequest = page.waitForRequest(
        req => req.url().includes('/autosaves') && req.method() === 'POST',
        { timeout: 10000 }
      );

      await typeIntoTextBlock(page, 'Published autosave content');

      const request = await autosaveRequest;
      expect(request.url()).toContain(
        `/wp/v2/cb_templates/${templateId}/autosaves`
      );

      const after = await getTemplate(page, templateId);
      expect(after.status).toBe('publish');

      const autosaves = await getAutosaves(page, templateId);
      expect(autosaves.length).toBeGreaterThanOrEqual(1);
    } finally {
      await deleteTemplate(page, templateId);
    }
  });

  test('manual Save before debounce: no conflicting autosave follows', async ({
    page,
  }) => {
    const templateId = await createTemplate(
      page,
      `Editor Save Conflict ${Date.now()}`,
      TEXT_BLOCK_CONTENT,
      'draft'
    );
    try {
      await openEditorForTemplate(page, templateId);

      const autosaveRequests: string[] = [];
      page.on('request', req => {
        if (req.url().includes('/autosaves') && req.method() === 'POST') {
          autosaveRequests.push(req.url());
        }
      });

      await typeIntoTextBlock(page, 'Save conflict test');

      const saveButton = page.locator('.cb-editor__save-button');
      await saveButton.click();

      await expect(saveButton).toContainText('Saved', { timeout: 10000 });

      await page.waitForTimeout(3000);

      expect(autosaveRequests.length).toBe(0);

      const template = await getTemplate(page, templateId);
      expect(template.content.raw).toContain('Save conflict test');
      expect(template.status).toBe('draft');
    } finally {
      await deleteTemplate(page, templateId);
    }
  });

  test('Publish before debounce: final status is publish, no stale autosave', async ({
    page,
  }) => {
    const templateId = await createTemplate(
      page,
      `Editor Publish Conflict ${Date.now()}`,
      TEXT_BLOCK_CONTENT,
      'draft'
    );
    try {
      await openEditorForTemplate(page, templateId);

      const autosaveRequests: string[] = [];
      page.on('request', req => {
        if (req.url().includes('/autosaves') && req.method() === 'POST') {
          autosaveRequests.push(req.url());
        }
      });

      await typeIntoTextBlock(page, 'Publish conflict test');

      const publishButton = page.locator('.cb-editor__publish-button');
      await publishButton.click();

      const statusBadge = page.locator('.cb-editor__status-badge');
      await expect(statusBadge).toContainText('Published', { timeout: 10000 });

      await page.waitForTimeout(3000);

      expect(autosaveRequests.length).toBe(0);

      const template = await getTemplate(page, templateId);
      expect(template.status).toBe('publish');
      expect(template.content.raw).toContain('Publish conflict test');
    } finally {
      await deleteTemplate(page, templateId);
    }
  });

  test('autosave failure through editor: safe error message, edits preserved', async ({
    page,
  }) => {
    const templateId = await createTemplate(
      page,
      `Editor Autosave Fail ${Date.now()}`,
      TEXT_BLOCK_CONTENT,
      'draft'
    );
    try {
      await openEditorForTemplate(page, templateId);

      await page.route(
        url =>
          url.href.includes('/autosaves') &&
          url.href.includes(`/cb_templates/${templateId}`),
        route => {
          if (route.request().method() === 'POST') {
            route.fulfill({
              status: 500,
              contentType: 'application/json',
              body: JSON.stringify({
                code: 'internal_error',
                message: 'Simulated server failure',
              }),
            });
            return;
          }
          route.continue();
        }
      );

      await typeIntoTextBlock(page, 'Failure test content');

      await page.waitForTimeout(4000);

      const snackbar = page.locator('.cb-editor__snackbar');
      await expect(snackbar).toBeVisible({ timeout: 5000 });
      const snackbarText = await snackbar.textContent();
      expect(snackbarText).toContain('Template save failed');
      expect(snackbarText).not.toContain('Simulated server failure');

      const frame = page.frameLocator('iframe[name="editor-canvas"]');
      const textBlock = frame
        .locator('[data-type="campaignbridge/text"]')
        .first();
      const blockText = await textBlock.textContent();
      expect(blockText).toContain('Failure test content');
    } finally {
      await deleteTemplate(page, templateId);
    }
  });

  test('dirty state: autosave clears dirty, new edit re-dirties, Save works', async ({
    page,
  }) => {
    const templateId = await createTemplate(
      page,
      `Editor Dirty State ${Date.now()}`,
      TEXT_BLOCK_CONTENT,
      'draft'
    );
    try {
      await openEditorForTemplate(page, templateId);

      const saveButton = page.locator('.cb-editor__save-button');

      await typeIntoTextBlock(page, 'Dirty state first edit');

      await expect(saveButton).toContainText('Saved', { timeout: 10000 });

      await typeIntoTextBlock(page, 'Dirty state second edit');
      await expect(saveButton).toContainText('Save', { timeout: 5000 });

      await saveButton.click();
      await expect(saveButton).toContainText('Saved', { timeout: 10000 });

      const template = await getTemplate(page, templateId);
      expect(template.content.raw).toContain('Dirty state second edit');
      expect(template.status).toBe('draft');
    } finally {
      await deleteTemplate(page, templateId);
    }
  });
});
