#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")/../.."

bash bin/local/test-environment.sh

WP_DIR="${CB_TESTS_WP_DIR:-.cache/tests/wordpress}"
MYSQL="$(command -v mysql || true)"

export CB_TESTS_ABSPATH="$(pwd -P)/${WP_DIR}"
export CB_TESTS_PLUGIN_DIR="$(cd .. && pwd -P)"
export CB_TESTS_DB_HOST="127.0.0.1:${CB_TESTS_DB_PORT:-13308}"
export WP_PHPUNIT__TESTS_CONFIG="$(pwd -P)/tests/wp-tests-config.php"

echo "phpunit: WordPress ${CB_TESTS_WP_VERSION:-7.0.4}, PHP $(php -r 'echo PHP_VERSION;')"
"${PHP_BINARY:-php}" vendor/bin/phpunit "$@"
status=$?

if [ -n "${MYSQL}" ]; then
	version="$(${MYSQL} --protocol=TCP --host=127.0.0.1 \
		--port="${CB_TESTS_DB_PORT:-13308}" --user=root --skip-column-names \
		--batch --execute='SELECT VERSION();' 2>/dev/null || echo unknown)"
	echo "phpunit: MySQL ${version}"
fi

exit "${status}"
