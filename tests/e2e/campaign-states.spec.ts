import type { Route } from '@playwright/test';
import { expectAccessible } from './support/axe';
import { expect, test, type Page } from './support/fixtures';
import {
  api,
  archiveCampaign,
  CAMPAIGNS_PATH,
  CAPABILITIES,
  deleteTemplate,
  installed,
  signInOperator,
  type Operator,
} from './support/operators';

const ROOT = '#campaignbridge-campaigns-root';
const VALID_CONTENT =
  '<!-- wp:campaignbridge/container --><!-- wp:campaignbridge/section --><!-- wp:paragraph --><p>States check.</p><!-- /wp:paragraph --><!-- /wp:campaignbridge/section --><!-- /wp:campaignbridge/container -->';

test.use({ actionTimeout: 20_000 });

/** The campaign collection read (not one campaign); one matcher so unroute finds it. */
const collection = (url: URL) =>
  /campaignbridge\/v1\/campaigns(\?|&|$)/.test(decodeURIComponent(url.href));

async function publishedTemplate(page: Page, title: string): Promise<number> {
  const template = await api<{ id: number }>(page, {
    path: '/wp/v2/cb_templates',
    method: 'POST',
    data: {
      title,
      status: 'publish',
      content: VALID_CONTENT,
      meta: {
        campaignbridge_subject: `${title} subject`,
        campaignbridge_sender_name: 'States Sender',
        campaignbridge_sender_email: 'sender@example.test',
      },
    },
  });

  return template.id;
}

/** Press Tab until the focused element has the given accessible text. */
async function tabTo(page: Page, name: string, limit = 80): Promise<void> {
  const seen: string[] = [];
  for (let presses = 0; presses < limit; presses++) {
    await page.keyboard.press('Tab');
    const focused = await page.evaluate(() => {
      const element = globalThis.document.activeElement as HTMLElement | null;
      if (!element) return '';
      if (element.tagName === 'IFRAME')
        return `iframe:${element.getAttribute('title') ?? ''}`;
      const label = element.id
        ? globalThis.document.querySelector(`label[for="${element.id}"]`)
        : null;
      return (
        element.getAttribute('aria-label') ??
        label?.textContent ??
        element.textContent ??
        ''
      ).trim();
    });
    if (focused === name) return;
    seen.push(
      focused ||
        (await page.evaluate(() => {
          const element = globalThis.document.activeElement;
          return `<${element?.tagName.toLowerCase()} ${element?.className}>`;
        }))
    );
  }
  throw new Error(
    `Keyboard focus never reached "${name}". Focused: ${seen.join(' | ')}`
  );
}

/** Move a focused select to the option with the given label using arrow keys. */
async function arrowToOption(page: Page, label: string): Promise<void> {
  for (let presses = 0; presses < 40; presses++) {
    const current = await page.evaluate(() => {
      const select = globalThis.document.activeElement as HTMLSelectElement;
      return select.options[select.selectedIndex]?.textContent?.trim() ?? '';
    });
    if (current === label) return;
    await page.keyboard.press('ArrowDown');
  }
  throw new Error(`The focused select has no option "${label}".`);
}

