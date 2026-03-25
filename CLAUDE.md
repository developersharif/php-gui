# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PHP GUI library providing cross-platform desktop GUI development using Tcl/Tk via PHP's FFI extension. Requires PHP 8.1+ with `ext-ffi`. The goal is a **true zero-dependency package** — users just `composer require` and it works without installing any system packages.

## Commands

```bash
# Install dependencies
composer install

# Run the example app
php example.php

# Run tests (no test framework — plain PHP scripts)
php tests/index_test.php
php tests/widgets_test/WindowTest.php

# Run all tests
php tests/index_test.php && for f in tests/widgets_test/*.php; do php "$f"; done

# Docker zero-dependency test
docker build -t phpgui-test -f Dockerfile.test . && docker run --rm phpgui-test
```

There is no PHPUnit, linter, or build system configured.

## Architecture

**FFI Bridge Pattern**: PHP classes → `ProcessTCL` (FFI singleton) → native Tcl/Tk C library.

### Core Components

- **`ProcessTCL`** — Singleton that loads the native Tcl library via FFI and executes Tcl commands. Handles platform-specific library paths (`.so`, `.dll`, `.dylib`). Manages a callback registry mapping unique IDs to PHP closures. Sets up `LD_LIBRARY_PATH`, `TCL_LIBRARY`, `TK_LIBRARY` env vars to find bundled libraries. Falls back to system library paths if bundled `.so` fails to load.

- **`Application`** — Event loop. Initializes Tcl/Tk, then continuously calls `update` while polling temp files for callback triggers (`/tmp/phpgui_callback.txt`) and quit signals (`/tmp/phpgui_quit.txt`).

- **`AbstractWidget`** — Base class for all widgets. Assigns unique IDs via `uniqid()`, manages parent-child relationships, provides layout methods (`pack()`, `grid()`, `place()`), and converts PHP option arrays to Tcl option strings.

### Event Handling

Callbacks use a temp-file bridge: when a Tcl event fires, it writes a callback ID to `/tmp/phpgui_callback.txt`. The `Application` event loop detects this, looks up the registered PHP closure, and executes it. This is the central pattern — all interactive widgets (Button, Input, Menu) use this mechanism.

### Widget Hierarchy

All widgets extend `AbstractWidget`. `Window` and `TopLevel` are root-level (null parent). All others (Button, Label, Input, Frame, Menu, Canvas, etc.) require a parent widget. `Input` is an alias/wrapper around `Entry`.

### Native Libraries

Bundled in `src/lib/` — the package ships all native libraries needed for each platform:

- **Linux** (complete zero-dependency):
  - `libtcl8.6.so` — Tcl interpreter (built on Debian Bullseye, requires glibc 2.29+)
  - `libtk8.6.so` — Tk GUI toolkit (built with `--disable-xft` to minimize deps)
  - `tcl8.6/` — Tcl script libraries (init.tcl, encodings, etc.)
  - `tk8.6/` — Tk script libraries (widget scripts, pkgIndex.tcl)
  - `x11/` — Bundled X11 runtime libraries (libX11, libxcb, libXau, libXdmcp, libbsd, libmd)
- **macOS**: `libtcl9.0.dylib` (Tk not yet bundled — **next platform to support**)
- **Windows**: `windows/bin/tcl86t.dll`

### Building Portable Libraries

Portable `.so` files are built via Docker using `debian:bullseye-slim` as the base (glibc 2.31). See `.github/workflows/build-libs.yml` for the build process. Key build flags:
- Tcl: `--enable-shared`
- Tk: `--enable-shared --disable-xft --disable-xss` (eliminates libXft/fontconfig/freetype dependency chain)

### Platform Status

| Platform | Tcl | Tk | X11/Display Libs | Status |
|----------|-----|-----|-------------------|--------|
| Linux    | Bundled | Bundled | Bundled | **Zero-dependency** ✓ |
| macOS    | Bundled | Not bundled | N/A (uses Aqua) | **Next target** |
| Windows  | Bundled | Not bundled | N/A | Partial |

### CI/CD

- `.github/workflows/tests.yml` — Runs all tests on PRs to `main` (Ubuntu, PHP 8.1, only `xvfb` needed)
- `.github/workflows/build-libs.yml` — Manual workflow to rebuild portable Tcl/Tk binaries
- `Dockerfile.test` — Docker-based zero-dependency verification (installs only `libffi-dev` and `xvfb`)

### Namespace & Autoloading

PSR-4: `PhpGui\` → `src/`. Widgets are under `PhpGui\Widget\`.

## Key Patterns to Follow

- **Zero-dependency principle**: All native libraries must be bundled. Users should never need to `apt install`, `brew install`, or download anything beyond `composer require`.
- **Library loading fallback**: `ProcessTCL::loadTclLibrary()` tries bundled path first, then system paths as fallback.
- **Environment setup**: `ProcessTCL::setupLibraryPaths()` sets `TCL_LIBRARY`, `TK_LIBRARY`, `TCLLIBPATH`, and `LD_LIBRARY_PATH` before any library loading.
- **pkgIndex.tcl**: Must use relative path (`$dir/../libtk8.6.so`) not hardcoded system paths.
- **Widget paths**: Tcl widget paths must start with `.` (dot prefix). Format: `.{parentId}.{widgetId}`.
