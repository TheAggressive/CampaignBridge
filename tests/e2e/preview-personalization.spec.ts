import { expect, test } from './support/fixtures';
import { dismissEditorWelcomeGuide } from './support/editor';

const NEW_TEMPLATE_PATH = '/wp-admin/post-new.php?post_type=cb_templates';
const SAMPLE_FRAME =
  'iframe[title="Email preview with sample personalization"]';
const TOKEN_FRAME = 'iframe[title="Email preview"]';

let cleanupPostId = 0;
test.afterEach(async ({ page }) => {
  if (cleanupPostId > 0 && !page.isClosed()) {
    await page.evaluate(async id => {
      const wp = (globalThis as typeof globalThis & { wp: any }).wp;
      await wp.apiFetch({
        path: `/wp/v2/cb_templates/${id}?force=true`,
        method: 'DELETE',
      });
    }, cleanupPostId);
  }
  cleanupPostId = 0;
});

test('email preview labels sample personalization and keeps tokens canonical', async ({
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
  // The template's container must render before blocks can be inserted.
  await expect(
    page
      .frameLocator('iframe[name="editor-canvas"]')
      .locator('[data-type="campaignbridge/container"]')
  ).toBeVisible();

  cleanupPostId = await page.evaluate(() => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const root = wp.data.select('core/block-editor').getBlocks()[0];
    wp.data.dispatch('core/block-editor').insertBlocks(
      wp.blocks.createBlock('campaignbridge/section', {}, [
        wp.blocks.createBlock('core/paragraph', {
          content: 'Hi {{cb:subscriber.first_name}}, welcome aboard.',
        }),
      ]),
      undefined,
      root.clientId
    );

    return Number(wp.data.select('core/editor').getCurrentPostId());
  });

  await expect
    .poll(() =>
      page.evaluate(() =>
        (globalThis as typeof globalThis & { wp: any }).wp.data
          .select('core/editor')
          .getEditedPostContent()
      )
    )
    .toContain('{{cb:subscriber.first_name}}');

  await page.getByRole('button', { name: /^(Preview|View)$/ }).click();
  await page.getByRole('menuitem', { name: 'Email Preview' }).click();
  // The toggle renders only for a successful compile that has a sample view.
  const personalization = page.getByRole('group', {
    name: 'Personalization',
  });
  await expect(personalization).toBeVisible();
  await expect(
    personalization.getByRole('button', { name: 'Sample values' })
  ).toHaveAttribute('aria-pressed', 'true');
  await expect(
    page.getByText('Sample values stand in for each subscriber’s data.', {
      exact: false,
    })
  ).toBeVisible();
  await expect(
    page.frameLocator(SAMPLE_FRAME).getByText('Hi Alex, welcome aboard.')
  ).toBeVisible();

  await personalization.getByRole('button', { name: 'Tokens' }).click();
  await expect(
    page
      .frameLocator(TOKEN_FRAME)
      .getByText('Hi {{cb:subscriber.first_name}}, welcome aboard.')
  ).toBeVisible();
});
