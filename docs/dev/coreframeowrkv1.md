# PHP GUI Framework — Architecture Plan

---

## 1. Core Architecture Decisions

### Layered Architecture

```
┌─────────────────────────────────────────────────┐
│  Application Layer (User Code)                  │
├─────────────────────────────────────────────────┤
│  Framework Layer                                │
│  ┌──────────┐ ┌──────────┐ ┌────────────────┐  │
│  │ Router   │ │ State    │ │ Plugin Manager │  │
│  │ (cmds)   │ │ Manager  │ │                │  │
│  └──────────┘ └──────────┘ └────────────────┘  │
├─────────────────────────────────────────────────┤
│  Core Layer                                     │
│  ┌──────────┐ ┌──────────┐ ┌────────────────┐  │
│  │ Kernel   │ │ Event    │ │ IPC Bridge     │  │
│  │          │ │ Dispatch │ │ (File→Socket)  │  │
│  └──────────┘ └──────────┘ └────────────────┘  │
├─────────────────────────────────────────────────┤
│  Backend Engines                                │
│  ┌──────────────────┐  ┌─────────────────────┐  │
│  │ Tcl/Tk (FFI)     │  │ WebView (stdio IPC) │  │
│  └──────────────────┘  └─────────────────────┘  │
└─────────────────────────────────────────────────┘
```

### Key Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Rendering | Dual-engine: Tcl/Tk native + WebView hybrid | Covers both native and modern UI needs. Positions as Tauri-for-PHP. |
| DI Container | Lightweight PSR-11 container (custom, no dep) | Replace singleton anti-pattern. Zero dependencies mandate. |
| Event System | PSR-14 compatible event dispatcher | Replace temp-file bridge with Unix domain sockets. Keep file bridge as fallback on Windows. |
| Config | Convention over configuration with `phpgui.json` | Single manifest for app metadata, window config, build options. |
| CLI Tool | `vendor/bin/phpgui` binary | `create`, `dev`, `build`, `serve` commands. |
| Min PHP | 8.2+ | Readonly classes, DNF types, fibers for async. |

---

## 2. Project Structure

### User App Structure

```
my-app/
├── phpgui.json                 # App manifest (name, version, window config, build)
├── src/
│   ├── App.php                 # Application entry point
│   ├── Commands/               # Bound commands (JS→PHP handlers)
│   │   └── GetTodos.php
│   ├── Events/                 # App-level event listeners
│   └── Providers/              # Service providers (plugin registration)
│       └── AppServiceProvider.php
├── resources/
│   ├── views/                  # For WebView mode: HTML/JS/CSS
│   │   ├── index.html
│   │   └── assets/
│   └── icons/                  # App icons per platform
├── tests/
├── dist/                       # Build output (generated)
└── composer.json
```

### Framework Package Structure (`phpgui/framework`)

```
src/
├── Foundation/
│   ├── Kernel.php              # Boot sequence, provider loading
│   ├── Application.php         # Enhanced event loop (replaces current)
│   └── Config.php              # phpgui.json parser
├── Container/
│   └── Container.php           # PSR-11 DI container
├── Events/
│   ├── EventDispatcher.php     # PSR-14 dispatcher
│   ├── AppEvent.php            # Lifecycle events (boot, ready, quit)
│   └── WidgetEvent.php         # Widget interaction events
├── Bridge/
│   ├── BridgeInterface.php     # Contract for callback transport
│   ├── SocketBridge.php        # Unix socket implementation (Linux/macOS)
│   ├── NamedPipeBridge.php     # Named pipe (Windows)
│   └── FileBridge.php          # Legacy file-based (fallback)
├── Widget/                     # Enhanced widget set (extends current)
│   ├── Concerns/
│   │   ├── HasState.php        # Reactive state trait
│   │   └── HasEvents.php       # Event binding trait
│   └── WebView/
│       ├── WebView.php         # Enhanced WebView widget
│       ├── CommandRouter.php   # Maps JS invoke() → PHP handler classes
│       └── DevServer.php       # HMR-capable dev server
├── Plugin/
│   ├── PluginInterface.php
│   ├── PluginManager.php
│   └── ServiceProvider.php     # Base provider class
├── State/
│   └── Store.php               # Reactive state store (emit on change)
├── Console/
│   ├── CreateCommand.php       # phpgui create <name>
│   ├── DevCommand.php          # phpgui dev (watch + rebuild)
│   ├── BuildCommand.php        # phpgui build (package for distribution)
│   └── ServeCommand.php        # phpgui serve (WebView dev server)
└── Support/
    ├── TclEscaper.php          # Parameterized Tcl command builder
    └── Platform.php            # OS/arch detection singleton
```

---

## 3. Build & Packaging Workflow

