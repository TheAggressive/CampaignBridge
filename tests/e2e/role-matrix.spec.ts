import { expect, test, type Page } from './support/fixtures';
import {
  api,
  archiveCampaign,
  CAMPAIGNS_PATH,
  CAPABILITIES,
  campaignThrough,
  deleteTemplate,
  deliverableTemplate,
  installed,
  mailchimp,
  signInOperator,
  type Operator,
} from './support/operators';

const POLICIES_PATH =
  '/wp-admin/admin.php?page=campaignbridge-settings&tab=policies';
const SEPARATE_DELIVERY = 'Require a second person to deliver';

test.use({ actionTimeout: 20_000 });

/** Turn the separate-delivery policy on or off through Settings → Policies. */
async function setSeparateDelivery(page: Page, on: boolean): Promise<void> {
  await page.goto(POLICIES_PATH);
  const toggle = page.getByLabel(SEPARATE_DELIVERY);
  if ((await toggle.isChecked()) !== on) {
    await toggle.setChecked(on);
    await page.getByRole('button', { name: 'Save policies' }).click();
    await expect(page.getByText('Delivery policies saved.')).toBeVisible();
  }
  // Later API calls run through wp.apiFetch, which the Campaigns screen loads.
  await page.goto(CAMPAIGNS_PATH);
}

/** The actions the server publishes for one campaign to the signed-in user. */
async function actionsOf(page: Page, id: string): Promise<string[]> {
  const response = await api<{ campaign: { actions: string[] } }>(page, {
    path: `/campaignbridge/v1/campaigns/${id}`,
  });

  return response.campaign.actions;
}

/** Campaign IDs the user may list for one owner, or null when the list is refused. */
async function listed(page: Page, ownerId: number): Promise<string[] | null> {
  return api<{ items: Array<{ id: string }> }>(page, {
    path: `/campaignbridge/v1/campaigns?owner_user_id=${ownerId}&per_page=100`,
  }).then(
    response => response.items.map(item => item.id),
    () => null
  );
}

const link = (page: Page, id: string) =>
  page.locator(`#campaignbridge-campaigns-root a[href*="campaign=${id}"]`);