test.describe('campaign screen states', () => {
  test.describe.configure({ timeout: 90_000 });
  let author: Operator | null = null;
  let templateId = 0;
  const campaigns: string[] = [];

  test.beforeEach(async ({ page, browser }) => {
    await page.goto(CAMPAIGNS_PATH);
    test.skip(
      !(await installed(page, '/campaignbridge-e2e/v1/operators')),
      'Runs only with the test-only operator accounts.'
    );
    templateId = await publishedTemplate(page, `States ${Date.now()}`);
    author = await signInOperator(page, browser, [
      CAPABILITIES.editTemplates,
      CAPABILITIES.createCampaigns,
    ]);
  });

  test.afterEach(async ({ page }) => {
    for (const id of campaigns.splice(0)) await archiveCampaign(page, id);
    await author?.remove();
    author = null;
    if (templateId) await deleteTemplate(page, templateId);
    templateId = 0;
  });

  test('empty, loading, and offline lists are announced and accessible', async () => {
    const page = author!.page;
    let release: () => void = () => undefined;
    const held = new Promise<void>(resolve => (release = resolve));
    const hold = async (route: Route) => {
      await held;
      await route.continue().catch(() => undefined);
    };
    await page.route(collection, hold);

    await page.goto(CAMPAIGNS_PATH);
    const root = page.locator(ROOT);
    await expect(root.locator('.components-spinner').first()).toBeVisible();
    await expectAccessible(page, ROOT, 'loading');
    release();
    await expect(root.getByText('No campaigns yet.')).toBeVisible();
    await page.unroute(collection, hold);
    await expect(
      root.getByRole('button', { name: 'Create your first campaign' })
    ).toBeVisible();
    await expectAccessible(page, ROOT, 'empty');

    // The network drops: the failure says so and offers a retry.
    const offline = (route: Route) => route.abort('internetdisconnected');
    await page.route(collection, offline);
    await page.reload();
    const failure = root.locator('.components-notice.is-error');
    await expect(failure).toBeVisible();
    await expect(
      failure.getByRole('button', { name: 'Try again' })
    ).toBeVisible();
    await expectAccessible(page, ROOT, 'offline');

    await page.unroute(collection, offline);
    await failure.getByRole('button', { name: 'Try again' }).click();
    await expect(root.getByText('No campaigns yet.')).toBeVisible();
    await expect(failure).toHaveCount(0);
  });

  test('a stale campaign and a denied campaign explain themselves accessibly', async ({
    page: admin,
  }) => {
    const page = author!.page;
    await page.goto(CAMPAIGNS_PATH);
    const created = await api<{ campaign: { id: string; version: number } }>(
      page,
      {
        path: '/campaignbridge/v1/campaigns',
        method: 'POST',
        data: { template_id: templateId },
      }
    );
    const id = created.campaign.id;
    campaigns.push(id);
    const snapshot = await api<{ campaign: { version: number } }>(page, {
      path: `/campaignbridge/v1/campaigns/${id}/snapshot`,
      method: 'POST',
      data: { expected_version: created.campaign.version },
    });

    await page.goto(`${CAMPAIGNS_PATH}&campaign=${id}`);
    const root = page.locator(ROOT);
    await expect(
      root.getByRole('button', { name: 'Submit for review' })
    ).toBeVisible();
    await expectAccessible(page, ROOT, 'review');

    // Someone else submits it while this page is open.
    await api(page, {
      path: `/campaignbridge/v1/campaigns/${id}/submit`,
      method: 'POST',
      data: { expected_version: snapshot.campaign.version },
    });
    await root.getByRole('button', { name: 'Submit for review' }).click();
    await expect(
      root.getByText(/This campaign changed since you opened it/)
    ).toBeVisible();
    await expect(root.getByRole('button', { name: 'Reload' })).toBeVisible();
    await expectAccessible(page, ROOT, 'stale');
    await root.getByRole('button', { name: 'Reload' }).click();
    await expect(root.getByText('Ready for review').first()).toBeVisible();

    // Another owner's campaign is refused without revealing it.
    const foreign = await api<{ campaign: { id: string } }>(admin, {
      path: '/campaignbridge/v1/campaigns',
      method: 'POST',
      data: { template_id: templateId },
    });
    campaigns.push(foreign.campaign.id);
    await page.goto(`${CAMPAIGNS_PATH}&campaign=${foreign.campaign.id}`);
    await expect(root.locator('.components-notice.is-error')).toBeVisible();
    await expect(
      root.getByRole('link', { name: /All campaigns/ })
    ).toBeVisible();
    await expectAccessible(page, ROOT, 'permission denied');
  });

  test('a keyboard-only operator creates and prepares a campaign', async ({
    page: admin,
  }) => {
    const page = author!.page;
    const title = await admin.evaluate(
      id =>
        (globalThis as typeof globalThis & { wp: any }).wp
          .apiFetch({ path: `/wp/v2/cb_templates/${id}?context=edit` })
          .then((template: { title: { raw: string } }) => template.title.raw),
      templateId
    );
    await page.goto(CAMPAIGNS_PATH);
    await expect(
      page.locator(ROOT).getByText('No campaigns yet.')
    ).toBeVisible();

    await tabTo(page, 'New campaign');
    await page.keyboard.press('Enter');
    const dialog = page.getByRole('dialog', { name: 'New campaign' });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByLabel('Email template')).toBeEnabled();

    // Escape closes the dialog and returns focus to the page.
    await page.keyboard.press('Escape');
    await expect(dialog).toHaveCount(0);
    await tabTo(page, 'New campaign');
    await page.keyboard.press('Enter');
    await expect(dialog.getByLabel('Email template')).toBeEnabled();

    await tabTo(page, 'Email template');
    await arrowToOption(page, title);
    await tabTo(page, 'Delivery');
    await arrowToOption(page, 'HTML export (no provider)');
    await tabTo(page, 'Create campaign');
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/&campaign=campaign-/);
    campaigns.push(new URL(page.url()).searchParams.get('campaign') ?? '');
    await expect(
      page.locator(ROOT).getByRole('button', { name: 'Prepare for review' })
    ).toBeVisible();

    await tabTo(page, 'Prepare for review');
    await page.keyboard.press('Enter');
    await expect(
      page.locator(ROOT).getByText('The email is ready for review.')
    ).toBeVisible();
  });

  test('campaign screens honour reduced motion', async () => {
    const page = author!.page;
    await page.emulateMedia({ reducedMotion: 'reduce' });
    let release: () => void = () => undefined;
    const held = new Promise<void>(resolve => (release = resolve));
    const hold = async (route: Route) => {
      await held;
      await route.continue().catch(() => undefined);
    };
    await page.route(collection, hold);
    await page.goto(CAMPAIGNS_PATH);
    await expect(
      page.locator(ROOT).locator('.components-spinner').first()
    ).toBeVisible();

    // Nothing in the screen animates or transitions for longer than a frame.
    const moving = await page.evaluate(root => {
      const seconds = (value: string) =>
        Math.max(
          ...value
            .split(',')
            .map(part =>
              part.trim().endsWith('ms')
                ? Number.parseFloat(part) / 1000
                : Number.parseFloat(part) || 0
            )
        );
      return [...globalThis.document.querySelectorAll(`${root} *`)]
        .map(element => {
          const style = globalThis.getComputedStyle(element);
          const animated =
            style.animationName !== 'none' &&
            seconds(style.animationDuration) > 0.02;
          const transitioned = seconds(style.transitionDuration) > 0.02;
          return animated || transitioned
            ? `${element.tagName.toLowerCase()}.${[...element.classList].join('.')}`
            : null;
        })
        .filter(Boolean);
    }, ROOT);
    release();
    await page.unroute(collection, hold);

    expect(moving).toEqual([]);
  });

  test('every campaign screen string is translatable', async () => {
    const page = author!.page;
    await page.goto(`${CAMPAIGNS_PATH}&campaignbridge_e2e_pseudo=1`);
    const root = page.locator(ROOT);

    // A real translation arrives through WordPress script translations.
    await expect(
      root.getByRole('button', { name: '⟦Nouvelle campagne⟧' })
    ).toBeVisible();
    await expect(root.getByText('⟦No campaigns yet.⟧')).toBeVisible();

    const untranslated = (selector: string) =>
      page.evaluate(
        ({ root, selector }) =>
          [
            ...(globalThis.document
              .querySelector(root)
              ?.querySelectorAll(selector) ?? []),
          ]
            .filter(element => !element.closest('.dataviews-wrapper'))
            .map(element => element.textContent?.trim() ?? '')
            .filter(text => text !== '' && !text.includes('⟦')),
        { root: ROOT, selector }
      );
    expect(await untranslated('h2, h3, h4, button, label, dt')).toEqual([]);

    await root.getByRole('button', { name: '⟦Nouvelle campagne⟧' }).click();
    const dialog = page.getByRole('dialog', { name: '⟦Nouvelle campagne⟧' });
    await expect(dialog).toBeVisible();
    const dialogStrings = await dialog
      .locator('label, button:not([aria-label="Close"])')
      .allTextContents();
    expect(
      dialogStrings
        .map(text => text.trim())
        .filter(text => text && !text.includes('⟦'))
    ).toEqual([]);
  });
});
