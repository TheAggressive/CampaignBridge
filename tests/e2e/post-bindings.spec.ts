import { expect, test, type Page } from './support/fixtures';
import { dismissEditorWelcomeGuide } from './support/editor';

const NEW_TEMPLATE_PATH = '/wp-admin/post-new.php?post_type=cb_templates';

/**
 * Wait until the container will actually accept a Post Card.
 *
 * A container publishes its inner-block allowlist when it renders, and an
 * insert before that is silently refused. This waits on that real editor
 * state rather than on a duration, so the tests do not depend on how fast the
 * machine happens to be.
 */
async function cardIsInsertable(page: Page): Promise<void> {
  await expect
    .poll(() =>
      page.evaluate(() => {
        const wp = (globalThis as typeof globalThis & { wp: any }).wp;
        const be = wp.data.select('core/block-editor');
        const root = be.getBlocks()[0];

        return root
          ? be.canInsertBlockType('campaignbridge/post-card', root.clientId)
          : false;
      })
    )
    .toBe(true);
}

interface SourcePost {
  id: number;
  title: string;
  excerpt: string;
}

/**
 * Post body used to prove the word cap applies to post content.
 *
 * Longer than the contract's minimum cap of 10 words, so capping is visible,
 * and split across two blocks so a word boundary must survive the block
 * delimiter between them.
 */
const BODY_WORDS =
  'alpha beta gamma delta epsilon zeta eta theta iota kappa lambda mu nu xi';

/** The same body capped at the contract minimum of 10 words. */
const BODY_CAPPED = 'alpha beta gamma delta epsilon zeta eta theta iota kappa…';

/**
 * Post content authored through the one read-only CampaignBridge binding
 * source.
 *
 * The Post Card owns the selected post; its Core children resolve their value
 * through `campaignbridge/post-data`. The source defines neither `setValues`
 * nor `canUserEditValue`, so WordPress itself makes every bound attribute
 * read-only. These assertions run against real Gutenberg because that
 * guarantee belongs to Core, not to CampaignBridge code.
 */
