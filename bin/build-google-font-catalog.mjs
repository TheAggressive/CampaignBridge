import { createHash } from 'node:crypto';
import fs from 'node:fs/promises';
import { pathToFileURL } from 'node:url';

const REPOSITORY = 'google/fonts';
const SOURCE_PATH = 'tags/all/families.csv';
const ROOT = new URL('../', import.meta.url);
const TARGET = new URL(
  'includes/Services/Email/google-fonts-catalog.json',
  ROOT
);
const PROVENANCE = new URL(
  'includes/Services/Email/google-fonts-catalog.source.json',
  ROOT
);
const CHECKSUM = new URL(
  'includes/Services/Email/google-fonts-catalog.sha256',
  ROOT
);

export function parseCatalog(csv) {
  const families = new Map();
  for (const line of csv.split(/\r?\n/u)) {
    const [family, axes = '', tag = ''] = line.split(',');
    if (!family) continue;
    const current = families.get(family) ?? {
      family,
      category: 'sans-serif',
      variants: new Set(['regular']),
    };
    const category = tag.match(
      /^\/(Sans|Serif|Slab|Script|Display|Mono)\//u
    )?.[1];
    if (category) {
      current.category =
        category === 'Serif' || category === 'Slab'
          ? 'serif'
          : category === 'Script'
            ? 'handwriting'
            : category === 'Display'
              ? 'display'
              : category === 'Mono'
                ? 'monospace'
                : 'sans-serif';
    }
    for (const weight of axes.matchAll(/(?:wght@|^)([1-9]00)(?:$|[^0-9])/gu)) {
      current.variants.add(weight[1] === '400' ? 'regular' : weight[1]);
    }
    families.set(family, current);
  }
  return [...families.values()]
    .sort((a, b) => a.family.localeCompare(b.family, 'en'))
    .map(font => ({
      family: font.family,
      category: font.category,
      variants: [...font.variants].sort(),
    }));
}

export function validateChange(previous, next, allowLargeChange = false) {
  if (next.length < 1000) {
    throw new Error(
      `Refusing incomplete catalog with ${next.length} families.`
    );
  }
  for (const font of next) {
    if (
      !font.family ||
      !['sans-serif', 'serif', 'handwriting', 'display', 'monospace'].includes(
        font.category
      ) ||
      !Array.isArray(font.variants)
    ) {
      throw new Error(`Invalid catalog record: ${JSON.stringify(font)}`);
    }
  }
  if (previous.length && !allowLargeChange) {
    const delta = Math.abs(next.length - previous.length) / previous.length;
    if (delta > 0.1) {
      throw new Error(
        `Catalog changed by ${(delta * 100).toFixed(1)}%; review with --allow-large-change.`
      );
    }
  }
}

async function latestCommit() {
  const response = await fetch(
    `https://api.github.com/repos/${REPOSITORY}/commits/main`,
    { headers: { Accept: 'application/vnd.github+json' } }
  );
  if (!response.ok)
    throw new Error(`Commit lookup failed (${response.status}).`);
  const data = await response.json();
  if (!/^[a-f0-9]{40}$/u.test(data.sha ?? '')) {
    throw new Error('Google Fonts returned an invalid commit SHA.');
  }
  return {
    commit: data.sha,
    committedAt: data.commit?.committer?.date ?? null,
  };
}

async function main() {
  const update = process.argv.includes('--latest');
  const allowLargeChange = process.argv.includes('--allow-large-change');
  const previous = JSON.parse(await fs.readFile(TARGET, 'utf8'));
  const pinned = JSON.parse(await fs.readFile(PROVENANCE, 'utf8'));
  const source = update
    ? await latestCommit()
    : { commit: pinned.commit, committedAt: pinned.committedAt };
  const url = `https://raw.githubusercontent.com/${REPOSITORY}/${source.commit}/${SOURCE_PATH}`;
  const response = await fetch(url);
  if (!response.ok)
    throw new Error(`Catalog download failed (${response.status}).`);
  const catalog = parseCatalog(await response.text());
  validateChange(previous, catalog, allowLargeChange);

  const serialized = `${JSON.stringify(catalog)}\n`;
  const checksum = createHash('sha256').update(serialized).digest('hex');
  await fs.writeFile(TARGET, serialized);
  await fs.writeFile(
    PROVENANCE,
    `${JSON.stringify(
      {
        schemaVersion: 1,
        repository: REPOSITORY,
        path: SOURCE_PATH,
        commit: source.commit,
        committedAt: source.committedAt,
        familyCount: catalog.length,
        sha256: checksum,
      },
      null,
      2
    )}\n`
  );
  await fs.writeFile(CHECKSUM, `${checksum}  google-fonts-catalog.json\n`);
  console.log(`Wrote ${catalog.length} families from ${source.commit}.`);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
  await main();
}
