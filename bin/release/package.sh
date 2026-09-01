#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")/../.."

slug=campaignbridge
build_dir=release
staging="${build_dir}/${slug}"
version="${1:-$(node -p "JSON.parse(require('fs').readFileSync('package.json', 'utf8')).version")}"
zip_path="${build_dir}/${slug}-${version}.zip"

if [[ ! "${version}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "Invalid package version: ${version}" >&2
	exit 2
fi

contents=(
	campaignbridge.php
	uninstall.php
	README.md
	CHANGELOG.md
	includes
	dist
)

required=(
	campaignbridge.php
	uninstall.php
	includes/Autoloader.php
	includes/Plugin.php
	dist/styles/styles.css
)

rm -rf "${build_dir}"
mkdir -p "${staging}"

for entry in "${contents[@]}"; do
	if [[ ! -e "${entry}" ]]; then
		echo "Missing packaged path: ${entry}" >&2
		exit 1
	fi
	cp -R "${entry}" "${staging}/"
done

for path in "${required[@]}"; do
	if [[ ! -f "${staging}/${path}" ]]; then
		echo "Required package file is missing: ${path}" >&2
		exit 1
	fi
done

credential_file=$(find "${staging}" -type f \( -name '.env*' -o -name '*.pem' -o -name '*.key' -o -name 'id_rsa*' \) -print -quit)
if [[ -n "${credential_file}" ]]; then
	echo "Possible credential file reached the package." >&2
	exit 1
fi

header_version=$(sed -nE 's/^\s*\* Version: ([0-9]+\.[0-9]+\.[0-9]+).*/\1/p' "${staging}/campaignbridge.php" | head -1)
constant_version=$(sed -nE "s/.*public const VERSION = '([^']+)'.*/\1/p" "${staging}/campaignbridge.php" | head -1)

if [[ "${header_version}" != "${version}" || "${constant_version}" != "${version}" ]]; then
	echo "Source versions do not agree with package.json (${version}). Run pnpm version:sync." >&2
	exit 1
fi

find "${staging}" -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

( cd "${build_dir}" && zip -qr "$(basename "${zip_path}")" "${slug}" )
( cd "${build_dir}" && sha256sum "$(basename "${zip_path}")" > "$(basename "${zip_path}").sha256" )

echo "Created ${zip_path}"
