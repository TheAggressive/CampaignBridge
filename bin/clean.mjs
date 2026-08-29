#!/usr/bin/env node

import { rmSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const dist = resolve(root, 'dist');

if (!dist.startsWith(`${root}/`)) {
  throw new Error('Refusing to clean a path outside the repository.');
}

rmSync(dist, { force: true, recursive: true });
