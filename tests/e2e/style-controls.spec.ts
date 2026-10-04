import { expect, test } from './support/fixtures';
import {
  dismissEditorWelcomeGuide,
  waitForInsertableCanvas,
} from './support/editor';

const NEW_TEMPLATE_PATH = '/wp-admin/post-new.php?post_type=cb_templates';

test('the canvas draws buttons with the email design shape, not Core defaults', async ({
  page,
}) => {
  await page.goto(NEW_TEMPLATE_PATH);
  await dismissEditorWelcomeGuide(page);
  await waitForInsertableCanvas(page);

  const supports = await page.evaluate(() => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const root = wp.data.select('core/block-editor').getBlocks()[0];
    const button = (attributes: Record<string, unknown>) =>
      wp.blocks.createBlock('core/button', {
        text: 'Go',
        url: 'https://example.com',
        ...attributes,
      });
    wp.data.dispatch('core/block-editor').insertBlocks(
      wp.blocks.createBlock('campaignbridge/section', {}, [
        wp.blocks.createBlock('core/buttons', {}, [
          button({}),
          button({
            style: {
              border: { radius: '6px' },
              spacing: {
                padding: {
                  top: '8px',
                  right: '16px',
                  bottom: '8px',
                  left: '16px',
                },
              },
            },
          }),
        ]),
      ]),
      undefined,
      root.clientId
    );
    const type = (name: string) => wp.blocks.getBlockType(name).supports;

    return {
      buttonBorder: type('core/button').__experimentalBorder,
      buttonPadding: Boolean(type('core/button').spacing?.padding),
      imageRadius: type('core/image').__experimentalBorder?.radius,
      paragraphBorder: type('core/paragraph').__experimentalBorder,
    };
  });

  expect(supports.buttonBorder).toMatchObject({
    color: true,
    radius: true,
    style: true,
    width: true,
  });
  expect(supports.buttonPadding).toBe(true);
  expect(supports.imageRadius).toBe(true);
  expect(supports.paragraphBorder).toBe(false);

  const links = page
    .frameLocator('iframe[name="editor-canvas"]')
    .locator('[data-type="core/button"] .wp-block-button__link');
  await expect(links).toHaveCount(2);
  const shape = (index: number) =>
    links.nth(index).evaluate(element => {
      const style = globalThis.getComputedStyle(element);
      return {
        radius: style.borderTopLeftRadius,
        padding: `${style.paddingTop} ${style.paddingLeft}`,
        weight: style.fontWeight,
        size: style.fontSize,
      };
    });

  // The packaged email design: a pill, 12px by 24px, 16px bold.
  expect(await shape(0)).toEqual({
    radius: '999px',
    padding: '12px 24px',
    weight: '700',
    size: '16px',
  });
  // Authored values win over the design, as they do in the compiler.
  expect(await shape(1)).toMatchObject({
    radius: '6px',
    padding: '8px 16px',
  });
});
