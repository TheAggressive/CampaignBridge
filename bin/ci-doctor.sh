#!/bin/bash
# CI Doctor - Diagnose CI/CD setup issues
# Usage: bash bin/ci-doctor.sh

set -e

echo "🏥 CI Doctor - Diagnosing your setup..."
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

success() {
    echo -e "${GREEN}✓${NC} $1"
}

warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

error() {
    echo -e "${RED}✗${NC} $1"
}

info() {
    echo "ℹ $1"
}

# Check if we're in the right directory
if [ ! -f "campaignbridge.php" ]; then
    error "Not in plugin root directory"
    exit 1
fi

success "In plugin root directory"

# Check required files
echo ""
echo "📁 Checking required files..."

files=(
    ".github/workflows/ci.yml"
    "package.json"
    "composer.json"
    "phpunit.xml.dist"
    ".wp-env.json"
)

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        success "$file exists"
    else
        error "$file missing"
    fi
done

# Check Node.js and pnpm
echo ""
echo "🔧 Checking tools..."

if command -v node &> /dev/null; then
    NODE_VERSION=$(node --version)
    success "Node.js installed: $NODE_VERSION"
    
    if [[ "$NODE_VERSION" =~ v2[0-9]\. ]]; then
        success "Node.js version is 20+ (recommended)"
    else
        warning "Node.js version should be 20+, you have $NODE_VERSION"
    fi
else
    error "Node.js not installed"
fi

if command -v pnpm &> /dev/null; then
    PNPM_VERSION=$(pnpm --version)
    success "pnpm installed: $PNPM_VERSION"
else
    error "pnpm not installed - install with: npm install -g pnpm"
fi

if command -v php &> /dev/null; then
    PHP_VERSION=$(php --version | head -n 1)
    success "PHP installed: $PHP_VERSION"
    
    if php -r "exit(version_compare(PHP_VERSION, '8.2.0', '>=') ? 0 : 1);"; then
        success "PHP version is 8.2+ (required)"
    else
        error "PHP version must be 8.2+, you have $PHP_VERSION"
    fi
else
    error "PHP not installed"
fi

if command -v composer &> /dev/null; then
    COMPOSER_VERSION=$(composer --version | head -n 1)
    success "Composer installed: $COMPOSER_VERSION"
else
    error "Composer not installed"
fi

if command -v docker &> /dev/null; then
    DOCKER_VERSION=$(docker --version)
    success "Docker installed: $DOCKER_VERSION"
    
    if docker ps &> /dev/null; then
        success "Docker daemon is running"
    else
        error "Docker daemon not running - start Docker Desktop"
    fi
else
    error "Docker not installed (required for wp-env)"
fi

# Check dependencies
echo ""
echo "📦 Checking dependencies..."

if [ -d "node_modules" ]; then
    success "node_modules exists"
    
    if [ -f "node_modules/.bin/wp-env" ]; then
        success "wp-env installed"
    else
        error "wp-env not installed - run: pnpm install"
    fi
else
    error "node_modules missing - run: pnpm install"
fi

if [ -d "vendor" ]; then
    success "vendor directory exists"
    
    if [ -f "vendor/bin/phpunit" ]; then
        success "PHPUnit installed"
    else
        error "PHPUnit not installed - run: composer install"
    fi
else
    error "vendor directory missing - run: composer install"
fi

# Check wp-env status
echo ""
echo "🌐 Checking wp-env..."

if command -v pnpm &> /dev/null && [ -d "node_modules" ]; then
    if pnpm wp-env --version &> /dev/null; then
        WP_ENV_VERSION=$(pnpm wp-env --version)
        success "wp-env is functional: $WP_ENV_VERSION"
        
        # Check if wp-env is running
        if docker ps --format '{{.Names}}' | grep -q "campaignbridge"; then
            success "wp-env containers are running"
            info "WordPress: http://localhost:8888"
            info "Test site: http://localhost:8889"
            
            # Get WordPress version
            if pnpm wp-env run cli wp core version &> /dev/null; then
                WP_VERSION=$(pnpm wp-env run cli wp core version 2>/dev/null)
                success "WordPress version: $WP_VERSION"
            fi
        else
            warning "wp-env not running - start with: pnpm env:start"
        fi
    else
        error "wp-env not working"
    fi
fi

# Test local test execution
echo ""
echo "🧪 Testing local test capability..."

