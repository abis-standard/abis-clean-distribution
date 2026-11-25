#!/usr/bin/env bash
# M-229D public disclosure gate — abis-standard/abis
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"

echo "=== ABIS Public Disclosure Gate (M-229D) ==="

"${DIR}/check-secrets.sh"
"${DIR}/check-test-vector-markers.sh"
python3 "${DIR}/enforce-allowlist.py"

echo "PUBLIC DISCLOSURE GATE: PASS"
# verify-private-approval.sh excluded from public scope (HD-ABIS-FINAL-PUBLICATION-SCOPE-BIND)
