#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")/../.."

WP_VERSION="${CB_TESTS_WP_VERSION:-7.0.4}"
WP_DIR="${CB_TESTS_WP_DIR:-.cache/tests/wordpress}"
WP_CLI="${CB_TESTS_TOOL_DIR:-.cache/tests}/wp"

bash bin/ci/install-wp-cli.sh

wp_cli() {
	php -d error_reporting='E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED' \
		"${WP_CLI}" --path="${WP_DIR}" "$@"
}

installed_version() {
	[ -f "${WP_DIR}/wp-includes/version.php" ] || return 1
	grep -m1 -oE "\\\$wp_version = '[^']+'" "${WP_DIR}/wp-includes/version.php" \
		| cut -d"'" -f2
}

if [ "$(installed_version || true)" = "${WP_VERSION}" ]; then
	echo "wp-core: ${WP_DIR} already at ${WP_VERSION}"
else
	echo "wp-core: downloading WordPress ${WP_VERSION}"
	if [ "wordpress" != "$(basename "${WP_DIR}")" ]; then
		echo "wp-core: refusing to replace non-wordpress directory: ${WP_DIR}" >&2
		exit 1
	fi

	mkdir -p "${WP_DIR}"
	find "${WP_DIR}" -mindepth 1 -delete
	wp_cli core download --version="${WP_VERSION}" --locale=en_US --skip-content --force
fi

verification_output="$(wp_cli core verify-checksums --version="${WP_VERSION}" --locale=en_US 2>&1)" || {
	echo "wp-core: checksum verification failed for ${WP_DIR}" >&2
	printf '%s\n' "${verification_output}" >&2
	exit 1
}

if grep -q '^Warning:' <<< "${verification_output}"; then
	echo "wp-core: checksum verification emitted warnings for ${WP_DIR}" >&2
	printf '%s\n' "${verification_output}" >&2
	exit 1
fi

echo "wp-core: ${WP_DIR} (${WP_VERSION})"
