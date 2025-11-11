# How Husky Works

## Overview

Husky is a tool that manages **Git hooks** - scripts that run automatically at specific points in your Git workflow. In CampaignBridge, Husky is configured to run code quality checks before commits and validate commit messages.

## What Are Git Hooks?

Git hooks are scripts that Git executes automatically when certain events occur:
- **pre-commit**: Runs before a commit is finalized
- **commit-msg**: Runs to validate commit message format
- **post-commit**: Runs after a commit is completed
- And many more...

## Husky Setup in CampaignBridge

### Installation

Husky is installed as a dev dependency:

```json
"devDependencies": {
  "husky": "^9.1.7"
}
```

### Automatic Setup

The `prepare` script in `package.json` automatically sets up Husky when you run `pnpm install`:

```json
"scripts": {
  "prepare": "husky"
}
```

This script:
1. Ensures `.husky` directory exists
2. Configures Git to use `.husky` as the hooks directory
3. Makes hook scripts executable

## Git Hooks Configured

### 1. Pre-Commit Hook

**Location:** `.husky/pre-commit`

**What it does:**
```bash
pnpm exec lint-staged
```

**When it runs:**
- Before every `git commit`
- Before commit is finalized

**What it checks:**
Runs `lint-staged` which processes only staged files based on file type:

#### JavaScript/TypeScript Files (`*.{js,jsx,ts,tsx}`)
```javascript
'eslint --fix',      // Fixes ESLint errors automatically
'prettier --write'   // Formats code with Prettier
```

#### CSS/SCSS Files (`*.{css,scss}`)
```javascript
'wp-scripts lint-style',  // WordPress style linting
'prettier --write'        // Formats CSS with Prettier
```

#### PHP Files (`*.php`)
```javascript
// Runs in wp-env container:
'phpcbf --standard=phpcs.xml.dist',  // Auto-fixes PHP coding standards
'phpstan analyse --memory-limit=2G'  // Static analysis
```

**What happens:**
1. You stage files: `git add file.js`
2. You commit: `git commit -m "feat: add feature"`
3. Pre-commit hook runs automatically
4. `lint-staged` processes staged files
5. If issues found:
   - Auto-fixes are applied (ESLint, Prettier, PHPCS)
   - Files are automatically staged again
   - If unfixable errors → Commit is **blocked**
6. If all checks pass → Commit proceeds

**Example Flow:**
```bash
$ git add src/components/Button.jsx
$ git commit -m "feat: add button component"

# Pre-commit hook runs:
# → Running lint-staged...
# → ESLint: Fixing issues...
# → Prettier: Formatting...
# → All checks passed ✅
# → Commit successful
```

### 2. Commit-Message Hook

**Location:** `.husky/commit-msg`

**What it does:**
```bash
pnpm exec commitlint --edit $1
```

**When it runs:**
- After you write commit message
- Before commit is finalized

**What it validates:**
Uses `commitlint` with `@commitlint/config-conventional` to enforce:

#### Required Format
```
<type>(<scope>): <subject>

[optional body]

[optional footer]
```

#### Valid Types
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting)
- `refactor`: Code refactoring
- `perf`: Performance improvements
- `test`: Adding/updating tests
- `build`: Build system changes
- `ci`: CI/CD changes
- `chore`: Other changes
- `revert`: Reverting a commit

#### Rules Enforced
1. **Type must be valid** - Must be from the list above
2. **Subject is required** - Cannot be empty
3. **No period at end** - Subject cannot end with `.`
4. **Case restrictions** - Subject cannot be Start Case, PascalCase, or UPPERCASE
5. **Max length** - Header must be ≤ 100 characters

**Valid Examples:**
```bash
✅ feat: add new email template
✅ fix: resolve file upload bug
✅ docs: update README with installation
✅ chore: update dependencies
✅ feat(api): add new endpoint
✅ fix(upload): handle large files
```

**Invalid Examples:**
```bash
❌ Add new feature          # Missing type
❌ feat: Add new feature    # Wrong case (Start Case)
❌ feat: add new feature.   # Period at end
❌ feat: add new feature for users to create custom email templates with advanced options # Too long
❌ invalid: do something    # Invalid type
```

**What happens:**
1. You commit: `git commit -m "your message"`
2. Commit-msg hook runs automatically
3. `commitlint` validates message format
4. If invalid:
   - Error message shows what's wrong
   - Commit is **blocked**
   - You must fix the message
5. If valid → Commit proceeds

**Example Flow:**
```bash
$ git commit -m "add feature"
# ❌ Error: type may not be empty [type-empty]

$ git commit -m "feat: add feature"
# ✅ Commit successful

$ git commit -m "feat: Add Feature"
# ❌ Error: subject may not be start-case [subject-case]

$ git commit -m "feat: add feature"
# ✅ Commit successful
```

## Complete Git Workflow with Husky

### Normal Commit Flow

