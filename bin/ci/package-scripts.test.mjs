import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import test from 'node:test';

const root = new URL('../../', import.meta.url);
const { scripts } = JSON.parse(readFileSync(new URL('package.json', root)));

// Check literal repo entrypoints, not shell syntax or dynamically loaded PHP classes.
test('package scripts reference existing entrypoints and config files', () => {
  for (const [name, command] of Object.entries(scripts)) {
    const paths = command.matchAll(
      /(?:^|[\s'"])((?:bin\/|src\/)[\w./-]+\.(?:mjs|js|ts|sh)|[\w.-]+\.config\.(?:mjs|js)|tsconfig[\w.-]*\.json)(?=$|[\s'"])/gu
    );
    for (const [, path] of paths) {
      assert.ok(existsSync(new URL(path, root)), `${name}: missing ${path}`);
    }
  }
});

test('watch and build use the same webpack configs', () => {
  for (const target of ['blocks', 'assets']) {
    const config = (command) => command.match(/--config\s+(\S+)/u)?.[1];
    assert.ok(config(scripts[`build:${target}`]));
    assert.equal(config(scripts[`start:${target}`]), config(scripts[`build:${target}`]));
  }
});
