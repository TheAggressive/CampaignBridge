#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")/../.."

slug=campaignbridge
version="${1:-$(node -p "JSON.parse(require('fs').readFileSync('package.json', 'utf8')).version")}"
zip_path="release/${slug}-${version}.zip"

if [[ ! -f "${zip_path}" || ! -f "${zip_path}.sha256" ]]; then
	echo "Package or checksum is missing. Run pnpm release:package first." >&2
	exit 1
fi

( cd release && sha256sum --check --quiet "$(basename "${zip_path}").sha256" )

listing=$(unzip -Z1 "${zip_path}")
top_level=$(printf '%s\n' "${listing}" | awk -F/ '{print $1}' | sort -u)

if [[ "${top_level}" != "${slug}" ]]; then
	echo "Package must contain exactly one top-level ${slug} directory." >&2
	exit 1
fi

for forbidden in node_modules vendor tests bin src docs .git .github .husky composer.json package.json pnpm-lock.yaml; do
	if printf '%s\n' "${listing}" | grep -qE "^${slug}/${forbidden}(/|$)"; then
		echo "Forbidden package path: ${forbidden}" >&2
		exit 1
	fi
done

for required in campaignbridge.php uninstall.php includes/Autoloader.php includes/Plugin.php dist/styles/styles.css; do
	if ! printf '%s\n' "${listing}" | grep -qxF "${slug}/${required}"; then
		echo "Required package file is missing: ${required}" >&2
		exit 1
	fi
done

extracted=$(mktemp -d)
trap 'rm -rf "${extracted}"' EXIT
unzip -qq "${zip_path}" -d "${extracted}"

header_version=$(sed -nE 's/^\s*\* Version: ([0-9]+\.[0-9]+\.[0-9]+).*/\1/p' "${extracted}/${slug}/campaignbridge.php" | head -1)
constant_version=$(sed -nE "s/.*public const VERSION = '([^']+)'.*/\1/p" "${extracted}/${slug}/campaignbridge.php" | head -1)

if [[ "${header_version}" != "${version}" || "${constant_version}" != "${version}" ]]; then
	echo "Packaged versions do not agree with ${version}." >&2
	exit 1
fi

find "${extracted}/${slug}" -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

echo "Verified ${zip_path}"
