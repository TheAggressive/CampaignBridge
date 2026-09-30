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

test('native font presets update the editor canvas and reset to inheritance', async ({
  page,
}) => {
  await page.goto(NEW_TEMPLATE_PATH);
  await waitForNativeEditor(page);
  cleanupPostId = (await editorSnapshot(page)).postId;

  const font = await page.evaluate(() => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const design = (
      globalThis as typeof globalThis & {
        campaignbridgeEditorDesign?: {
          fontAssets: Record<string, string>;
          defaultFonts: string[];
        };
      }
    ).campaignbridgeEditorDesign;
    if (!design)
      throw new Error('CampaignBridge editor design is unavailable.');

    const root = wp.data.select('core/block-editor').getBlocks()[0];
    const defaults = new Set(design.defaultFonts);
    const presets = wp.hooks.applyFilters(
      'blockEditor.useSetting.before',
      undefined,
      'typography.fontFamilies.theme',
      undefined,
      'core/heading'
    );
    const palette = wp.hooks.applyFilters(
      'blockEditor.useSetting.before',
      undefined,
      'color.palette.theme',
      undefined,
      'core/heading'
    );
    const paletteSlugs = palette.map(
      (candidate: { slug: string }) => candidate.slug
    );
    const expectedPalette = [
      'text',
      'secondary',
      'background',
      'card',
      'border',
      'brand',
      'on-brand',
    ];
    if (JSON.stringify(paletteSlugs) !== JSON.stringify(expectedPalette)) {
      throw new Error(
        `Site palette leaked into email authoring: ${JSON.stringify(paletteSlugs)}`
      );
    }
    if (
      wp.hooks.applyFilters(
        'blockEditor.useSetting.before',
        undefined,
        'color.custom',
        undefined,
        'core/heading'
      ) !== false
    ) {
      throw new Error('Custom email colors must remain disabled.');
    }
    const preset = presets.find(
      (candidate: { slug: string }) =>
        design.fontAssets[candidate.slug] && !defaults.has(candidate.slug)
    );
    if (!preset) {
      throw new Error(
        `No non-default web font preset is available: ${JSON.stringify({
          assets: design.fontAssets,
          defaults: Array.from(defaults),
          presetSlugs: presets.map(
            (candidate: { slug: string }) => candidate.slug
          ),
        })}`
      );
    }

    const heading = wp.blocks.createBlock('core/heading', {
      content: 'Typography preview',
    });
    const section = wp.blocks.createBlock('campaignbridge/section', {}, [
      heading,
    ]);
    wp.data
      .dispatch('core/block-editor')
      .insertBlocks(section, undefined, root.clientId);
    return {
      clientId: heading.clientId,
      family: preset.fontFamily.split(',')[0].replaceAll('"', ''),
      slug: preset.slug,
      url: design.fontAssets[preset.slug],
    };
  });

  const canvas = page.frameLocator('iframe[name="editor-canvas"]');
  const heading = canvas.locator(`[data-block="${font.clientId}"]`);
  const inheritedFamily = await heading.evaluate(
    element =>
      element.ownerDocument.defaultView?.getComputedStyle(element).fontFamily ??
      ''
  );

  await page.evaluate(({ clientId, slug }) => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    wp.data
      .dispatch('core/block-editor')
      .updateBlockAttributes(clientId, { fontFamily: slug });
  }, font);

  await expect(heading).toHaveCSS('font-family', new RegExp(font.family));
  await expect
    .poll(() =>
      canvas
        .locator('head link[data-campaignbridge-editor-font]')
        .evaluateAll(
          (links, url) =>
            links.filter(
              link =>
                (link as HTMLLinkElement).dataset.campaignbridgeEditorFont ===
                url
            ).length,
          font.url
        )
    )
    .toBe(1);

  const explicit = await editorSnapshot(page);
  expect(explicit.content).toContain(`"fontFamily":"${font.slug}"`);

  await page.evaluate(clientId => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    wp.data
      .dispatch('core/block-editor')
      .updateBlockAttributes(clientId, { fontFamily: undefined });
  }, font.clientId);

  await expect(heading).toHaveCSS('font-family', inheritedFamily);
  await expect
    .poll(() =>
      canvas
        .locator('head link[data-campaignbridge-editor-font]')
        .evaluateAll(
          (links, url) =>
            links.filter(
              link =>
                (link as HTMLLinkElement).dataset.campaignbridgeEditorFont ===
                url
            ).length,
          font.url
        )
    )
    .toBe(0);
  const inherited = await editorSnapshot(page);
  expect(inherited.content).not.toContain('"fontFamily"');
});

