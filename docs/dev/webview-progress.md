# WebView Widget — Development Progress

## Phase 1: C Helper Binary — Completed (2026-04-01)

### What was implemented
- `src/lib/webview_helper/webview_helper.cc` — ~280 LOC C++ helper binary
- `src/lib/webview_helper/CMakeLists.txt` — CMake build with FetchContent for webview v0.12.0
- `src/lib/webview_helper/build.sh` — convenience build script
- `src/lib/webview_helper/cJSON.c` + `cJSON.h` — vendored JSON parser (v1.7.18)
- `tests/webview/HelperBinaryTest.php` — 16 passing tests

### Deviations from plan
- **File renamed from `.c` to `.cc`**: The webview library is header-only C++ (`webview::core` is an INTERFACE target requiring `cxx_std_11`). The helper source must be compiled as C++ for the webview API symbols to be available. cJSON is wrapped with `extern "C"`.
- **`_exit(0)` instead of `pthread_join`**: After `webview_run()` returns, the reader thread may be blocked on `fgets(stdin)`. Closing stdin from another thread is undefined behavior on Linux. Using `_exit(0)` is the pragmatic solution — all useful work (writing the "closed" event, destroying the webview) is done before exit.
- **`static_cast<webview_hint_t>(hint)`**: C++ requires explicit int-to-enum conversion.

### Known issues
- `bind()` command leaks `strdup(name)` memory (the name string passed to `on_bound_call` is never freed). Minimal impact since bindings are typically created once and live for the process lifetime.
- No malformed JSON test from PHP side (tested at binary level only).

---

## Phase 2: ProcessWebView.php — Completed (2026-04-01)

### What was implemented
- `src/ProcessWebView.php` — process manager with full IPC lifecycle
- Non-blocking stdout with buffer accumulation for partial line handling
- Platform detection for binary path resolution
- Shutdown function for orphan prevention
- Windows `stream_select()` fallback

### Deviations from plan
- Initial config passed via argv (not stdin first-message) — simpler, avoids "first message is special" protocol complication. HTML content sent via `setHtml()` after construction.

### Known issues
- Windows `stream_set_blocking(false)` on pipes from `proc_open` is unreliable. The `stream_select()` fallback is in place but untested on actual Windows.

---

## Phase 3: WebView.php Widget — Completed (2026-04-01)

### What was implemented
- `src/Widget/WebView.php` — full widget API with 23 passing tests
- Command dispatch (JS→PHP) with error handling and automatic error return to JS
- Event dispatch (ready, closed, error, pong)
- Lifecycle callbacks (onReady, onClose, onError)
- `tests/webview/WebViewWidgetTest.php` — 23 tests
- `tests/webview/fixtures/test.html` — deterministic test page

### Design decisions
- Does NOT extend `AbstractWidget` — confirmed as correct. WebView is a separate native window, not a Tcl widget.
- `bind()` sends IPC to helper (Approach A from plan) — each binding creates a real `webview_bind()` in the helper, so JS can call functions directly by name.

---

## Phase 4: Application.php Integration — Completed (2026-04-01)

### What was implemented
- Modified `src/Application.php`: added `$webviews` array, `addWebView()`, `removeWebView()`, `tick()` method
- WebView polling in main event loop with auto-cleanup of closed instances
- Adaptive sleep: 20ms when WebViews active, 100ms when idle
- `quit()` destroys all WebViews before exiting
- `tests/webview/EventLoopIntegrationTest.php` — 7 tests
- **No regression**: existing Tk widget tests (WindowTest, ButtonTest) still pass

### Deviations from plan
- Added `tick()` method for testability — allows running single event loop iterations from tests without blocking.

---

## Code Review Fixes — Completed (2026-04-01)

### Security: Event name injection in emit command
- Event names passed to `window.__phpEmit()` were interpolated directly into JavaScript via `snprintf('%s', ...)`, allowing injection through crafted event names containing quotes.
- **Fix**: Event names are now JSON-encoded via `cJSON_CreateString` + `cJSON_PrintUnformatted`, producing a properly escaped quoted string passed to `__phpEmit()`.

### Memory leak: strdup(name) in bind command
- Each `bind` command called `strdup(name)` but the allocated string was never freed on `unbind` or process exit.
- **Fix**: Added a binding name registry (`binding_names[]` array). `bind` registers the pointer, `unbind` frees it, and `binding_registry_free_all()` cleans up at exit.

### Buffer limitation for large HTML payloads
- The stdin reader used a 64 KiB line buffer (`LINE_BUF_SIZE = 65536`), which would silently truncate large `set_html` payloads.
- **Fix**: Increased to 1 MiB (`1048576`).

### Code duplication in Application.php
- `run()` and `tick()` contained identical Tcl polling, callback dispatch, quit-file checking, and WebView polling logic.
- **Fix**: `run()` now delegates to `tick()` in a loop, with adaptive sleep after each tick.

### MSVC build errors on Windows CI
- Designated initializers (`.field = value`) require C++20 on MSVC; we target C++14. Replaced with sequential assignment.
- `_setmode` / `_O_BINARY` need `<io.h>` and `<fcntl.h>` — added missing includes.
- MSVC multi-config generators place binaries in `Release/` subdirectory, ignoring `RUNTIME_OUTPUT_DIRECTORY`. Added per-config overrides.
