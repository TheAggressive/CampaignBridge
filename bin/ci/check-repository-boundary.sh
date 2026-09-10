#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")/../.."

if ! command -v php >/dev/null 2>&1; then
	echo "Repository boundary check requires PHP for token-based scanning." >&2
	exit 1
fi

# Existing debt is explicit so new persistence access cannot spread while the
# Repository layer is extracted incrementally.
allowed=(
	includes/Post_Types/Post_Type_Email_Template.php
	includes/REST/Routes.php
)

mapfile -t matches < <(php bin/ci/find-repository-boundary-violations.php)

failed=0
for file in "${matches[@]}"; do
	permitted=0
	for existing in "${allowed[@]}"; do
		if [[ "${file}" == "${existing}" ]]; then
			permitted=1
			break
		fi
	done

	if (( permitted == 0 )); then
		echo "Persistence access outside the approved migration baseline: ${file}" >&2
		failed=1
	fi
done

if (( failed != 0 )); then
	echo "Route new persistence through Core/Storage or a Repository abstraction." >&2
	exit 1
fi

echo "Repository boundary verified (${#matches[@]} grandfathered files)."
