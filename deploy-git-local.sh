#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   ./deploy-git-local.sh [branch] [commit_message]
# Example:
#   ./deploy-git-local.sh main "fix breakfast guest list"

BRANCH="${1:-main}"
COMMIT_MSG="${2:-update: $(date +'%Y-%m-%d %H:%M:%S')}"

# Ensure we are inside a git repo.
git rev-parse --is-inside-work-tree >/dev/null

# Stage and commit only if there are changes.
if ! git diff --quiet || ! git diff --cached --quiet; then
  git add -A
  git commit -m "$COMMIT_MSG"
else
  echo "Tidak ada perubahan lokal untuk di-commit."
fi

# Push to selected branch.
git push origin "$BRANCH"

echo "Selesai: kode ter-push ke origin/$BRANCH"
