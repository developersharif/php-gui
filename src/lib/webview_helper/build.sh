#!/usr/bin/env bash
#
# Build the webview helper binary for the current platform.
#
# Prerequisites:
#   Linux:   sudo apt-get install -y cmake libgtk-3-dev libwebkit2gtk-4.1-dev
#   macOS:   Xcode command line tools (xcode-select --install)
#   Windows: CMake + MSVC or MinGW with C++14 support
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="${SCRIPT_DIR}/build"

echo "==> Building webview_helper..."
echo "    Source: ${SCRIPT_DIR}"
echo "    Build:  ${BUILD_DIR}"

mkdir -p "${BUILD_DIR}"
cd "${BUILD_DIR}"

cmake "${SCRIPT_DIR}" -DCMAKE_BUILD_TYPE=Release
cmake --build . --config Release

echo ""
echo "==> Build complete! Binary placed in: $(dirname "${SCRIPT_DIR}")"
ls -lh "$(dirname "${SCRIPT_DIR}")"/webview_helper_* 2>/dev/null || echo "    (binary in build dir)"
