import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parseCatalog, validateChange } from '../build-google-font-catalog.mjs';

test('catalog generation is deterministic and sorted', () => {
  const source =
    'Zulu,,/Sans/Humanist,20\nAlpha,wght@700,/Serif/Modern,80\nAlpha,wght@400,/Serif/Modern,70\n';
  const first = parseCatalog(source);
  assert.deepEqual(first, parseCatalog(source));
  assert.deepEqual(
    first.map(font => font.family),
    ['Alpha', 'Zulu']
  );
});

test('committed catalog matches provenance and checksum', () => {
  const catalogText = readFileSync(
    'includes/Services/Email/google-fonts-catalog.json',
    'utf8'
  );
  const catalog = JSON.parse(catalogText);
  const source = JSON.parse(
    readFileSync(
      'includes/Services/Email/google-fonts-catalog.source.json',
      'utf8'
    )
  );
  validateChange([], catalog);
  assert.equal(catalog.length, source.familyCount);
  assert.match(source.commit, /^[a-f0-9]{40}$/u);
  assert.equal(
    createHash('sha256').update(catalogText).digest('hex'),
    source.sha256
  );
});
