#!/usr/bin/env node
/** Validate the squash subject before it reaches semantic-release. */

import { KNOWN_TYPES, parseTitle } from './pr-policy-rules.mjs';

const title = process.env.PR_TITLE ?? '';
const { valid, problem } = parseTitle(title);

if (!valid) {
  console.error(`Pull request title: ${title}`);
  console.error(`Not usable as a squash subject: ${problem}`);
  console.error('Use Conventional Commit form:');
  console.error('  fix(api): reject invalid input');
  console.error('  feat: add campaign approvals');
  console.error('  feat!: change the provider contract');
  console.error(`Types: ${KNOWN_TYPES.join(', ')}`);
  process.exit(1);
}

console.log(`check-pr-title: ok (${title})`);
