#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   APP_DIR=/home/adfb2574/public_html ./deploy-git-server.sh [branch]
# Example:
#   APP_DIR=/home/adfb2574/public_html ./deploy-git-server.sh main

APP_DIR="${APP_DIR:-/home/adfb2574/public_html}"
BRANCH="${1:-main}"

cd "$APP_DIR"

git rev-parse --is-inside-work-tree >/dev/null

echo "Deploy kode dari origin/$BRANCH di $APP_DIR"
git fetch origin "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "Selesai: deploy kode berhasil. Database tidak disentuh."
