#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")/../.."

# Existing debt is explicit so new persistence access cannot spread while the
# Repository layer is extracted incrementally.
allowed=(
	includes/Admin/Core/Forms/Form_Query_Optimizer.php
	includes/Core/Performance_Optimizer.php
	includes/Post_Types/Post_Type_Email_Template.php
	includes/REST/Routes.php
)

mapfile -t candidates < <(
	rg -l '\b(WP_Query|get_posts|get_post_meta|update_post_meta|delete_post_meta|get_option|update_option|delete_option)\s*\(|\$wpdb\b' includes -g '*.php' -g '!includes/Core/Storage.php' | sort
)

matches=()
for candidate in "${candidates[@]}"; do
	if rg '\b(WP_Query|get_posts|get_post_meta|update_post_meta|delete_post_meta|get_option|update_option|delete_option)\s*\(|\$wpdb\b' "${candidate}" \
		| rg -v 'Storage::(get_option|update_option|delete_option|get_post_meta|update_post_meta|delete_post_meta)\s*\(' >/dev/null; then
		matches+=( "${candidate}" )
	fi
done

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
