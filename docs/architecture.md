# Architecture

## Overview

PHP GUI uses PHP's FFI (Foreign Function Interface) extension to bridge PHP code with native Tcl/Tk libraries. This enables building desktop GUI applications in pure PHP without any compiled extensions.

<img src="system.svg" style="width:250px" alt="System Architecture Diagram">

## How It Works

```
PHP Application
    ↓
Widget Classes (Window, Button, Label, ...)
    ↓
ProcessTCL (FFI Singleton)
    ↓
Native Tcl/Tk C Libraries
    ↓
OS Window Manager
```

### Core Components

#### ProcessTCL

The `ProcessTCL` class is the FFI singleton that:

- Loads the native Tcl/Tk library via FFI
- Detects the platform (Linux, Windows, macOS) and loads the appropriate binaries
- Executes Tcl commands from PHP
- Manages a callback registry mapping unique IDs to PHP closures
- Supports both Tcl 8.6 and Tcl 9.0

#### Application

The `Application` class manages the event loop:

- Initializes the Tcl/Tk environment
- Continuously calls `update` to process Tcl events
- Polls for callback triggers via temp files
- Manages the application lifecycle (start/stop)

#### AbstractWidget

The base class for all widgets:

- Assigns unique IDs via `uniqid()`
- Manages parent-child widget relationships
- Provides layout methods: `pack()`, `grid()`, `place()`
- Converts PHP option arrays to Tcl option strings

## Event Handling

The event system uses a temp-file bridge pattern:

1. A Tcl event fires (e.g., button click)
2. The callback ID is written to `/tmp/phpgui_callback.txt`
3. The `Application` event loop detects this file
4. It looks up the PHP closure by ID and executes it
5. The temp file is cleaned up

This approach bridges Tcl's event system with PHP's synchronous execution model.

## Bundled Libraries

The library bundles native Tcl/Tk binaries for all platforms, so no system installation is required:

| Platform | Libraries |
|----------|-----------|
| Linux | `libtcl8.6.so`, `libtk8.6.so` + X11 dependencies |
| Windows | `tcl86t.dll`, `tk86t.dll` |
| macOS | `libtcl9.0.dylib`, `libtk9.0.dylib` |

## WebView Architecture <sup>Beta</sup>

The WebView widget uses a **helper process** model instead of FFI. A small native binary hosts the platform's browser engine and communicates with PHP over JSON-over-stdio IPC.

```
PHP Application
    ↓
WebView Widget (PHP)
    ↓ JSON/stdio IPC
webview_helper binary (C++)
    ↓
Platform Browser Engine
    ├── WebKitGTK (Linux)
    ├── WKWebView (macOS)
    └── WebView2 (Windows)
```

### Key differences from Tcl/Tk widgets

| Aspect         | Tcl/Tk Widgets              | WebView                         |
|----------------|-----------------------------|---------------------------------|
| Rendering      | Native Tk controls          | HTML/CSS/JS in browser engine   |
| Bridge         | FFI (in-process)            | Separate helper process + IPC   |
| Base class     | Extends `AbstractWidget`    | Standalone (no inheritance)     |
| Communication  | Tcl commands                | JSON messages over stdin/stdout |

### IPC Protocol

Messages are newline-delimited JSON objects with a `version` field:

```json
{"version":1,"cmd":"navigate","url":"https://example.com"}
{"version":1,"event":"ready"}
{"version":1,"event":"command","name":"greet","id":"0","args":"[\"Alice\"]"}
```

- **Commands** (PHP → Helper): `navigate`, `set_html`, `set_title`, `set_size`, `eval`, `init`, `bind`, `unbind`, `return`, `emit`, `ping`, `destroy`
- **Events** (Helper → PHP): `ready`, `closed`, `command`, `error`, `pong`

### Helper Binary

The helper binary is platform-specific and auto-downloaded from GitHub Releases on first use:

| Platform      | Binary                              |
|---------------|-------------------------------------|
| Linux x86_64  | `webview_helper_linux_x86_64`       |
| macOS ARM     | `webview_helper_darwin_arm64`       |
| macOS Intel   | `webview_helper_darwin_x86_64`      |
| Windows x64   | `webview_helper_windows_x86_64.exe` |

If auto-download fails, build from source: `cd src/lib/webview_helper && bash build.sh`

## Namespace & Autoloading

The project uses PSR-4 autoloading:

- `PhpGui\` → `src/`
- `PhpGui\Widget\` → `src/Widget/`
