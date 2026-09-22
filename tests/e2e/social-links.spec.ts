import { expect, test } from './support/fixtures';
import { dismissEditorWelcomeGuide } from './support/editor';

test('Core Social Icons exposes only packaged services and serializes email-safe links', async ({
  page,
}) => {
  await page.goto('/wp-admin/post-new.php?post_type=cb_templates');
  await page.waitForFunction(() => {
    const wp = (globalThis as typeof globalThis & { wp?: any }).wp;
    return (
      wp?.data?.select('core/editor')?.getCurrentPostType?.() ===
        'cb_templates' &&
      wp?.blocks?.getBlockType?.('core/social-links') &&
      wp?.blocks?.getBlockType?.('core/social-link')
    );
  });
  await dismissEditorWelcomeGuide(page);
  await expect(
    page
      .frameLocator('iframe[name="editor-canvas"]')
      .locator('[data-type="campaignbridge/container"]')
  ).toBeVisible();

  const state = await page.evaluate(() => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const root = wp.data.select('core/block-editor').getBlocks()[0];
    const social = wp.blocks.createBlock(
      'core/social-links',
      {
        align: 'center',
        showLabels: true,
        size: 'has-normal-icon-size',
      },
      [
        wp.blocks.createBlock('core/social-link', {
          service: 'facebook',
          url: 'https://example.com/facebook',
          label: 'Follow on Facebook',
        }),
        wp.blocks.createBlock('core/social-link', {
          service: 'youtube',
          url: 'https://example.com/youtube',
          label: 'Watch on YouTube',
        }),
      ]
    );
    const section = wp.blocks.createBlock('campaignbridge/section', {}, [
      social,
    ]);
    wp.data
      .dispatch('core/block-editor')
      .insertBlocks(section, undefined, root.clientId);

    const variations = wp.blocks
      .getBlockVariations('core/social-link')
      .map((variation: { name: string }) => variation.name)
      .sort();
    const styles = wp.data
      .select('core/blocks')
      .getBlockStyles('core/social-links')
      .map((style: { name: string }) => style.name);

    return {
      variations,
      styles,
      saved: wp.blocks.serialize([social]),
      socialId: social.clientId as string,
    };
  });

  expect(state.variations).toEqual([
    'bluesky',
    'facebook',
    'instagram',
    'linkedin',
    'mastodon',
    'tiktok',
    'x',
    'youtube',
  ]);
  expect(state.styles).toEqual(['logos-only']);
  expect(state.saved).toContain('wp:social-links');
  expect(state.saved).toContain('"service":"facebook"');
  expect(state.saved).toContain('https://example.com/youtube');
  await page.waitForFunction(
    () =>
      (globalThis as typeof globalThis & { wp: any }).wp.data
        .select('core/editor')
        .isEditedPostDirty() === true
  );

  const socialBlock = page
    .frameLocator('iframe[name="editor-canvas"]')
    .locator(`[data-block="${state.socialId}"]`);
  await expect(socialBlock).toBeVisible();
});