const DESIGN_FONT_META_KEY = 'campaignbridge_template_design_fonts';

interface AppliedGoogleFont {
  slug: string;
  registry: { fonts: Array<{ name: string; slug: string; url: string }> };
}

function editorFontLinkLoaded(page: Page, family: string): Promise<boolean> {
  return page
    .frameLocator('iframe[name="editor-canvas"]')
    .locator('head link[data-campaignbridge-editor-font]')
    .evaluateAll(
      (links, needle) =>
        links.some(link =>
          (
            (link as HTMLLinkElement).dataset.campaignbridgeEditorFont ?? ''
          ).includes(needle)
        ),
      `family=${family.replaceAll(' ', '+')}`
    );
}

/** Insert one section with the given blocks and select the target block. */
async function insertSectionAndSelect(
  page: Page,
  blocks: Array<
    [string, Record<string, unknown>, Array<[string, Record<string, unknown>]>?]
  >,
  selectPath: number[]
): Promise<string> {
  return page.evaluate(
    ({ specs, path }) => {
      const wp = (globalThis as typeof globalThis & { wp: any }).wp;
      const build = ([name, attributes, inner = []]: any): any =>
        wp.blocks.createBlock(name, attributes, inner.map(build));
      const children = specs.map(build);
      const root = wp.data.select('core/block-editor').getBlocks()[0];
      const section = wp.blocks.createBlock(
        'campaignbridge/section',
        {},
        children
      );
      wp.data
        .dispatch('core/block-editor')
        .insertBlocks(section, undefined, root.clientId);
      const target = path.reduce(
        (block: any, index: number) => block.innerBlocks[index],
        section
      );
      wp.data.dispatch('core/block-editor').selectBlock(target.clientId);
      return target.clientId as string;
    },
    { specs: blocks, path: selectPath }
  );
}

/** Search, resolve, and apply a Google Font through the block Styles panel. */
async function applyGoogleFontToSelectedBlock(
  page: Page,
  clientId: string,
  family: string
): Promise<AppliedGoogleFont> {
  const fontSearch = page.getByRole('textbox', {
    name: 'Find a Google Font for this block',
  });
  const fontPanel = page.getByRole('button', {
    name: 'Google Font',
    exact: true,
  });
  if (!(await fontSearch.isVisible()) && !(await fontPanel.isVisible())) {
    await page.getByRole('button', { name: 'Settings', exact: true }).click();
  }
  const stylesTab = page.getByRole('tab', { name: 'Styles', exact: true });
  if (await stylesTab.isVisible()) {
    await stylesTab.click();
  }
  if (!(await fontSearch.isVisible())) {
    await expect(fontPanel).toBeVisible();
    await fontPanel.click();
  }
  await expect(fontSearch).toBeVisible();

  await fontSearch.fill(family);
  const searchResponse = page.waitForResponse(
    response =>
      response.request().method() === 'GET' &&
      matchesRoute(response.request(), '/campaignbridge/v1/design-fonts')
  );
  await page.getByRole('button', { name: 'Search fonts' }).click();
  expect((await searchResponse).ok()).toBe(true);

  const resolveResponse = page.waitForResponse(
    response =>
      response.request().method() === 'POST' &&
      matchesRoute(response.request(), '/campaignbridge/v1/design-fonts')
  );
  await page.getByRole('button', { name: `Add and use ${family}` }).click();
  expect((await resolveResponse).ok()).toBe(true);

  await page.waitForFunction(
    ({ id, metaKey, name }) => {
      const wp = (globalThis as typeof globalThis & { wp: any }).wp;
      const block = wp.data.select('core/block-editor').getBlock(id);
      const meta =
        wp.data.select('core/editor').getEditedPostAttribute('meta') ?? {};
      const raw = meta[metaKey];
      return (
        typeof block?.attributes?.fontFamily === 'string' &&
        block.attributes.fontFamily.startsWith('custom-') &&
        typeof raw === 'string' &&
        raw.includes(name)
      );
    },
    { id: clientId, metaKey: DESIGN_FONT_META_KEY, name: family }
  );

  return page.evaluate(
    ({ id, metaKey }) => {
      const wp = (globalThis as typeof globalThis & { wp: any }).wp;
      const block = wp.data.select('core/block-editor').getBlock(id);
      const raw =
        wp.data.select('core/editor').getEditedPostAttribute('meta')?.[
          metaKey
        ] ?? '';
      return {
        slug: block.attributes.fontFamily as string,
        registry: JSON.parse(raw),
      };
    },
    { id: clientId, metaKey: DESIGN_FONT_META_KEY }
  );
}