### `phpgui.json` Manifest

```json
{
  "name": "my-app",
  "version": "1.0.0",
  "main": "src/App.php",
  "mode": "webview",
  "window": {
    "title": "My App",
    "width": 1024,
    "height": 768,
    "resizable": true
  },
  "build": {
    "frontend": {
      "dir": "resources/views",
      "command": "npm run build",
      "output": "resources/views/dist"
    },
    "targets": ["linux-x64", "darwin-arm64", "win-x64"],
    "php": "embed",
    "obfuscate": false
  },
  "plugins": []
}
```

### Build Pipeline

```
phpgui build
    │
    ├── 1. Validate phpgui.json
    ├── 2. Run frontend build (if configured)
    │      └── npm run build → resources/views/dist/
    ├── 3. Compile PHP → single PHAR or micro binary
    │      ├── Option A: PHAR archive (php app.phar)
    │      └── Option B: php-micro (static PHP + PHAR = single binary)
    ├── 4. Bundle native deps per target
    │      ├── Tcl/Tk libs (from src/lib/)
    │      ├── webview_helper binary
    │      └── Frontend assets (embedded in PHAR or alongside)
    ├── 5. Platform packaging
    │      ├── Linux: AppImage or tar.gz + .desktop
    │      ├── macOS: .app bundle (Info.plist + dylibs)
    │      └── Windows: .exe (NSIS installer or portable zip)
    └── 6. Output → dist/{target}/
```

### Packaging Strategy

| Platform | Format | Tooling |
|---|---|---|
| Linux | AppImage | `appimagetool` — single file, no install needed |
| macOS | `.app` bundle | Directory structure + `codesign` |
| Windows | Portable `.exe` + installer | `php-micro` + NSIS or Inno Setup |

> **PHP Embedding:** Use `static-php-cli` to compile a minimal PHP binary with only `ffi`, `json`, `sockets` extensions. Concatenate with PHAR to produce a single executable (~15–20 MB).

---

## 4. Runtime Model

### Boot Sequence

**User entry point** (`src/App.php`):

```php
<?php
use PhpGui\Foundation\Kernel;

$app = Kernel::boot(__DIR__);

$app->webview('main', function ($wv) {
    $wv->serveDirectory(__DIR__ . '/resources/views/dist');
    $wv->bind('getTodos', GetTodosCommand::class);
    $wv->bind('saveTodo', SaveTodoCommand::class);
});

$app->run();
```

**Internal boot sequence:**

```
Kernel::boot()
    ├── Load phpgui.json
    ├── Initialize DI Container
    ├── Register core services (EventDispatcher, BridgeFactory, Platform)
    ├── Detect mode (tcl | webview | hybrid)
    ├── Load user ServiceProviders
    ├── Boot plugins
    ├── Initialize rendering engine(s)
    │   ├── Tcl/Tk: ProcessTCL::init() via FFI
    │   └── WebView: Spawn helper process
    ├── Select callback bridge (socket > pipe > file)
    └── Return Application instance
```

### Event Loop (Enhanced)

```
Application::run()
    └── while (running) {
        ├── Bridge::poll()              // Check for callbacks (socket-based, non-blocking)
        │   └── Dispatch to EventDispatcher
        ├── Tcl: evalTcl("update")      // Process Tk events (if Tcl mode)
        ├── WebView::processEvents()    // Poll each WebView (if WebView mode)
        ├── State::flush()              // Emit pending state changes to frontend
        └── usleep(adaptive)            // 1ms socket, 5ms WebView, 50ms idle
    }
```

### Command Routing (WebView Mode)

```php
// Commands/GetTodos.php
class GetTodos implements CommandInterface
{
    public function __construct(private Store $store) {}

    public function handle(string $requestId, array $args): mixed
    {
        return $this->store->get('todos', []);
    }
}
```

> The framework auto-serializes the return value and calls `returnValue()`. No manual JSON encoding. Exceptions are caught and sent as error responses to JS.

---

## 5. Extensibility & Plugin System

### Service Provider Pattern

```php
abstract class ServiceProvider
{
    abstract public function register(Container $container): void;
    public function boot(Application $app): void {}
}
```

### Plugin Interface

```php
interface PluginInterface
{
    public function name(): string;
    public function version(): string;
    public function providers(): array;  // ServiceProvider classes
}
```

### Registration

```json
// phpgui.json
{
  "plugins": [
    "phpgui/sqlite-plugin",
    "phpgui/tray-plugin"
  ]
}
```

### Plugin Capabilities (First-Party Roadmap)