```
┌─────────────────────────────────────────────────┐
│ 1. Developer stages files                      │
│    git add src/components/Button.jsx            │
│    git add includes/Form.php                    │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│ 2. Developer runs commit                        │
│    git commit -m "feat: add button component"   │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│ 3. PRE-COMMIT HOOK runs (.husky/pre-commit)     │
│    → pnpm exec lint-staged                      │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│ 4. lint-staged processes staged files           │
│    ┌─────────────────────────────────────────┐ │
│    │ Button.jsx:                              │ │
│    │   → ESLint --fix                         │ │
│    │   → Prettier --write                     │ │
│    │                                          │ │
│    │ Form.php:                                │ │
│    │   → PHPCS auto-fix                       │ │
│    │   → PHPStan check                       │ │
│    └─────────────────────────────────────────┘ │
└─────────────────────────────────────────────────┘
                    ↓
              ┌─────────┐
              │ Checks  │
              │ Pass?   │
              └─────────┘
           ┌──────┴──────┐
          YES           NO
           │             │
           ↓             ↓
    ┌──────────┐   ┌──────────┐
    │ Continue │   │  Block   │
    │          │   │  Commit  │
    └──────────┘   └──────────┘
           │
           ↓
┌─────────────────────────────────────────────────┐
│ 5. COMMIT-MSG HOOK runs (.husky/commit-msg)    │
│    → pnpm exec commitlint --edit $1             │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│ 6. commitlint validates message format          │
│    Checks:                                      │
│    ✓ Type is valid (feat, fix, etc.)          │
│    ✓ Subject follows rules                    │
│    ✓ Length is within limit                   │
└─────────────────────────────────────────────────┘
                    ↓
              ┌─────────┐
              │ Valid?  │
              └─────────┘
           ┌──────┴──────┐
          YES           NO
           │             │
           ↓             ↓
    ┌──────────┐   ┌──────────┐
    │ Continue │   │  Block   │
    │          │   │  Commit │
    └──────────┘   └──────────┘
           │
           ↓
┌─────────────────────────────────────────────────┐
│ 7. Commit completes successfully ✅            │
│    Files are committed with fixes applied      │
│    Commit message is validated                 │
└─────────────────────────────────────────────────┘
```

## Bypassing Hooks (Not Recommended)

### Skip Pre-Commit Hook
```bash
# Skip all hooks
git commit --no-verify -m "feat: add feature"

# OR use shorthand
git commit -n -m "feat: add feature"
```

⚠️ **Warning:** Only use `--no-verify` if you're absolutely sure the code is correct. This bypasses all quality checks.

### Skip Commit-Message Hook
```bash
# Same as above - bypasses all hooks
git commit --no-verify -m "bad message"
```

## Troubleshooting

### Hook Not Running

**Check 1: Husky is installed**
```bash
ls -la .husky/
# Should show pre-commit and commit-msg files
```

**Check 2: Hooks are executable**
```bash
ls -la .husky/pre-commit .husky/commit-msg
# Should show -rwxr-xr-x (executable)
```

**Check 3: Git is configured**
```bash
git config core.hooksPath
# Should output: .husky (or similar)
```

**Fix: Reinstall Husky**
```bash
pnpm install
# This runs the "prepare" script which sets up Husky
```

### Pre-Commit Hook Failing

**Problem: ESLint errors won't auto-fix**
```bash
# Fix: Run ESLint manually
pnpm lint:fix

# Then commit again
git add .
git commit -m "feat: add feature"
```

**Problem: PHP files failing**
```bash
# Fix: Run PHP linter manually
pnpm lint:php:fix

# Then commit again
git add .
git commit -m "feat: add feature"
```

### Commit-Message Hook Failing

**Problem: Commit message format error**
```bash
# Error: type may not be empty
# Fix: Add type prefix
git commit -m "feat: your message here"

# Error: subject may not be start-case
# Fix: Use lowercase
git commit -m "feat: add feature"  # NOT "Add Feature"
```

**Problem: Message too long**
```bash
# Error: header may not be longer than 100 characters
# Fix: Shorten message or use body
git commit -m "feat: add feature" -m "Detailed description here"
```

## Configuration Files

### `.husky/pre-commit`
Simple script that runs lint-staged:
```bash
pnpm exec lint-staged
```

### `.husky/commit-msg`
Script that validates commit messages:
```bash
pnpm exec commitlint --edit $1
```

### `.lintstagedrc.js`
Configures what runs on which file types:
- JavaScript/TypeScript → ESLint + Prettier
- CSS → WordPress lint + Prettier
- PHP → PHPCS + PHPStan

### `commitlint.config.js`
Configures commit message validation rules:
- Valid types
- Format requirements
- Length limits

## Benefits

### Automatic Code Quality
- ✅ Catches errors before commit
- ✅ Auto-fixes formatting issues
- ✅ Ensures consistent code style

### Consistent Commit Messages
- ✅ Enables semantic-release to work properly
- ✅ Makes git history readable
- ✅ Enforces team standards

### Team Collaboration
- ✅ Everyone follows same standards
- ✅ No manual code review needed for style
- ✅ Consistent project quality

## Summary

**Husky manages Git hooks that:**
1. **Pre-commit:** Runs lint-staged to check and fix code quality
2. **Commit-msg:** Validates commit message format

**It runs automatically when you:**
- Run `git commit`
- No manual steps needed

**It ensures:**
- Code quality before commits
- Consistent commit message format
- Automatic fixes where possible

**To use it:**
- Just commit normally: `git commit -m "feat: add feature"`
- Hooks run automatically
- Fix issues if hook fails, then commit again










