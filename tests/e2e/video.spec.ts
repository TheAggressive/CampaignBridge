import { expect, test } from './support/fixtures';
import { dismissEditorWelcomeGuide } from './support/editor';

test('video block serializes a bounded linked poster without playback markup', async ({
  page,
}) => {
  await page.goto('/wp-admin/post-new.php?post_type=cb_templates');
  await page.waitForFunction(() => {
    const wp = (globalThis as typeof globalThis & { wp?: any }).wp;
    return (
      wp?.data?.select('core/editor')?.getCurrentPostType?.() ===
        'cb_templates' && wp?.blocks?.getBlockType?.('campaignbridge/video')
    );
  });
  await dismissEditorWelcomeGuide(page);

  await expect
    .poll(() =>
      page.evaluate(() => {
        const wp = (globalThis as typeof globalThis & { wp: any }).wp;
        const root = wp.data.select('core/block-editor').getBlocks()[0];
        return Boolean(
          root &&
          wp.data
            .select('core/block-editor')
            .canInsertBlockType('campaignbridge/section', root.clientId)
        );
      })
    )
    .toBe(true);

  const sectionId = await page.evaluate(() => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const root = wp.data.select('core/block-editor').getBlocks()[0];
    const section = wp.blocks.createBlock('campaignbridge/section');
    wp.data
      .dispatch('core/block-editor')
      .insertBlocks(section, undefined, root.clientId);
    return section.clientId as string;
  });
  await expect
    .poll(() =>
      page.evaluate(id => {
        const wp = (globalThis as typeof globalThis & { wp: any }).wp;
        return Boolean(wp.data.select('core/block-editor').getBlock(id));
      }, sectionId)
    )
    .toBe(true);
  await expect
    .poll(() =>
      page.evaluate(id => {
        const wp = (globalThis as typeof globalThis & { wp: any }).wp;
        return wp.data
          .select('core/block-editor')
          .canInsertBlockType('campaignbridge/video', id);
      }, sectionId)
    )
    .toBe(true);

  const videoId = await page.evaluate(id => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const video = wp.blocks.createBlock('campaignbridge/video');
    wp.data.dispatch('core/block-editor').insertBlocks(video, undefined, id);
    wp.data.dispatch('core/block-editor').selectBlock(video.clientId);
    return video.clientId as string;
  }, sectionId);

  const settings = page.getByRole('button', { name: 'Settings' });
  if (
    !(await page.getByRole('textbox', { name: 'Poster HTTPS URL' }).isVisible())
  ) {
    await settings.click();
  }
  await page
    .getByRole('textbox', { name: 'Poster HTTPS URL' })
    .fill('https://example.com/poster.jpg');
  await page
    .getByRole('textbox', { name: 'Poster alternative text' })
    .fill('Product demo poster');
  await page
    .getByRole('textbox', { name: 'Video HTTPS URL' })
    .fill('https://example.com/watch');
  await page
    .getByRole('textbox', { name: 'Play link label' })
    .fill('Watch the demo');

  const state = await page.evaluate(id => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const block = wp.data.select('core/block-editor').getBlock(id);
    return {
      attributes: block.attributes,
      saved: wp.blocks.serialize([block]),
      dirty: wp.data.select('core/editor').isEditedPostDirty(),
    };
  }, videoId);
  expect(state.attributes).toMatchObject({
    posterUrl: 'https://example.com/poster.jpg',
    posterAlt: 'Product demo poster',
    videoUrl: 'https://example.com/watch',
    label: 'Watch the demo',
    width: 600,
    height: 338,
  });
  expect(state.saved).toContain('campaignbridge/video');
  expect(state.saved).not.toContain('<video');
  expect(state.saved).not.toContain('<iframe');
  expect(state.dirty).toBe(true);
  await expect(
    page
      .frameLocator('iframe[name="editor-canvas"]')
      .locator('[data-type="campaignbridge/video"]')
  ).toContainText('Watch the demo');
});
