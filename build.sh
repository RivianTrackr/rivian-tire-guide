#!/usr/bin/env bash
#
# Rivian Tire Guide — Build Script
#
# Thin wrapper over the one real pipeline (esbuild via npm). The script used
# to be a second, divergent pipeline: it built only 3 of esbuild's 8 targets
# (a checkout built with it 404'd every admin script), and its no-tooling
# fallback stripped `//.*$` — corrupting any line carrying `https://` inside
# a string. There is exactly one way to build now.
#
# Usage:  bash build.sh
#
# Requirements: Node.js 18+ with npm.

set -euo pipefail

cd "$(cd "$(dirname "$0")" && pwd)"

if ! command -v npm &>/dev/null; then
    echo "Error: npm is required — install Node.js 18+ and re-run." >&2
    exit 1
fi

if [ ! -d node_modules ]; then
    echo "Installing build dependencies..."
    npm ci
fi

npm run build
