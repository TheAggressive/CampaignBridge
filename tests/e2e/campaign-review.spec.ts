import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from './support/fixtures';

const CAMPAIGNS_PATH = '/wp-admin/admin.php?page=campaignbridge-campaigns';
const VALID_CONTENT =
  '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:paragraph --><p>Review greeting, {{cb:subscriber.first_name}}</p><!-- /wp:paragraph --><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->';
const INVALID_CONTENT =
  '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:html --><div>raw</div><!-- /wp:html --><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->';

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

async function template(page: Page, title: string, content: string) {
  return api<{ id: number }>(page, {
    path: '/wp/v2/cb_templates',
    method: 'POST',
    data: {
      title,
      status: 'publish',
      content,
      meta: {
        campaignbridge_subject: 'Review subject line',
        campaignbridge_sender_name: 'Review Sender',
        campaignbridge_sender_email: 'sender@example.com',
      },
    },
  });
}

async function openNewCampaign(page: Page, title: string) {
  await page.goto(CAMPAIGNS_PATH);
  await page.getByRole('button', { name: 'New campaign' }).click();
  const dialog = page.getByRole('dialog', { name: 'New campaign' });
  await dialog.getByLabel('Email template').selectOption({ label: title });
  await dialog.getByLabel('Delivery').selectOption({ value: '' });
  await dialog.getByRole('button', { name: 'Create campaign' }).click();
  await expect(page).toHaveURL(/&campaign=campaign-/);
  await expect(page.getByRole('heading', { name: title })).toBeVisible();
}

test('operators review the frozen email and take it through approval', async ({
  page,
}) => {
  await page.goto(CAMPAIGNS_PATH);
  const title = `E2E review ${Date.now()}`;
  const created = await template(page, title, VALID_CONTENT);
  const screen = page.locator('#campaignbridge-campaigns-root');

  try {
    await openNewCampaign(page, title);
    await screen.getByRole('button', { name: 'Prepare for review' }).click();
    await expect(
      screen.getByText('The email is ready for review.')
    ).toBeVisible();
    await expect(screen.getByText('Review subject line')).toBeVisible();

    const frame = page.frameLocator('iframe[title="Reviewed email preview"]');
    await expect(frame.getByText(/Review greeting/)).toBeVisible();
    await expect(frame.getByText(/\{\{cb:/)).toHaveCount(0);
    await screen.getByLabel('Show sample personalization').uncheck();
    await expect(
      frame.getByText(/\{\{cb:subscriber\.first_name\}\}/)
    ).toBeVisible();

    // Live edits after review never reach the reviewed email.
    await api(page, {
      path: `/wp/v2/cb_templates/${created.id}`,
      method: 'POST',
      data: { content: VALID_CONTENT.replace('Review greeting', 'Live edit') },
    });
    await page.reload();
    await expect(frame.getByText(/Review greeting/)).toBeVisible();
    await expect(frame.getByText(/Live edit/)).toHaveCount(0);

    const accessibility = await new AxeBuilder({ page })
      .include('#campaignbridge-campaigns-root')
      .exclude('iframe')
      .analyze();
    expect(
      accessibility.violations.filter(violation =>
        ['critical', 'serious'].includes(violation.impact ?? '')
      )
    ).toEqual([]);

    await screen.getByRole('button', { name: 'Submit for review' }).click();
    await expect(screen.getByText('Submitted for review.')).toBeVisible();
    await expect(screen.getByText('Ready for review').first()).toBeVisible();

    await screen.getByRole('button', { name: 'Approve' }).click();
    await expect(screen.getByText('Campaign approved.')).toBeVisible();
    await expect(
      screen.getByRole('button', { name: 'Revoke approval' })
    ).toBeVisible();

    // A change made elsewhere makes this page's version stale.
    const id = new URL(page.url()).searchParams.get('campaign') ?? '';
    const current = await api<{ campaign: { version: number } }>(page, {
      path: `/campaignbridge/v1/campaigns/${id}`,
    });
    await api(page, {
      path: `/campaignbridge/v1/campaigns/${id}/revoke-approval`,
      method: 'POST',
      data: { expected_version: current.campaign.version },
    });
    await screen.getByRole('button', { name: 'Revoke approval' }).click();
    await expect(
      screen.getByText(/This campaign changed since you opened it/)
    ).toBeVisible();
    await screen.getByRole('button', { name: 'Reload' }).click();
    await expect(screen.getByRole('button', { name: 'Approve' })).toBeVisible();
  } finally {
    await api(page, {
      path: `/wp/v2/cb_templates/${created.id}?force=true`,
      method: 'DELETE',
    });
  }
});

test('an invalid template shows its problems and cannot be prepared for review', async ({
  page,
}) => {
  await page.goto(CAMPAIGNS_PATH);
  const title = `E2E invalid ${Date.now()}`;
  const created = await template(page, title, INVALID_CONTENT);
  const screen = page.locator('#campaignbridge-campaigns-root');

  try {
    await openNewCampaign(page, title);
    await screen.getByRole('button', { name: 'Prepare for review' }).click();
    await expect(screen.getByText(/Error:/).first()).toBeVisible();
    await expect(
      screen.getByRole('button', { name: 'Submit for review' })
    ).toHaveCount(0);
    await expect(
      screen.getByRole('button', { name: 'Prepare for review' })
    ).toBeVisible();
  } finally {
    await api(page, {
      path: `/wp/v2/cb_templates/${created.id}?force=true`,
      method: 'DELETE',
    });
  }
});