if [ -f "vendor/bin/phpunit" ] && [ -d "node_modules" ]; then
    info "Attempting to run a quick test..."
    
    if docker ps --format '{{.Names}}' | grep -q "campaignbridge"; then
        info "Running: pnpm wp-env run tests-cli bash -c './vendor/bin/phpunit --version'"
        if pnpm wp-env run tests-cli bash -c './vendor/bin/phpunit --version' &> /dev/null; then
            success "Can execute tests in wp-env"
        else
            error "Cannot execute tests in wp-env"
        fi
    else
        warning "wp-env not running, cannot test execution"
        info "Start with: pnpm env:start"
    fi
fi

# Check GitHub Actions workflow
echo ""
echo "⚙️  Checking CI configuration..."

if [ -f ".github/workflows/ci.yml" ]; then
    success "CI workflow file exists"
    
    # Check for common issues
    if grep -q "wp-env" ".github/workflows/ci.yml"; then
        success "Using wp-env in CI (recommended)"
    else
        warning "Not using wp-env in CI"
    fi
    
    if grep -q "matrix:" ".github/workflows/ci.yml"; then
        success "Matrix testing configured"
    fi
    
    if grep -q "cache@v" ".github/workflows/ci.yml"; then
        success "Caching configured"
    fi
fi

# Check for common issues
echo ""
echo "🔍 Checking for common issues..."

if [ -f ".wp-env.json" ]; then
    if grep -q "\"phpVersion\":" ".wp-env.json"; then
        PHP_IN_CONFIG=$(grep "phpVersion" .wp-env.json | grep -oP '\d+\.\d+')
        success "PHP version specified in .wp-env.json: $PHP_IN_CONFIG"
    else
        warning "PHP version not specified in .wp-env.json (will use default)"
    fi
fi

if [ -f "phpunit.xml.dist" ]; then
    if grep -q "bootstrap" "phpunit.xml.dist"; then
        success "PHPUnit bootstrap configured"
    else
        error "PHPUnit bootstrap not found in phpunit.xml.dist"
    fi
fi

# Check git status
echo ""
echo "📝 Checking git status..."

if command -v git &> /dev/null; then
    if git rev-parse --git-dir > /dev/null 2>&1; then
        success "Git repository initialized"
        
        BRANCH=$(git branch --show-current)
        success "Current branch: $BRANCH"
        
        if git remote -v | grep -q "github.com"; then
            success "GitHub remote configured"
            REMOTE_URL=$(git remote get-url origin)
            info "Remote: $REMOTE_URL"
        else
            warning "No GitHub remote found"
        fi
        
        # Check for uncommitted changes
        if [ -n "$(git status --porcelain)" ]; then
            warning "You have uncommitted changes"
        else
            success "Working directory clean"
        fi
    fi
fi

# Summary and recommendations
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 Summary"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Count issues
ERRORS=0
WARNINGS=0

if ! command -v docker &> /dev/null || ! docker ps &> /dev/null; then
    ((ERRORS++))
fi

if ! docker ps --format '{{.Names}}' | grep -q "campaignbridge"; then
    ((WARNINGS++))
fi

if [ $ERRORS -eq 0 ]; then
    if [ $WARNINGS -eq 0 ]; then
        echo ""
        success "Everything looks good! 🎉"
        echo ""
        echo "Next steps:"
        echo "1. Start wp-env: pnpm env:start"
        echo "2. Run tests: pnpm test"
        echo "3. Push to GitHub to trigger CI"
    else
        echo ""
        warning "Setup is mostly good, but there are $WARNINGS warnings"
        echo ""
        echo "Recommended:"
        echo "1. Review warnings above"
        echo "2. Start wp-env: pnpm env:start"
        echo "3. Run tests: pnpm test"
    fi
else
    echo ""
    error "Found $ERRORS critical issues that need to be fixed"
    echo ""
    echo "Fix these issues:"
    echo "1. Install Docker: https://docs.docker.com/get-docker/"
    echo "2. Start Docker Desktop"
    echo "3. Run: pnpm install && composer install"
    echo "4. Start wp-env: pnpm env:start"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📚 Resources"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Quick Start:  .github/CI-QUICK-START.md"
echo "Full Guide:   .github/CI-TESTING-GUIDE.md"
echo "CI Workflow:  .github/workflows/ci.yml"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

