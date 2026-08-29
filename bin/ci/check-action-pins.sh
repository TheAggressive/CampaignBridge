#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")/../.."

if [[ ! -d .github/workflows ]]; then
	echo "Missing .github/workflows; action pin check cannot run." >&2
	exit 1
fi

files=$(find .github/workflows -type f \( -name '*.yml' -o -name '*.yaml' \) | sort)

if [[ -z "${files}" ]]; then
	echo "No workflow files found." >&2
	exit 1
fi

unpinned=$(
	grep -nE '^\s*-?\s*uses:\s*[^#]+' ${files} \
		| grep -vE 'uses:\s*\./' \
		| grep -vE 'uses:\s*[^@]+@[0-9a-f]{40}\b' \
		|| true
)

if [[ -n "${unpinned}" ]]; then
	echo "GitHub Actions must be pinned to full commit SHAs:" >&2
	echo "${unpinned}" >&2
	exit 1
fi

undocumented=$(
	grep -nE '^\s*-?\s*uses:\s*[^@]+@[0-9a-f]{40}\s*$' ${files} || true
)

if [[ -n "${undocumented}" ]]; then
	echo "Pinned Actions must include a version comment:" >&2
	echo "${undocumented}" >&2
	exit 1
fi

echo "GitHub Action pins verified."
