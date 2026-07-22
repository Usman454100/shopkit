#!/usr/bin/env bash
# Builds admin-web and superadmin-web, then copies the output into the
# Laravel backend's public/ directory (docs/02-ARCHITECTURE.md §4 steps 1-2).
#
# Runs on the build machine (locally or in CI) — NOT on the cPanel server
# itself. Only the finished artifacts get uploaded; Node.js is never needed
# at runtime on cPanel.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

build_and_copy() {
  local app_dir="$1"
  local public_target="$2"

  echo "==> Building ${app_dir}"
  (cd "${ROOT_DIR}/${app_dir}" && npm install && npm run build)

  echo "==> Copying ${app_dir}/dist -> backend/public/${public_target}"
  rm -rf "${ROOT_DIR}/backend/public/${public_target}"
  mkdir -p "${ROOT_DIR}/backend/public/${public_target}"
  cp -r "${ROOT_DIR}/${app_dir}/dist/." "${ROOT_DIR}/backend/public/${public_target}/"
}

build_and_copy "admin-web" "admin"
build_and_copy "superadmin-web" "superadmin"

echo "==> Done. Built assets are in backend/public/admin and backend/public/superadmin."
