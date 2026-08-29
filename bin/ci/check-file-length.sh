#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")/../.."

failed=0
while IFS= read -r file; do
	lines=$(wc -l < "${file}")
	if (( lines > 1200 )); then
		echo "ERROR: ${file} has ${lines} lines (maximum 1200)." >&2
		failed=1
	elif (( lines > 800 )); then
		echo "WARNING: ${file} has ${lines} lines; split it before it reaches 1200." >&2
	fi
done < <(find includes -type f -name '*.php' | sort)

exit "${failed}"
