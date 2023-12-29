#!/usr/bin/env bash

# Move into project root
BIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$BIN_DIR"
cd ..

# Exit on errors
set -e

CHANGED_FILES=$(git diff --cached --name-only --diff-filter=ACM -- '***.php')

if [[ -z "$CHANGED_FILES" ]]; then
  echo 'No changed files'
  exit 0
fi

if [[ -x vendor/bin/pint ]]; then
  vendor/bin/pint --dirty
  git add $CHANGED_FILES
else
  echo 'pint is not installed'
  exit 1
fi
