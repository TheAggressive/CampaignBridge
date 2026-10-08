import { expect, test, type Page } from './support/fixtures';

const CAMPAIGNS_PATH = '/wp-admin/admin.php?page=campaignbridge-campaigns';
const CONTENT =
  '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:paragraph --><p>Delivery check, {{cb:subscriber.first_name}}. <a href="{{cb:campaign.unsubscribe_url}}">Unsubscribe</a></p><!-- /wp:paragraph --><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->';

interface Simulation {
  fail: string;
  calls: Record<'test' | 'schedule' | 'unschedule' | 'send', number>;
}

type ApiFetch = <T>(
  // eslint-disable-next-line no-unused-vars -- Documents the callable WordPress API contract.
  options: { path: string; method?: string; data?: unknown }
) => Promise<T>;

function api<T>(
  page: Page,
  options: { path: string; method?: string; data?: unknown }
): Promise<T> {
  return page.evaluate(
    request =>
      (
        globalThis as typeof globalThis & { wp: { apiFetch: ApiFetch } }
      ).wp.apiFetch(request),
    options
  ) as Promise<T>;
}

/** Whether the test-only simulated Mailchimp is installed on this site. */
async function simulated(page: Page): Promise<boolean> {
  return api(page, { path: '/campaignbridge-e2e/v1/mailchimp' }).then(
    () => true,
    () => false
  );
}

const control = (page: Page, data: Record<string, unknown>) =>
  api<Simulation>(page, {
    path: '/campaignbridge-e2e/v1/mailchimp',
    method: 'POST',
    data,
  });

/** An approved Mailchimp campaign, ready for handoff, and its template. */
async function approvedCampaign(
  page: Page
): Promise<{ id: string; templateId: number }> {
  const template = await api<{ id: number }>(page, {
    path: '/wp/v2/cb_templates',
    method: 'POST',
    data: {
      title: `E2E delivery ${Date.now()}`,
      status: 'publish',
      content: CONTENT,
      meta: {
        campaignbridge_subject: 'Delivery subject',
        campaignbridge_sender_name: 'E2E Sender',
        campaignbridge_sender_email: 'sender@example.test',
      },
    },
  });
  const audiencesPath =
    '/campaignbridge/v1/providers/mailchimp/discovery/audiences';
  let audiences = await api<{ items: Array<{ id: string }> }>(page, {
    path: audiencesPath,
  });
  if (!audiences.items.length) {
    audiences = await api(page, {
      path: `${audiencesPath}/refresh`,
      method: 'POST',
    });
  }
  const created = await api<{ campaign: { id: string; version: number } }>(
    page,
    {
      path: '/campaignbridge/v1/campaigns',
      method: 'POST',
      data: {
        template_id: template.id,
        provider: 'mailchimp',
        audience_reference: audiences.items[0].id,
      },
    }
  );
  let version = created.campaign.version;
  for (const step of ['snapshot', 'submit', 'approve']) {
    const result = await api<{ campaign: { version: number } }>(page, {
      path: `/campaignbridge/v1/campaigns/${created.campaign.id}/${step}`,
      method: 'POST',
      data: { expected_version: version },
    });
    version = result.campaign.version;
  }

  return { id: created.campaign.id, templateId: template.id };
}

/** Skip unless the simulator is installed, then reset it. */
async function simulatedOnly(page: Page): Promise<void> {
  await page.goto(CAMPAIGNS_PATH);
  test.skip(
    !(await simulated(page)),
    'Runs only against the test-only simulated Mailchimp, never a real account.'
  );
  await control(page, { reset: true, connect: true });
}

async function cleanUp(page: Page, templateId: number): Promise<void> {
  await control(page, { reset: true, fail: '' });
  await api(page, {
    path: `/wp/v2/cb_templates/${templateId}?force=true`,
    method: 'DELETE',
  });
}

