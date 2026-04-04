# PHP GUI Framework — Core Architecture

> **Version:** V1 Target Specification
> **Status:** Pre-implementation
> **Based on:** [Framework Architecture Review](./FRAMEWORK_ARCHITECTURE_REVIEW.md)

This document is the engineering blueprint. It specifies exactly what gets built, how the pieces connect, and where the boundaries are. No aspirational features — only what ships in V1.

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Current State & What Changes](#2-current-state--what-changes)
3. [Core Components](#3-core-components)
4. [The App Class](#4-the-app-class)
5. [Callback Bridge (Socket-Based)](#5-callback-bridge-socket-based)
6. [Tcl Security Layer](#6-tcl-security-layer)
7. [WebView Bind API (v2)](#7-webview-bind-api-v2)
8. [JS Bridge (Auto-Injected)](#8-js-bridge-auto-injected)
9. [Error Handling](#9-error-handling)
10. [Event Loop — Revised](#10-event-loop--revised)
11. [CLI Tooling](#11-cli-tooling)
12. [Build & Distribution](#12-build--distribution)
13. [Project Structure (When You Need One)](#13-project-structure-when-you-need-one)
14. [File Manifest](#14-file-manifest)
15. [Migration Path from Current API](#15-migration-path-from-current-api)
16. [What Is Explicitly NOT in V1](#16-what-is-explicitly-not-in-v1)
17. [Implementation Order](#17-implementation-order)

---

## 1. System Overview

```
┌─────────────────────────────────────────────────────────┐
│                    User Code                            │
│                                                         │
│  $app = App::create();                                  │
│  $app->window(...)  or  $app->webview(...)  or  both    │
│  $app->run();                                           │
├─────────────────────────────────────────────────────────┤
│                    PhpGui\App                            │
│          Single entry point. No config files.           │
│          No containers. No providers.                   │
├──────────────────────┬──────────────────────────────────┤
│   Tcl/Tk Engine      │       WebView Engine             │
│                      │                                  │
│   ProcessTCL (FFI)   │   ProcessWebView (stdio IPC)     │
│   AbstractWidget     │   WebView widget                 │
│   Window, Button...  │   bind/emit/invoke               │
├──────────────────────┴──────────────────────────────────┤
│                  Callback Bridge                        │
│         SocketBridge (Linux/macOS)                      │
│         FileBridge   (Windows fallback)                 │
├─────────────────────────────────────────────────────────┤
│                  Platform Layer                         │
│      OS detection, library paths, binary lookup         │
└─────────────────────────────────────────────────────────┘
```

**Invariants:**
- Single-file apps work. Always. No config files required.
- Zero Composer dependencies. No exceptions.
- The current widget API continues to work unchanged.
- `App` is sugar on top of existing classes, not a replacement.

---

## 2. Current State & What Changes

### What exists today and stays untouched

| Component | File | Status |
|---|---|---|
| `ProcessTCL` | `src/ProcessTCL.php` | **Keep.** Singleton FFI bridge. Works. |
| `Application` | `src/Application.php` | **Keep.** Still works for users who don't want `App`. |
| `AbstractWidget` | `src/Widget/AbstractWidget.php` | **Keep.** Base class for all Tk widgets. |
| All widgets | `src/Widget/*.php` | **Keep.** Button, Label, Input, Canvas, Menu, etc. |
| `ProcessWebView` | `src/ProcessWebView.php` | **Keep.** stdio IPC to helper binary. |
| `WebView` | `src/Widget/WebView.php` | **Extend.** Add auto-return bind, keep old API working. |

### What gets added

| Component | File | Purpose |
|---|---|---|
| `App` | `src/App.php` | Unified entry point with fluent API |
| `SocketBridge` | `src/Bridge/SocketBridge.php` | Unix socket callback transport |
| `FileBridge` | `src/Bridge/FileBridge.php` | Extract current file-based logic |
| `BridgeInterface` | `src/Bridge/BridgeInterface.php` | Contract (3 methods) |
| `Tcl` | `src/Support/Tcl.php` | Static escaper for Tcl strings |
| `TclException` | `src/Support/TclException.php` | Rich error context |
| `phpgui.js` | `src/WebView/phpgui.js` | Auto-injected JS bridge |
| CLI commands | `src/Console/*.php` | `new`, `build`, `check` |

### What gets modified

| Component | Change |
|---|---|
| `ProcessTCL::evalTcl()` | Throws `TclException` instead of generic `RuntimeException` |
| `ProcessTCL::definePhpCallbackBridge()` | Uses socket bridge when available |
| `Application::tick()` | Delegates to bridge instead of direct file I/O |
| `WebView::bind()` | Adds auto-return overload (old signature still works) |

---

## 3. Core Components

### Dependency Graph

```
App
 ├── Application (existing, unchanged)
 ├── ProcessTCL (existing, patched for TclException + bridge)
 ├── BridgeInterface
 │    ├── SocketBridge (default on Linux/macOS)
 │    └── FileBridge (default on Windows, fallback everywhere)
 ├── Window, Button, Label... (existing widgets, unchanged)
 └── WebView (existing, extended with auto-return bind)
      └── ProcessWebView (existing, unchanged)
```

No circular dependencies. No container. No service location. `App` constructs what it needs directly.

---

## 4. The App Class

### Design Constraints

- Must not break existing `new Application()` usage
- Must not require any config files
- Must support Tcl-only, WebView-only, and hybrid apps
- Must be the only new class a user needs to learn

### Interface

```php
namespace PhpGui;

class App
{
    // ── Construction ──────────────────────────────────────

    /**
     * Create a new App instance.
     * This is the single entry point for the framework.
     */
    public static function create(): self;

    // ── Tcl/Tk Windows ────────────────────────────────────

    /**
     * Create a Tk window. Returns the Window widget.
     * Shorthand for: new Window(['title' => $title, ...])
     */
    public function window(
        string $title = 'PhpGui App',
        int $width = 800,
        int $height = 600,
        array $options = [],
    ): Widget\Window;

    // ── WebView Windows ───────────────────────────────────

    /**
     * Create a WebView window. Returns the WebView widget.
     * Auto-registers it for event polling.
     * Auto-injects the JS bridge (phpgui.invoke, phpgui.on).
     */
    public function webview(
        string $title = 'PhpGui App',
        int $width = 800,
        int $height = 600,
        array $options = [],
    ): Widget\WebView;

    // ── Event Loop ────────────────────────────────────────

    /**
     * Start the event loop. Blocks until quit() is called.
     */
    public function run(): void;

    /**
     * Stop the event loop and clean up.
     */
    public function quit(): void;

    // ── Lifecycle Hooks ───────────────────────────────────

    /**
     * Called once after all engines are initialized,
     * before the event loop starts.
     */
    public function onReady(callable $callback): self;

    /**
     * Called before shutdown. Use for cleanup.
     */
    public function onQuit(callable $callback): self;

    // ── Escape Hatch ──────────────────────────────────────

    /**
     * Direct access to the Tcl interpreter.
     * For advanced users who need raw Tcl commands.
     */
    public function tcl(): ProcessTCL;

    /**
     * Direct access to the underlying Application instance.
     */
    public function application(): Application;
}
```

### Internal Implementation

```php
class App
{
    private Application $application;
    private BridgeInterface $bridge;
    private array $webviews = [];
    private array $onReadyCallbacks = [];
    private array $onQuitCallbacks = [];
    private bool $tclInitialized = false;

    private function __construct()
    {
        // Bridge selection — one decision, no factory
        $this->bridge = match (PHP_OS_FAMILY) {
            'Windows' => new Bridge\FileBridge(),
            default   => $this->trySocketBridge(),
        };
    }

    public static function create(): self
    {
        return new self();
    }

    public function window(
        string $title = 'PhpGui App',
        int $width = 800,
        int $height = 600,
        array $options = [],
    ): Widget\Window {
        $this->ensureTclInitialized();
        return new Widget\Window(array_merge(
            ['title' => $title, 'width' => $width, 'height' => $height],
            $options,
        ));
    }

    public function webview(
        string $title = 'PhpGui App',
        int $width = 800,
        int $height = 600,
        array $options = [],
    ): Widget\WebView {
        $wv = new Widget\WebView(array_merge(
            ['title' => $title, 'width' => $width, 'height' => $height],
            $options,
        ));

        // Auto-inject JS bridge on ready
        $wv->onReady(function () use ($wv) {
            $wv->initJs($this->getJsBridgeCode());
        });

        $this->webviews[] = $wv;
        return $wv;
    }

    public function run(): void
    {
        // Lazy-init Application only when run() is called
        $this->ensureTclInitialized();

        // Register all WebViews with the Application event loop
        foreach ($this->webviews as $wv) {
            $this->application->addWebView($wv);
        }

        // Fire onReady callbacks
        foreach ($this->onReadyCallbacks as $cb) {
            $cb($this);
        }

        $this->application->run();
    }

    public function quit(): void
    {
        foreach ($this->onQuitCallbacks as $cb) {
            $cb($this);
        }
        $this->application->quit();
    }

    private function ensureTclInitialized(): void
    {
        if (!$this->tclInitialized) {
            $this->application = new Application();
            $this->tclInitialized = true;
        }
    }

    private function trySocketBridge(): BridgeInterface
    {
        if (extension_loaded('sockets')) {
            try {
                return new Bridge\SocketBridge();
            } catch (\Throwable) {
                // Fall back to file bridge
            }
        }
        return new Bridge\FileBridge();
    }

    private function getJsBridgeCode(): string
    {
        static $code = null;
        if ($code === null) {
            $code = file_get_contents(__DIR__ . '/WebView/phpgui.js');
        }
        return $code;
    }
}
```

### Usage — Simplest Possible App

```php
<?php
// app.php — a complete desktop app in 8 lines
require __DIR__ . '/vendor/autoload.php';

use PhpGui\App;

$app = App::create();
$win = $app->window('Hello World', 400, 300);

$label = new \PhpGui\Widget\Label($win->getId(), ['text' => 'Hello from PHP!']);
$label->pack(['pady' => 20]);

$app->run();
```

### Usage — Full WebView App

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use PhpGui\App;

$app = App::create();
$wv = $app->webview('Todo App', 900, 700);
$wv->serve(__DIR__ . '/ui');

$todos = [];

$wv->bind('addTodo', function (array $args) use (&$todos, $wv) {
    $todos[] = ['id' => uniqid(), 'text' => $args[0], 'done' => false];
    $wv->emit('todosChanged', $todos);
    return ['ok' => true];
});

$wv->bind('getTodos', fn () => $todos);

$wv->bind('toggleTodo', function (array $args) use (&$todos, $wv) {
    foreach ($todos as &$todo) {
        if ($todo['id'] === $args[0]) {
            $todo['done'] = !$todo['done'];
        }
    }
    $wv->emit('todosChanged', $todos);
    return ['ok' => true];
});

$wv->onClose(fn () => $app->quit());

$app->run();
```

### Usage — Hybrid (Tk + WebView)

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use PhpGui\App;
use PhpGui\Widget\{Button, Label};

$app = App::create();

// WebView for main content
$wv = $app->webview('Dashboard', 1024, 768);
$wv->serve(__DIR__ . '/dashboard');

// Tk window for developer controls
$ctrl = $app->window('Controls', 300, 200);

$status = new Label($ctrl->getId(), ['text' => 'Ready']);
$status->pack(['pady' => 10]);

new Button($ctrl->getId(), [
    'text' => 'Reload',
    'command' => fn () => $wv->evalJs('location.reload()'),
]);
new Button($ctrl->getId(), [
    'text' => 'Quit',
    'command' => fn () => $app->quit(),
]);

$wv->onClose(fn () => $app->quit());

$app->run();
```

---

## 5. Callback Bridge (Socket-Based)

### Why

The current callback system writes to `/tmp/phpgui_callback.txt` on every user interaction. Problems:

1. **Race condition:** Two rapid button clicks → second write overwrites first → callback lost.
2. **Latency:** File I/O + `file_exists()` polling adds 50-100ms per callback.
3. **Orphan files:** Crash leaves `/tmp/phpgui_callback.txt` on disk permanently.
4. **Security:** Any process on the machine can read/write the callback file.

### Bridge Interface

```php
namespace PhpGui\Bridge;

interface BridgeInterface
{
    /**
     * Start the bridge. Called once during initialization.
     * Returns the Tcl script fragment that the callback procedure should use
     * to notify PHP (e.g., writing to a socket or file).
     */
    public function start(): string;

    /**
     * Poll for pending callback IDs. Non-blocking. Returns immediately.
     * @return string[] Array of callback IDs that fired since last poll.
     */
    public function poll(): array;

    /**
     * Clean up resources (close sockets, delete files).
     */
    public function shutdown(): void;
}
```

The key insight: `start()` returns a **Tcl script fragment**. This fragment replaces the body of `php::executeCallback`. The bridge tells Tcl *how* to notify PHP, and Tcl executes that notification when a callback fires.

### SocketBridge Implementation

```php
namespace PhpGui\Bridge;

class SocketBridge implements BridgeInterface
{
    private \Socket $server;
    private string $socketPath;
    /** @var \Socket[] */
    private array $clients = [];

    public function __construct()
    {
        // Unique socket per app instance — no collisions
        $this->socketPath = sys_get_temp_dir() . '/phpgui_' . getmypid() . '.sock';

        // Clean up stale socket from previous crash
        if (file_exists($this->socketPath)) {
            unlink($this->socketPath);
        }

        $this->server = socket_create(AF_UNIX, SOCK_STREAM, 0);
        socket_bind($this->server, $this->socketPath);
        socket_listen($this->server, 5);
        socket_set_nonblock($this->server);

        // Restrictive permissions — only current user can connect
        chmod($this->socketPath, 0600);
    }

    public function start(): string
    {
        // Tcl script: open a socket, write the callback ID, close.
        // Tcl uses its own socket client — no FFI callback needed.
        $path = $this->socketPath;
        return <<<TCL
        proc php::executeCallback {id} {
            set sock [socket -async unix "{$path}"]
            chan configure \$sock -buffering line
            puts \$sock \$id
            close \$sock
            update
        }
        TCL;
    }

    public function poll(): array
    {
        $ids = [];

        // Accept new connections
        while (true) {
            $client = @socket_accept($this->server);
            if ($client === false) break;
            socket_set_nonblock($client);
            $this->clients[] = $client;
        }

        // Read from connected clients
        foreach ($this->clients as $i => $client) {
            $data = @socket_read($client, 1024);
            if ($data === false || $data === '') {
                socket_close($client);
                unset($this->clients[$i]);
                continue;
            }
            // Multiple IDs may arrive in one read (newline-separated)
            foreach (explode("\n", trim($data)) as $id) {
                $id = trim($id);
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
            socket_close($client);
            unset($this->clients[$i]);
        }

        $this->clients = array_values($this->clients);
        return $ids;
    }

    public function shutdown(): void
    {
        foreach ($this->clients as $client) {
            @socket_close($client);
        }
        @socket_close($this->server);
        @unlink($this->socketPath);
    }
}
```

### FileBridge Implementation

Extracts the current logic from `ProcessTCL::definePhpCallbackBridge()` and `Application::tick()`.

```php
namespace PhpGui\Bridge;

class FileBridge implements BridgeInterface
{
    private string $callbackFile;

    public function __construct()
    {
        $tempDir = str_replace('\\', '/', sys_get_temp_dir());
        $this->callbackFile = $tempDir . '/phpgui_callback_' . getmypid() . '.txt';
    }

    public function start(): string
    {
        $path = $this->callbackFile;
        return <<<TCL
        proc php::executeCallback {id} {
            set f [open "{$path}" w]
            puts \$f \$id
            close \$f
            update
        }
        TCL;
    }

    public function poll(): array
    {
        if (!file_exists($this->callbackFile)) {
            return [];
        }

        $id = trim(file_get_contents($this->callbackFile));
        unlink($this->callbackFile);

        return $id !== '' ? [$id] : [];
    }

    public function shutdown(): void
    {
        @unlink($this->callbackFile);
    }
}
```

**Note:** FileBridge now appends PID to the filename. This prevents collisions when multiple php-gui apps run simultaneously — a real bug in the current implementation.

### Integration with ProcessTCL

The bridge modifies how `definePhpCallbackBridge()` works:

```php
// In ProcessTCL — new method
public function setBridge(BridgeInterface $bridge): void
{
    $this->bridge = $bridge;
}

// Modified definePhpCallbackBridge()
private function definePhpCallbackBridge($interp): void
{
    $this->evalTcl('
        namespace eval php {
            variable callbacks
            array set callbacks {}
        }
    ');

    if ($this->bridge !== null) {
        // Bridge provides the Tcl procedure body
        $this->evalTcl($this->bridge->start());
    } else {
        // Legacy fallback — current file-based behavior (unchanged)
        $tempDir = str_replace('\\', '/', sys_get_temp_dir());
        $callbackFile = $tempDir . "/phpgui_callback.txt";
        $this->evalTcl("proc php::executeCallback {id} {
            set f [open \"{$callbackFile}\" w]
            puts \$f \$id
            close \$f
            update
        }");
    }
}
```

### Integration with Application::tick()

```php
// In Application — modified tick()
public function tick(): void
{
    $this->tcl->evalTcl("update");

    // Use bridge if available, fall back to legacy file check
    if ($this->bridge !== null) {
        $ids = $this->bridge->poll();
        foreach ($ids as $id) {
            ProcessTCL::getInstance()->executeCallback($id);
        }
    } else {
        // Legacy file-based check (current behavior, preserved)
        $tempDir = str_replace('\\', '/', sys_get_temp_dir());
        $callbackFile = $tempDir . "/phpgui_callback.txt";
        if (file_exists($callbackFile)) {
            $id = trim(file_get_contents($callbackFile));
            unlink($callbackFile);
            ProcessTCL::getInstance()->executeCallback($id);
        }
    }

    // Quit file check (unchanged)
    $tempDir = str_replace('\\', '/', sys_get_temp_dir());
    $quitFile = $tempDir . "/phpgui_quit.txt";
    if (file_exists($quitFile)) {
        unlink($quitFile);
        $this->running = false;
    }

    // WebView polling (unchanged)
    foreach ($this->webviews as $key => $wv) {
        if ($wv->isClosed()) {
            unset($this->webviews[$key]);
            continue;
        }
        $wv->processEvents();
    }
}
```

### Performance Comparison

| Metric | FileBridge (current) | SocketBridge (V1) |
|---|---|---|
| Callback latency | ~50-100ms (file I/O + poll interval) | ~1-5ms (socket notification) |
| Rapid clicks (10/sec) | Drops callbacks (race condition) | Handles all (queued) |
| Cleanup on crash | Orphaned temp file | Socket auto-cleaned by OS |
| Security | World-readable temp file | User-only socket (0600) |
| Windows support | Works | Falls back to FileBridge |

---

## 6. Tcl Security Layer

### The Problem

Every widget in the current codebase does this:

```php
// Button.php line 34 — user input directly in Tcl command
$this->tcl->evalTcl("button .{$this->parentId}.{$this->id} -text \"{$text}\" ...");
```

If `$text` contains `"; destroy .;#` — the app crashes. If it contains `"; exec rm -rf /;#` — Tcl executes the command. This is **Tcl injection**, analogous to SQL injection.

### The Fix

```php
namespace PhpGui\Support;

/**
 * Tcl string escaping utilities.
 *
 * Tcl has specific quoting rules. Curly braces {} are the safest
 * quoting mechanism — they prevent all substitution. But the string
 * itself cannot contain unbalanced braces.
 *
 * Strategy:
 * 1. If string has no special chars → return as-is
 * 2. If string has balanced braces and no backslash-newline → use {$str}
 * 3. Otherwise → escape every special char with backslashes
 */
class Tcl
{
    /**
     * Escape a value for safe inclusion in a Tcl command.
     *
     *   Tcl::escape('Hello World')       → '{Hello World}'
     *   Tcl::escape('Say "hello"')        → '{Say "hello"}'
     *   Tcl::escape('Tricky {brace')      → 'Tricky\ \{brace'
     *   Tcl::escape('')                   → '{}'
     */
    public static function escape(string $value): string
    {
        if ($value === '') {
            return '{}';
        }

        // No special characters — return bare word
        if (preg_match('/^[a-zA-Z0-9_.\/:-]+$/', $value)) {
            return $value;
        }

        // Try brace quoting (safest, most readable)
        if (self::canBraceQuote($value)) {
            return '{' . $value . '}';
        }

        // Fallback: backslash-escape every special character
        return self::backslashEscape($value);
    }

    /**
     * Format a Tcl option string from an associative array.
     * All values are escaped.
     *
     *   Tcl::options(['text' => 'Click "me"', 'bg' => 'red'])
     *   → '-text {Click "me"} -bg red'
     */
    public static function options(array $options, array $skip = []): string
    {
        $parts = [];
        foreach ($options as $key => $value) {
            if (in_array($key, $skip, true)) {
                continue;
            }
            $parts[] = "-{$key} " . self::escape((string) $value);
        }
        return implode(' ', $parts);
    }

    private static function canBraceQuote(string $value): bool
    {
        // Cannot brace-quote if: unbalanced braces, or contains backslash-newline
        if (str_contains($value, "\\\n")) {
            return false;
        }
        $depth = 0;
        for ($i = 0, $len = strlen($value); $i < $len; $i++) {
            if ($value[$i] === '{') $depth++;
            elseif ($value[$i] === '}') $depth--;
            if ($depth < 0) return false;
        }
        return $depth === 0;
    }

    private static function backslashEscape(string $value): string
    {
        // Characters that need escaping in Tcl
        $special = ['\\', '{', '}', '[', ']', '$', '"', ';', ' ', "\t", "\n"];
        $escaped = '';
        for ($i = 0, $len = strlen($value); $i < $len; $i++) {
            if (in_array($value[$i], $special, true)) {
                $escaped .= '\\' . $value[$i];
            } else {
                $escaped .= $value[$i];
            }
        }
        return $escaped;
    }
}
```

### Adoption in Widgets

Widgets adopt `Tcl::escape()` incrementally. Example for Button:

```php
// Before (vulnerable):
$this->tcl->evalTcl("button .{$this->parentId}.{$this->id} -text \"{$text}\" {$extra}");

// After (safe):
$opts = Tcl::options($this->options, skip: ['command']);
$this->tcl->evalTcl("button .{$this->parentId}.{$this->id} {$opts}");
```

The `Tcl::options()` method replaces the various `formatOptions()` / `getOptionString()` methods scattered across widgets. One implementation, used everywhere.

---

## 7. WebView Bind API (v2)

### The Problem

Current bind API is verbose and error-prone:

```php
// Current — 6 lines of boilerplate per command
$wv->bind('getTodos', function (string $requestId, string $argsJson) use ($wv, $db) {
    $args = json_decode($argsJson, true);
    $todos = $db->query('SELECT * FROM todos');
    $wv->returnValue($requestId, 0, json_encode($todos));
});
```

Every bind callback must: decode args, encode result, handle errors, call returnValue. Developers will forget. They'll ship bugs.

### The Solution

Add auto-return bind alongside the existing raw bind. Detect which style to use by whether the callback declares `$requestId` as its first param.

```php
// In WebView::bind() — extended, not replaced

/**
 * Bind a JS function name to a PHP callback.
 *
 * Supports two callback signatures:
 *
 * 1. Auto-return (recommended):
 *    fn(array $args): mixed
 *    Return value is JSON-encoded and sent to JS automatically.
 *    Exceptions are caught and sent as error responses.
 *
 * 2. Raw (for advanced control):
 *    fn(string $requestId, string $argsJson): void
 *    You call returnValue() yourself.
 */
public function bind(string $name, callable $callback): void
{
    // Detect signature: if first param is type-hinted as array, use auto-return
    $ref = new \ReflectionFunction(\Closure::fromCallable($callback));
    $params = $ref->getParameters();

    $isAutoReturn = true;
    if (count($params) >= 2) {
        $firstType = $params[0]->getType();
        if ($firstType instanceof \ReflectionNamedType && $firstType->getName() === 'string') {
            $isAutoReturn = false; // Raw mode: fn(string $requestId, string $argsJson)
        }
    }

    if ($isAutoReturn) {
        // Wrap in auto-return handler
        $wrapped = function (string $requestId, string $argsJson) use ($callback, $name) {
            try {
                $args = json_decode($argsJson, true) ?? [];
                $result = $callback($args);
                $this->returnValue($requestId, 0, json_encode(
                    $result,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ));
            } catch (\Throwable $e) {
                $this->returnValue($requestId, 1, json_encode($e->getMessage()));
                if ($this->onErrorCallback) {
                    ($this->onErrorCallback)("Command '{$name}' threw: " . $e->getMessage());
                }
            }
        };
        $this->commandHandlers[$name] = $wrapped;
    } else {
        // Raw mode — current behavior, unchanged
        $this->commandHandlers[$name] = $callback;
    }

    $this->process->sendCommand(['cmd' => 'bind', 'name' => $name]);
}
```

### Result

```php
// Auto-return — clean, no boilerplate
$wv->bind('getTodos', fn (array $args) => $db->query('SELECT * FROM todos'));

$wv->bind('addTodo', function (array $args) use (&$todos, $wv) {
    $todos[] = ['text' => $args[0], 'done' => false];
    $wv->emit('todosChanged', $todos);
    return ['ok' => true];
});

// Errors sent to JS automatically
$wv->bind('divide', function (array $args) {
    if ($args[1] === 0) {
        throw new \InvalidArgumentException('Division by zero');
    }
    return $args[0] / $args[1];
});

// Raw mode still works for advanced use cases (streaming, etc.)
$wv->bind('streamData', function (string $requestId, string $argsJson) use ($wv) {
    // Custom handling...
    $wv->returnValue($requestId, 0, json_encode($result));
});
```

---

## 8. JS Bridge (Auto-Injected)

### The Problem

Currently, users must know the raw WebView IPC protocol to call PHP from JavaScript. There's no standard JS API — every app reinvents the wheel.

### The Solution

A tiny JS module injected via `initJs()` when using `App::webview()`. ~35 lines.

```js
// src/WebView/phpgui.js
// Auto-injected by App::webview(). Provides the JS-side API.
;(function () {
    if (window.__phpgui) return; // Already injected

    const listeners = {};

    window.__phpgui = {
        /**
         * Call a PHP-bound function and return a Promise.
         * Usage: const result = await phpgui.invoke('getTodos', []);
         */
        invoke(command, args = []) {
            // The webview helper binary creates global functions for each bind().
            // Those globals accept JSON args and return a Promise.
            // This wrapper provides a consistent namespace.
            if (typeof window[command] === 'function') {
                return window[command](...args);
            }
            return Promise.reject(new Error(`Command "${command}" is not bound`));
        },

        /**
         * Listen for events emitted from PHP.
         * Usage: phpgui.on('todosChanged', (data) => { ... });
         */
        on(event, callback) {
            if (!listeners[event]) listeners[event] = [];
            listeners[event].push(callback);
        },

        /**
         * Remove an event listener.
         */
        off(event, callback) {
            if (!listeners[event]) return;
            listeners[event] = listeners[event].filter(cb => cb !== callback);
        },

        /** @internal — called by the PHP emit() mechanism */
        _dispatch(event, data) {
            if (!listeners[event]) return;
            listeners[event].forEach(cb => {
                try { cb(data); } catch (e) { console.error(`[phpgui] Event handler error:`, e); }
            });
        },
    };

    // Expose as both phpgui and __phpgui for flexibility
    window.phpgui = window.__phpgui;

    // Hook into the existing onPhpEvent mechanism
    // (emitted by the webview helper via eval)
    window.onPhpEvent = function (event, callback) {
        window.phpgui.on(event, callback);
    };
})();
```

### Usage from JavaScript

```js
// Call PHP
const todos = await phpgui.invoke('getTodos');
await phpgui.invoke('addTodo', ['Buy groceries']);

// Listen for PHP events
phpgui.on('todosChanged', (todos) => {
    renderTodoList(todos);
});

// Old API still works
onPhpEvent('todosChanged', (todos) => { ... });
```

---

## 9. Error Handling

### TclException

```php
namespace PhpGui\Support;

class TclException extends \RuntimeException
{
    public function __construct(
        public readonly string $tclCommand,
        public readonly string $tclError,
        public readonly string $tclErrorInfo,
        ?\Throwable $previous = null,
    ) {
        $message = "Tcl Error: {$tclError}";
        if ($tclCommand !== '') {
            // Truncate very long commands
            $cmd = strlen($tclCommand) > 200
                ? substr($tclCommand, 0, 200) . '...'
                : $tclCommand;
            $message .= "\n  Command: {$cmd}";
        }
        if ($tclErrorInfo !== '' && $tclErrorInfo !== $tclError) {
            $message .= "\n  Stack: {$tclErrorInfo}";
        }

        parent::__construct($message, 0, $previous);
    }
}
```

### Modified evalTcl()

```php
// In ProcessTCL
public function evalTcl(string $command)
{
    $interp = $this->getInterp();
    if ($this->isTcl9) {
        $result = $this->ffi->Tcl_EvalEx($interp, $command, -1, 0);
    } else {
        $result = $this->ffi->Tcl_Eval($interp, $command);
    }
    if ($result !== 0) {
        $error = $this->getResult();
        $errorInfo = $this->getVar('errorInfo');
        throw new TclException($command, $error, $errorInfo);
    }
    return $this->getResult();
}
```

**Before:**
```
RuntimeException: Tcl Error: invalid command name "buttton"
```

**After:**
```
TclException: Tcl Error: invalid command name "buttton"
  Command: buttton .w6789.w1234 -text {Hello}
  Stack: invalid command name "buttton"
    while executing
  "buttton .w6789.w1234 -text {Hello}"
```

---

## 10. Event Loop — Revised

### Timing Model

```
Mode                Sleep per tick    Why
─────────────────   ──────────────    ─────────────────────────
Tcl-only            50ms              Tk events are coarse-grained
WebView-only        10ms              stdio IPC needs responsiveness
Hybrid              10ms              Limited by the fastest engine
Socket bridge       5ms               Socket notification is near-instant
```

The current 100ms sleep for Tcl-only mode is too slow — UI feels sluggish on interactions like typing in Entry widgets. 50ms is the right balance for Tk without eating CPU.

### Revised tick() Pseudocode

```
tick():
    tcl.evalTcl("update")               # Process pending Tk events

    ids = bridge.poll()                  # Non-blocking callback check
    for each id in ids:
        tcl.executeCallback(id)          # Run the PHP closure

    for each webview in webviews:
        if webview.isClosed():
            remove(webview)
            continue
        webview.processEvents()          # Poll WebView IPC

    check quit signal                    # File-based (keep for WM_DELETE_WINDOW)
```

---

## 11. CLI Tooling

### Binary

Entry point: `bin/phpgui` (added to `composer.json` `bin` field).

```php
#!/usr/bin/env php
<?php
// bin/phpgui

require __DIR__ . '/../vendor/autoload.php';

$command = $argv[1] ?? 'help';

match ($command) {
    'new'   => (new PhpGui\Console\NewCommand())($argv),
    'build' => (new PhpGui\Console\BuildCommand())($argv),
    'check' => (new PhpGui\Console\CheckCommand())($argv),
    default => printUsage(),
};

function printUsage(): void
{
    echo <<<USAGE
    phpgui — PHP GUI Framework CLI

    Commands:
      new <name> [--mode=webview|tcl|hybrid]   Create a new project
      build                                     Package app for distribution
      check                                     Verify system requirements

    USAGE;
}
```

### `phpgui new`

Creates a minimal, working project. Not a skeleton — a real app that runs.

**Tcl mode output:**

```
my-app/
├── app.php
├── composer.json
```

`app.php`:
```php
<?php
require __DIR__ . '/vendor/autoload.php';

use PhpGui\App;
use PhpGui\Widget\{Label, Button};

$app = App::create();
$win = $app->window('my-app', 600, 400);

$label = new Label($win->getId(), [
    'text' => 'Welcome to my-app!',
    'font' => 'Helvetica 18',
]);
$label->pack(['pady' => 30]);

$count = 0;
new Button($win->getId(), [
    'text' => 'Click me',
    'command' => function () use ($label, &$count) {
        $count++;
        $label->setText("Clicked {$count} times");
    },
]);

$app->run();
```

**WebView mode output:**

```
my-app/
├── app.php
├── composer.json
└── ui/
    └── index.html
```

`app.php`:
```php
<?php
require __DIR__ . '/vendor/autoload.php';

use PhpGui\App;

$app = App::create();
$wv = $app->webview('my-app', 900, 700);
$wv->serveFromDisk(__DIR__ . '/ui');

$wv->bind('greet', fn (array $args) => "Hello, {$args[0]}!");

$wv->onClose(fn () => $app->quit());

$app->run();
```

`ui/index.html`:
```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>my-app</title>
    <style>
        body { font-family: system-ui; max-width: 600px; margin: 60px auto; text-align: center; }
        input { padding: 8px 12px; font-size: 16px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 8px 16px; font-size: 16px; cursor: pointer; }
        #result { margin-top: 20px; font-size: 18px; color: #333; }
    </style>
</head>
<body>
    <h1>my-app</h1>
    <input id="name" type="text" placeholder="Enter your name">
    <button onclick="doGreet()">Greet</button>
    <div id="result"></div>
    <script>
        async function doGreet() {
            const name = document.getElementById('name').value || 'World';
            const result = await phpgui.invoke('greet', [name]);
            document.getElementById('result').textContent = result;
        }
    </script>
</body>
</html>
```

### `phpgui build`

Phase 1 (V1): PHAR packaging only.

```php
class BuildCommand
{
    public function __invoke(array $argv): void
    {
        $root = getcwd();

        // 1. Detect mode from app.php
        $appFile = $root . '/app.php';
        if (!file_exists($appFile)) {
            $this->error("No app.php found in current directory");
            return;
        }

        // 2. Build PHAR
        $pharFile = $root . '/dist/' . basename($root) . '.phar';
        @mkdir($root . '/dist', 0755, true);

        $phar = new \Phar($pharFile);
        $phar->startBuffering();

        // Add PHP source
        $phar->buildFromDirectory($root, '/\.(php|json)$/');

        // Add frontend assets if ui/ exists
        if (is_dir($root . '/ui')) {
            $this->addDirectory($phar, $root . '/ui', 'ui');
        }

        // Add vendor (including phpgui framework + native libs)
        $this->addDirectory($phar, $root . '/vendor', 'vendor');

        // Set entry point
        $phar->setDefaultStub('app.php');
        $phar->stopBuffering();

        // Make executable
        chmod($pharFile, 0755);

        echo "Built: {$pharFile}\n";
        echo "Run:   php {$pharFile}\n";
    }
}
```

### `phpgui check`

System requirements verification.

```
$ phpgui check

PHP GUI System Check
────────────────────

PHP Version     ✓  8.3.4 (requires ≥8.2)
ext-ffi         ✓  enabled
ext-sockets     ✓  enabled (optional, improves performance)
Tcl/Tk          ✓  8.6.13 (bundled)
WebView Helper  ✓  installed (linux_x86_64)

All checks passed.
```

---

## 12. Build & Distribution

### V1 Strategy: PHAR Only

One format. One build path. No platform-specific packaging in V1.

```
phpgui build
    ├── Scan app.php for mode detection
    ├── Include: src/, vendor/, ui/ (if exists), composer.json
    ├── Exclude: tests/, .git/, node_modules/, docs/
    ├── Output: dist/app-name.phar
    └── User runs: php dist/app-name.phar
```

**Requirements for the target machine:**
- PHP 8.2+ with ext-ffi
- Tcl/Tk libs (bundled in the PHAR via vendor)
- WebView libs (system-installed: WebKitGTK on Linux, native on macOS/Windows)

### Future (V2+): Static Binary

When `static-php-cli` matures, add an opt-in flag:

```bash
phpgui build --static    # Produces a single self-contained binary
```

This concatenates a minimal PHP binary (~10MB) with the PHAR. Output is a single file that runs on machines without PHP installed. Not in V1 scope.

---

## 13. Project Structure (When You Need One)

### Single-File App (default, encouraged)

```
my-app/
├── app.php              # Everything in one file
└── composer.json
```

### Small WebView App

```
my-app/
├── app.php              # PHP backend
├── composer.json
└── ui/                  # Frontend (plain HTML/CSS/JS)
    ├── index.html
    ├── style.css
    └── app.js
```

### Larger WebView App (with frontend tooling)

```
my-app/
├── app.php              # PHP entry point
├── composer.json
├── package.json         # Frontend deps (Vite, React, etc.)
├── src/                 # PHP source (optional, for larger apps)
│   ├── Handlers.php     # Grouped bind handlers
│   └── Database.php     # App logic
└── ui/
    ├── src/             # Frontend source (JSX, TS, etc.)
    ├── dist/            # Built output (served by app.php)
    └── vite.config.js
```

**There is no mandated structure.** The framework doesn't scan directories. It doesn't auto-discover files. `app.php` is the entry point. The developer decides how to organize from there.

---

## 14. File Manifest

### New Files

```
src/
├── App.php                          ~120 lines
├── Bridge/
│   ├── BridgeInterface.php          ~20 lines
│   ├── SocketBridge.php             ~100 lines
│   └── FileBridge.php               ~45 lines
├── Support/
│   ├── Tcl.php                      ~70 lines
│   └── TclException.php             ~25 lines
├── WebView/
│   └── phpgui.js                    ~40 lines
└── Console/
    ├── NewCommand.php               ~120 lines
    ├── BuildCommand.php             ~80 lines
    └── CheckCommand.php             ~60 lines

bin/
└── phpgui                           ~30 lines

Total new code: ~710 lines
```

### Modified Files

```
src/ProcessTCL.php          +15 lines (TclException, bridge injection)
src/Application.php         +10 lines (bridge polling in tick())
src/Widget/WebView.php      +25 lines (auto-return bind detection)
composer.json               +3 lines (bin, ext-sockets suggest)

Total modifications: ~53 lines changed
```

### Untouched Files

Everything else. All existing widgets, ProcessWebView, Config, Install — unchanged.

---

## 15. Migration Path from Current API

### Zero breaking changes.

The current API continues to work exactly as it does today:

```php
// This still works — nothing changes
$app = new Application();
$window = new Window(['title' => 'Hello', 'width' => 800, 'height' => 600]);
$label = new Label($window->getId(), ['text' => 'Hello']);
$label->pack(['pady' => 20]);
$app->run();
```

`App::create()` is a new alternative, not a replacement. Users migrate when they want to, or never.

### Gradual Widget Migration

Widgets can adopt `Tcl::escape()` one at a time. Each widget fix is a standalone PR. Priority order:

1. **Button** — most common widget, most exposed to user input
2. **Label** — `setText()` takes arbitrary strings
3. **Window/TopLevel** — titles come from user config
4. **Input/Entry** — default text values
5. **Menu** — menu labels
6. **Canvas** — text drawing
7. **All others**

---

## 16. What Is Explicitly NOT in V1

| Feature | Why Not |
|---|---|
| **DI Container** | No use case. `App` constructs directly. |
| **Plugin system** | No ecosystem. Add when users demand it. |
| **Reactive state store** | PHP is not React. Let devs manage state. |
| **Command router classes** | Closures work. Class-per-command is over-engineering. |
| **Vite/HMR integration** | Devs can run Vite themselves. Don't couple to a JS tool. |
| **`phpgui dev` with file watcher** | `php app.php` in one terminal, edit in another. |
| **`phpgui.json` config file** | Everything is PHP code. No config files. |
| **AppImage / .app / .exe packaging** | PHAR is enough for V1. Native packaging is V2. |
| **Static PHP binary builds** | Depends on `static-php-cli` project maturity. V2. |
| **Custom themes/styling** | Tcl/Tk theming is a rabbit hole. WebView has CSS. |
| **Data binding** | Too opinionated for V1. Ship helpers, not a framework. |
| **Accessibility** | Important but requires per-platform audit. V2. |
| **Event hook system** | `onReady` and `onQuit` are enough. |

---

## 17. Implementation Order

### Phase 1: Foundation (Week 1-2)

```
Priority  Task                                        Depends On
────────  ──────────────────────────────────────────   ──────────
P0        Tcl::escape() + TclException                 Nothing
P0        BridgeInterface + FileBridge + SocketBridge   Nothing
P0        Integrate bridge into ProcessTCL             Bridge
P0        Integrate bridge into Application::tick()    Bridge
```

Ship these four as a single PR. They fix real bugs (injection, race condition) with zero API changes. The existing test suite should pass unchanged.

### Phase 2: App Class (Week 2-3)

```
Priority  Task                                        Depends On
────────  ──────────────────────────────────────────   ──────────
P1        App class                                    Phase 1
P1        WebView auto-return bind                     Nothing
P1        phpgui.js auto-injection                     App class
P1        Tests for App, auto-return, JS bridge        All above
```

Ship as a second PR. This is the new API surface.

### Phase 3: CLI (Week 3-4)

```
Priority  Task                                        Depends On
────────  ──────────────────────────────────────────   ──────────
P2        bin/phpgui + NewCommand                      Phase 2
P2        CheckCommand                                 Nothing
P2        BuildCommand (PHAR)                          Nothing
P2        Update composer.json (bin, suggest)           All above
```

Ship as a third PR. This completes the developer-facing toolchain.

### Phase 4: Polish (Week 4-5)

```
Priority  Task                                        Depends On
────────  ──────────────────────────────────────────   ──────────
P3        Migrate Button to Tcl::escape()              Phase 1
P3        Migrate Label to Tcl::escape()               Phase 1
P3        Migrate Window/TopLevel to Tcl::escape()     Phase 1
P3        Update README with App examples              Phase 2
P3        Demo app (WebView todo)                      Phase 2
```

---

## Appendix A: Decisions Log

| Decision | Chosen | Rejected | Why |
|---|---|---|---|
| Entry point | `App::create()` static factory | Constructor `new App()` | Factory allows future internal changes without breaking `new` calls |
| Bridge selection | Auto-detect in constructor | User-configured | Users shouldn't know bridges exist |
| WebView bind detection | Reflection on first param type | Separate method name (`bindRaw`) | One method name, auto-detected, less API surface |
| JS bridge injection | `initJs()` on WebView ready | Separate `<script>` tag | Works with `setHtml()`, `serve()`, and `navigate()` — all content modes |
| CLI framework | Raw `match` on `$argv` | Symfony Console | Zero deps. Three commands don't need a framework. |
| Config format | No config file. PHP code only. | `phpgui.json`, YAML, TOML | Config files add a concept. PHP closures are more flexible than any config format. |
| Error type | `TclException extends RuntimeException` | Custom exception hierarchy | One exception class. No hierarchy to learn. |
| PHAR vs static binary | PHAR for V1, static binary for V2 | Both in V1 | PHAR works everywhere PHP runs. Static binary is a distribution optimization, not a requirement. |
| Tcl escaping strategy | Brace-quoting with backslash fallback | Always backslash, or Tcl list commands | Brace-quoting is idiomatic Tcl, most readable in debug output, handles 99% of cases |

## Appendix B: Risk Register

| Risk | Probability | Impact | Mitigation |
|---|---|---|---|
| Tcl doesn't support Unix sockets natively | Medium | High — bridge won't work | Tcl has `socket` command for TCP. For Unix sockets, use `exec echo $id | socat - UNIX:$path` or fall back to TCP localhost. Test on Tcl 8.6 and 9.0. |
| `ext-sockets` not installed on user systems | Low | Medium | SocketBridge is opt-in. FileBridge is the universal fallback. `phpgui check` warns. `composer.json` suggests it. |
| Reflection-based bind detection is fragile | Low | Low | Only checks first param type. `string` → raw, `array` → auto-return, anything else → auto-return. Simple heuristic, easy to document. |
| PHAR can't include native .so/.dll files | Low | High | PHAR *can* include binary files. PHP extracts them to a temp directory. ProcessTCL already handles temp paths for library loading. Needs testing. |
| Brace-quoting fails on unusual strings | Very Low | Low | Backslash fallback handles all cases. Edge case: strings with embedded null bytes — reject those at the `escape()` level. |
