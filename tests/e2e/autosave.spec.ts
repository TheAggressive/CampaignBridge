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