test('each operator role sees only its own data and actions', async ({
  page,
  browser,
}) => {
  test.setTimeout(240_000);
  await page.goto(CAMPAIGNS_PATH);
  test.skip(
    !(await installed(page, '/campaignbridge-e2e/v1/mailchimp')) ||
      !(await installed(page, '/campaignbridge-e2e/v1/operators')),
    'Runs only with the test-only simulated Mailchimp and operator accounts.'
  );
  await mailchimp(page, { reset: true, connect: true });
  const separateBefore = await page.evaluate(
    () =>
      (
        globalThis as typeof globalThis & {
          campaignbridgeCampaigns: { separateDelivery: boolean };
        }
      ).campaignbridgeCampaigns.separateDelivery
  );

  const templateId = await deliverableTemplate(
    page,
    `Role matrix ${Date.now()}`
  );
  const operators: Operator[] = [];
  const campaigns: string[] = [];
  const sign = async (capabilities: string[]) => {
    const operator = await signInOperator(page, browser, capabilities);
    operators.push(operator);
    return operator;
  };

  try {
    await setSeparateDelivery(page, true);
    const adminId = (
      await api<{ id: number }>(page, { path: '/wp/v2/users/me' })
    ).id;
    const adminCampaign = await campaignThrough(page, templateId, []);
    campaigns.push(adminCampaign.id);

    const author = await sign([
      CAPABILITIES.editTemplates,
      CAPABILITIES.createCampaigns,
    ]);
    const manager = await sign([
      CAPABILITIES.createCampaigns,
      CAPABILITIES.manage,
    ]);
    // Approval reads the template, so approvers need template access too.
    const approver = await sign([
      CAPABILITIES.createCampaigns,
      CAPABILITIES.manage,
      CAPABILITIES.sendCampaigns,
      CAPABILITIES.editTemplates,
    ]);
    const deliverer = await sign([
      CAPABILITIES.createCampaigns,
      CAPABILITIES.manage,
      CAPABILITIES.sendCampaigns,
      CAPABILITIES.testCampaigns,
    ]);
    const outsider = await sign([]);

    // Author: their own campaigns and templates, nothing else.
    await author.page.goto(CAMPAIGNS_PATH);
    const authored = await campaignThrough(author.page, templateId, [
      'snapshot',
      'submit',
    ]);
    campaigns.push(authored.id);
    await author.page.reload();
    await expect(link(author.page, authored.id).first()).toBeVisible();
    await expect(link(author.page, adminCampaign.id)).toHaveCount(0);
    expect(await listed(author.page, adminId)).toBeNull();
    expect(await actionsOf(author.page, authored.id)).not.toContain('approve');
    await author.page.goto(`${CAMPAIGNS_PATH}&campaign=${authored.id}`);
    const authorScreen = author.page.locator('#campaignbridge-campaigns-root');
    await expect(
      authorScreen.getByText('Waiting for someone who can approve campaigns.')
    ).toBeVisible();
    await expect(
      authorScreen.getByRole('button', { name: 'Approve' })
    ).toHaveCount(0);
    await author.page.goto(`${CAMPAIGNS_PATH}&campaign=${adminCampaign.id}`);
    await expect(
      authorScreen.locator('.components-notice.is-error')
    ).toBeVisible();
    await expect(
      authorScreen.getByText('Template', { exact: true })
    ).toHaveCount(0);
    // Template authors reach the editor without being WordPress editors.
    await author.page.goto('/wp-admin/post-new.php?post_type=cb_templates');
    await expect(
      author.page.getByText('Sorry, you are not allowed')
    ).toHaveCount(0);
    await expect
      .poll(() =>
        author.page.evaluate(
          () =>
            (globalThis as typeof globalThis & { wp?: any }).wp?.data
              ?.select('core/editor')
              ?.getCurrentPostType?.() ?? null
        )
      )
      .toBe('cb_templates');
    const authorMenu = author.page.locator('#adminmenu');
    await expect(
      authorMenu.getByRole('link', { name: 'Settings', exact: true })
    ).toHaveCount(0);
    await expect(
      authorMenu.getByRole('link', { name: 'Status', exact: true })
    ).toHaveCount(0);

    // Manager: every owner's campaigns, but no approval or delivery authority.
    await manager.page.goto(CAMPAIGNS_PATH);
    expect(await listed(manager.page, author.id)).toContain(authored.id);
    expect(await listed(manager.page, adminId)).toContain(adminCampaign.id);
    const managerActions = await actionsOf(manager.page, authored.id);
    for (const denied of [
      'approve',
      'create_provider_draft',
      'schedule',
      'send',
      'test_send',
    ]) {
      expect(managerActions).not.toContain(denied);
    }
    await manager.page.goto(`${CAMPAIGNS_PATH}&campaign=${authored.id}`);
    await expect(
      manager.page.getByText('Waiting for someone who can approve campaigns.')
    ).toBeVisible();
    await expect(
      manager.page
        .locator('#campaignbridge-campaigns-root')
        .getByRole('button', { name: 'Refresh from template' })
    ).toHaveCount(0);
    // The menu lists each screen once, without repeating its own title.
    await expect(
      manager.page
        .locator('#adminmenu .wp-submenu')
        .getByRole('link', { name: 'CampaignBridge', exact: true })
    ).toHaveCount(0);

    // Approver: approves and hands off, but the policy keeps delivery with someone else.
    await approver.page.goto(`${CAMPAIGNS_PATH}&campaign=${authored.id}`);
    const approverScreen = approver.page.locator(
      '#campaignbridge-campaigns-root'
    );
    await approverScreen.getByRole('button', { name: 'Approve' }).click();
    await expect(approverScreen.getByText('Campaign approved.')).toBeVisible();
    const approverDelivery = approverScreen.locator(
      '.campaignbridge-campaigns__delivery'
    );
    await approverDelivery
      .getByRole('button', { name: 'Create Mailchimp draft' })
      .click();
    await expect(
      approverDelivery.getByText('The Mailchimp draft is ready.')
    ).toBeVisible();
    for (const denied of ['Schedule', 'Send now', 'Send a test']) {
      await expect(
        approverDelivery.getByRole('button', { name: denied })
      ).toHaveCount(0);
    }
    const approverActions = await actionsOf(approver.page, authored.id);
    expect(approverActions).not.toContain('schedule');
    expect(approverActions).not.toContain('send');

    // Deliverer: a second person may test, schedule, and send it.
    await deliverer.page.goto(`${CAMPAIGNS_PATH}&campaign=${authored.id}`);
    const delivererDelivery = deliverer.page.locator(
      '.campaignbridge-campaigns__delivery'
    );
    for (const allowed of ['Send a test', 'Schedule', 'Send now']) {
      await expect(
        delivererDelivery.getByRole('button', { name: allowed })
      ).toBeVisible();
    }
    expect(await actionsOf(deliverer.page, authored.id)).toEqual(
      expect.arrayContaining(['test_send', 'schedule', 'send'])
    );

    // Outsider: no CampaignBridge menu, screen, or data.
    await outsider.page.goto('/wp-admin/');
    await expect(
      outsider.page
        .locator('#adminmenu')
        .getByRole('link', { name: 'CampaignBridge' })
    ).toHaveCount(0);
    await outsider.page.goto(CAMPAIGNS_PATH);
    await expect(
      outsider.page.getByText('Sorry, you are not allowed to access this page.')
    ).toBeVisible();
    await outsider.page.goto('/wp-admin/profile.php');
    const refused = await outsider.page.evaluate(async () => {
      const nonce = await globalThis
        .fetch('/wp-admin/admin-ajax.php?action=rest-nonce', {
          credentials: 'same-origin',
        })
        .then(response => response.text());
      // rest_route works with plain and pretty permalinks alike.
      return globalThis
        .fetch('/?rest_route=/campaignbridge/v1/campaigns', {
          credentials: 'same-origin',
          headers: { 'X-WP-Nonce': nonce },
        })
        .then(response => response.status);
    });
    expect(refused).toBe(403);
    expect((await mailchimp(page, {})).calls).toEqual({
      test: 0,
      schedule: 0,
      unschedule: 0,
      send: 0,
    });
  } finally {
    for (const id of campaigns) await archiveCampaign(page, id);
    for (const operator of operators) await operator.remove();
    await setSeparateDelivery(page, separateBefore);
    await deleteTemplate(page, templateId);
    await mailchimp(page, { reset: true, fail: '' });
  }
});
