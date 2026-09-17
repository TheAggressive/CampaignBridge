import { test as base } from '@playwright/test';

export { expect } from '@playwright/test';
export type { Locator, Page, Request } from '@playwright/test';

/** Shared authenticated Playwright test without legacy editor state. */
export const test = base;