/** Open Email Preview and return the unsaved request body it compiled. */
async function openEmailPreview(page: Page): Promise<Record<string, unknown>> {
  await page.getByRole('button', { name: /^(Preview|View)$/ }).click();
  const previewRequest = page.waitForRequest(request =>
    matchesRoute(request, '/campaignbridge/v1/preview')
  );
  await page.getByRole('menuitem', { name: 'Email Preview' }).click();
  const body = (await previewRequest).postDataJSON() as Record<string, unknown>;
  await expect(page.locator('.cb-editor__preview-status')).toContainText(
    'Preview up to date'
  );
  return body;
}

test('searches and applies a Google Font directly to one block', async ({
  page,
}) => {
  test.setTimeout(90_000);
  await page.goto(NEW_TEMPLATE_PATH);
  await waitForNativeEditor(page);
  cleanupPostId = (await editorSnapshot(page)).postId;

  const headingId = await insertSectionAndSelect(
    page,
    [['core/heading', { content: 'Direct Google Font' }]],
    [0]
  );
  const applied = await applyGoogleFontToSelectedBlock(
    page,
    headingId,
    'Aguafina Script'
  );
  expect(applied.registry.fonts).toEqual([
    expect.objectContaining({
      name: 'Aguafina Script',
      slug: applied.slug,
      url: expect.stringContaining('family=Aguafina+Script'),
    }),
  ]);

  const heading = page
    .frameLocator('iframe[name="editor-canvas"]')
    .locator(`[data-block="${headingId}"]`);
  await expect(heading).toHaveCSS('font-family', /Aguafina Script/);
  await expect
    .poll(() => editorFontLinkLoaded(page, 'Aguafina Script'))
    .toBe(true);

  const request = await openEmailPreview(page);
  expect(request).toEqual(
    expect.objectContaining({
      content: expect.stringContaining(`"fontFamily":"${applied.slug}"`),
      design_fonts: expect.stringContaining('Aguafina Script'),
    })
  );
  await expect(
    page.frameLocator('iframe[title="Email preview"]').getByRole('heading', {
      name: 'Direct Google Font',
    })
  ).toHaveCSS('font-family', /Aguafina Script/);
});

