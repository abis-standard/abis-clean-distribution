#!/usr/bin/env bash
# Public export build helper — run from extracted source tree (HD-ABIS-GITHUB-RECOVERY-OPTION-B-EXECUTION-01).
# Set BUILD_OUTDIR to a writable directory outside the source tree.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PRODUCT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
BUILD_OUTDIR="${BUILD_OUTDIR:?Set BUILD_OUTDIR to an isolated artifacts directory}"
STAGING="${BUILD_OUTDIR}/staging"
ARTIFACTS="${BUILD_OUTDIR}/artifacts"
export SOURCE_DATE_EPOCH="${SOURCE_DATE_EPOCH:-1732492800}"
python3 -m venv "${BUILD_OUTDIR}/.venv-build"
"${BUILD_OUTDIR}/.venv-build/bin/pip" install -q "setuptools==84.2.1" "wheel==0.48.0" "build==1.2.2.post1"
rm -rf "${STAGING}" && mkdir -p "${STAGING}" "${ARTIFACTS}"
rsync -a --delete \
  --exclude 'build/' --exclude 'dist/' --exclude '*.egg-info/' \
  --exclude '.venv-test/' --exclude '__pycache__/' \
  --exclude '/registry.json' \
  "${PRODUCT_ROOT}/" "${STAGING}/"
"${BUILD_OUTDIR}/.venv-build/bin/python" -m build --outdir "${ARTIFACTS}" "${STAGING}"
