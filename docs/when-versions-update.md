# When Versions Update

## Overview

Version updates happen **automatically** when specific conditions are met. The system will **NOT** update versions on every push - only when there are new commits that warrant a release.

## Trigger Conditions

### 1. Branch Requirement
✅ **Must push to `master` branch**

```yaml
# From .github/workflows/ci.yml
if: github.ref == 'refs/heads/master' && github.event_name == 'push'
```

**What this means:**
- ✅ Push to `master` → Release job runs
- ❌ Push to other branches → No release
- ❌ Pull request → No release (even if targeting master)
- ❌ Manual workflow dispatch → No release (unless on master)

### 2. CI/CD Pipeline Status
✅ **Quality and test jobs must pass first**

The release job depends on:
1. `quality` job (code quality checks)
2. `test` job (unit tests)

```
quality → test → release
```

If either fails, release is skipped.

### 3. Commit Message Requirements
✅ **Must have commits with conventional commit messages**

Semantic-release analyzes commits since the **last git tag** and only bumps version if it finds:

#### Version Bump Triggers

| Commit Type | Version Bump | Example |
|------------|--------------|---------|
| `feat:` | MINOR (0.3.26 → 0.4.0) | `feat: add new email template` |
| `fix:` | PATCH (0.3.26 → 0.3.27) | `fix: resolve file upload bug` |
| `feat!:` or `BREAKING CHANGE:` | MAJOR (0.3.26 → 1.0.0) | `feat!: refactor API endpoints` |
| `perf:` | PATCH | `perf: optimize database queries` |
| `refactor:` | PATCH | `refactor: clean up form handler` |

#### Commits That DON'T Trigger Releases

| Commit Type | Effect |
|------------|--------|
| `chore:` | No version bump |
| `docs:` | No version bump |
| `style:` | No version bump |
| `test:` | No version bump |
| `ci:` | No version bump |
| `build:` | No version bump |

**Note:** If all commits are `chore:`, `docs:`, etc., semantic-release will **skip** the release.

### 4. New Commits Since Last Release
✅ **Must have commits since the last git tag**

Semantic-release compares commits against the **latest git tag** (e.g., `v0.3.26`).

- ✅ New commits since last tag → Analyzes and potentially releases
- ❌ No new commits → No release (already released)
- ❌ Same commits as last release → No release

## Complete Flow Example

### Scenario 1: Version Update Happens ✅

```
1. Current version: 0.3.26
2. Developer commits:
   git commit -m "feat: add new email template"
   git commit -m "fix: resolve upload bug"
3. Push to master:
   git push origin master
4. CI/CD runs:
   ✓ quality checks pass
   ✓ tests pass
5. Semantic-release:
   ✓ Analyzes commits (feat + fix)
   ✓ Determines: MINOR bump (feat takes precedence)
   ✓ Updates: 0.3.26 → 0.4.0
   ✓ Syncs PHP file
   ✓ Creates tag: v0.4.0
   ✓ Creates GitHub release
```

### Scenario 2: Version Update Does NOT Happen ❌

```
1. Current version: 0.3.26
2. Developer commits:
   git commit -m "chore: update dependencies"
   git commit -m "docs: update README"
3. Push to master:
   git push origin master
4. CI/CD runs:
   ✓ quality checks pass
   ✓ tests pass
5. Semantic-release:
   ✓ Analyzes commits (chore + docs)
   ✗ No release needed (no version bump)
   ✗ No new version created
   ✗ No tag created
```

### Scenario 3: Version Update Does NOT Happen ❌ (Wrong Branch)

```
1. Current version: 0.3.26
2. Developer commits:
   git commit -m "feat: add new feature"
3. Push to feature branch:
   git push origin feature/new-feature
4. CI/CD runs:
   ✓ quality checks pass
   ✓ tests pass
   ✗ Release job skipped (not on master)
```

### Scenario 4: Version Update Does NOT Happen ❌ (Tests Fail)

