#!/bin/bash
#
# Build script for Secure Freelancer Access WordPress plugin
# Creates a clean ZIP archive ready for WordPress installation
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Plugin info
PLUGIN_SLUG="secure-freelancer-access"
PLUGIN_FILE="secure-freelancer-access.php"

# Get version from main plugin file
VERSION=$(grep -E "^\s*\*\s*Version:" "$PLUGIN_FILE" | sed 's/.*Version:\s*//' | tr -d ' \r')

if [ -z "$VERSION" ]; then
    echo -e "${RED}Error: Could not extract version from $PLUGIN_FILE${NC}"
    exit 1
fi

echo -e "${GREEN}Building ${PLUGIN_SLUG} v${VERSION}${NC}"
echo "=================================="

# Create build directory
BUILD_DIR="build"
PLUGIN_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"
ZIP_FILE="${PLUGIN_SLUG}-${VERSION}.zip"

# Clean previous build
if [ -d "$BUILD_DIR" ]; then
    echo -e "${YELLOW}Cleaning previous build...${NC}"
    rm -rf "$BUILD_DIR"
fi

mkdir -p "$PLUGIN_DIR"

# Files and folders to include
echo "Copying plugin files..."

# Main plugin file
cp "$PLUGIN_FILE" "$PLUGIN_DIR/"

# readme.txt (required for WordPress.org)
cp "readme.txt" "$PLUGIN_DIR/"

# PHP includes
if [ -d "includes" ]; then
    cp -r "includes" "$PLUGIN_DIR/"
    echo "  ✓ includes/"
fi

# Assets (CSS, JS, images)
if [ -d "assets" ]; then
    cp -r "assets" "$PLUGIN_DIR/"
    echo "  ✓ assets/"
fi

# Languages (translations)
if [ -d "languages" ]; then
    cp -r "languages" "$PLUGIN_DIR/"
    # Remove .gitkeep if present
    rm -f "$PLUGIN_DIR/languages/.gitkeep"
    echo "  ✓ languages/"
fi

# Remove any .DS_Store files
find "$PLUGIN_DIR" -name ".DS_Store" -delete 2>/dev/null || true

# Create ZIP archive
echo ""
echo "Creating ZIP archive..."
cd "$BUILD_DIR"
zip -r "../$ZIP_FILE" "$PLUGIN_SLUG" -x "*.DS_Store" -x "*/.git/*"
cd ..

# Get file size
ZIP_SIZE=$(du -h "$ZIP_FILE" | cut -f1)

# Cleanup build directory
rm -rf "$BUILD_DIR"

echo ""
echo -e "${GREEN}✓ Build complete!${NC}"
echo "=================================="
echo -e "Archive: ${GREEN}${ZIP_FILE}${NC}"
echo -e "Size:    ${ZIP_SIZE}"
echo ""
echo "Contents:"
unzip -l "$ZIP_FILE" | grep -E "^\s+[0-9]" | awk '{print "  " $4}'
echo ""
echo -e "${YELLOW}Ready for WordPress installation!${NC}"
