import { expect, test, type Page, type Request } from './support/fixtures';
import AxeBuilder from '@axe-core/playwright';
import { dismissEditorWelcomeGuide } from './support/editor';

const NEW_TEMPLATE_PATH = '/wp-admin/post-new.php?post_type=cb_templates';

type ApiFetch = <T>(
  // eslint-disable-next-line no-unused-vars -- Documents the callable WordPress API contract.
  options: {
    path: string;
    method?: string;
    data?: Record<string, unknown>;
  }
) => Promise<T>;

interface EditorSnapshot {
  content: string;
  postId: number;
  subject: unknown;
}

interface RevisionRecord {
  content?: { raw?: string };
  meta?: Record<string, unknown>;
}

function matchesRoute(request: Request, route: string): boolean {
  return decodeURIComponent(request.url()).includes(route);
}

async function waitForNativeEditor(page: Page): Promise<void> {
  await page.waitForFunction(() => {
    const wp = (globalThis as typeof globalThis & { wp?: any }).wp;
    return (
      wp?.data?.select('core/editor')?.getCurrentPostType?.() ===
        'cb_templates' && wp?.blocks?.getBlockType?.('campaignbridge/container')
    );
  });
  await dismissEditorWelcomeGuide(page);
  await expect(page.locator('#campaignbridge-native-editor-js')).toHaveCount(1);
  await expect(
    page.locator('#cb-campaignbridge-block-editor-script-js')
  ).toHaveCount(0);
  await expect(page.locator('#cb-block-editor-root')).toHaveCount(0);
  await expect(
    page
      .frameLocator('iframe[name="editor-canvas"]')
      .locator('[data-type="campaignbridge/container"]')
  ).toBeVisible();
}

async function editorSnapshot(page: Page): Promise<EditorSnapshot> {
  return page.evaluate(() => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const editor = wp.data.select('core/editor');
    const meta = editor.getEditedPostAttribute('meta') ?? {};

    return {
      content: editor.getEditedPostContent(),
      postId: Number(editor.getCurrentPostId()),
      subject: meta.campaignbridge_subject,
    };
  });
}

async function deleteTemplate(page: Page, postId: number): Promise<void> {
  await page.evaluate(async id => {
    const apiFetch = (
      globalThis as typeof globalThis & { wp: { apiFetch: ApiFetch } }
    ).wp.apiFetch;
    await apiFetch({
      path: `/wp/v2/cb_templates/${id}?force=true`,
      method: 'DELETE',
    });
  }, postId);
}

let cleanupPostId = 0;
test.afterEach(async ({ page }) => {
  if (cleanupPostId > 0 && !page.isClosed()) {
    await deleteTemplate(page, cleanupPostId);
  }
  cleanupPostId = 0;
});