| Plugin | Provides |
|---|---|
| `phpgui/sqlite` | SQLite via FFI, no `ext-pdo` needed |
| `phpgui/tray` | System tray icon + menu |
| `phpgui/notifications` | Native OS notifications |
| `phpgui/dialog` | Enhanced file/color/font dialogs |
| `phpgui/updater` | Auto-update mechanism (check + download + replace) |
| `phpgui/devtools` | WebView inspector, state viewer, event logger |

### Extension Points

| Hook | When | Use Case |
|---|---|---|
| `app.booting` | Before providers loaded | Modify container bindings |
| `app.ready` | After all engines initialized | Initial data loading |
| `app.tick` | Each event loop iteration | Custom polling |
| `app.quit` | Before shutdown | Cleanup |
| `webview.navigate` | Before navigation | URL rewriting, auth injection |
| `widget.created` | After widget instantiation | Auto-attach behaviors |
| `state.changed` | Reactive state mutation | Logging, sync |

---

## 6. Security Considerations

### Code Protection

| Threat | Mitigation |
|---|---|
| PHP source exposure | PHAR with compactors — strip comments/whitespace. Optional: use `php-scoper` to prefix namespaces + obfuscate class names. |
| Frontend source exposure | Standard JS minification/bundling (Vite/esbuild). For sensitive logic, keep it in PHP commands, not JS. |
| Binary tampering | Code-sign binaries on macOS/Windows. Embed checksum in PHAR stub. |
| IPC interception | Unix sockets with restrictive permissions (`0600`). Named pipes with ACL on Windows. |

### Runtime Security

| Concern | Approach |
|---|---|
| Tcl injection | `TclEscaper::quote()` — proper Tcl list quoting for all user input. Never interpolate raw strings into `evalTcl()`. |
| WebView XSS | CSP headers injected via `initJs()`. Default policy: `script-src 'self'`. |
| JS→PHP bridge | Command whitelist — only explicitly `bind()`-ed commands are callable. Type validation on args. |
| File system access | `WebView::serveFromDisk()` confined to declared directory. Path traversal check on all serve operations. |
| Process isolation | WebView helper runs as separate process. Crash doesn't take down PHP. PHP can kill helper on timeout. |

### Supply Chain

- Zero Composer dependencies (maintained).
- WebView helper binaries: publish SHA-256 checksums alongside releases. Verify on download.
- Static PHP binary: reproducible build via `static-php-cli` with pinned versions.

---

## 7. Developer Workflow (DX Focus)

### Scaffolding

```bash
composer create-project phpgui/skeleton my-app
cd my-app
phpgui dev
```

Generates full project structure with `phpgui.json`, example command, and basic HTML frontend.

### Development Mode

```bash
phpgui dev
```

Does three things simultaneously:

1. **PHP watcher** — monitors `src/` for changes, restarts app process
2. **Frontend dev server** — Vite HMR on `resources/views/` (WebView mode)
3. **WebView** — connects to Vite dev server instead of built assets

File changes = instant reload. No manual restart.

### CLI Commands

| Command | Action |
|---|---|
| `phpgui create <name>` | Scaffold new project (interactive: Tcl/WebView/hybrid) |
| `phpgui dev` | Development mode with hot reload |
| `phpgui build` | Production build for current platform |
| `phpgui build --target=all` | Cross-platform build |
| `phpgui serve` | Start frontend dev server only |
| `phpgui doctor` | Check system dependencies (PHP version, FFI, Tcl, WebView) |
| `phpgui plugin add <name>` | Install and register plugin |

### Debugging

- `phpgui dev --verbose` — log all IPC messages (Tcl commands, WebView JSON)
- `phpgui dev --inspect` — open WebView DevTools automatically
- **State inspector:** built-in devtools plugin shows reactive state tree in a side panel

### Testing

```php
// Framework provides test helpers
use PhpGui\Testing\TestCase;

class TodoTest extends TestCase
{
    public function test_get_todos(): void
    {
        $result = $this->invoke('getTodos', []);
        $this->assertIsArray($result);
    }
}
```

> `TestCase` mocks the WebView bridge so commands can be tested without spawning a window. Runs under PHPUnit.

---

## Summary of Priorities

| Phase | Focus | Deliverables |
|---|---|---|
| Phase 1 | Core framework | Kernel, Container, EventDispatcher, Socket Bridge, `phpgui.json`, CLI (`create`, `dev`) |
| Phase 2 | WebView DX | CommandRouter, reactive State, Vite integration, HMR, `phpgui dev` |
| Phase 3 | Build & distribute | PHAR packaging, `static-php-cli` integration, AppImage/`.app`/`.exe`, `phpgui build` |
| Phase 4 | Plugin ecosystem | Plugin interface, first-party plugins (sqlite, tray, notifications, updater) |
| Phase 5 | Polish | `phpgui doctor`, devtools plugin, docs site, starter templates |
