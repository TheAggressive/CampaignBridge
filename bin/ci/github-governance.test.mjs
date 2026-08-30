import { strict as assert } from 'node:assert';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import { REQUIRED_CHECKS } from './pr-policy-rules.mjs';

const ruleset = JSON.parse(
  readFileSync('.github/rulesets/release-branches.json', 'utf8')
);
const releaseConfig = JSON.parse(readFileSync('.releaserc.json', 'utf8'));
const ciWorkflow = readFileSync('.github/workflows/ci.yml', 'utf8');
const policyWorkflow = readFileSync('.github/workflows/pr-policy.yml', 'utf8');

test('ruleset required checks match the policy bootstrap guard', () => {
  const requiredRule = ruleset.rules.find(
    rule => rule.type === 'required_status_checks'
  );
  const contexts = requiredRule.parameters.required_status_checks.map(
    check => check.context
  );

  assert.deepEqual([...contexts].sort(), [...REQUIRED_CHECKS].sort());
});

test('release branch protection is active with no bypass actors', () => {
  assert.equal(ruleset.enforcement, 'active');
  assert.deepEqual(ruleset.bypass_actors, []);
  assert.deepEqual(ruleset.conditions.ref_name.include, ['refs/heads/master']);
  assert.ok(ruleset.rules.some(rule => rule.type === 'required_signatures'));
  assert.ok(ruleset.rules.some(rule => rule.type === 'pull_request'));
});

test('CodeQL enforcement records the temporary critical threshold', () => {
  const scanning = ruleset.rules.find(rule => rule.type === 'code_scanning');
  const [tool] = scanning.parameters.code_scanning_tools;

  assert.equal(tool.tool, 'CodeQL');
  assert.equal(tool.alerts_threshold, 'errors');
  assert.equal(tool.security_alerts_threshold, 'critical');
});

test('semantic-release does not push metadata through protected master', () => {
  assert.ok(releaseConfig.plugins.includes('@semantic-release/changelog'));
  assert.ok(releaseConfig.plugins.includes('@semantic-release/github'));
  assert.equal(releaseConfig.plugins.includes('@semantic-release/git'), false);
  assert.match(ciWorkflow, /version-sync:/u);
  assert.match(ciWorkflow, /sign-commits: true/u);
  assert.match(ciWorkflow, /gh pr merge "\$\{number\}" --auto --squash/u);
});

test('privileged PR policy jobs execute only protected base code', () => {
  assert.match(policyWorkflow, /pull_request_target:/u);
  assert.match(policyWorkflow, /workflow_run:/u);
  assert.match(policyWorkflow, /ref: master/u);
  assert.doesNotMatch(policyWorkflow, /ref: \$\{\{ github\.head_ref \}\}/u);
  assert.doesNotMatch(policyWorkflow, /pull\/\$\{\{/u);
});
