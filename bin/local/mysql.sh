#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")/../.."

DATA_ROOT="${CB_TESTS_MYSQL_DIR:-.cache/tests/mysql}"
DATA_DIR="${DATA_ROOT}/data"
PID_FILE="${DATA_ROOT}/mysqld.pid"
LOG_FILE="${DATA_ROOT}/error.log"
PORT="${CB_TESTS_DB_PORT:-13308}"
SOCKET="/tmp/campaignbridge-mysqld-$(id -u)-${PORT}.sock"
DB_NAME="${CB_TESTS_DB_NAME:-campaignbridge_test}"
DB_USER="${CB_TESTS_DB_USER:-campaignbridge}"
DB_PASSWORD="${CB_TESTS_DB_PASSWORD:-campaignbridge}"

find_binary() {
	local name="$1"
	local candidate
	for candidate in \
		"/usr/sbin/${name}" \
		"/usr/bin/${name}" \
		"/home/linuxbrew/.linuxbrew/opt/mysql-client/bin/${name}" \
		"/usr/local/mysql/bin/${name}"; do
		[ -x "${candidate}" ] && { printf '%s\n' "${candidate}"; return 0; }
	done
	command -v "${name}" 2>/dev/null
}

MYSQL="$(find_binary mysql || true)"
MYSQLADMIN="$(find_binary mysqladmin || true)"
MYSQLD="$(find_binary mysqld || find_binary mariadbd || true)"

require_clients() {
	if [ -z "${MYSQL}" ] || [ -z "${MYSQLADMIN}" ]; then
		echo "local-mysql: mysql and mysqladmin clients are required" >&2
		exit 1
	fi
}

client() {
	"${MYSQL}" --protocol=TCP --host=127.0.0.1 --port="${PORT}" --user=root
}

is_up() {
	[ -n "${MYSQLADMIN}" ] && "${MYSQLADMIN}" --protocol=TCP --host=127.0.0.1 \
		--port="${PORT}" --user=root ping >/dev/null 2>&1
}

start() {
	require_clients
	if is_up; then
		echo "local-mysql: already listening on 127.0.0.1:${PORT}"
		return 0
	fi

	if [ -z "${MYSQLD}" ]; then
		echo "local-mysql: no mysqld or mariadbd server found" >&2
		exit 1
	fi

	mkdir -p "${DATA_ROOT}"
	if [ ! -d "${DATA_DIR}/mysql" ]; then
		echo "local-mysql: initializing ${DATA_DIR}"
		rm -rf "${DATA_DIR}"
		mkdir -p "${DATA_DIR}"
		"${MYSQLD}" --initialize-insecure --datadir="$(pwd -P)/${DATA_DIR}" \
			--basedir=/usr --log-error="$(pwd -P)/${LOG_FILE}"
	fi

	echo "local-mysql: starting on 127.0.0.1:${PORT}"
	"${MYSQLD}" --datadir="$(pwd -P)/${DATA_DIR}" --basedir=/usr \
		--socket="${SOCKET}" --port="${PORT}" --bind-address=127.0.0.1 \
		--pid-file="$(pwd -P)/${PID_FILE}" --log-error="$(pwd -P)/${LOG_FILE}" \
		--mysqlx=0 >/dev/null 2>&1 &

	local waited=0
	while [ "${waited}" -lt 60 ]; do
		is_up && break
		sleep 1
		waited=$(( waited + 1 ))
	done

	if ! is_up; then
		echo "local-mysql: did not become ready within ${waited}s" >&2
		tail -n 20 "${LOG_FILE}" >&2 2>/dev/null || true
		exit 1
	fi

	client <<-SQL
		CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;
		CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASSWORD}';
		GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
		FLUSH PRIVILEGES;
	SQL

	echo "local-mysql: ready ($("${MYSQLD}" --version))"
}

stop() {
	require_clients
	if ! is_up; then
		echo "local-mysql: not running"
		return 0
	fi
	"${MYSQLADMIN}" --protocol=TCP --host=127.0.0.1 --port="${PORT}" \
		--user=root shutdown >/dev/null 2>&1 || true
	rm -f "${SOCKET}"
	echo "local-mysql: stopped"
}

case "${1:-start}" in
	start) start ;;
	stop) stop ;;
	status)
		if is_up; then echo "local-mysql: up on 127.0.0.1:${PORT}"; else echo "local-mysql: down"; exit 1; fi
		;;
	destroy)
		stop
		rm -rf "${DATA_ROOT}"
		echo "local-mysql: removed ${DATA_ROOT}"
		;;
	*)
		echo "usage: mysql.sh start|stop|status|destroy" >&2
		exit 2
		;;
esac
