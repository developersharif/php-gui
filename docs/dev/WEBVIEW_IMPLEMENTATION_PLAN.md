# WebView Widget Implementation Plan

## Research Summary

**Library**: [webview/webview](https://github.com/webview/webview) v0.12.0 (MIT license)

- 17 C API functions, tiny footprint
- Uses native engines: WebKitGTK (Linux), WKWebView (macOS), WebView2/Edge (Windows)
- **Existing PHP FFI binding exists**: [0hr/php-webview](https://github.com/0hr/php-webview) (64 stars, ships prebuilt binaries)

### Critical Constraints

1. `webview_run()` is **blocking** — it owns the main thread event loop. No `webview_step()` exists.
2. PHP FFI **cannot create C function pointers from closures** — so `webview_bind()` callback can't be passed directly from PHP FFI.
3. Tk and GTK **cannot share the same event loop** — they use different windowing stacks.

---

## Tauri Architecture Analysis

Our design is informed by [Tauri v2](https://v2.tauri.app/concept/architecture/), a production-grade framework that uses the same native webview approach. Tauri validates our architectural decisions.

### Tauri's Process Model

```
┌─────────────────────────────────────┐
│       Core Process (Rust/TAO)       │
│  - Full OS access                   │
│  - Creates/manages windows          │
│  - Routes ALL IPC (central hub)     │
│  - Global state, DB, filesystem     │
│  - Security: intercepts & filters   │
└──────────────┬──────────────────────┘
               │ IPC (commands ↑ events ↓)
┌──────────────▼──────────────────────┐
│       WebView Process (WRY)         │
│  - HTML/CSS/JS only                 │
│  - NO direct OS access              │
│  - Calls backend via invoke()       │
│  - Native engine per OS:            │
│    Linux: WebKitGTK                 │
│    macOS: WKWebView                 │
│    Windows: WebView2                │
└─────────────────────────────────────┘
```

**Key Tauri libraries:**
- **WRY**: Cross-platform webview abstraction (wraps native engines — same as `webview/webview`)
- **TAO**: Window creation/management (forked from `winit`)

### Mapping Tauri → php-gui

| Tauri Concept | php-gui Equivalent |
|---------------|-------------------|
| Core Process (Rust) | **PHP Application** (ProcessTCL + ProcessWebView) |
| WRY (webview abstraction) | **webview/webview** C library (same native engines) |
| TAO (window management) | **Tcl/Tk** via FFI |
| `invoke()` commands (JS → Rust) | **Commands** (JS → PHP via helper IPC) |
| `emit()` events (Rust → JS) | **Events** (PHP → JS via helper IPC) |
| IPC routed through Core | **All IPC through PHP** (JSON over stdin/stdout) |
| Permissions/Capabilities | Planned for future phase |

### Key Lessons From Tauri Applied to Our Design

1. **All IPC routes through the Core (PHP)** — WebView has zero direct OS access. PHP is the gatekeeper that validates and routes every message. The helper binary is intentionally dumb.

2. **Commands + Events pattern** — Tauri separates two IPC directions: Commands (JS → backend, request/response) and Events (backend → JS, push/pub-sub). We adopt this same pattern.

3. **Native webview over bundled Chromium** — Tauri explicitly chose OS native webviews. Result: ~2-5 MB apps vs ~150 MB Electron apps. Rendering differences are minimal for app UIs. This confirms our `webview/webview` approach.

4. **Principle of Least Privilege** — WebView processes get minimal permissions. Sensitive logic stays in the Core. User input is sanitized. Secrets never touch the frontend.

5. **Plugin architecture** — Tauri uses a 3-part plugin pattern (Rust impl + glue + JS API). We plan this for a future phase.

---

## Architecture Decision

| Approach | Feasibility | Verdict |
|----------|------------|---------|
| Direct FFI (load libwebview.so) | Blocked by: blocking event loop + can't create C function pointers for `webview_bind()` | **Not viable** |
| Embed in Tk window via X11 reparenting | Extremely brittle, platform-specific, Tk≠GTK | **Not viable** |
| Manual GTK event pumping | No public `webview_step()`, fragile | **Not viable** |
| **Helper process + JSON IPC** | Clean separation, no event loop conflict, cross-platform, Tauri-aligned | **Recommended** |

---

## Architecture: Helper Process Model

```
┌──────────────────────────────────────────────┐
│              PHP Application (Core)          │
│                                              │
│  ┌─────────┐  ┌───────────────────────────┐  │
│  │ Tk GUI  │  │  WebView Widget (PHP)     │  │
│  │ Window  │  │  ProcessWebView.php       │  │
│  │ (TAO≈)  │  │  - command dispatch       │  │
│  │         │  │  - event handling          │  │
│  │         │  │  - binding registry        │  │
│  └────┬────┘  └─────────────┬─────────────┘  │
│       │                     │ stdin/stdout    │
│       │                     │ (JSON lines)    │
│  Application::run()         │                │
│  ├─ Tcl update              │                │
│  ├─ poll Tk callbacks       │                │
│  └─ poll webview events ────┘                │
│                                              │
│  Security Layer:                             │
│  ├─ URL allowlist (optional)                 │
│  ├─ Binding validation                       │
│  └─ All OS access gated through PHP          │
└──────────────────┬───────────────────────────┘
                   │ proc_open()
       ┌───────────▼───────────┐
       │   webview_helper      │
       │   (C binary ~200 LOC) │
       │   (WRY≈)              │
       │                       │
       │  stdin ──► command    │
       │  reader    dispatcher │
       │  thread    (dispatch) │
       │            ▼          │
       │       webview_run()   │
       │       [main thread]   │
       │            │          │
       │  stdout ◄── events    │
       └───────────────────────┘
```

**Design principle (from Tauri):** The helper binary is intentionally minimal — it only hosts the webview and relays messages. All business logic, state management, and security decisions live in the PHP Core process.

---

## webview/webview C API Reference

### Type Definitions

```c
typedef void *webview_t;

typedef enum {
    WEBVIEW_ERROR_MISSING_DEPENDENCY = -5,
    WEBVIEW_ERROR_CANCELED           = -4,
    WEBVIEW_ERROR_INVALID_STATE      = -3,
    WEBVIEW_ERROR_INVALID_ARGUMENT   = -2,
    WEBVIEW_ERROR_UNSPECIFIED        = -1,
    WEBVIEW_ERROR_OK                 =  0,
    WEBVIEW_ERROR_DUPLICATE          =  1,
    WEBVIEW_ERROR_NOT_FOUND          =  2
} webview_error_t;

typedef enum {
    WEBVIEW_HINT_NONE,   // Default size
    WEBVIEW_HINT_MIN,    // Minimum bounds
    WEBVIEW_HINT_MAX,    // Maximum bounds
    WEBVIEW_HINT_FIXED   // Cannot be resized
} webview_hint_t;

typedef enum {
    WEBVIEW_NATIVE_HANDLE_KIND_UI_WINDOW,           // GtkWindow / NSWindow / HWND
    WEBVIEW_NATIVE_HANDLE_KIND_UI_WIDGET,           // GtkWidget / NSView / HWND
    WEBVIEW_NATIVE_HANDLE_KIND_BROWSER_CONTROLLER   // WebKitWebView / WKWebView / ICoreWebView2Controller
} webview_native_handle_kind_t;

typedef struct {
    unsigned int major, minor, patch;
} webview_version_t;

typedef struct {
    webview_version_t version;
    char version_number[32];
    char pre_release[48];
    char build_metadata[48];
} webview_version_info_t;
```

### Function Signatures (17 functions)

```c
// Lifecycle
webview_t       webview_create(int debug, void *window);
webview_error_t webview_destroy(webview_t w);
webview_error_t webview_run(webview_t w);
webview_error_t webview_terminate(webview_t w);

// Threading
webview_error_t webview_dispatch(webview_t w, void (*fn)(webview_t w, void *arg), void *arg);

// Window management
void           *webview_get_window(webview_t w);
void           *webview_get_native_handle(webview_t w, webview_native_handle_kind_t kind);
webview_error_t webview_set_title(webview_t w, const char *title);
webview_error_t webview_set_size(webview_t w, int width, int height, webview_hint_t hints);

// Content
webview_error_t webview_navigate(webview_t w, const char *url);
webview_error_t webview_set_html(webview_t w, const char *html);

// JavaScript
webview_error_t webview_init(webview_t w, const char *js);
webview_error_t webview_eval(webview_t w, const char *js);

// Bindings (JS <-> native)
webview_error_t webview_bind(webview_t w, const char *name,
                             void (*fn)(const char *id, const char *req, void *arg),
                             void *arg);
webview_error_t webview_unbind(webview_t w, const char *name);
webview_error_t webview_return(webview_t w, const char *id, int status, const char *result);

// Metadata
const webview_version_info_t *webview_version(void);
```

### Threading Notes

- Most API functions are **NOT thread-safe** — must be called from the main/GUI thread.
- **Thread-safe functions**: `webview_terminate()`, `webview_return()`, `webview_dispatch()`.
- `webview_dispatch()` schedules a callback on the main thread from any thread — this is the key mechanism for the helper's stdin reader thread.

---

## IPC Protocol Design (Tauri-Inspired)

Newline-delimited JSON over stdin/stdout between PHP and the helper binary.

Following Tauri's pattern, IPC is split into two directions:
- **Commands**: JS → PHP (request/response, like Tauri's `invoke()`)
- **Events**: PHP → JS (push notifications, like Tauri's `emit()`)

### Commands (PHP → helper)

```json
{"cmd":"navigate","url":"https://example.com"}
{"cmd":"set_html","html":"<h1>Hello</h1>"}
{"cmd":"set_title","title":"New Title"}
{"cmd":"set_size","width":1024,"height":768,"hint":0}
{"cmd":"eval","js":"document.title"}
{"cmd":"init","js":"window.__PHP_BRIDGE = true;"}
{"cmd":"bind","name":"sendToPhp"}
{"cmd":"unbind","name":"sendToPhp"}
{"cmd":"return","id":"req123","status":0,"result":"\"ok\""}
{"cmd":"emit","event":"dataUpdated","payload":"{\"count\":42}"}
{"cmd":"destroy"}
```

### Events (helper → PHP)

```json
{"event":"ready"}
{"event":"command","name":"sendToPhp","id":"req123","args":"[1,\"hello\"]"}
{"event":"closed"}
{"event":"error","message":"Navigation failed: net::ERR_NAME_NOT_RESOLVED"}
```

### IPC Flow Examples

**Command flow (JS → PHP → JS):**
```
1. JS calls:       sendToPhp("hello", 42)
2. Helper sends:   {"event":"command","name":"sendToPhp","id":"req1","args":"[\"hello\",42]"}
3. PHP receives:   pollEvents() returns the command
4. PHP executes:   registered callback('req1', '["hello",42]')
5. PHP responds:   {"cmd":"return","id":"req1","status":0,"result":"\"received\""}
6. JS resolves:    promise returns "received"
```

**Event flow (PHP → JS):**
```
1. PHP calls:      $webview->emit('dataUpdated', ['count' => 42])
2. PHP sends:      {"cmd":"emit","event":"dataUpdated","payload":"{\"count\":42}"}
3. Helper evals:   window.__phpEmit('dataUpdated', {"count":42})
4. JS listener:    window.addEventListener('php:dataUpdated', handler) fires
```

---

## Implementation Phases

### Phase 1: WebView Helper Binary

A small C program (~200 lines) that:

- **Main thread**: calls `webview_create()` → `webview_run()` (blocks)
- **Reader thread**: reads JSON commands from stdin, uses `webview_dispatch()` to marshal them to the main thread
- **Events**: writes JSON to stdout (bound JS calls, window closed, errors)
- **Self-terminates** on stdin EOF (parent process died)
- **Injects bridge JS** on startup via `webview_init()` for the event system

**Bridge JS injected by helper on init:**

```javascript
// Injected via webview_init() — runs before every page load
window.__phpEmit = function(event, payload) {
    window.dispatchEvent(new CustomEvent('php:' + event, { detail: payload }));
};

// Convenience: listen for PHP events
window.onPhpEvent = function(event, callback) {
    window.addEventListener('php:' + event, function(e) { callback(e.detail); });
};
```

**New files:**

- `src/lib/webview_helper/webview_helper.c` — the helper program
- `src/lib/webview_helper/CMakeLists.txt` — build config
- `src/lib/webview_helper/build.sh` — convenience build script

**Platform build dependencies (build time only):**

| Platform | Packages |
|----------|----------|
| Linux (Debian/Ubuntu) | `libgtk-3-dev libwebkit2gtk-4.1-dev` or `libgtk-4-dev libwebkitgtk-6.0-dev` |
| macOS | Xcode command line tools (WebKit is built-in) |
| Windows | WebView2 SDK + MSVC/MinGW C++14 |

**Distribution:** Ship prebuilt binaries per platform in `src/lib/`:

- `webview_helper_linux_x86_64`
- `webview_helper_linux_aarch64`
- `webview_helper_darwin_arm64`
- `webview_helper_darwin_x86_64`
- `webview_helper_windows_x86_64.exe`

### Phase 2: ProcessWebView.php — Process Manager

Manages the helper child process lifecycle and IPC. Analogous to `ProcessTCL.php` but for the webview subprocess.

```php
class ProcessWebView {
    private $process;       // proc_open handle
    private $stdin;         // write pipe
    private $stdout;        // read pipe (non-blocking)
    private array $bindings = [];  // name => callable
    private array $pendingReturns = [];
    private bool $ready = false;
    private bool $closed = false;

    public function __construct(array $options);  // launches helper binary
    public function sendCommand(array $cmd): void;  // write JSON to stdin
    public function pollEvents(): array;  // non-blocking read from stdout
    public function isReady(): bool;
    public function isClosed(): bool;
    public function close(): void;  // send destroy, close pipes, proc_close
}
```

Key details:
- `stdout` set to **non-blocking** via `stream_set_blocking($this->stdout, false)` so `pollEvents()` never blocks the Tk event loop
- PHP registers a **shutdown function** to kill the child process (prevents orphans)
- Uses `stream_select()` with 0 timeout as cross-platform fallback

### Phase 3: WebView.php — Widget Class

```php
class WebView {
    private ProcessWebView $process;
    private array $commandHandlers = [];  // name => callable (JS → PHP commands)
    private array $eventListeners = [];   // for internal lifecycle events

    public function __construct(array $options = []);

    // Content
    public function navigate(string $url): void;
    public function setHtml(string $html): void;

    // Window
    public function setTitle(string $title): void;
    public function setSize(int $width, int $height, int $hint = 0): void;

    // JavaScript
    public function evalJs(string $js): void;
    public function initJs(string $js): void;  // runs before every page load

    // Commands: JS → PHP (Tauri invoke() equivalent)
    public function bind(string $name, callable $callback): void;
    public function unbind(string $name): void;
    public function returnValue(string $id, int $status, string $result): void;

    // Events: PHP → JS (Tauri emit() equivalent)
    public function emit(string $event, mixed $payload): void;

    // Lifecycle
    public function destroy(): void;
    public function isClosed(): bool;
    public function onClose(callable $callback): void;

    // Called by Application::run() each iteration
    public function processEvents(): void;
}
```

**Note:** `WebView` does NOT extend `AbstractWidget` — it doesn't create a Tcl widget. It's a standalone native window managed by the helper process (similar to how Tauri's webview is a separate process from the Core).

### Phase 4: Event Loop Integration in Application.php

Modify the existing polling loop to also service WebView instances. This mirrors Tauri's Core process which manages both window events and IPC routing.

```php
// Current loop:
while ($this->running) {
    $this->tcl->evalTcl("update");
    // check callback file...
    usleep(100000);
}

// Modified loop:
while ($this->running) {
    $this->tcl->evalTcl("update");
    // check Tk callback file...
    foreach ($this->webviews as $key => $wv) {
        if ($wv->isClosed()) {
            unset($this->webviews[$key]);
            continue;
        }
        $wv->processEvents();  // non-blocking poll + dispatch commands
    }
    usleep(50000);  // reduce to 50ms for better responsiveness
}
```

Add registration methods:

```php
public function addWebView(WebView $wv): void;
public function removeWebView(WebView $wv): void;
```

### Phase 5: Security Layer (Tauri-Inspired)

Following Tauri's Principle of Least Privilege:

```php
$webview = new WebView([
    'title' => 'My App',
    'permissions' => [
        // URL navigation allowlist
        'navigation' => [
            'https://*.example.com/*',
            'file://' . __DIR__ . '/frontend/*',
        ],
        // Only these JS→PHP bindings are allowed
        'commands' => ['getUser', 'saveData', 'loadConfig'],
        // Block devtools in production
        'devtools' => false,
    ],
]);
```

PHP validates every command before execution:
- Unknown command names are rejected
- Navigation URLs are checked against the allowlist
- Eval'd JS is logged in debug mode

### Phase 6: Build & Distribution

- GitHub Actions CI to compile helper binaries for all platforms
- Store prebuilt binaries in `src/lib/` (like Tcl/Tk binaries already are)
- Runtime check: if binary not found, provide clear error with build instructions
- Add to `LibraryInstaller.php` for platform detection

---

## Usage Examples

### Basic: Tk Controls + WebView Display

```php
$app = new Application();
$window = new Window(['title' => 'Control Panel', 'width' => 300, 'height' => 200]);

$label = new Label($window->getId(), ['text' => 'Waiting...']);
$label->pack();

$input = new Input($window->getId());
$input->pack();

$button = new Button($window->getId(), [
    'text' => 'Load URL',
    'command' => function() use ($input, $webview) {
        $webview->navigate($input->getValue());
    }
]);
$button->pack();

// WebView opens as a separate native window
$webview = new WebView([
    'title' => 'Browser',
    'width' => 800,
    'height' => 600,
    'url' => 'https://example.com',
    'debug' => true,
]);
$app->addWebView($webview);

$app->run();
```

### Full HTML/CSS/JS App (Tauri-style)

```php
$app = new Application();

$webview = new WebView([
    'title' => 'My App',
    'width' => 800,
    'height' => 600,
]);
$app->addWebView($webview);

$webview->setHtml('
<!DOCTYPE html>
<html>
<head>
<style>
    * { margin: 0; box-sizing: border-box; }
    body { font-family: system-ui; background: #1a1a2e; color: #eee; padding: 2rem; }
    .card { background: #16213e; border-radius: 12px; padding: 1.5rem; margin: 1rem 0; }
    button { background: #0f3460; color: #eee; border: none; padding: 10px 20px;
             border-radius: 8px; cursor: pointer; font-size: 1rem; }
    button:hover { background: #e94560; }
    input { padding: 10px; border-radius: 8px; border: 1px solid #333;
            background: #0f3460; color: #eee; width: 100%; margin: 0.5rem 0; }
    #result { margin-top: 1rem; color: #e94560; }
</style>
</head>
<body>
    <h1>PHP GUI WebView App</h1>
    <div class="card">
        <input id="name" placeholder="Enter your name" />
        <button onclick="greet()">Greet</button>
        <div id="result"></div>
    </div>
    <div class="card">
        <button onclick="getSystemInfo()">Get System Info</button>
        <pre id="info"></pre>
    </div>

    <script>
    async function greet() {
        const name = document.getElementById("name").value;
        const result = await invoke("greet", name);
        document.getElementById("result").textContent = result;
    }

    async function getSystemInfo() {
        const info = await invoke("getSystemInfo");
        document.getElementById("info").textContent = JSON.stringify(info, null, 2);
    }

    // Listen for PHP-pushed events
    onPhpEvent("statusUpdate", function(data) {
        document.getElementById("result").textContent = "Status: " + data.message;
    });
    </script>
</body>
</html>
');

// Command handlers (like Tauri's #[tauri::command])
$webview->bind('greet', function(string $id, string $args) use ($webview) {
    $data = json_decode($args, true);
    $name = $data[0] ?? 'World';
    $webview->returnValue($id, 0, json_encode("Hello, {$name}! From PHP " . PHP_VERSION));
});

$webview->bind('getSystemInfo', function(string $id, string $args) use ($webview) {
    $info = [
        'php_version' => PHP_VERSION,
        'os' => PHP_OS,
        'hostname' => gethostname(),
        'memory' => memory_get_usage(true),
        'pid' => getmypid(),
    ];
    $webview->returnValue($id, 0, json_encode($info));
});

// Push events from PHP to JS
$webview->emit('statusUpdate', ['message' => 'App initialized']);

$app->run();
```

### Bidirectional: Tk + WebView Communication

```php
$app = new Application();
$window = new Window(['title' => 'Dashboard Controls', 'width' => 300, 'height' => 400]);

$label = new Label($window->getId(), ['text' => 'Messages: 0']);
$label->pack(['pady' => 10]);

$messageCount = 0;

// Tk button pushes event to WebView
$button = new Button($window->getId(), [
    'text' => 'Send Alert to WebView',
    'command' => function() use ($webview, &$messageCount) {
        $messageCount++;
        $webview->emit('alert', [
            'title' => 'Alert from Tk',
            'body' => "Message #{$messageCount}",
        ]);
    }
]);
$button->pack(['pady' => 5]);

$webview = new WebView([
    'title' => 'Web Dashboard',
    'width' => 600,
    'height' => 400,
]);
$app->addWebView($webview);

// WebView command updates Tk label
$webview->bind('notify', function(string $id, string $args) use ($label, $webview, &$messageCount) {
    $data = json_decode($args, true);
    $messageCount++;
    $label->setText("Messages: {$messageCount}");
    $webview->returnValue($id, 0, json_encode('ok'));
});

$webview->setHtml('
<html>
<body>
    <h2>Web Dashboard</h2>
    <button onclick="notify(\'clicked from web\')">Notify PHP</button>
    <div id="alerts"></div>
    <script>
    onPhpEvent("alert", function(data) {
        const div = document.getElementById("alerts");
        div.innerHTML += "<p><b>" + data.title + ":</b> " + data.body + "</p>";
    });
    </script>
</body>
</html>
');

$app->run();
```

---

## Bundled Chromium Option (Future)

For users who need pixel-perfect cross-platform consistency, a bundled Chromium option can be offered as an alternative. The helper process architecture stays the same — only the binary changes.

| Approach | Bundle Size | Consistency | Effort |
|----------|------------|-------------|--------|
| **Native webview** (default) | ~2 MB | Varies slightly by OS | Current plan |
| WebView2 fixed version (Windows) | ~150 MB Win, tiny others | Partial | Low |
| CEF helper binary | ~200 MB | Identical everywhere | High |
| Electron shell | ~180 MB | Identical everywhere | Medium |

**Recommendation:** Start with native webview. The architecture supports swapping the helper binary for a CEF-based one later without changing any PHP code.

---

## Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| Orphan helper processes | Helper detects stdin EOF → self-terminates. PHP registers shutdown function to kill child. |
| 50ms polling latency | Acceptable for most UI. Can reduce to 10ms if needed (minimal CPU cost). |
| WebView is a separate window, not embedded in Tk | Inherent to the architecture. Most desktop apps (VS Code, Slack, 1Password) use this pattern. Tauri also uses a separate webview process. |
| Build complexity for helper binary | Ship prebuilt binaries. CI builds for all platforms. Users only need build tools if compiling from source. |
| Windows `stream_set_blocking` quirks | Use `stream_select()` with 0 timeout as fallback. |
| WebKitGTK not installed on user's Linux | Runtime check with clear error: "Install libwebkit2gtk-4.1". |
| IPC message tampering | All commands validated in PHP Core before dispatch. URL allowlist for navigation. Binding allowlist for commands. |

---

## Implementation Order

1. **webview_helper.c** — the C helper binary (most critical, standalone testable)
2. **ProcessWebView.php** — process manager with IPC
3. **WebView.php** — PHP widget API with Commands + Events
4. **Application.php** modifications — event loop integration
5. **Security layer** — permissions, URL allowlist, command validation
6. **Build scripts + CI** — cross-platform compilation
7. **Example + docs**

---

## Documentation Requirements

**Every completed phase MUST produce two separate documents before moving to the next phase.**

### 1. Public User Manual (`docs/manual/`)

End-user facing documentation. Written for PHP developers who want to use the WebView widget in their apps.

- **Per-phase pages**: Each phase adds its section (e.g., `docs/manual/webview.md`, `docs/manual/webview-ipc.md`)
- **Must include**: constructor options, all public methods with signatures, return types, parameter descriptions, and working code examples
- **Code examples must be copy-paste runnable** — no pseudocode, no "..." placeholders
- **Follow the existing widget documentation style** already used for Button, Label, etc.

### 2. Development Progress Log (`docs/dev/webview-progress.md`)

Internal-facing changelog tracking what was built, decisions made, and problems encountered.

- **Per-phase entries** with date, what was implemented, what changed from the plan, and why
- **Architecture decisions** — record any deviations from this plan and the reasoning
- **Known issues / technical debt** — anything deferred or worked around
- **Performance notes** — benchmarks, latency measurements, memory usage observations

---

## Test-Driven Development

**Every phase MUST have tests written BEFORE or alongside the implementation. No phase is complete without passing tests.**

### Testing Strategy Per Phase

| Phase | Test Type | What to Test |
|-------|-----------|-------------|
| Phase 1: Helper Binary | Integration | Binary launches, accepts JSON stdin, produces JSON stdout, self-terminates on stdin EOF, handles malformed input gracefully |
| Phase 2: ProcessWebView | Unit + Integration | Process spawns/kills correctly, `sendCommand()` writes valid JSON, `pollEvents()` returns parsed events, non-blocking behavior verified, orphan cleanup on shutdown |
| Phase 3: WebView Widget | Unit + Integration | All public methods dispatch correct commands, `bind()`/`unbind()` register/deregister callbacks, `emit()` sends proper JSON, `processEvents()` dispatches to correct handlers |
| Phase 4: Event Loop | Integration | Tk + WebView polling coexists without blocking, WebView closure is detected and cleaned up, multiple WebViews work simultaneously |
| Phase 5: Security | Unit | URL allowlist blocks/permits correctly, unknown commands are rejected, malformed IPC payloads don't crash |
| Phase 6: Build & Dist | CI/Smoke | Binary compiles on all platforms, runtime detection picks correct binary, clear error when binary is missing |

### Test File Locations

```
tests/
├── webview/
│   ├── HelperBinaryTest.php       # Phase 1
│   ├── ProcessWebViewTest.php     # Phase 2
│   ├── WebViewWidgetTest.php      # Phase 3
│   ├── EventLoopIntegrationTest.php  # Phase 4
│   └── SecurityTest.php           # Phase 5
```

### Test Conventions

- Tests are plain PHP scripts (consistent with existing project — no PHPUnit)
- Each test file must be runnable standalone: `php tests/webview/HelperBinaryTest.php`
- Use simple assert helper or `assert()` with descriptive messages
- Exit code 0 = all pass, non-zero = failure
- Helper binary tests may require platform-specific skip logic (e.g., skip on Windows CI if no WebView2)

---

## Suggestions for Future-Proofing

These are architectural notes and recommendations to keep in mind during implementation. They don't require immediate action but will save significant rework later.

### 1. Define a Versioned IPC Protocol

Add a `"version": 1` field to every IPC message from the start. When the protocol evolves (and it will), this allows the PHP side and helper binary to negotiate compatibility. Without it, upgrading the helper binary independently from the PHP library becomes a breaking change.

```json
{"version":1,"cmd":"navigate","url":"https://example.com"}
```

### 2. Inject `window.invoke()` Bridge in the Helper

The current plan's HTML examples call `invoke("greet", name)` and `notify(...)`, but neither is defined in the bridge JS. The helper must inject a `window.invoke()` function that calls the bound function and returns a Promise. Without this, the JS→PHP command flow is broken. Define this in Phase 1 alongside `__phpEmit` and `onPhpEvent`.

```javascript
// Must be injected via webview_init() in the helper
window.invoke = function(name, ...args) {
    return window[name](...args);  // calls webview_bind'd function, returns Promise
};
```

### 3. Structured Error Propagation

Add an `onError(callable $callback)` method to `WebView.php`. Currently, if the helper crashes or `webview_navigate()` fails, there's no way for PHP code to react. The `{"event":"error",...}` message is defined in the protocol but has no handler on the PHP side.

### 4. Adaptive Polling Interval

Instead of a fixed `usleep(50000)`, use an adaptive approach: poll faster (10-20ms) when WebViews are active and there's recent IPC activity, fall back to slower polling (100ms) when idle. This balances responsiveness with CPU usage. Avoids punishing users who don't use WebView.

### 5. Consider a Shared `invoke()` Helper for Existing Tk Callbacks

The current Tk callback mechanism (temp file `/tmp/phpgui_callback.txt`) is a bottleneck — only one callback can fire per poll cycle. The WebView's stdio-based IPC is strictly better. In a future major version, consider migrating Tk callbacks to a similar pipe/socket mechanism to unify event handling.

### 6. Helper Binary Health Check

Add a `{"cmd":"ping"}` / `{"event":"pong"}` heartbeat to the IPC protocol. If PHP sends a ping and gets no pong within N ms, it can detect a hung or crashed helper early instead of waiting for the next failed command. Useful for `isClosed()` reliability.

### 7. Plan for `webview_dispatch()` Thread Safety

The helper's stdin reader thread will call `webview_dispatch()` to marshal commands to the main thread. This is correct, but be careful: the dispatch callback must not reference freed memory if `webview_destroy()` races with a pending dispatch. Use a simple flag or atomic to gate dispatches after destroy is requested.

### 8. Bundle a Minimal Test HTML Page

Ship a `tests/webview/fixtures/test.html` with known DOM structure. Use it in integration tests to verify `evalJs()`, `bind()`, and `emit()` work end-to-end. Testing against live URLs is flaky; testing against a local fixture is deterministic.

### 9. Log IPC Traffic in Debug Mode

When `debug: true`, log all IPC messages (both directions) to a file or stderr. This is invaluable for diagnosing timing issues, malformed messages, or dropped events. Tauri has a similar feature.

### 10. Document the "Two Windows" UX Upfront

The WebView opens as a **separate native window**, not embedded in the Tk window. This is an inherent architectural constraint. Document this prominently in the user manual with a clear explanation of why (Tk and GTK can't share a window) and how to design apps around it (e.g., use Tk for controls, WebView for content display — or go full WebView with no Tk).

---

## Future Roadmap

- **Phase 7: Plugin system** — Tauri-style plugins (PHP impl + JS API) for filesystem, shell, dialog, clipboard, notification
- **Phase 8: DevTools integration** — conditional devtools access based on debug flag
- **Phase 9: Hot reload** — file watcher that auto-reloads HTML/CSS/JS during development
- **Phase 10: Bundler** — package PHP + helper binary + frontend assets into a single distributable app
