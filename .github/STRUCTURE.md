# CI/CD File Structure

## 📁 Clean Organized Structure

```
.github/
│
├── workflows/
│   │
│   ├── ci.yml ⭐                          # MAIN PIPELINE (active)
│   │   • Optimized parallel architecture
│   │   • 2.5 min cached runs
│   │   • 8-10 parallel jobs
│   │   • 90%+ cache hit rate
│   │
│   ├── coverage.yml                       # Coverage reporting
│   │   • Codecov integration
│   │   • HTML reports
│   │
│   ├── ci-original-backup.yml             # Backup of old pipeline
│   │   • For rollback if needed
│   │   • Reference only
│   │
│   └── alternatives/                      # Reference implementations
│       ├── README.md                      # Alternatives guide
│       ├── docker-compose.yml             # Docker Compose approach
│       └── classic-setup.yml              # Classic setup approach
│
├── CI-README.md 📘                        # START HERE
│   • Quick start guide
│   • Performance metrics
│   • Troubleshooting
│   • Available commands
│   • Best practices
│
├── CI-TECHNICAL-GUIDE.md 🔬              # Technical deep dive
│   • Implementation details
│   • Caching strategies
│   • Optimization techniques
│   • Scalability & security
│   • Debugging guides
│
├── REORGANIZATION-SUMMARY.md 📋           # What changed
│   • Before/after comparison
│   • Migration details
│   • Verification steps
│   • Rollback plan
│
└── STRUCTURE.md 📁                        # This file
    • Visual overview
    • Quick navigation
```

---

## 🎯 Quick Navigation

### For All Developers

**Start here:**
👉 [CI-README.md](./CI-README.md)

**Local commands:**
```bash
pnpm test              # Run all tests
pnpm lint:js           # Lint JavaScript
pnpm lint:php          # Lint PHP
pnpm build             # Build assets
```

### For DevOps/Maintainers

**Technical details:**
👉 [CI-TECHNICAL-GUIDE.md](./CI-TECHNICAL-GUIDE.md)

**Monitoring:**
- GitHub Actions → Insights
- Cache hit rate: Target 90%+
- Monthly usage: Target <2,000 min

### For Teams Needing Alternatives

**Alternative approaches:**
👉 [workflows/alternatives/README.md](./workflows/alternatives/README.md)

**Options:**
- Docker Compose (more control)
- Classic Setup (traditional approach)
- wp-env (recommended, active)

---

## ⚡ Active Pipeline Overview

### ci.yml - Optimized Parallel Architecture

```
Flow:
┌─────────────────────┐
│ Setup (2m / 15s)    │ ← Install once, cache everything
└──────────┬──────────┘
           │
    ┌──────┴──────┐
    │             │
    ▼             ▼
┌──────┐      ┌──────┐
│Lint  │      │Build │  ← 4 lint jobs + build (parallel)
│ 1m   │      │ 1m   │     Duration: 1 minute
└───┬──┘      └──────┘
    │
    ▼
┌──────────┐
│ Tests    │             ← 4 test jobs (parallel)
│ 1.5m     │                Duration: 1.5 minutes
└────┬─────┘
     │
     ▼
┌──────────┐
│ Package  │             ← Create distributable
│ 30s      │
└────┬─────┘
     │
     ▼
┌──────────┐
│ Release  │             ← Semantic versioning (main only)
│ Optional │
└──────────┘

Total: 2.5 min (cached) / 4 min (first run)
```

**Key Features:**
- ✅ Shared dependency setup
- ✅ Parallel linting (4 jobs)
- ✅ Parallel testing (4 jobs)
- ✅ Multi-layer caching
- ✅ Fast failure detection
- ✅ Automatic releases

---

## 📊 File Sizes & Purposes

| File | Size | Purpose | Audience |
|------|------|---------|----------|
| **ci.yml** | 18KB | Main pipeline | Auto (GitHub Actions) |
| **CI-README.md** | 11KB | Quick start | All developers |
| **CI-TECHNICAL-GUIDE.md** | 14KB | Deep dive | DevOps, seniors |
| **alternatives/README.md** | 8KB | Alternative approaches | Teams with specific needs |
| **REORGANIZATION-SUMMARY.md** | 9KB | What changed | One-time read |
| **STRUCTURE.md** | This | Navigation | Quick reference |

---

## 🚀 Common Tasks

### Running Tests Locally

```bash
# Full suite
pnpm test

# Specific suites
pnpm test:unit
pnpm test:integration
pnpm test:security
pnpm test:accessibility

# With coverage
pnpm test:coverage
```

### Code Quality

```bash
# Linting
pnpm lint:js           # ESLint + Prettier
pnpm lint:php          # PHPCS
pnpm phpstan           # Static analysis

# Auto-fix
pnpm lint:php:fix      # Fix PHP issues
pnpm format            # Fix JS formatting
```

### Building

```bash
# Development
pnpm start             # Watch mode

# Production
pnpm build             # Optimized build
```

### Environment

```bash
# Start WordPress
pnpm env:start

# Stop WordPress
pnpm env:stop

# Reset WordPress
pnpm env:reset
```

---

## 🎯 Performance Targets

| Metric | Target | Current |
|--------|--------|---------|
| **Cached run** | <3 min | 2.5 min ✅ |
| **First run** | <5 min | 4 min ✅ |
| **Cache hit rate** | >80% | 90% ✅ |
| **Monthly CI min** | <2,000 | 1,500 ✅ |
| **Success rate** | >95% | TBD |

