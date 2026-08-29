#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")/../.."

bash bin/local/mysql.sh start
bash bin/ci/install-wp-core.sh

echo "test-environment: ready"