test('post content binds read-only to the selected post', async ({ page }) => {
  await page.goto(NEW_TEMPLATE_PATH);
  await page.waitForFunction(() => {
    const wp = (globalThis as typeof globalThis & { wp?: any }).wp;
    return (
      wp?.data?.select('core/editor')?.getCurrentPostType?.() ===
        'cb_templates' && wp?.blocks?.getBlockType?.('campaignbridge/post-card')
    );
  });
  await dismissEditorWelcomeGuide(page);

  const posts: SourcePost[] = await page.evaluate(async () => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const create = async (title: string, excerpt: string) => {
      const post = await wp.apiFetch({
        path: '/wp/v2/posts',
        method: 'POST',
        data: {
          title,
          excerpt,
          // Two blocks, so the compiler and the editor must both keep a word
          // boundary where one block ends and the next begins.
          content:
            '<!-- wp:paragraph --><p>alpha beta gamma delta epsilon zeta eta</p><!-- /wp:paragraph -->' +
            '<!-- wp:paragraph --><p>theta iota kappa lambda mu nu xi</p><!-- /wp:paragraph -->',
          status: 'publish',
        },
      });

      return { id: post.id as number, title, excerpt };
    };

    return [
      await create('CampaignBridge binding source A', 'Excerpt from post A.'),
      await create('CampaignBridge binding source B', 'Excerpt from post B.'),
    ];
  });

  try {
    await cardIsInsertable(page);
    const cardId = await page.evaluate(id => {
      const wp = (globalThis as typeof globalThis & { wp: any }).wp;
      const root = wp.data.select('core/block-editor').getBlocks()[0];
      const card = wp.blocks.createBlock('campaignbridge/post-card', {
        postId: id,
        postType: 'post',
      });
      wp.data
        .dispatch('core/block-editor')
        .insertBlocks(card, undefined, root.clientId);

      return card.clientId as string;
    }, posts[0].id);

    const canvas = page.frameLocator('iframe[name="editor-canvas"]');
    const boundParagraph = canvas
      .locator('[data-type="core/paragraph"]')
      .first();

    // The Post Card seeds the bound Core blocks, and the binding source
    // resolves the selected post for the canvas. A new card leads with the
    // post summary.
    await expect(boundParagraph).toHaveText(posts[0].excerpt);
    await expect(
      canvas.locator('[data-type="core/heading"]').first()
    ).toHaveText(posts[0].title);

    // WordPress disables editing because the source exposes no
    // canUserEditValue.
    await expect(boundParagraph).toHaveAttribute('contenteditable', 'false');
    await expect(boundParagraph).toHaveAttribute('aria-readonly', 'true');
    await expect(
      canvas.locator('[data-type="core/heading"]').first()
    ).toHaveAttribute('contenteditable', 'false');

    // Typing into bound post content changes neither the template nor the
    // article it displays.
    await boundParagraph.click();
    await page.keyboard.type('Rewriting the source article');
    await expect(boundParagraph).toHaveText(posts[0].excerpt);

    const sourceExcerpt = await page.evaluate(async id => {
      const wp = (globalThis as typeof globalThis & { wp: any }).wp;
      const post = await wp.apiFetch({
        path: `/wp/v2/posts/${id}?context=edit`,
      });

      return post.excerpt.raw as string;
    }, posts[0].id);
    expect(sourceExcerpt).toBe(posts[0].excerpt);

    // The resolved value is never written into the saved block: the template
    // stores the binding, and the compiler reads the immutable snapshot.
    const saved = await page.evaluate(cid => {
      const wp = (globalThis as typeof globalThis & { wp: any }).wp;
      const card = wp.data.select('core/block-editor').getBlock(cid);
      const child = (name: string) =>
        card.innerBlocks.find((block: any) => block.name === name);
      const button = child('core/buttons').innerBlocks[0];

      return {
        children: card.innerBlocks.map((block: any) => block.name),
        heading: child('core/heading').attributes,
        paragraph: child('core/paragraph').attributes,
        button: button.attributes,
      };
    }, cardId);

    expect(saved.children).toEqual([
      'campaignbridge/post-image',
      'core/heading',
      'core/paragraph',
      'core/buttons',
    ]);
    expect(saved.heading.content).toBe('');
    expect(saved.heading.metadata.bindings.content).toEqual({
      source: 'campaignbridge/post-data',
      args: { field: 'title' },
    });
    expect(saved.paragraph.content).toBe('');
    expect(saved.paragraph.metadata.bindings.content).toEqual({
      source: 'campaignbridge/post-data',
      args: { field: 'excerpt', maxWords: 50 },
    });
    expect(saved.button.url).toBeUndefined();
    expect(saved.button.metadata.bindings.url).toEqual({
      source: 'campaignbridge/post-data',
      args: { field: 'url' },
    });
    // Only the destination comes from the post; the label stays authored.
    expect(saved.button.text).toBe('Read more');
    await expect(
      canvas.locator('[data-type="core/button"] [contenteditable]').first()
    ).toHaveAttribute('contenteditable', 'true');

    // Selecting another post re-resolves every bound child through context.
    await page.evaluate(
      ({ cid, id }) => {
        const wp = (globalThis as typeof globalThis & { wp: any }).wp;
        wp.data
          .dispatch('core/block-editor')
          .updateBlockAttributes(cid, { postId: id });
      },
      { cid: cardId, id: posts[1].id }
    );
    await expect(boundParagraph).toHaveText(posts[1].excerpt);
    await expect(
      canvas.locator('[data-type="core/heading"]').first()
    ).toHaveText(posts[1].title);

    // The contract-aware inspector switches the bound field and its word cap.
    await page.evaluate(
      cid =>
        (globalThis as typeof globalThis & { wp: any }).wp.data
          .dispatch('core/block-editor')
          .selectBlock(cid),
      await page.evaluate(cid => {
        const wp = (globalThis as typeof globalThis & { wp: any }).wp;

        return wp.data
          .select('core/block-editor')
          .getBlock(cid)
          .innerBlocks.find((block: any) => block.name === 'core/paragraph')
          .clientId as string;
      }, cardId)
    );

    const field = page.getByRole('combobox', { name: 'Show' });
    await expect(field).toBeVisible();
    // Only the fields the contract allows on this attribute are offered.
    await expect(field.locator('option')).toHaveText([
      'Not connected',
      'Excerpt',
      'Content',
    ]);

    // The post body is an explicit choice away from the seeded summary.
    await field.selectOption('content');
    await expect(boundParagraph).toHaveText(BODY_WORDS);

    // The word cap is bounded by the contract, so the control clamps rather
    // than letting an author write a value the compiler would reject. It is
    // exercised against the body, where truncation is visible.
    const wordCap = page.getByRole('spinbutton', { name: 'Maximum words' });
    await wordCap.fill('1');
    await wordCap.press('Enter');
    await expect(wordCap).toHaveValue('10');
    await expect(boundParagraph).toHaveText(BODY_CAPPED);

    const capped = await page.evaluate(cid => {
      const wp = (globalThis as typeof globalThis & { wp: any }).wp;
      const paragraph = wp.data
        .select('core/block-editor')
        .getBlock(cid)
        .innerBlocks.find((block: any) => block.name === 'core/paragraph');

      return paragraph.attributes.metadata.bindings.content.args;
    }, cardId);
    expect(capped).toEqual({ field: 'content', maxWords: 10 });

    // Switching back proves the panel drives the binding in both directions.
    await field.selectOption('excerpt');
    await expect(boundParagraph).toHaveText(posts[1].excerpt);
  } finally {
    await page.evaluate(
      async ids => {
        const wp = (globalThis as typeof globalThis & { wp: any }).wp;
        for (const id of ids) {
          await wp.apiFetch({
            path: `/wp/v2/posts/${id}?force=true`,
            method: 'DELETE',
          });
        }
      },
      posts.map(post => post.id)
    );
  }
});

