# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PHP GUI library providing cross-platform desktop GUI development using Tcl/Tk via PHP's FFI extension. Requires PHP 8.1+ with `ext-ffi`.

## Commands

```bash
# Install dependencies
composer install

# Run the example app
php example.php

# Run tests (no test framework — plain PHP scripts)
php tests/index_test.php
php tests/widgets_test/WindowTest.php
```

There is no PHPUnit, linter, or build system configured.

## Architecture

**FFI Bridge Pattern**: PHP classes → `ProcessTCL` (FFI singleton) → native Tcl/Tk C library.

### Core Components

- **`ProcessTCL`** — Singleton that loads the native Tcl library via FFI and executes Tcl commands. Handles platform-specific library paths (`.so`, `.dll`, `.dylib`). Manages a callback registry mapping unique IDs to PHP closures.

- **`Application`** — Event loop. Initializes Tcl/Tk, then continuously calls `update` while polling temp files for callback triggers (`/tmp/phpgui_callback.txt`) and quit signals (`/tmp/phpgui_quit.txt`).

- **`AbstractWidget`** — Base class for all widgets. Assigns unique IDs via `uniqid()`, manages parent-child relationships, provides layout methods (`pack()`, `grid()`, `place()`), and converts PHP option arrays to Tcl option strings.

### Event Handling

Callbacks use a temp-file bridge: when a Tcl event fires, it writes a callback ID to `/tmp/phpgui_callback.txt`. The `Application` event loop detects this, looks up the registered PHP closure, and executes it. This is the central pattern — all interactive widgets (Button, Input, Menu) use this mechanism.

### Widget Hierarchy

All widgets extend `AbstractWidget`. `Window` and `TopLevel` are root-level (null parent). All others (Button, Label, Input, Frame, Menu, Canvas, etc.) require a parent widget. `Input` is an alias/wrapper around `Entry`.

### Native Libraries

Pre-compiled Tcl libraries are bundled in `src/lib/`:
- Linux: `libtcl8.6.so`
- macOS: `libtcl9.0.dylib`
- Windows: `windows/bin/tcl86t.dll`

### Namespace & Autoloading

PSR-4: `PhpGui\` → `src/`. Widgets are under `PhpGui\Widget\`.
