# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PHP GUI library for cross-platform desktop apps. Two rendering modes share one event loop:

- **Native widgets** — Tcl/Tk loaded via PHP FFI (forms, dialogs, system controls).
- **WebView** — HTML/CSS/JS frontend driven by a bundled `webview_helper` subprocess (Tauri-like, with a JS↔PHP bridge).

Requires PHP 8.1+ with `ext-ffi` (`ffi.enable=true` in `php.ini`). Native libraries for both modes are bundled under `src/lib/` — no system packages required on Linux/macOS/Windows (Linux WebView still needs `libwebkit2gtk-4.1-dev`).

## Commands

```bash
composer install                          # install (no runtime deps; sets up autoload)
php example.php                           # run example app

# Tests — plain PHP scripts, no PHPUnit. Each script exits 1 on failure.
php tests/index_test.php                  # integration smoke test
php tests/widgets_test/WindowTest.php     # single widget suite
php tests/webview/WebViewWidgetTest.php   # webview suite (needs helper binary)
```

There is no linter, build system, or PHPUnit. Tests use the in-repo `tests/TestRunner.php` (suite + `assert*` helpers, summary on exit).

## Architecture

### Two processes, one event loop

```
PHP process ──FFI──▶ libtcl/libtk           (native widgets, in-process)
       │
       └──proc_open──▶ webview_helper       (separate native window, JSON-over-stdio IPC)
```

`Application::run()` is the single event loop driving both: it calls Tcl `update`, polls callback temp files, and pumps stdin/stdout for any registered WebView helpers.

### Native-widget path

- **`ProcessTCL`** (`src/ProcessTCL.php`) — FFI singleton. Loads the platform-specific Tcl shared library from `src/lib/`, executes Tcl commands, and owns the callback registry (unique ID → PHP closure).
- **`AbstractWidget`** (`src/Widget/AbstractWidget.php`) — base for all Tcl/Tk widgets. Generates IDs via `uniqid()`, manages parent paths, exposes `pack()` / `grid()` / `place()`, converts PHP option arrays into Tcl option strings.
- **Widget hierarchy** — `Window` and `TopLevel` are root (null parent). All others (`Button`, `Label`, `Input`/`Entry`, `Frame`, `Menu`, `Canvas`, `Checkbutton`, `Combobox`, `Menubutton`, `Message`, `Image`) require a parent widget ID. `Input` wraps `Entry`.

#### Tcl callback bridge (the central pattern)

When a Tcl event fires, the bound Tcl command writes the callback ID to `/tmp/phpgui_callback.txt`. The event loop tails this file, looks up the ID in `ProcessTCL`'s registry, and invokes the PHP closure. A second temp file (`/tmp/phpgui_quit.txt`) signals quit. **Every interactive widget (Button `command`, Input `onEnter`, Menu commands, etc.) goes through this file-based bridge** — when adding a new event type, follow the same pattern and register via `ProcessTCL`.

### WebView path

- **`ProcessWebView`** (`src/ProcessWebView.php`) — analogous to `ProcessTCL`, but spawns the `webview_helper` binary via `proc_open()` and speaks newline-delimited JSON on stdin/stdout. Non-blocking reads buffered through a single read buffer.
- **`Widget\WebView`** (`src/Widget/WebView.php`) — does **not** extend `AbstractWidget` (it owns a separate OS window, not a Tcl/Tk widget). Registered with `Application::addWebView()` so the event loop pumps it.
- **Helper binary** — prebuilt per platform under `src/lib/`: `webview_helper_linux_x86_64`, `webview_helper` (macOS), and a Windows variant. Wraps WebKitGTK / WKWebView / WebView2 with a uniform JSON protocol. Installed/copied by `src/Install/LibraryInstaller.php` and `scripts/install-webview-helper.php`.
- **JS↔PHP bridge** — `WebView::bind($name, $cb)` exposes PHP to JS as `invoke(name, ...args)`; `WebView::emit($event, $data)` pushes events to the page (`onPhpEvent(name, cb)` on the JS side). Both flow over the same JSON IPC channel.
- **Frontend serving** — `serveFromDisk($dir)` registers a custom URI scheme per platform (`phpgui://` on Linux, `https://phpgui.localhost/` via virtual host on Windows, `loadFileURL:allowingReadAccess:` on macOS). `serveVite($distDir)` auto-detects a running Vite dev server (HMR) vs. production build. `enableFetchProxy()` routes `fetch()` calls through PHP to bypass CORS on `phpgui://`/`file://` origins — must be called **before** `serveFromDisk()` / `serveVite()`.

### Native libraries (`src/lib/`)

| Platform | Tcl/Tk | WebView helper |
|---|---|---|
| Linux x86-64 | `libtcl8.6.so`, `libtk8.6.so` | `webview_helper_linux_x86_64` |
| macOS | `libtcl9.0.dylib`, `libtk9.0.dylib`, `libtommath.1.dylib` | `webview_helper` |
| Windows | `windows/bin/tcl86t.dll` | (under `windows/`) |

Linux Tcl libs are rebuilt against an older glibc via `build/rebuild-linux-libs.dockerfile` to stay compatible with glibc 2.34+. Don't replace these by-hand — use the dockerfile.

### Namespace & autoload

PSR-4: `PhpGui\` → `src/`. Widgets under `PhpGui\Widget\`. Test helper under `PhpGuiTest\` → `tests/`.

## Tests

Each test file is standalone: `require` `vendor/autoload.php` and `TestRunner.php`, call `TestRunner::suite('Name')`, run assertions, end with `TestRunner::summary()` (which `exit(1)`s on any failure).

Common assertions:
- `TestRunner::assert(bool, msg)` / `assertEqual(expected, actual, msg)`
- `TestRunner::assertWidgetExists(".widgetPath", msg)` — checks via Tcl `winfo exists`

Callbacks can be triggered without a real GUI event by calling `ProcessTCL::getInstance()->executeCallback($widgetId)` directly (see `tests/index_test.php` for the pattern). WebView tests under `tests/webview/` exercise the helper subprocess and IPC; they require the helper binary to be present.
