import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { expect } from './fixtures';

/**
 * Fail on critical or serious WCAG 2.1 AA violations inside one region.
 *
 * The reviewed-email preview is a sandboxed iframe without scripts, which axe
 * cannot enter; the compiled preview has its own accessibility check.
 */
export async function expectAccessible(
  page: Page,
  include: string,
  state: string
): Promise<void> {
  const results = await new AxeBuilder({ page })
    .include(include)
    .exclude('iframe[title="Reviewed email preview"]')
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  const blocking = results.violations.filter(violation =>
    ['critical', 'serious'].includes(violation.impact ?? '')
  );

  expect(
    blocking.map(
      violation =>
        `[${violation.impact}] ${violation.id}: ${violation.nodes
          .slice(0, 3)
          .map(node => node.target.join(' '))
          .join(', ')}`
    ),
    `Accessibility violations in the ${state} state`
  ).toEqual([]);
}
