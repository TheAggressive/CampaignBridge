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

interface RevisionRecord {
  id: number;
  content: { raw: string };
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

async function currentContent(page: Page, templateId: number): Promise<string> {
  const template = await apiFetch<{ content: { raw: string } }>(page, {
    path: `/wp/v2/cb_templates/${templateId}?context=edit`,
  });
  return template.content.raw;
}

// `?rest_route=` works with and without pretty permalinks.
function restoreUrl(templateId: number, revisionId: number): string {
  return `/?rest_route=${encodeURIComponent(
    `/campaignbridge/v1/templates/${templateId}/revisions/${revisionId}/restore`
  )}`;
}

test('restore relies on WordPress REST cookie authentication and its nonce check', async ({
  page,
}) => {
  await page.goto(EDITOR_PATH);
  await expect(page.locator('.cb-editor__header')).toBeVisible();
  const templateId = (
    await apiFetch<{ id: number }>(page, {
      path: '/wp/v2/cb_templates',
      method: 'POST',
      data: {
        title: `Restore Auth ${Date.now()}`,
        status: 'publish',
        content: 'Initial',
      },
    })
  ).id;

  let primaryError: unknown = null;
  try {
    // Each normal save creates a WordPress revision of the saved state.
    for (const content of ['Version A', 'Version B']) {
      await apiFetch(page, {
        path: `/wp/v2/cb_templates/${templateId}`,
        method: 'PUT',
        data: { content },
      });
    }
    const revisions = await apiFetch<RevisionRecord[]>(page, {
      path: `/wp/v2/cb_templates/${templateId}/revisions?context=edit`,
    });
    const revisionA = revisions.find(
      revision => revision.content.raw === 'Version A'
    );
    expect(revisionA).toBeDefined();
    const url = restoreUrl(templateId, revisionA!.id);

    // A logged-in browser cookie without the REST nonce is treated by
    // WordPress as unauthenticated, which blocks cross-site requests.
    const withoutNonce = await page.request.post(url);
    expect(withoutNonce.status()).toBe(401);
    expect((await withoutNonce.json()).code).toBe('unauthenticated');

    // An invalid nonce is refused by WordPress before the route runs.
    const invalidNonce = await page.request.post(url, {
      headers: { 'X-WP-Nonce': 'invalid-nonce' },
    });
    expect(invalidNonce.status()).toBe(403);
    expect((await invalidNonce.json()).code).toBe('rest_cookie_invalid_nonce');

    expect(await currentContent(page, templateId)).toBe('Version B');

    // The editor's normal nonce-authenticated request restores the revision.
    const restored = await apiFetch<{ success: boolean }>(page, {
      path: `/campaignbridge/v1/templates/${templateId}/revisions/${revisionA!.id}/restore`,
      method: 'POST',
    });
    expect(restored.success).toBe(true);
    expect(await currentContent(page, templateId)).toBe('Version A');
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
