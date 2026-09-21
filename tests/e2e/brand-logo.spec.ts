import { expect, test } from './support/fixtures';
import { dismissEditorWelcomeGuide } from './support/editor';

test('Brand Logo is a bound Core Image variation', async ({ page }) => {
  await page.goto('/wp-admin/post-new.php?post_type=cb_templates');
  await page.waitForFunction(() => {
    const wp = (globalThis as typeof globalThis & { wp?: any }).wp;
    return (
      wp?.data?.select('core/editor')?.getCurrentPostType?.() ===
        'cb_templates' &&
      wp?.blocks
        ?.getBlockVariations?.('core/image', 'inserter')
        ?.some(
          (variation: { name?: string }) =>
            variation.name === 'campaignbridge-brand-logo'
        )
    );
  });
  await dismissEditorWelcomeGuide(page);

  const state = await page.evaluate(() => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const root = wp.data.select('core/block-editor').getBlocks()[0];
    const variation = wp.blocks
      .getBlockVariations('core/image', 'inserter')
      .find(
        (candidate: { name?: string }) =>
          candidate.name === 'campaignbridge-brand-logo'
      );
    const section = wp.blocks.createBlock('campaignbridge/section');
    const logo = wp.blocks.createBlock('core/image', variation.attributes);
    wp.data
      .dispatch('core/block-editor')
      .insertBlocks(section, undefined, root.clientId);
    wp.data
      .dispatch('core/block-editor')
      .insertBlocks(logo, undefined, section.clientId);

    return {
      title: variation.title,
      attributes: logo.attributes,
      saved: wp.blocks.serialize([logo]),
    };
  });

  expect(state.title).toBe('Brand Logo');
  expect(state.attributes).toEqual(
    expect.objectContaining({ width: 240, align: 'center' })
  );
  expect(state.attributes.metadata.bindings).toEqual({
    url: {
      source: 'campaignbridge/brand-data',
      args: { field: 'logoUrl' },
    },
    alt: {
      source: 'campaignbridge/brand-data',
      args: { field: 'logoAlt' },
    },
    href: {
      source: 'campaignbridge/brand-data',
      args: { field: 'logoLink' },
    },
  });
  expect(state.saved).toContain('campaignbridge/brand-data');
  expect(state.saved).toContain('logoLink');
});
