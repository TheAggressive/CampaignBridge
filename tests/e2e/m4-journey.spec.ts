import {
  dismissEditorWelcomeGuide,
  waitForInsertableCanvas,
} from './support/editor';
import { expect, test, type Page } from './support/fixtures';
import {
  api,
  archiveCampaign,
  CAMPAIGNS_PATH,
  CAPABILITIES,
  deleteTemplate,
  installed,
  mailchimp,
  signInOperator,
} from './support/operators';

// A missing control fails at its step instead of exhausting the journey's budget.
test.use({ actionTimeout: 20_000 });

/** Build and publish a template in the block editor, as an operator would. */
async function publishTemplate(page: Page, title: string): Promise<number> {
  await page.waitForFunction(() => {
    const wp = (globalThis as typeof globalThis & { wp?: any }).wp;
    return (
      wp?.data?.select('core/editor')?.getCurrentPostType?.() ===
        'cb_templates' && wp?.blocks?.getBlockType?.('campaignbridge/section')
    );
  });
  await dismissEditorWelcomeGuide(page);
  await waitForInsertableCanvas(page);

  // Block insertion is the editor's own UI; the journey is about what follows.
  await page.evaluate(() => {
    const wp = (globalThis as typeof globalThis & { wp: any }).wp;
    const root = wp.data.select('core/block-editor').getBlocks()[0];
    const section = wp.blocks.createBlock('campaignbridge/section', {}, [
      wp.blocks.createBlock('core/paragraph', {
        content:
          'Hello {{cb:subscriber.first_name}}, the October drop is live.',
      }),
    ]);
    wp.data
      .dispatch('core/block-editor')
      .insertBlocks(section, undefined, root.clientId);
  });
  await expect
    .poll(() =>
      page.evaluate(() =>
        (globalThis as typeof globalThis & { wp: any }).wp.data
          .select('core/editor')
          .getEditedPostContent()
      )
    )
    .toContain('the October drop is live');

  await page
    .frameLocator('iframe[name="editor-canvas"]')
    .getByRole('textbox', { name: 'Add title' })
    .fill(title);

  const subject = page.getByRole('textbox', { name: 'Subject Line' });
  if (!(await subject.isVisible())) {
    const panel = page.getByRole('button', { name: 'Template Settings' });
    if (!(await panel.isVisible())) {
      await page.getByRole('button', { name: 'Settings' }).click();
    }
    if (!(await subject.isVisible())) await panel.click();
  }
  await subject.fill('The October drop');
  const senderName = page.getByRole('textbox', { name: 'Sender Name' });
  if (!(await senderName.isVisible())) {
    await page.getByRole('button', { name: 'Email Settings' }).click();
  }
  await senderName.fill('Journey Sender');
  await page
    .getByRole('textbox', { name: 'Sender Email' })
    .fill('sender@example.test');

  await page
    .locator('.editor-header')
    .getByRole('button', { name: 'Publish', exact: true })
    .click();
  await page
    .locator('.editor-post-publish-panel')
    .getByRole('button', { name: 'Publish', exact: true })
    .click();
  await expect
    .poll(() =>
      page.evaluate(() =>
        (globalThis as typeof globalThis & { wp: any }).wp.data
          .select('core/editor')
          .isCurrentPostPublished()
      )
    )
    .toBe(true);

  return page.evaluate(() =>
    Number(
      (globalThis as typeof globalThis & { wp: any }).wp.data
        .select('core/editor')
        .getCurrentPostId()
    )
  );
}