```
1. Current version: 0.3.26
2. Developer commits:
   git commit -m "feat: add new feature"
3. Push to master:
   git push origin master
4. CI/CD runs:
   ✓ quality checks pass
   ✗ tests fail
   ✗ Release job skipped (tests must pass)
```

## When Semantic-Release Runs

### Automatic (Recommended)
- **On every push to `master`** (after CI passes)
- Semantic-release analyzes commits and decides if release is needed
- No manual intervention required

### Manual Trigger (Not Recommended)
You can manually run semantic-release locally, but it's not recommended:

```bash
# Only works if you have GITHUB_TOKEN set
pnpm semantic-release
```

**Note:** This is mainly for testing/debugging. The CI/CD workflow is the intended way.

## Version Bump Logic

Semantic-release uses this logic to determine version bumps:

### Priority Order
1. **MAJOR** - If ANY commit has `!` or `BREAKING CHANGE:`
2. **MINOR** - If ANY commit has `feat:` (and no breaking changes)
3. **PATCH** - If commits have `fix:`, `perf:`, `refactor:` (and no feat/breaking)

### Examples

```bash
# Scenario A: Multiple commits
feat: add template system
fix: resolve bug
→ Result: MINOR (0.3.26 → 0.4.0) - feat takes precedence

# Scenario B: Breaking change
feat!: change API structure
→ Result: MAJOR (0.3.26 → 1.0.0) - breaking change

# Scenario C: Only fixes
fix: bug 1
fix: bug 2
→ Result: PATCH (0.3.26 → 0.3.27)

# Scenario D: Mixed with chores
feat: new feature
chore: update dependencies
docs: update README
→ Result: MINOR (0.3.26 → 0.4.0) - feat triggers minor
```

## Checking If Release Will Happen

### Before Pushing
Check your commits since last tag:

```bash
# See commits since last tag
git log $(git describe --tags --abbrev=0)..HEAD --oneline

# Check if any trigger version bumps
git log $(git describe --tags --abbrev=0)..HEAD --oneline | grep -E "^(feat|fix|perf|refactor|feat!)"
```

### After Pushing
Check GitHub Actions logs:
1. Go to Actions tab in GitHub
2. Find the latest workflow run
3. Check the "Release & Deploy" job
4. Look for semantic-release output

### Semantic-Release Output

When semantic-release runs, it will show:

```
✅ Creating release...
⏭  Skipping release: No new commits since last release
```

OR

```
✅ Creating release...
📦 @campaignbridge/campaignbridge@0.4.0
📝 Generated release notes
🏷️  Created tag v0.4.0
📦 Published GitHub release
```

## Troubleshooting

### Version Not Updating

**Check 1: Are you on master?**
```bash
git branch --show-current
# Should output: master
```

**Check 2: Do you have new commits?**
```bash
git log $(git describe --tags --abbrev=0)..HEAD
# Should show commits since last tag
```

**Check 3: Do commits have correct format?**
```bash
git log --oneline -5
# Should show: feat:, fix:, etc.
```

**Check 4: Did CI pass?**
- Check GitHub Actions
- Quality and test jobs must pass

**Check 5: Did semantic-release run?**
- Check "Release & Deploy" job logs
- Look for semantic-release output

### Forcing a Version Update

If you need to force a version update (not recommended):

```bash
# Option 1: Add a commit that triggers release
git commit --allow-empty -m "feat: trigger version bump"
git push origin master

# Option 2: Manual version bump (bypasses semantic-release)
pnpm version patch  # or minor/major
git add package.json campaignbridge.php
git commit -m "chore: bump version manually"
git push origin master
```

## Summary

**Version updates happen when:**
1. ✅ Push to `master` branch
2. ✅ CI/CD jobs pass (quality + tests)
3. ✅ Commits have conventional messages (`feat:`, `fix:`, etc.)
4. ✅ New commits exist since last release

**Version updates DO NOT happen when:**
1. ❌ Push to other branches
2. ❌ All commits are `chore:`, `docs:`, etc.
3. ❌ Tests or quality checks fail
4. ❌ No new commits since last release
5. ❌ Pull requests (even if targeting master)



