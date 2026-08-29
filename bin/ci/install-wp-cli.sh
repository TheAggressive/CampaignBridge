#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")/../.."

WP_CLI_VERSION="${WP_CLI_VERSION:-2.12.0}"
WP_CLI_SHA256="${WP_CLI_SHA256:-ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c}"
WP_CLI_URL="https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar"
TOOL_DIR="${CB_TESTS_TOOL_DIR:-.cache/tests}"
TARGET="${TOOL_DIR}/wp"

verify() {
	[ -f "$1" ] || return 1
	echo "${WP_CLI_SHA256}  $1" | sha256sum --check --status
}

if verify "${TARGET}"; then
	echo "install-wp-cli: ${TARGET} already at ${WP_CLI_VERSION}"
	exit 0
fi

mkdir -p "${TOOL_DIR}"
staging="${TARGET}.download"
trap 'rm -f "${staging}"' EXIT

echo "install-wp-cli: fetching WP-CLI ${WP_CLI_VERSION}"
curl --fail --silent --show-error --location --retry 3 \
	--output "${staging}" "${WP_CLI_URL}"

if ! verify "${staging}"; then
	echo "install-wp-cli: checksum mismatch for ${WP_CLI_URL}" >&2
	echo "expected: ${WP_CLI_SHA256}" >&2
	echo "actual:   $(sha256sum "${staging}" | awk '{print $1}')" >&2
	exit 1
fi

chmod 0755 "${staging}"
mv "${staging}" "${TARGET}"
echo "install-wp-cli: ${TARGET} (${WP_CLI_VERSION})"