test('an operator goes from first run to a reconciled send without leaving WordPress', async ({
  page,
  browser,
}) => {
  test.setTimeout(180_000);
  await page.goto(CAMPAIGNS_PATH);
  test.skip(
    !(await installed(page, '/campaignbridge-e2e/v1/mailchimp')) ||
      !(await installed(page, '/campaignbridge-e2e/v1/operators')),
    'Runs only with the test-only simulated Mailchimp and operator accounts.'
  );
  const simulation = await mailchimp(page, { reset: true, connect: true });
  const kit = await api<{ source: string; slots: Array<{ color: string }> }>(
    page,
    { path: '/campaignbridge/v1/brand-kit' }
  );

  const operator = await signInOperator(
    page,
    browser,
    Object.values(CAPABILITIES)
  );
  const screen = operator.page.locator('#campaignbridge-campaigns-root');
  const title = `M4 journey ${Date.now()}`;
  let templateId = 0;
  let campaignId: string | null = null;

  try {
    // First run: the checklist shows what is left and links to each screen.
    await operator.page.goto(CAMPAIGNS_PATH);
    const checklist = operator.page.getByRole('region', {
      name: 'Get set up',
    });
    await expect(checklist).toBeVisible();
    const step = (name: RegExp) =>
      checklist.getByRole('listitem').filter({ hasText: name });

    // Only a connection the simulator created may be verified here: a
    // development site's real key is never marked verified by a fake answer.
    if (simulation.created_connection) {
      await step(/Connect Mailchimp/)
        .getByRole('link', { name: 'Set up' })
        .click();
      await expect(
        operator.page.locator('.campaignbridge-providers__configuration')
      ).toContainText('Connected');
      await operator.page.goto(CAMPAIGNS_PATH);
      await expect(step(/Connect Mailchimp/)).toContainText('(done)');
      await expect(step(/default Mailchimp audience/)).toContainText('(done)');
    }

    if (kit.source === 'defaults') {
      await api(operator.page, {
        path: '/campaignbridge/v1/brand-kit',
        method: 'PUT',
        data: { id: 'text', color: kit.slots[0].color },
      });
    }

    const templateLink = step(/Publish an email template/).getByRole('link', {
      name: 'Set up',
    });
    if (await templateLink.count()) {
      await templateLink.click();
    } else {
      await operator.page.goto('/wp-admin/post-new.php?post_type=cb_templates');
    }
    templateId = await publishTemplate(operator.page, title);

    // A first campaign, straight from the checklist.
    await operator.page.goto(CAMPAIGNS_PATH);
    await checklist.getByRole('button', { name: 'Create a campaign' }).click();
    const dialog = operator.page.getByRole('dialog', { name: 'New campaign' });
    await dialog.getByLabel('Email template').selectOption({ label: title });
    await dialog.getByLabel('Delivery').selectOption({ value: 'mailchimp' });
    const audience = dialog.getByRole('combobox', { name: 'Audience' });
    await expect(audience.locator('option').first()).toBeAttached();
    const audienceName =
      (await audience.locator('option').first().textContent())
        ?.replace(/\s*\(.*$/, '')
        .trim() ?? '';
    await audience.selectOption({ index: 0 });
    await dialog.getByRole('button', { name: 'Create campaign' }).click();
    await expect(operator.page).toHaveURL(/&campaign=campaign-/);
    campaignId = new URL(operator.page.url()).searchParams.get('campaign');
    const header = operator.page.locator(
      '.campaignbridge-campaigns__detail-header'
    );

    // Review: freeze the email, check it, and approve it.
    await screen.getByRole('button', { name: 'Prepare for review' }).click();
    await expect(
      screen.getByText('The email is ready for review.')
    ).toBeVisible();
    await expect(screen.getByText('The October drop')).toBeVisible();
    await expect(
      operator.page
        .frameLocator('iframe[title="Reviewed email preview"]')
        .getByText(/October drop is live/)
    ).toBeVisible({ timeout: 15_000 });
    await screen.getByRole('button', { name: 'Submit for review' }).click();
    await expect(screen.getByText('Submitted for review.')).toBeVisible();
    await screen.getByRole('button', { name: 'Approve' }).click();
    await expect(screen.getByText('Campaign approved.')).toBeVisible();

    // Delivery: hand off, test, schedule, unschedule, send, reconcile.
    const delivery = screen.locator('.campaignbridge-campaigns__delivery');
    await delivery
      .getByRole('button', { name: 'Create Mailchimp draft' })
      .click();
    await expect(
      delivery.getByText('The Mailchimp draft is ready.')
    ).toBeVisible();
    await expect(header).toContainText('In provider');

    await delivery.getByRole('button', { name: 'Send a test' }).click();
    const testDialog = operator.page.getByRole('dialog', {
      name: 'Send a test',
    });
    await testDialog.getByLabel('Test recipients').fill('qa@example.test');
    await testDialog.getByRole('button', { name: 'Send test' }).click();
    await expect(
      delivery.getByText('Test sent to 1 address(es).')
    ).toBeVisible();

    await delivery.getByRole('button', { name: 'Schedule' }).click();
    const schedule = operator.page.getByRole('dialog', {
      name: 'Schedule campaign',
    });
    // The confirmation names the audience and stays locked until confirmed.
    await expect(schedule).toContainText(audienceName);
    await expect(
      schedule.getByRole('button', { name: 'Schedule campaign' })
    ).toBeDisabled();
    await schedule.getByRole('checkbox').check();
    await schedule.getByRole('button', { name: 'Schedule campaign' }).click();
    await expect(
      delivery.getByText('The campaign is scheduled.')
    ).toBeVisible();
    await expect(header).toContainText('Scheduled');

    await delivery.getByRole('button', { name: 'Unschedule' }).click();
    await operator.page
      .getByRole('dialog', { name: 'Unschedule campaign' })
      .getByRole('button', { name: 'Unschedule campaign' })
      .click();
    await expect(
      delivery.getByText('The campaign is no longer scheduled.')
    ).toBeVisible();

    await delivery.getByRole('button', { name: 'Send now' }).click();
    const send = operator.page.getByRole('dialog', {
      name: 'Send campaign now',
    });
    await expect(send).toContainText(audienceName);
    await expect(
      send.getByRole('button', { name: 'Send campaign now' })
    ).toBeDisabled();
    await send.getByRole('checkbox').check();
    await send.getByRole('button', { name: 'Send campaign now' }).click();
    await expect(header).toContainText('Sending');

    // Mailchimp reports the send in progress, then finished.
    await delivery.getByRole('button', { name: 'Reconcile' }).click();
    await expect(
      delivery.getByText('Reconciled with what the provider reports.')
    ).toBeVisible();
    await delivery.getByRole('button', { name: 'Reconcile' }).click();
    await expect(header).toContainText('Sent');

    // One request of each kind reached Mailchimp; the history tells the story.
    expect((await mailchimp(page, {})).calls).toEqual({
      test: 1,
      schedule: 1,
      unschedule: 1,
      send: 1,
    });
    const timeline = screen.locator('.campaignbridge-campaigns__timeline');
    for (const event of [
      'Created',
      'Prepared for review',
      'Approved',
      'Provider draft created',
      'Scheduled',
      'Sent to the provider',
      'Reconciled with the provider',
    ]) {
      await expect(
        timeline.getByText(event, { exact: true }).first()
      ).toBeVisible();
    }
    await expect(operator.page.getByText('qa@example.test')).toHaveCount(0);

    // Back on the list, setup is complete and the campaign shows as sent.
    await screen.getByRole('link', { name: 'All campaigns' }).click();
    await expect(
      operator.page.getByRole('region', { name: 'You’re set up' })
    ).toBeVisible();
    await expect(
      operator.page.getByRole('row').filter({ hasText: title })
    ).toContainText('Sent');
  } finally {
    if (campaignId) await archiveCampaign(operator.page, campaignId);
    await operator.remove();
    if (templateId) await deleteTemplate(page, templateId);
    await mailchimp(page, { reset: true, fail: '' });
  }
});