test('native editor owns the template lifecycle and previews unsaved blocks', async ({
  page,
}) => {
  test.setTimeout(90_000);
  const title = `Native CampaignBridge ${Date.now()}`;
  const savedText = `Saved native content ${Date.now()}`;
  const unsavedText = `Unsaved preview content ${Date.now()}`;
  const unsavedPreviewTitle = `Unsaved preview title ${Date.now()}`;
  let postId = 0;

  await page.goto(NEW_TEMPLATE_PATH);
  await waitForNativeEditor(page);
  postId = (await editorSnapshot(page)).postId;
  cleanupPostId = postId;
  await expect(
    page.getByRole('button', { name: 'Block Inserter' })
  ).toBeVisible();
  await expect(page.getByRole('button', { name: 'Undo' })).toBeVisible();
  await expect(
    page.getByRole('button', { name: 'Document Overview' })
  ).toBeVisible();

  await page.getByRole('button', { name: 'Document Overview' }).click();
  const listViewTab = page.getByRole('tab', { name: 'List View' });
  await expect(listViewTab).toBeVisible();
  await page
    .locator('.editor-list-view-sidebar')
    .getByRole('button', { name: 'Close' })
    .click({ force: true });
  await expect(listViewTab).toBeHidden();

  const initial = await page.evaluate(() => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const blocks = wp.data.select('core/block-editor').getBlocks();
    return {
      count: blocks.length,
      name: blocks[0]?.name,
      lock: blocks[0]?.attributes?.lock,
    };
  });
  expect(initial).toEqual({
    count: 1,
    name: 'campaignbridge/container',
    lock: { move: false, remove: true },
  });

  const insertion = await page.evaluate(
    ({ nextTitle, nextSubject, text }) => {
      const wp = (globalThis as typeof globalThis & { wp: any }).wp;
      const root = wp.data.select('core/block-editor').getBlocks()[0];
      const section = wp.blocks.createBlock('campaignbridge/section', {}, [
        wp.blocks.createBlock('campaignbridge/text', { content: text }),
      ]);
      const currentMeta =
        wp.data.select('core/editor').getEditedPostAttribute('meta') ?? {};

      wp.data
        .dispatch('core/block-editor')
        .insertBlocks(section, undefined, root.clientId);
      wp.data.dispatch('core/editor').editPost({
        title: nextTitle,
        meta: {
          ...currentMeta,
          campaignbridge_subject: nextSubject,
        },
      });

      return {
        canInsert: wp.data
          .select('core/block-editor')
          .canInsertBlockType('campaignbridge/section', root.clientId),
        childCount: wp.data
          .select('core/block-editor')
          .getBlockOrder(root.clientId).length,
      };
    },
    {
      nextTitle: title,
      nextSubject: 'Native subject',
      text: savedText,
    }
  );
  expect(insertion).toEqual({ canInsert: true, childCount: 1 });

  const saveDraft = page.getByRole('button', { name: 'Save draft' });
  await expect(saveDraft).toBeEnabled();
  await saveDraft.click();
  await page.waitForFunction(() => {
    const editor = (
      globalThis as typeof globalThis & { wp: any }
    ).wp.data.select('core/editor');
    return !editor.isSavingPost() && !editor.isEditedPostDirty();
  });

  let snapshot = await editorSnapshot(page);
  postId = snapshot.postId;
  cleanupPostId = postId;
  expect(postId).toBeGreaterThan(0);
  expect(snapshot.content).toContain(savedText);
  expect(snapshot.subject).toBe('Native subject');
  await expect
    .poll(() => {
      const url = new URL(page.url());
      return {
        path: url.pathname,
        post: url.searchParams.get('post'),
        action: url.searchParams.get('action'),
      };
    })
    .toEqual({
      path: '/wp-admin/post.php',
      post: String(postId),
      action: 'edit',
    });

  await page.reload();
  await waitForNativeEditor(page);
  snapshot = await editorSnapshot(page);
  expect(snapshot.content).toContain(savedText);
  expect(snapshot.subject).toBe('Native subject');
  await expect(page.locator('.block-editor-warning')).toHaveCount(0);
  const templateSettings = page.getByRole('button', {
    name: 'Template Settings',
  });
  if (!(await templateSettings.isVisible())) {
    await page.getByRole('button', { name: 'Settings' }).click();
  }
  await expect(templateSettings).toBeVisible();
  await expect(
    page.getByRole('button', { name: 'Email Settings' })
  ).toBeVisible();
  await expect(
    page.getByRole('button', { name: 'Footer & Compliance' })
  ).toBeVisible();
  const subjectControl = page.getByRole('textbox', { name: 'Subject Line' });
  if (!(await subjectControl.isVisible())) {
    await templateSettings.click();
  }
  await expect(subjectControl).toHaveValue('Native subject');
  await subjectControl.fill('Native panel subject');
  await page.waitForFunction(() =>
    (globalThis as typeof globalThis & { wp: any }).wp.data
      .select('core/editor')
      .isEditedPostDirty()
  );
  await saveDraft.click();
  await page.waitForFunction(() => {
    const editor = (
      globalThis as typeof globalThis & { wp: any }
    ).wp.data.select('core/editor');
    return !editor.isSavingPost() && !editor.isEditedPostDirty();
  });
  await page.reload();
  await waitForNativeEditor(page);
  const reloadedTemplateSettings = page.getByRole('button', {
    name: 'Template Settings',
  });
  if (!(await reloadedTemplateSettings.isVisible())) {
    await page.getByRole('button', { name: 'Settings' }).click();
  }
  const reloadedSubject = page.getByRole('textbox', {
    name: 'Subject Line',
  });
  if (!(await reloadedSubject.isVisible())) {
    await reloadedTemplateSettings.click();
  }
  await expect(reloadedSubject).toHaveValue('Native panel subject');

  const interactions = await page.evaluate(text => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const root = wp.data.select('core/block-editor').getBlocks()[0];
    const emailButton = wp.blocks.createBlock('campaignbridge/button', {
      label: 'Native action',
      url: 'https://example.com/',
    });
    const section = wp.blocks.createBlock('campaignbridge/section', {}, [
      wp.blocks.createBlock('campaignbridge/text', { content: text }),
      emailButton,
    ]);
    const postButton = wp.blocks.createBlock('campaignbridge/post-button', {
      label: 'Native action',
    });
    const secondSection = wp.blocks.createBlock('campaignbridge/section');
    wp.data
      .dispatch('core/block-editor')
      .insertBlocks([section, secondSection], undefined, root.clientId);

    const transformed = wp.blocks.switchToBlockType(
      postButton,
      'campaignbridge/post-link'
    )[0];
    wp.data
      .dispatch('core/block-editor')
      .moveBlocksToPosition(
        [secondSection.clientId],
        root.clientId,
        root.clientId,
        0
      );
    wp.data.dispatch('core/block-editor').selectBlock(emailButton.clientId);

    return {
      selectedClientId: emailButton.clientId,
      transformedName: transformed.name,
      firstChild: wp.data
        .select('core/block-editor')
        .getBlockOrder(root.clientId)[0],
      movedClientId: secondSection.clientId,
    };
  }, unsavedText);
  expect(interactions).toEqual(
    expect.objectContaining({
      transformedName: 'campaignbridge/post-link',
      firstChild: interactions.movedClientId,
    })
  );
  await expect(
    page
      .frameLocator('iframe[name="editor-canvas"]')
      .locator(`[data-block="${interactions.selectedClientId}"]`)
  ).toHaveClass(/is-selected/);
  await expect(page.locator('.block-editor-block-toolbar')).toBeVisible();
  await expect(page.getByText('Email button', { exact: true })).toBeVisible();
  await expect(page.getByRole('textbox', { name: 'Label' })).toHaveValue(
    'Native action'
  );

  await page.evaluate(clientId => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    wp.data
      .dispatch('core/block-editor')
      .updateBlockAttributes(clientId, { label: 'Undo marker' });
  }, interactions.selectedClientId);
  const labelControl = page.getByRole('textbox', { name: 'Label' });
  await expect(labelControl).toHaveValue('Undo marker');

  const undo = page.getByRole('button', { name: 'Undo' });
  await expect(undo).toBeEnabled();
  await undo.click();
  await expect(labelControl).toHaveValue('Native action');
  const redo = page.getByRole('button', { name: 'Redo' });
  await expect(redo).toBeEnabled();
  await redo.click();
  await expect(labelControl).toHaveValue('Undo marker');

  await page.evaluate(nextTitle => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    wp.data.dispatch('core/editor').editPost({ title: nextTitle });
  }, unsavedPreviewTitle);

  const previewButton = page.getByRole('button', { name: /^(Preview|View)$/ });
  await previewButton.click();
  const emailPreview = page.getByRole('menuitem', { name: 'Email Preview' });
  await expect(emailPreview).toBeVisible();
  const previewRequest = page.waitForRequest(request =>
    matchesRoute(request, '/campaignbridge/v1/preview')
  );
  await emailPreview.click();
  const request = await previewRequest;
  expect(request.postDataJSON()).toEqual(
    expect.objectContaining({
      template_id: postId,
      content: expect.stringContaining(unsavedText),
      metadata: { title: unsavedPreviewTitle },
    })
  );
  await expect(
    page.getByRole('dialog', { name: 'Email Preview' })
  ).toBeVisible();
  await expect(page.locator('.cb-editor__preview-status')).toContainText(
    'Preview up to date'
  );
  await expect(
    page
      .frameLocator('iframe[title="Email preview"]')
      .getByText(unsavedText, { exact: true })
  ).toBeVisible();
  await expect
    .poll(() =>
      page
        .frameLocator('iframe[title="Email preview"]')
        .locator('html')
        .evaluate(() => globalThis.document.title)
    )
    .toBe(unsavedPreviewTitle);

  const accessibility = await new AxeBuilder({ page })
    .include('.cb-editor__preview-modal-frame')
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  expect(
    accessibility.violations.filter(violation =>
      ['critical', 'serious'].includes(violation.impact ?? '')
    )
  ).toHaveLength(0);

  await page.getByRole('button', { name: 'Close' }).click();
  await page.evaluate(() => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const root = wp.data.select('core/block-editor').getBlocks()[0];
    const section = wp.blocks.createBlock('campaignbridge/section', {}, [
      wp.blocks.createBlock('campaignbridge/text', {
        content: 'Native autosave content',
      }),
    ]);
    wp.data
      .dispatch('core/block-editor')
      .insertBlocks(section, undefined, root.clientId);
  });
  const autosaveResponse = page.waitForResponse(
    response =>
      response.request().method() === 'POST' &&
      matchesRoute(
        response.request(),
        `/wp/v2/cb_templates/${postId}/autosaves`
      )
  );
  await page.evaluate(async () => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    await wp.data.dispatch('core/editor').autosave();
  });
  expect((await autosaveResponse).status()).toBe(200);
  await page.waitForFunction(() => {
    const editor = (
      globalThis as typeof globalThis & { wp: any }
    ).wp.data.select('core/editor');
    return !editor.isAutosavingPost();
  });

  await page.evaluate(() => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const currentMeta =
      wp.data.select('core/editor').getEditedPostAttribute('meta') ?? {};
    wp.data.dispatch('core/editor').editPost({
      meta: {
        ...currentMeta,
        campaignbridge_subject: 'Native revision subject',
      },
    });
  });
  await saveDraft.click();
  await page.waitForFunction(() => {
    const editor = (
      globalThis as typeof globalThis & { wp: any }
    ).wp.data.select('core/editor');
    return !editor.isSavingPost() && !editor.isEditedPostDirty();
  });
  const revisions = await page.evaluate(async id => {
    const apiFetch = (
      globalThis as typeof globalThis & { wp: { apiFetch: ApiFetch } }
    ).wp.apiFetch;
    return apiFetch<RevisionRecord[]>({
      path: `/wp/v2/cb_templates/${id}/revisions?context=edit&per_page=100`,
    });
  }, postId);
  expect(revisions.length).toBeGreaterThan(0);
  expect(revisions[0]?.content?.raw).toContain('Native autosave content');
  expect(revisions[0]?.meta?.campaignbridge_subject).toBe(
    'Native revision subject'
  );
});
