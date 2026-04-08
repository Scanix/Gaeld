#!/bin/sh
#
# Set up git hooks and custom merge drivers for the Gäld API repo.
# Run once after cloning: ./scripts/setup-git.sh
#

set -e

echo "🔧 Configuring git hooks path..."
git config core.hooksPath .githooks

echo "🔧 Registering 'keep-ours' merge driver (production-only files)..."
git config merge.keep-ours.name "Keep ours (production-only files)"
git config merge.keep-ours.driver true

echo "✅ Git setup complete."
