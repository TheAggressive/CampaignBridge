import { expect, test } from './support/fixtures';
import { dismissEditorWelcomeGuide } from './support/editor';

const NEW_TEMPLATE_PATH = '/wp-admin/post-new.php?post_type=cb_templates';

test('templates author with constrained WordPress Core blocks', async ({
  page,
}) => {
  await page.goto(NEW_TEMPLATE_PATH);
  await page.waitForFunction(() => {
    const wp = (globalThis as typeof globalThis & { wp?: any }).wp;
    return (
      wp?.data?.select('core/editor')?.getCurrentPostType?.() ===
        'cb_templates' && wp?.blocks?.getBlockType?.('campaignbridge/container')
    );
  });
  await dismissEditorWelcomeGuide(page);
  await expect(
    page
      .frameLocator('iframe[name="editor-canvas"]')
      .locator('[data-type="campaignbridge/container"]')
  ).toBeVisible();

  const sectionId = await page.evaluate(() => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const root = wp.data.select('core/block-editor').getBlocks()[0];
    const section = wp.blocks.createBlock('campaignbridge/section', {}, [
      wp.blocks.createBlock('core/list', {}, [
        wp.blocks.createBlock('core/list-item', { content: 'Item' }),
      ]),
    ]);
    wp.data
      .dispatch('core/block-editor')
      .insertBlocks(section, undefined, root.clientId);

    return section.clientId as string;
  });
  // The section registers its inner-block allowlist once it renders.
  await expect
    .poll(() =>
      page.evaluate(
        id =>
          Boolean(
            (globalThis as typeof globalThis & { wp: any }).wp.data
              .select('core/block-editor')
              .getBlockListSettings(id)
          ),
        sectionId
      )
    )
    .toBe(true);

  const editor = await page.evaluate(sectionClientId => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const blockEditor = wp.data.select('core/block-editor');
    const insertable = (name: string) =>
      blockEditor.canInsertBlockType(name, sectionClientId);
    const paragraph = wp.blocks.getBlockType('core/paragraph');
    const heading = wp.blocks.getBlockType('core/heading');
    const styles = (name: string) =>
      wp.data
        .select('core/blocks')
        .getBlockStyles(name)
        .map((style: { name: string }) => style.name);

    return {
      core: [
        'core/paragraph',
        'core/heading',
        'core/image',
        'core/buttons',
        'core/list',
        'core/separator',
        'core/spacer',
      ].map(insertable),
      unsupported: [
        'core/group',
        'core/html',
        'core/columns',
        'core/cover',
        'core/button',
        'core/list-item',
      ].map(insertable),
      obsolete: [
        'campaignbridge/text',
        'campaignbridge/heading',
        'campaignbridge/image',
        'campaignbridge/button',
        'campaignbridge/divider',
        'campaignbridge/spacer',
      ].map(name => Boolean(wp.blocks.getBlockType(name))),
      paragraphAnchor: paragraph.supports.anchor,
      paragraphLetterSpacing:
        paragraph.supports.typography.__experimentalLetterSpacing,
      nestedList: blockEditor.canInsertBlockType(
        'core/list',
        blockEditor.getBlocks(sectionClientId)[0].innerBlocks[0].clientId
      ),
      headingBackground: heading.supports.color.background,
      headingLevels: heading.attributes.levelOptions.default,
      separatorStyles: styles('core/separator'),
      buttonStyles: styles('core/button'),
    };
  }, sectionId);

  expect(editor.core).toEqual(Array(7).fill(true));
  expect(editor.unsupported).toEqual(Array(6).fill(false));
  expect(editor.obsolete).toEqual(Array(6).fill(false));
  expect(editor.paragraphAnchor).toBe(false);
  expect(editor.paragraphLetterSpacing).toBe(false);
  expect(editor.nestedList).toBe(false);
  expect(editor.headingBackground).toBe(false);
  expect(editor.headingLevels).toEqual([1, 2, 3, 4]);
  expect(editor.separatorStyles).not.toContain('dots');
  expect(editor.buttonStyles).toEqual(
    expect.arrayContaining(['fill', 'outline', 'ghost'])
  );
});