/**
 * Connecting an attribute must leave the document compilable.
 *
 * The compiler refuses a bound attribute that also holds an authored literal.
 * Rather than weakening that rule, the CampaignBridge panel maintains the
 * invariant: connecting clears exactly the bound attribute, in one action.
 */
test('connecting a binding clears only the literal it replaces', async ({
  page,
}) => {
  await page.goto(NEW_TEMPLATE_PATH);
  await page.waitForFunction(() => {
    const wp = (globalThis as typeof globalThis & { wp?: any }).wp;
    return (
      wp?.data?.select('core/editor')?.getCurrentPostType?.() ===
        'cb_templates' && wp?.blocks?.getBlockType?.('campaignbridge/post-card')
    );
  });
  await dismissEditorWelcomeGuide(page);

  const postId = await page.evaluate(async () => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const post = await wp.apiFetch({
      path: '/wp/v2/posts',
      method: 'POST',
      data: {
        title: 'Authoring workflow source',
        excerpt: 'Summary for the authoring workflow.',
        content: '<!-- wp:paragraph --><p>Body copy.</p><!-- /wp:paragraph -->',
        status: 'publish',
      },
    });

    return post.id as number;
  });

  try {
    await cardIsInsertable(page);
    // A card whose children carry authored literals and unrelated styling.
    const ids = await page.evaluate(id => {
      const wp = (globalThis as typeof globalThis & { wp: any }).wp;
      const root = wp.data.select('core/block-editor').getBlocks()[0];
      const paragraph = wp.blocks.createBlock('core/paragraph', {
        content: 'Authored paragraph copy',
        style: { typography: { fontSize: '18px' } },
      });
      const heading = wp.blocks.createBlock('core/heading', {
        level: 3,
        content: 'Authored heading copy',
      });
      const button = wp.blocks.createBlock('core/button', {
        text: 'Authored label',
        url: 'https://example.com/authored',
      });
      const buttons = wp.blocks.createBlock('core/buttons', {}, [button]);
      const card = wp.blocks.createBlock(
        'campaignbridge/post-card',
        { postId: id, postType: 'post' },
        [paragraph, heading, buttons]
      );
      wp.data
        .dispatch('core/block-editor')
        .insertBlocks(card, undefined, root.clientId);

      return {
        paragraph: paragraph.clientId as string,
        heading: heading.clientId as string,
        button: button.clientId as string,
      };
    }, postId);

    const attributesOf = (clientId: string) =>
      page.evaluate(
        cid =>
          (globalThis as typeof globalThis & { wp: any }).wp.data
            .select('core/block-editor')
            .getBlockAttributes(cid),
        clientId
      );

    const connect = async (clientId: string, option: string) => {
      await page.evaluate(
        cid =>
          (globalThis as typeof globalThis & { wp: any }).wp.data
            .dispatch('core/block-editor')
            .selectBlock(cid),
        clientId
      );
      const control = page.getByRole('combobox', {
        name: option === 'url' ? 'Link to' : 'Show',
      });
      await expect(control).toBeVisible();
      await control.selectOption(option);
    };

    await connect(ids.paragraph, 'excerpt');
    const paragraph = await attributesOf(ids.paragraph);
    expect(paragraph.content).toBe('');
    expect(paragraph.metadata.bindings.content.args.field).toBe('excerpt');
    // Unrelated styling survives the connection.
    expect(paragraph.style).toEqual({ typography: { fontSize: '18px' } });

    await connect(ids.heading, 'title');
    const heading = await attributesOf(ids.heading);
    expect(heading.content).toBe('');
    expect(heading.metadata.bindings.content.args.field).toBe('title');
    expect(heading.level).toBe(3);

    await connect(ids.button, 'url');
    const button = await attributesOf(ids.button);
    expect(button.url).toBe('');
    // Only the destination is bound, so the authored label stays.
    expect(button.text).toBe('Authored label');
    expect(button.metadata.bindings.url.args.field).toBe('url');

    // A card built this way compiles, which is the whole point of clearing.
    const canvas = page.frameLocator('iframe[name="editor-canvas"]');
    await expect(
      canvas.locator('[data-type="core/paragraph"]').first()
    ).toHaveText('Summary for the authoring workflow.');

    // Disconnecting returns the block to a valid native state without
    // inventing or restoring content.
    await connect(ids.paragraph, '');
    const disconnected = await attributesOf(ids.paragraph);
    expect(disconnected.content).toBe('');
    expect(disconnected.metadata?.bindings?.content).toBeUndefined();
    expect(disconnected.style).toEqual({ typography: { fontSize: '18px' } });
  } finally {
    await page.evaluate(
      id =>
        (globalThis as typeof globalThis & { wp: any }).wp.apiFetch({
          path: `/wp/v2/posts/${id}?force=true`,
          method: 'DELETE',
        }),
      postId
    );
  }
});