test('operators hand off, test, schedule, and send without duplicate delivery', async ({
  page,
}) => {
  await simulatedOnly(page);
  const { id, templateId } = await approvedCampaign(page);

  try {
    await page.goto(`${CAMPAIGNS_PATH}&campaign=${id}`);
    const delivery = page.locator('.campaignbridge-campaigns__delivery');

    await delivery
      .getByRole('button', { name: 'Create Mailchimp draft' })
      .click();
    await expect(
      delivery.getByText('The Mailchimp draft is ready.')
    ).toBeVisible();
    await expect(delivery.getByText(/e2e\w+ \(draft\)/)).toBeVisible();

    await delivery.getByRole('button', { name: 'Send a test' }).click();
    const testDialog = page.getByRole('dialog', { name: 'Send a test' });
    await testDialog.getByLabel('Test recipients').fill('qa@example.test');
    await testDialog.getByRole('button', { name: 'Send test' }).click();
    await expect(
      delivery.getByText('Test sent to 1 address(es).')
    ).toBeVisible();

    await delivery.getByRole('button', { name: 'Schedule' }).click();
    const schedule = page.getByRole('dialog', { name: 'Schedule campaign' });
    const submitSchedule = schedule.getByRole('button', {
      name: 'Schedule campaign',
    });
    await expect(submitSchedule).toBeDisabled();
    await schedule.getByRole('checkbox').check();
    // A double click is one request: the button locks and the key is reused.
    await submitSchedule.dblclick();
    await expect(
      delivery.getByText('The campaign is scheduled.')
    ).toBeVisible();
    expect((await control(page, {})).calls.schedule).toBe(1);

    await delivery.getByRole('button', { name: 'Unschedule' }).click();
    await page
      .getByRole('dialog', { name: 'Unschedule campaign' })
      .getByRole('button', { name: 'Unschedule campaign' })
      .click();
    await expect(
      delivery.getByText('The campaign is no longer scheduled.')
    ).toBeVisible();

    // Mailchimp applies the send but the response is lost.
    await control(page, { fail: 'send' });
    await delivery.getByRole('button', { name: 'Send now' }).click();
    const send = page.getByRole('dialog', { name: 'Send campaign now' });
    await send.getByRole('checkbox').check();
    await send.getByRole('button', { name: 'Send campaign now' }).click();
    await expect(
      delivery.getByText(/may have reached the audience/).first()
    ).toBeVisible();
    await expect(page.getByText('Needs reconciliation').first()).toBeVisible();
    for (const blocked of ['Send now', 'Schedule', 'Send a test']) {
      await expect(delivery.getByRole('button', { name: blocked })).toHaveCount(
        0
      );
    }
    expect((await control(page, {})).calls.send).toBe(1);

    // The history records the unconfirmed send as such.
    const timeline = page.locator('.campaignbridge-campaigns__timeline');
    await expect(
      timeline
        .locator('.campaignbridge-campaigns__event--unknown')
        .filter({ hasText: 'Sent to the provider' })
    ).toHaveCount(1);
    await expect(
      timeline.getByRole('row').filter({ hasText: 'Send' }).first()
    ).toContainText('Not confirmed');

    // Mailchimp reports the send in progress, then finished.
    await delivery.getByRole('button', { name: 'Reconcile' }).click();
    await expect(
      delivery.getByText('Reconciled with what the provider reports.')
    ).toBeVisible();
    await expect(
      page.locator('.campaignbridge-campaigns__detail-header')
    ).toContainText('Sending');
    await delivery.getByRole('button', { name: 'Reconcile' }).click();
    await expect(
      page.locator('.campaignbridge-campaigns__detail-header')
    ).toContainText('Sent');
    expect((await control(page, {})).calls.send).toBe(1);

    // The settled attempt and the reconciliation are both on record, and
    // the test recipient's address is not.
    await expect(
      timeline.getByRole('row').filter({ hasText: 'Send' }).first()
    ).toContainText('Succeeded');
    await expect(
      timeline.getByText('Reconciled with the provider').first()
    ).toBeVisible();
    await expect(page.getByText('qa@example.test')).toHaveCount(0);
  } finally {
    await cleanUp(page, templateId);
  }
});

test('a campaign deleted in Mailchimp stays unresolved with recovery steps', async ({
  page,
}) => {
  await simulatedOnly(page);
  const { id, templateId } = await approvedCampaign(page);

  try {
    await page.goto(`${CAMPAIGNS_PATH}&campaign=${id}`);
    const delivery = page.locator('.campaignbridge-campaigns__delivery');
    await delivery
      .getByRole('button', { name: 'Create Mailchimp draft' })
      .click();
    await expect(
      delivery.getByText('The Mailchimp draft is ready.')
    ).toBeVisible();
    const remoteId = (await control(page, {})) as unknown as {
      campaigns: Record<string, unknown>;
    };

    // Someone deletes the campaign in Mailchimp.
    await control(page, { forget: Object.keys(remoteId.campaigns)[0] });
    await delivery.getByRole('button', { name: 'Reconcile' }).click();

    await expect(
      delivery.getByText(/Mailchimp no longer has this campaign/)
    ).toBeVisible();
    await expect(
      page.locator('.campaignbridge-campaigns__detail-header')
    ).toContainText('In provider');
    await expect(delivery.getByText(/\(missing\)/)).toBeVisible();
    await expect(
      page
        .locator('.campaignbridge-campaigns__event--unknown')
        .filter({ hasText: 'Reconciled with the provider' })
    ).toHaveCount(1);
  } finally {
    await cleanUp(page, templateId);
  }
});
