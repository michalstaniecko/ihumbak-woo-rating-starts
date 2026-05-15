#!/bin/bash
#
# Build ZIP package for WordPress plugin installation
# Usage: ./build-zip.sh

PLUGIN_SLUG="ihumbak-woo-rating-stars"
PLUGIN_FILE="${PLUGIN_SLUG}.php"
VERSION=$(grep -m1 "Version:" "$PLUGIN_FILE" | sed 's/.*Version: *//' | tr -d '[:space:]')

if [ -z "$VERSION" ]; then
    echo "Error: Could not read version from ${PLUGIN_FILE}"
    exit 1
fi

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
BUILD_DIR="/tmp/${PLUGIN_SLUG}-build"

echo "Building ${ZIP_NAME}..."

# Install production Composer dependencies so vendor/ ships in the release ZIP.
if [ -f composer.json ]; then
    if ! command -v composer >/dev/null 2>&1; then
        echo "Error: composer.json is present but the 'composer' command is not available."
        exit 1
    fi
    echo "Installing Composer production dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction --quiet || {
        echo "Error: composer install failed"
        exit 1
    }
fi

# Clean previous build
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/${PLUGIN_SLUG}"

# Copy plugin files
cp "$PLUGIN_FILE" "$BUILD_DIR/${PLUGIN_SLUG}/"
cp uninstall.php "$BUILD_DIR/${PLUGIN_SLUG}/"

cp -r admin "$BUILD_DIR/${PLUGIN_SLUG}/"
cp -r assets "$BUILD_DIR/${PLUGIN_SLUG}/"
cp -r database "$BUILD_DIR/${PLUGIN_SLUG}/"
cp -r includes "$BUILD_DIR/${PLUGIN_SLUG}/"
cp -r public "$BUILD_DIR/${PLUGIN_SLUG}/"
cp -r templates "$BUILD_DIR/${PLUGIN_SLUG}/"

# Vendor dependencies (required for plugin-update-checker at runtime)
[ -d vendor ] && cp -r vendor "$BUILD_DIR/${PLUGIN_SLUG}/"
[ -f composer.json ] && cp composer.json "$BUILD_DIR/${PLUGIN_SLUG}/"
[ -f composer.lock ] && cp composer.lock "$BUILD_DIR/${PLUGIN_SLUG}/"

# Copy optional files if they exist
[ -f README.md ] && cp README.md "$BUILD_DIR/${PLUGIN_SLUG}/"
[ -f CHANGELOG.md ] && cp CHANGELOG.md "$BUILD_DIR/${PLUGIN_SLUG}/"
[ -f readme.txt ] && cp readme.txt "$BUILD_DIR/${PLUGIN_SLUG}/"
[ -d languages ] && cp -r languages "$BUILD_DIR/${PLUGIN_SLUG}/"

# Remove unwanted files from build
find "$BUILD_DIR" -name '.DS_Store' -delete
find "$BUILD_DIR" -name '*.map' -delete
find "$BUILD_DIR" -name '.gitkeep' -delete

# Create ZIP
rm -f "$ZIP_NAME"
cd "$BUILD_DIR" && zip -r "$OLDPWD/$ZIP_NAME" "$PLUGIN_SLUG" -x "*.DS_Store"

# Cleanup
rm -rf "$BUILD_DIR"

echo ""
echo "Done! Created: ${ZIP_NAME} ($(du -h "$OLDPWD/$ZIP_NAME" | cut -f1))"
echo "Version: ${VERSION}"
