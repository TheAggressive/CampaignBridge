import path from 'node:path';

export const BASE_URL = (
  process.env.CB_E2E_BASE_URL ?? 'http://localhost:8882'
).replace(/\/$/u, '');

export const AUTH_STATE_PATH =
  process.env.CB_E2E_STORAGE_STATE ??
  path.resolve('.cache/e2e/storage-state.json');
