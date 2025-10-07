#!/usr/bin/env bash

# CampaignBridge Test Runner Script
# Usage: ./bin/run-tests.sh [test-type] [options]

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default values
TEST_TYPE="all"
COVERAGE=false
VERBOSE=false
WATCH=false

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        unit|integration|legacy|all)
            TEST_TYPE="$1"
            shift
            ;;
        --coverage)
            COVERAGE=true
            shift
            ;;
        --verbose|-v)
            VERBOSE=true
            shift
            ;;
        --watch|-w)
            WATCH=true
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [test-type] [options]"
            echo ""
            echo "Test Types:"
            echo "  unit        Run unit tests only"
            echo "  integration Run integration tests only"
            echo "  legacy      Run legacy tests only"
            echo "  all         Run all tests (default)"
            echo ""
            echo "Options:"
            echo "  --coverage  Generate coverage report"
            echo "  --verbose   Verbose output"
            echo "  --watch     Watch for file changes"
            echo "  --help      Show this help"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Function to check if WP-Env is running
check_wp_env() {
    if ! wp-env status >/dev/null 2>&1; then
        print_error "WP-Env is not running. Starting it now..."
        wp-env start

        # Wait for environment to be ready
        print_status "Waiting for environment to be ready..."
        sleep 10
    else
        print_success "WP-Env is running"
    fi
}

# Function to install dependencies
install_dependencies() {
    if [ ! -d "vendor" ]; then
        print_status "Installing Composer dependencies..."
        composer install --dev --no-interaction
    fi
}

# Function to run linting
run_linting() {
    print_status "Running PHP linting..."

    if ! wp-env run tests-cli --env-cwd=wp-content/plugins/campaignbridge vendor/bin/phpcs --standard=phpcs.xml.dist --report=summary; then
        print_warning "PHP CodeSniffer found issues. Run 'pnpm lint:php:fix' to auto-fix."
    fi

    print_status "Running PHPStan analysis..."
    if ! wp-env run tests-cli --env-cwd=wp-content/plugins/campaignbridge vendor/bin/phpstan analyse --no-progress; then
        print_error "PHPStan found issues. Please fix them before running tests."
        exit 1
    fi
}

# Function to run tests
run_tests() {
    local cmd_args=""

    # Add testsuite based on test type
    if [ "$TEST_TYPE" != "all" ]; then
        cmd_args="--testsuite=$TEST_TYPE"
    fi

    # Add coverage options
    if [ "$COVERAGE" = true ]; then
        cmd_args="$cmd_args --coverage-html=coverage/html --coverage-text"
        print_status "Coverage report will be generated in coverage/html/"
    fi

    # Add verbose option
    if [ "$VERBOSE" = true ]; then
        cmd_args="$cmd_args --verbose"
    fi

    print_status "Running $TEST_TYPE tests..."

    if [ "$WATCH" = true ]; then
        print_status "Watching for file changes... (Press Ctrl+C to stop)"
        while true; do
            wp-env run tests-cli --env-cwd=wp-content/plugins/campaignbridge vendor/bin/phpunit $cmd_args
            inotifywait -r -e modify,create,delete includes/ tests/ --timeout 5 >/dev/null 2>&1 || true
            clear
            echo "Re-running tests after file change..."
        done
    else
        wp-env run tests-cli --env-cwd=wp-content/plugins/campaignbridge vendor/bin/phpunit $cmd_args
    fi
}

# Function to generate coverage report
generate_coverage_badge() {
    if [ "$COVERAGE" = true ] && [ -f "coverage/clover.xml" ]; then
        print_status "Generating coverage badge..."
        # You could add a coverage badge generator here
    fi
}

# Main execution
main() {
    print_status "CampaignBridge Test Runner"
    print_status "=========================="

    # Check prerequisites
    check_wp_env
    install_dependencies

    # Run linting first (unless running watch mode)
    if [ "$WATCH" = false ]; then
        run_linting
    fi

    # Run tests
    run_tests

    # Generate additional reports
    generate_coverage_badge

    print_success "Testing complete!"
}

# Trap to handle cleanup
cleanup() {
    print_status "Cleaning up..."
    exit 0
}

trap cleanup INT TERM

# Run main function
main "$@"