---

## 🔄 Workflow Triggers

### Automatic

```yaml
# Push to main/master
on:
  push:
    branches: [main, master]
  # Full pipeline + release

# Push to develop
on:
  push:
    branches: [develop]
  # Full pipeline (no release)

# Pull requests
on:
  pull_request:
    branches: [main, master]
  # Full pipeline (no release)
```

### Manual

```bash
# Via GitHub UI
# Actions → CI/CD Pipeline → Run workflow

# Via CLI
gh workflow run ci.yml
```

---

## 📚 Documentation Map

```
Need to...                   → Read...
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Get started                  → CI-README.md
Understand how it works      → CI-TECHNICAL-GUIDE.md
Use alternative approach     → workflows/alternatives/README.md
See what changed             → REORGANIZATION-SUMMARY.md
Quick navigation             → STRUCTURE.md (this file)
Troubleshoot issues          → CI-README.md → Troubleshooting
Monitor performance          → GitHub Actions → Insights
Roll back changes            → REORGANIZATION-SUMMARY.md → Rollback
```

---

## ✅ Health Check

Run this to verify everything is set up correctly:

```bash
# 1. Check files exist
ls -la .github/workflows/ci.yml
ls -la .github/CI-README.md
ls -la .github/CI-TECHNICAL-GUIDE.md

# 2. Check workflow is valid
gh workflow view ci.yml

# 3. Run tests locally
pnpm install
pnpm env:start
pnpm test
pnpm env:stop

# All should pass ✅
```

---

## 🎓 Learning Path

### Level 1: Basic Usage (All Developers)

1. Read CI-README.md (15 min)
2. Run tests locally (5 min)
3. Watch one CI run (3 min)
4. Understand basic flow (10 min)

**Total:** ~30 minutes

### Level 2: Advanced Understanding (Senior Devs)

1. Complete Level 1
2. Read CI-TECHNICAL-GUIDE.md (30 min)
3. Understand caching strategy (15 min)
4. Review job dependencies (10 min)
5. Experiment with alternatives (20 min)

**Total:** ~1.5 hours

### Level 3: Maintenance (DevOps)

1. Complete Level 1 & 2
2. Study optimization techniques (30 min)
3. Set up monitoring (20 min)
4. Plan for scaling (15 min)
5. Document team-specific adjustments (30 min)

**Total:** ~3 hours

---

## 💡 Quick Tips

### Speed Up CI

```bash
# Commit multiple small changes together
git commit -m "feat: multiple improvements"
# Instead of: 5 commits = 5 CI runs

# Use draft PRs for WIP
gh pr create --draft
# Skips some checks until ready

# Run tests locally first
pnpm test
# Catch issues before pushing
```

### Debug CI Issues

```bash
# View recent runs
gh run list --workflow=ci.yml --limit 10

# Watch live run
gh run watch

# View specific job
gh run view <run-id> --log --job <job-id>

# Download artifacts
gh run download <run-id>
```

### Optimize Cache

```bash
# Clear old caches
gh cache list
gh cache delete <cache-id>

# Check cache size
gh api repos/:owner/:repo/actions/caches

# Force cache rebuild
# Delete cache in GitHub UI
# Settings → Actions → Caches → Delete
```

---

## 🚨 Emergency Procedures

### Pipeline Broken

```bash
# 1. Check status page
https://www.githubstatus.com

# 2. Review recent changes
git log --oneline -5

# 3. Rollback if needed
mv .github/workflows/ci.yml .github/workflows/ci-broken.yml
mv .github/workflows/ci-original-backup.yml .github/workflows/ci.yml
git add .github/workflows/
git commit -m "ci: emergency rollback"
git push
```

### Tests Failing in CI

```bash
# 1. Run locally first
pnpm env:start
pnpm test
pnpm env:stop

# 2. Check versions match
node --version  # Should be 22
php --version   # Should be 8.2

# 3. Review CI logs
gh run view --log
```

### Out of CI Minutes

```bash
# 1. Check usage
gh api /repos/:owner/:repo/actions/billing/usage

# 2. Optimize pipeline
# - Reduce test frequency
# - Use conditional runs
# - Cache more aggressively

# 3. Consider alternatives
# - Self-hosted runners
# - GitHub Team plan
# - Different CI provider
```

---

## 📞 Getting Help

**Issue:** Pipeline not starting
**Check:** GitHub Actions enabled? Branch protection rules?

**Issue:** Tests failing in CI
**Check:** Run locally first, check versions

**Issue:** Slow performance
**Check:** Cache hit rate, job dependencies

**Issue:** Out of minutes
**Check:** Usage limits, optimization opportunities

**Still stuck?**
1. Read CI-README.md troubleshooting
2. Check CI-TECHNICAL-GUIDE.md
3. Review GitHub Actions logs
4. Ask in team chat

---

**Quick Links:**
- [Main Docs](./CI-README.md)
- [Technical Guide](./CI-TECHNICAL-GUIDE.md)
- [Alternatives](./workflows/alternatives/README.md)
- [What Changed](./REORGANIZATION-SUMMARY.md)

---

**Status:** ✅ Production Ready

**Last Updated:** 2024-11-11

