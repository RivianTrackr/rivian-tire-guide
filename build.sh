#!/usr/bin/env bash
#
# Rivian Tire Guide — Build Script
# Generates minified CSS and JS files for production.
#
# Usage:  bash build.sh
#
# Requirements: Node.js with npx available, OR terser/csso installed globally.
# Falls back to basic regex-based minification if no tools are found.

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
CSS_SRC="$PLUGIN_DIR/frontend/css/rivian-tires.css"
JS_SRC="$PLUGIN_DIR/frontend/js/rivian-tires.js"
ADMIN_CSS_SRC="$PLUGIN_DIR/admin/css/admin-styles.css"

CSS_MIN="$PLUGIN_DIR/frontend/css/rivian-tires.min.css"
JS_MIN="$PLUGIN_DIR/frontend/js/rivian-tires.min.js"
ADMIN_CSS_MIN="$PLUGIN_DIR/admin/css/admin-styles.min.css"

echo "Building Rivian Tire Guide assets..."

# --- CSS Minification ---
minify_css() {
    local src="$1" dest="$2"
    if command -v npx &>/dev/null; then
        npx --yes csso-cli "$src" -o "$dest" 2>/dev/null && return 0
    fi
    # Fallback: basic CSS minification via sed.
    sed -e 's|/\*[^*]*\*\+([^/][^*]*\*\+)*/||g' \
        -e 's/^[[:space:]]*//g' \
        -e 's/[[:space:]]*$//g' \
        -e '/^$/d' \
        "$src" | tr -d '\n' | sed 's/  */ /g' > "$dest"
}

# --- JS Minification ---
minify_js() {
    local src="$1" dest="$2"
    if command -v npx &>/dev/null; then
        npx --yes terser "$src" -o "$dest" --compress --mangle 2>/dev/null && return 0
    fi
    # Fallback: strip comments and blank lines.
    sed -e 's|//.*$||g' \
        -e '/^[[:space:]]*$/d' \
        "$src" > "$dest"
}

# Build each asset.
printf "  CSS: %s → %s ... " "$(basename "$CSS_SRC")" "$(basename "$CSS_MIN")"
minify_css "$CSS_SRC" "$CSS_MIN"
echo "done ($(wc -c < "$CSS_MIN") bytes)"

printf "  CSS: %s → %s ... " "$(basename "$ADMIN_CSS_SRC")" "$(basename "$ADMIN_CSS_MIN")"
minify_css "$ADMIN_CSS_SRC" "$ADMIN_CSS_MIN"
echo "done ($(wc -c < "$ADMIN_CSS_MIN") bytes)"

printf "   JS: %s → %s ... " "$(basename "$JS_SRC")" "$(basename "$JS_MIN")"
minify_js "$JS_SRC" "$JS_MIN"
echo "done ($(wc -c < "$JS_MIN") bytes)"

echo ""
echo "Build complete."
