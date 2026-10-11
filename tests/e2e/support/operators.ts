import type { Browser, BrowserContext, Page } from '@playwright/test';
import { BASE_URL } from './environment';
import { expect } from './fixtures';

export const CAMPAIGNS_PATH =
  '/wp-admin/admin.php?page=campaignbridge-campaigns';

/** Every CampaignBridge capability, as `Capabilities::ALL` names them. */
export const CAPABILITIES = {
  manage: 'campaignbridge_manage',
  manageConnections: 'campaignbridge_manage_connections',
  editTemplates: 'campaignbridge_edit_templates',
  createCampaigns: 'campaignbridge_create_campaigns',
  sendCampaigns: 'campaignbridge_send_campaigns',
  viewReports: 'campaignbridge_view_reports',
  testCampaigns: 'campaignbridge_test_campaigns',
} as const;

type ApiOptions = { path: string; method?: string; data?: unknown };

type ApiFetch = <T>(
  // eslint-disable-next-line no-unused-vars -- Documents the callable WordPress API contract.
  options: ApiOptions
) => Promise<T>;

/** Call the WordPress REST API as the page's signed-in user. */
export function api<T>(page: Page, options: ApiOptions): Promise<T> {
  return page.evaluate(
    request =>
      (
        globalThis as typeof globalThis & { wp: { apiFetch: ApiFetch } }
      ).wp.apiFetch(request),
    options
  ) as Promise<T>;
}

/** Whether a test-only control route is installed on this site. */
export function installed(page: Page, path: string): Promise<boolean> {
  return api(page, { path }).then(
    () => true,
    () => false
  );
}

export interface Simulation {
  campaigns: Record<string, unknown>;
  fail: string;
  calls: Record<'test' | 'schedule' | 'unschedule' | 'send', number>;
  created_connection?: boolean;
}

/** Drive the test-only simulated Mailchimp. */
export function mailchimp(
  page: Page,
  data: Record<string, unknown>
): Promise<Simulation> {
  return api<Simulation>(page, {
    path: '/campaignbridge-e2e/v1/mailchimp',
    method: 'POST',
    data,
  });
}

/** A signed-in throwaway user holding exactly the given capabilities. */
export interface Operator {
  id: number;
  page: Page;
  context: BrowserContext;
  /** Sign out and delete the account. */
  remove: () => Promise<void>;
}

export async function signInOperator(
  admin: Page,
  browser: Browser,
  capabilities: string[]
): Promise<Operator> {
  const account = await api<{ id: number; login: string; password: string }>(
    admin,
    {
      path: '/campaignbridge-e2e/v1/operators',
      method: 'POST',
      data: { capabilities },
    }
  );
  const context = await browser.newContext({
    baseURL: BASE_URL,
    storageState: { cookies: [], origins: [] },
  });
  const page = await context.newPage();
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(account.login);
  await page.locator('#user_pass').fill(account.password);
  await page.locator('#wp-submit').click();
  await expect(page.locator('#wpadminbar')).toBeVisible();

  return {
    id: account.id,
    page,
    context,
    remove: async () => {
      await context.close().catch(() => undefined);
      await api(admin, {
        path: `/campaignbridge-e2e/v1/operators/${account.id}`,
        method: 'DELETE',
      });
    },
  };
}

/** A published template that passes every check and can be handed to Mailchimp. */
export async function deliverableTemplate(
  page: Page,
  title: string
): Promise<number> {
  const template = await api<{ id: number }>(page, {
    path: '/wp/v2/cb_templates',
    method: 'POST',
    data: {
      title,
      status: 'publish',
      content:
        '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:paragraph --><p>Hello {{cb:subscriber.first_name}}.</p><!-- /wp:paragraph --><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->',
      meta: {
        campaignbridge_subject: `${title} subject`,
        campaignbridge_sender_name: 'E2E Sender',
        campaignbridge_sender_email: 'sender@example.test',
      },
    },
  });

  return template.id;
}

export async function deleteTemplate(page: Page, id: number): Promise<void> {
  await api(page, {
    path: `/wp/v2/cb_templates/${id}?force=true`,
    method: 'DELETE',
  }).catch(() => undefined);
}

/** The audience the simulated Mailchimp offers, refreshed when nothing is cached. */
export async function simulatedAudience(page: Page): Promise<string> {
  const path = '/campaignbridge/v1/providers/mailchimp/discovery/audiences';
  let audiences = await api<{ items: Array<{ id: string }> }>(page, { path });
  if (!audiences.items.length) {
    audiences = await api(page, { path: `${path}/refresh`, method: 'POST' });
  }

  return audiences.items[0].id;
}

/** Create a Mailchimp campaign and move it through the named local steps. */
export async function campaignThrough(
  page: Page,
  templateId: number,
  steps: Array<'snapshot' | 'submit' | 'approve'>
): Promise<{ id: string; version: number }> {
  const created = await api<{ campaign: { id: string; version: number } }>(
    page,
    {
      path: '/campaignbridge/v1/campaigns',
      method: 'POST',
      data: {
        template_id: templateId,
        provider: 'mailchimp',
        audience_reference: await simulatedAudience(page),
      },
    }
  );
  let version = created.campaign.version;
  for (const step of steps) {
    const result = await api<{ campaign: { version: number } }>(page, {
      path: `/campaignbridge/v1/campaigns/${created.campaign.id}/${step}`,
      method: 'POST',
      data: { expected_version: version },
    });
    version = result.campaign.version;
  }

  return { id: created.campaign.id, version };
}

/** Archive a campaign, ignoring one that is already archived or not reachable. */
export async function archiveCampaign(page: Page, id: string): Promise<void> {
  const current = await api<{ campaign: { version: number } }>(page, {
    path: `/campaignbridge/v1/campaigns/${id}`,
  }).catch(() => null);
  if (!current) return;
  await api(page, {
    path: `/campaignbridge/v1/campaigns/${id}/archive`,
    method: 'POST',
    data: { expected_version: current.campaign.version },
  }).catch(() => undefined);
}
