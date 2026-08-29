import fs from 'node:fs';

const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
const composerJson = JSON.parse(fs.readFileSync('composer.json', 'utf8'));
const dependencySections = [
  'dependencies',
  'devDependencies',
  'optionalDependencies',
  'peerDependencies',
];
const nonRegistrySpecifier =
  /^(?:bitbucket:|file:|git(?:\+|:)|github:|gitlab:|https?:|link:|ssh:)/i;
const violations = [];
let npmDependencyCount = 0;

for (const section of dependencySections) {
  for (const [name, specifier] of Object.entries(packageJson[section] ?? {})) {
    npmDependencyCount += 1;

    if (nonRegistrySpecifier.test(specifier)) {
      violations.push(`package.json ${section}.${name}: ${specifier}`);
    }
  }
}

if (Array.isArray(composerJson.repositories)) {
  for (const repository of composerJson.repositories) {
    if (repository && repository.packagist === false) {
      continue;
    }

    violations.push(
      `composer.json repositories: ${JSON.stringify(repository)}`
    );
  }
}

if (violations.length > 0) {
  console.error(
    'Non-registry dependency sources require an explicit policy change:'
  );
  for (const violation of violations) {
    console.error(`- ${violation}`);
  }
  process.exit(1);
}

const composerDependencyCount =
  Object.keys(composerJson.require ?? {}).length +
  Object.keys(composerJson['require-dev'] ?? {}).length;

console.log(
  `Dependency sources verified (${npmDependencyCount} npm, ${composerDependencyCount} Composer; public registries only).`
);