test('applies a per-block Google Font to the Core Button link', async ({
  page,
}) => {
  test.setTimeout(90_000);
  await page.goto(NEW_TEMPLATE_PATH);
  await waitForNativeEditor(page);
  cleanupPostId = (await editorSnapshot(page)).postId;

  const buttonId = await insertSectionAndSelect(
    page,
    [
      ['core/paragraph', { content: 'Unchanged paragraph' }],
      [
        'core/buttons',
        {},
        [
          [
            'core/button',
            { text: 'Direct Google Button', url: 'https://example.com/offer' },
          ],
        ],
      ],
    ],
    [1, 0]
  );
  expect(await editorFontLinkLoaded(page, 'Aguafina Script')).toBe(false);

  const applied = await applyGoogleFontToSelectedBlock(
    page,
    buttonId,
    'Aguafina Script'
  );
  expect(applied.slug).toMatch(/^custom-[a-f0-9]{12}$/);
  expect(applied.registry.fonts).toEqual([
    expect.objectContaining({
      name: 'Aguafina Script',
      slug: applied.slug,
      url: expect.stringContaining('family=Aguafina+Script'),
    }),
  ]);

  const serialized = (await editorSnapshot(page)).content;
  expect(serialized).toContain(
    `<!-- wp:button {"fontFamily":"${applied.slug}"`
  );
  expect(serialized).not.toContain('Aguafina Script');

  const canvas = page.frameLocator('iframe[name="editor-canvas"]');
  await expect(
    canvas.locator(`[data-block="${buttonId}"] .wp-block-button__link`)
  ).toHaveCSS('font-family', /Aguafina Script/);
  await expect(
    canvas.locator('[data-type="core/paragraph"]', {
      hasText: 'Unchanged paragraph',
    })
  ).not.toHaveCSS('font-family', /Aguafina Script/);
  await expect
    .poll(() => editorFontLinkLoaded(page, 'Aguafina Script'))
    .toBe(true);

  const request = await openEmailPreview(page);
  expect(request).toEqual(
    expect.objectContaining({
      content: expect.stringContaining(
        `<!-- wp:button {"fontFamily":"${applied.slug}"`
      ),
      design_fonts: expect.stringContaining('Aguafina Script'),
    })
  );

  const preview = page.frameLocator('iframe[title="Email preview"]');
  await expect(
    preview.getByRole('link', { name: 'Direct Google Button' })
  ).toHaveCSS('font-family', /Aguafina Script/);
  await expect(
    preview.locator(
      'head link[rel="stylesheet"][href*="family=Aguafina+Script"]'
    )
  ).toHaveCount(1);
  await expect(preview.getByText('Unchanged paragraph')).not.toHaveCSS(
    'font-family',
    /Aguafina Script/
  );
});

