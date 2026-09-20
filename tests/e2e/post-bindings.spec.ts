import { expect, test } from './support/fixtures';
import { dismissEditorWelcomeGuide } from './support/editor';

const NEW_TEMPLATE_PATH = '/wp-admin/post-new.php?post_type=cb_templates';

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
    // resolves the selected post for the canvas.
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

    await field.selectOption('content');
    await expect(boundParagraph).toHaveText(BODY_WORDS);

    // The word cap is bounded by the contract, so the control clamps rather
    // than letting an author write a value the compiler would reject.
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