/**
 * A plain Post Card seeds its composition through Core's InnerBlocks template.
 *
 * The block passes `template` to `useInnerBlocksProps()`, so Core applies it in
 * its own layout effect while the card is empty and marks the change
 * non-persistent. No effect, store, or post-render insertion of our own.
 */
test('a newly inserted post card seeds its children', async ({ page }) => {
  await page.goto(NEW_TEMPLATE_PATH);
  await page.waitForFunction(() => {
    const wp = (globalThis as typeof globalThis & { wp?: any }).wp;
    return (
      wp?.data?.select('core/editor')?.getCurrentPostType?.() ===
        'cb_templates' && wp?.blocks?.getBlockType?.('campaignbridge/post-card')
    );
  });
  await dismissEditorWelcomeGuide(page);

  await cardIsInsertable(page);

  const cardId = await page.evaluate(() => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const root = wp.data.select('core/block-editor').getBlocks()[0];
    // A plain card, with no variation and no attributes: exactly what the
    // inserter produces for the bare block.
    const card = wp.blocks.createBlock('campaignbridge/post-card');
    wp.data
      .dispatch('core/block-editor')
      .insertBlocks(card, undefined, root.clientId);

    return card.clientId as string;
  });

  // createBlock() alone never applies a template, so this can only pass once
  // the block renders and Core synchronises it.
  await expect
    .poll(() =>
      page.evaluate(
        cid =>
          (globalThis as typeof globalThis & { wp: any }).wp.data
            .select('core/block-editor')
            .getBlock(cid)
            ?.innerBlocks.map((block: any) => block.name),
        cardId
      )
    )
    .toEqual([
      'campaignbridge/post-image',
      'core/heading',
      'core/paragraph',
      'core/buttons',
    ]);

  const seeded = await page.evaluate(cid => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const card = wp.data.select('core/block-editor').getBlock(cid);
    const child = (name: string) =>
      card.innerBlocks.find((block: any) => block.name === name);

    return {
      heading: child('core/heading').attributes.metadata?.bindings?.content,
      paragraph: child('core/paragraph').attributes.metadata?.bindings?.content,
      button:
        child('core/buttons').innerBlocks[0]?.attributes.metadata?.bindings
          ?.url,
      buttonName: child('core/buttons').innerBlocks[0]?.name,
    };
  }, cardId);

  expect(seeded.buttonName).toBe('core/button');
  expect(seeded.heading).toEqual({
    source: 'campaignbridge/post-data',
    args: { field: 'title' },
  });
  expect(seeded.paragraph).toEqual({
    source: 'campaignbridge/post-data',
    args: { field: 'excerpt', maxWords: 50 },
  });
  expect(seeded.button).toEqual({
    source: 'campaignbridge/post-data',
    args: { field: 'url' },
  });
});