test('saved per-block Google Fonts are styled by server preset rules after reload', async ({
  page,
}) => {
  test.setTimeout(90_000);
  await page.goto(NEW_TEMPLATE_PATH);
  await waitForNativeEditor(page);
  cleanupPostId = (await editorSnapshot(page)).postId;

  const headingId = await insertSectionAndSelect(
    page,
    [
      ['core/heading', { content: 'Saved Google Heading' }],
      [
        'core/buttons',
        {},
        [
          [
            'core/button',
            { text: 'Saved Google Button', url: 'https://example.com/offer' },
          ],
        ],
      ],
    ],
    [0]
  );
  const applied = await applyGoogleFontToSelectedBlock(
    page,
    headingId,
    'Aguafina Script'
  );
  await page.evaluate(slug => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const editor = wp.data.select('core/block-editor');
    const button = editor
      .getBlocks()
      .flatMap(function flatten(block: any): any[] {
        return [block, ...block.innerBlocks.flatMap(flatten)];
      })
      .find((block: any) => block.name === 'core/button');
    wp.data
      .dispatch('core/block-editor')
      .updateBlockAttributes(button.clientId, { fontFamily: slug });
  }, applied.slug);

  await page.getByRole('button', { name: 'Save draft' }).click();
  await page.waitForFunction(() => {
    const editor = (
      globalThis as typeof globalThis & { wp: any }
    ).wp.data.select('core/editor');
    return !editor.isSavingPost() && !editor.isEditedPostDirty();
  });
  await page.reload();
  await waitForNativeEditor(page);

  const canvas = page.frameLocator('iframe[name="editor-canvas"]');
  const targets = [
    canvas.locator('[data-type="core/heading"]', {
      hasText: 'Saved Google Heading',
    }),
    canvas.locator('[data-type="core/button"] .wp-block-button__link', {
      hasText: 'Saved Google Button',
    }),
  ];
  for (const target of targets) {
    await expect(target).toHaveCSS('font-family', /Aguafina Script/);
    // A server-emitted preset rule (not the client type-font stylesheet)
    // must match the class Core wrote on the block.
    const serverRules = await target.evaluate(element => {
      const matches: string[] = [];
      for (const sheet of Array.from(element.ownerDocument.styleSheets)) {
        const owner = sheet.ownerNode as Element | null;
        if (owner?.hasAttribute('data-campaignbridge-editor-type-fonts')) {
          continue;
        }
        let rules: CSSRule[] = [];
        try {
          rules = Array.from((sheet as CSSStyleSheet).cssRules);
        } catch {
          continue;
        }
        for (const rule of rules) {
          const style = (rule as CSSStyleRule).style;
          const selector = (rule as CSSStyleRule).selectorText;
          if (
            selector?.includes('-font-family') &&
            style?.getPropertyPriority('font-family') === 'important' &&
            element.matches(selector)
          ) {
            matches.push(selector);
          }
        }
      }
      return matches;
    });
    expect(serverRules).toEqual([
      expect.stringMatching(
        /^\.editor-styles-wrapper \.has-custom-[a-z0-9-]+-font-family$/
      ),
    ]);
  }
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

  // Start closed so the toggle below always opens the sidebar.
  await page.evaluate(() => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    wp.data.dispatch('core/editor').setIsListViewOpened(false);
  });
  await expect(page.locator('.editor-list-view-sidebar')).toHaveCount(0);

  await page.getByRole('button', { name: 'Document Overview' }).click();
  const listViewTab = page.getByRole('tab', { name: 'List View' });
  await expect(listViewTab).toBeVisible();
  // The sidebar slides in; an unforced click waits for Close to settle
  // instead of landing where the button was mid-animation.
  await page
    .locator('.editor-list-view-sidebar')
    .getByRole('button', { name: 'Close' })
    .click();
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
        wp.blocks.createBlock('core/paragraph', { content: text }),
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
    const emailButton = wp.blocks.createBlock('core/button', {
      text: 'Native action',
      url: 'https://example.com/',
    });
    const section = wp.blocks.createBlock('campaignbridge/section', {}, [
      wp.blocks.createBlock('core/paragraph', { content: text }),
      wp.blocks.createBlock('core/buttons', {}, [emailButton]),
    ]);
    const secondSection = wp.blocks.createBlock('campaignbridge/section');
    wp.data
      .dispatch('core/block-editor')
      .insertBlocks([section, secondSection], undefined, root.clientId);

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
      // The post text-link CTA is now a native Core button style rather than
      // a separate CampaignBridge block with a block-to-block transform.
      buttonStyles: (wp.blocks.getBlockType('core/button').styles ?? []).map(
        (style: { name: string }) => style.name
      ),
      firstChild: wp.data
        .select('core/block-editor')
        .getBlockOrder(root.clientId)[0],
      movedClientId: secondSection.clientId,
    };
  }, unsavedText);
  expect(interactions).toEqual(
    expect.objectContaining({
      buttonStyles: expect.arrayContaining(['ghost']),
      firstChild: interactions.movedClientId,
    })
  );
  await expect(
    page
      .frameLocator('iframe[name="editor-canvas"]')
      .locator(`[data-block="${interactions.selectedClientId}"]`)
  ).toHaveClass(/is-selected/);
  await expect(page.locator('.block-editor-block-toolbar')).toBeVisible();
  const buttonLabel = page
    .frameLocator('iframe[name="editor-canvas"]')
    .locator(
      `[data-block="${interactions.selectedClientId}"] .wp-block-button__link`
    );
  await expect(buttonLabel).toHaveText('Native action');

  await page.evaluate(clientId => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    wp.data
      .dispatch('core/block-editor')
      .updateBlockAttributes(clientId, { text: 'Undo marker' });
  }, interactions.selectedClientId);
  await expect(buttonLabel).toHaveText('Undo marker');

  const undo = page.getByRole('button', { name: 'Undo' });
  await expect(undo).toBeEnabled();
  await undo.click();
  await expect(buttonLabel).toHaveText('Native action');
  const redo = page.getByRole('button', { name: 'Redo' });
  await expect(redo).toBeEnabled();
  await redo.click();
  await expect(buttonLabel).toHaveText('Undo marker');

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
      wp.blocks.createBlock('core/paragraph', {
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
