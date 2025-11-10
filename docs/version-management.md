# Version Management System

## Overview

CampaignBridge uses **semantic versioning** with **automated version synchronization** to keep all version references in sync across the codebase.

## Version Source of Truth

**`package.json`** is the **single source of truth** for version numbers.

```json
{
  "version": "0.3.26"
}
```

## Version Locations

The version appears in **3 places** that must stay synchronized:

1. **`package.json`** - Source of truth
   ```json
   "version": "0.3.26"
   ```

2. **`campaignbridge.php`** - Plugin header (WordPress requirement)
   ```php
   /**
    * Version: 0.3.26
    */
   ```

3. **`campaignbridge.php`** - PHP constant (runtime usage)
   ```php
   public const VERSION = '0.3.26';
   ```

## Version Sync Script

The `version:sync` script automatically updates the PHP file from `package.json`:

```bash
pnpm version:sync
```

**What it does:**
1. Reads `package.json` to get current version
2. Updates plugin header: `* Version: X.X.X`
3. Updates PHP constant: `public const VERSION = 'X.X.X'`
4. Writes changes back to `campaignbridge.php`

## Semantic Versioning Rules

Versions follow **MAJOR.MINOR.PATCH** format:

- **MAJOR** (1.0.0): Breaking changes (API changes, incompatible updates)
- **MINOR** (0.1.0): New features (backward compatible)
- **PATCH** (0.0.1): Bug fixes (backward compatible)

## How Versions Are Bumped

### Option 1: Automated (Recommended) - Semantic Release

When you push to `master`, **semantic-release** automatically:

1. **Analyzes commits** since last release
2. **Determines version bump** based on commit messages:
   - `feat:` → MINOR bump (0.3.26 → 0.4.0)
   - `fix:` → PATCH bump (0.3.26 → 0.3.27)
   - `feat!:` or `BREAKING CHANGE:` → MAJOR bump (0.3.26 → 1.0.0)
3. **Updates package.json** with new version
4. **Runs `pnpm version:sync`** to update PHP file
5. **Updates CHANGELOG.md** with release notes
6. **Creates git tag** (e.g., `v0.4.0`)
7. **Creates GitHub release** with changelog
8. **Commits all changes** back to repository

**Commit Message Examples:**
```bash
feat: add new email template feature
# → 0.3.26 → 0.4.0 (MINOR bump)

fix: resolve file upload bug
# → 0.3.26 → 0.3.27 (PATCH bump)

feat!: refactor API to use REST endpoints
# → 0.3.26 → 1.0.0 (MAJOR bump - breaking change)
```

### Option 2: Manual Version Bump

If you need to manually bump version:

```bash
# Update package.json version
pnpm version patch  # 0.3.26 → 0.3.27
pnpm version minor  # 0.3.26 → 0.4.0
pnpm version major  # 0.3.26 → 1.0.0

# The preversion hook automatically runs version:sync
```

**Manual Process:**
1. `pnpm version <type>` updates `package.json`
2. `preversion` hook runs → `pnpm version:sync`
3. PHP file is automatically updated
4. Commit the changes

## Version Sync Workflow

### Automated Release Flow

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Developer pushes commits to master                       │
│    feat: add new feature                                     │
│    fix: resolve bug                                         │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. GitHub Actions CI/CD runs                                │
│    - Quality checks pass                                    │
│    - Tests pass                                             │
│    - Release job starts                                     │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. Semantic Release analyzes commits                        │
│    - Analyzes commit messages                                │
│    - Determines version bump (patch/minor/major)            │
│    - Calculates next version: 0.3.26 → 0.3.27              │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. Semantic Release updates files                           │
│    ✓ package.json: "0.3.27"                                 │
│    ✓ CHANGELOG.md: Adds release notes                       │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. @semantic-release/exec runs                              │
│    pnpm version:sync                                        │
│    ↓                                                         │
│    ✓ campaignbridge.php header: "Version: 0.3.27"          │
│    ✓ campaignbridge.php constant: VERSION = '0.3.27'        │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 6. @semantic-release/git commits all changes                │
│    - package.json                                           │
│    - campaignbridge.php                                     │
│    - CHANGELOG.md                                           │
│    Commits with: "chore(release): 0.3.27 [skip ci]"        │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 7. @semantic-release/github creates release                 │
│    - Creates git tag: v0.3.27                               │
│    - Creates GitHub release                                 │
│    - Includes changelog notes                               │
└─────────────────────────────────────────────────────────────┘
```

### Manual Build Flow

```
┌─────────────────────────────────────────────────────────────┐
│ Developer runs: pnpm build                                   │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 1. pnpm version:sync runs first                             │
│    Ensures PHP file matches package.json                    │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. Build process continues                                  │
│    - Build blocks                                           │
│    - Build interactivity                                    │
│    - Build assets                                           │
└─────────────────────────────────────────────────────────────┘
```

## Configuration Files

### `.releaserc.json`
Semantic release configuration that:
- Analyzes commits to determine version bumps
- Runs `version:sync` after updating `package.json`
- Commits updated files back to repository
- Creates GitHub releases

### `package.json` scripts
- `version:sync` - Syncs PHP file from package.json
- `preversion` - Runs before npm/pnpm version commands
- `build` - Runs version:sync before building

## Best Practices

### ✅ DO:
- Let semantic-release handle version bumps automatically
- Use conventional commit messages (`feat:`, `fix:`, etc.)
- Run `pnpm version:sync` manually if you edit `package.json` directly
- Always commit version changes together

### ❌ DON'T:
- Manually edit version numbers in `campaignbridge.php`
- Skip version synchronization
- Mix manual and automated versioning
- Edit version without syncing

## Troubleshooting

### Version Out of Sync

If versions get out of sync:

```bash
# Check current versions
grep -E "(version|Version|VERSION)" package.json campaignbridge.php

# Sync manually
pnpm version:sync

# Verify sync
grep -E "(version|Version|VERSION)" package.json campaignbridge.php
```

### Semantic Release Not Working

1. **Check commit messages** - Must follow conventional format
2. **Check branch** - Must be on `master` branch
3. **Check CI/CD logs** - Look for semantic-release errors
4. **Verify tokens** - `GITHUB_TOKEN` must be set in GitHub secrets

### Manual Version Update Needed

If you need to manually update version:

```bash
# 1. Update package.json
npm version patch  # or minor/major

# 2. Sync will run automatically via preversion hook
# 3. Commit the changes
git add package.json campaignbridge.php
git commit -m "chore: bump version to X.X.X"
```

## Version History

Check `CHANGELOG.md` for complete version history and release notes.






