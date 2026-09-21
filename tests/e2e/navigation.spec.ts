import { expect, test } from './support/fixtures';
import { dismissEditorWelcomeGuide } from './support/editor';

test('navigation links use native block state and serialize bounded controls', async ({
  page,
}) => {
  await page.goto('/wp-admin/post-new.php?post_type=cb_templates');
  await page.waitForFunction(() => {
    const wp = (globalThis as typeof globalThis & { wp?: any }).wp;
    return (
      wp?.data?.select('core/editor')?.getCurrentPostType?.() ===
        'cb_templates' &&
      wp?.blocks?.getBlockType?.('campaignbridge/navigation')
    );
  });
  await dismissEditorWelcomeGuide(page);

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
        return wp.data
          .select('core/block-editor')
          .canInsertBlockType('campaignbridge/navigation', id);
      }, sectionId)
    )
    .toBe(true);

  const navigationId = await page.evaluate(id => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const navigation = wp.blocks.createBlock('campaignbridge/navigation');
    wp.data
      .dispatch('core/block-editor')
      .insertBlocks(navigation, undefined, id);
    wp.data.dispatch('core/block-editor').selectBlock(navigation.clientId);
    return navigation.clientId as string;
  }, sectionId);

  const settings = page.getByRole('button', { name: 'Settings' });
  if (
    !(await page.getByRole('textbox', { name: 'Link 1 label' }).isVisible())
  ) {
    await settings.click();
  }
  await page.getByRole('textbox', { name: 'Link 1 label' }).fill('Shop');
  await page
    .getByRole('textbox', { name: 'Link 1 HTTPS URL' })
    .fill('https://example.com/shop');
  await page.getByRole('button', { name: 'Add link' }).click();
  await page.getByRole('textbox', { name: 'Link 2 label' }).fill('About');
  await page
    .getByRole('textbox', { name: 'Link 2 HTTPS URL' })
    .fill('https://example.com/about');

  const state = await page.evaluate(id => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const block = wp.data.select('core/block-editor').getBlock(id);
    return {
      items: block.attributes.items,
      saved: wp.blocks.serialize([block]),
      dirty: wp.data.select('core/editor').isEditedPostDirty(),
    };
  }, navigationId);
  expect(state.items).toEqual([
    { label: 'Shop', url: 'https://example.com/shop' },
    { label: 'About', url: 'https://example.com/about' },
  ]);
  expect(state.saved).toContain('campaignbridge/navigation');
  expect(state.saved).toContain('https://example.com/shop');
  expect(state.dirty).toBe(true);
  await expect(
    page
      .frameLocator('iframe[name="editor-canvas"]')
      .locator('[data-type="campaignbridge/navigation"]')
  ).toContainText('Shop');
});
