#!/usr/bin/env bash
# CYBER EXAM — full test runner.
# Boots a disposable PHP server against the configured database, then runs the
# unit tests and both end-to-end HTTP flows (fail path and pass path).
#
#   tests/run.sh            uses APP_URL host:port from .env (default localhost:8000)
#
# Requires: a seeded database and a .env with APP_ENV=local, PAYMENT_PROVIDER=sandbox.
set -euo pipefail
cd "$(dirname "$0")/.."

HOST_PORT="${1:-localhost:8000}"
ROUTER="tools/dev-router.php"
LOG="$(mktemp)"

echo "== Lint =="
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 -P4 php -l >/dev/null && echo "  all PHP files parse"

echo "== Starting test server on ${HOST_PORT} =="
php -S "${HOST_PORT}" "${ROUTER}" >"${LOG}" 2>&1 &
SERVER_PID=$!
trap 'kill ${SERVER_PID} 2>/dev/null || true; rm -f "${LOG}"' EXIT
for i in $(seq 1 20); do
    curl -fs "http://${HOST_PORT}/" >/dev/null 2>&1 && break
    sleep 0.3
done

STATUS=0
echo "== Unit tests =="
php tests/unit_test.php || STATUS=1
echo "== HTTP flow (fail path, authorization, security) =="
php tests/http_flow_test.php "http://${HOST_PORT}" || STATUS=1
echo "== HTTP flow (pass path, certificate, verification, revocation) =="
php tests/pass_flow_test.php "http://${HOST_PORT}" || STATUS=1

echo
if [ "${STATUS}" -eq 0 ]; then
    echo "ALL TESTS PASSED"
else
    echo "SOME TESTS FAILED"; echo "--- server log ---"; tail -20 "${LOG}"
fi
exit "${STATUS}"
