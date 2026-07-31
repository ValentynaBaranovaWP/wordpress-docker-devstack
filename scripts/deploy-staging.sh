#!/usr/bin/env bash
set -euo pipefail

: "${STAGING_HOST:?STAGING_HOST is required}"
: "${STAGING_USER:?STAGING_USER is required}"
: "${STAGING_PATH:?STAGING_PATH is required}"
STAGING_PORT="${STAGING_PORT:-22}"

rsync -az --delete \
  -e "ssh -p ${STAGING_PORT} -o StrictHostKeyChecking=accept-new" \
  --exclude-from=.deployignore \
  ./ "${STAGING_USER}@${STAGING_HOST}:${STAGING_PATH%/}/"

echo "Deployed to ${STAGING_USER}@${STAGING_HOST}:${STAGING_PATH}"
