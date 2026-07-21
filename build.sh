#!/usr/bin/env bash
#
# Build a clean, distributable zip of the plugin.
# Excludes dev tooling, VCS, and the previous build so the package that ships
# to customers / WordPress.org contains only runtime files.
#
# Usage: ./build.sh
# Output: dist/meta-pixel-capi.zip  (unpacks to a meta-pixel-capi/ folder)

set -euo pipefail

SLUG="meta-pixel-capi"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST="$ROOT/dist"
STAGE="$DIST/$SLUG"

# Read version from the plugin header for a nice, versioned filename.
VERSION="$(grep -m1 -E "^\s*\*\s*Version:" "$ROOT/$SLUG.php" | sed -E 's/.*Version:\s*//' | tr -d '[:space:]')"
VERSION="${VERSION:-dev}"

echo "Building $SLUG $VERSION ..."

rm -rf "$DIST"
mkdir -p "$STAGE"

# Copy the plugin, excluding everything that should never ship.
rsync -a --delete \
	--exclude '.git' \
	--exclude '.github' \
	--exclude '.gitignore' \
	--exclude '.gitattributes' \
	--exclude '.editorconfig' \
	--exclude '.vscode' \
	--exclude '.DS_Store' \
	--exclude 'dist' \
	--exclude 'node_modules' \
	--exclude '*.log' \
	--exclude 'build.sh' \
	--exclude 'README.md' \
	--exclude 'composer.json' \
	--exclude 'composer.lock' \
	--exclude 'phpcs.xml.dist' \
	--exclude 'vendor' \
	--exclude "$SLUG.zip" \
	"$ROOT/" "$STAGE/"

# Zip so it unpacks to a folder named after the slug.
( cd "$DIST" && zip -rq "$SLUG.zip" "$SLUG" )
rm -rf "$STAGE"

echo "Done -> dist/$SLUG.zip"
