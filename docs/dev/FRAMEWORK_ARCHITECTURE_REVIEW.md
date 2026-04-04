# PHP GUI Framework — Architecture Review & V1 Blueprint

> **Review Date:** 2026-04-03
> **Reviewer Role:** Technical investor / framework architect
> **Verdict:** Conditionally fundable — but the original plan needs surgery.

---

## Part I: Tearing the Original Plan Apart

### 1. What Will Break at Scale

**The DI Container is a trap.**
You have zero Composer dependencies today. That's your competitive moat. The moment you build a PSR-11 container, you're maintaining a DI framework inside a GUI framework. Laravel's container is ~3,000 lines. Symfony's is ~8,000. You will spend months on edge cases (circular deps, lazy loading, contextual binding) that have nothing to do with making GUIs. Every PHP dev who opens the source will compare it to Laravel's container and find it lacking. You cannot win this fight.

**The Plugin System is premature.**
You have zero users. Zero plugins. You're designing `PluginInterface`, `PluginManager`, `ServiceProvider`, hooks (`app.booting`, `app.tick`, `widget.created`, `state.changed`) — that's 8+ extension points for an ecosystem that doesn't exist. Wordpress has plugins because it has millions of users, not the other way around. Every hook you add is a public API you must maintain forever. Ship without plugins. Let real users tell you where they need extensibility.

**Reactive State Store will become a bottleneck.**
A `Store` that emits changes to the frontend on every mutation sounds clean in a diagram. In practice: a todo app updates 3 fields to save one item. That's 3 state emissions, 3 JSON serializations, 3 IPC round-trips, 3 DOM re-renders. Now add a list view refreshing 50 items. You've just built the performance problems that React spent 8 years solving (batching, virtual DOM, concurrent mode). PHP is not the language to solve frontend rendering performance.

**Cross-platform build targets will drown you.**
`"targets": ["linux-x64", "darwin-arm64", "win-x64"]` — that's 3 platform builds x 2 engines (Tcl + WebView) x 2 PHP embed strategies (PHAR vs micro) = 12 build configurations. Each has its own dynamic linking, path resolution, and code signing story. Electron has a 50-person team for this. Tauri has 20+. You likely have 1-3 people. One breaking macOS Gatekeeper update will consume a month.

**`phpgui dev` with Vite HMR is scope cancer.**
You are now maintaining: a PHP file watcher, a Vite integration layer, a WebView ↔ Vite dev server handshake, and HMR state preservation across reloads. That's 4 systems that each break independently. When Vite ships a major version (they do yearly), you break. When a user's `vite.config.ts` does something unexpected, they file a bug on your repo, not Vite's.

### 2. What Is Unnecessarily Complex

**Three callback bridge implementations.**
`SocketBridge`, `NamedPipeBridge`, `FileBridge` with a `BridgeFactory` that auto-selects. Three implementations means three sets of bugs, three code paths to test, three behaviors to document. The file bridge works today. Replace it with one better option, not three.

**CommandRouter + Command classes for JS→PHP.**
```php
class GetTodos implements CommandInterface {
    public function handle(string $requestId, array $args): mixed {}
}
```
This is enterprise Java cosplay. A closure does the same thing:
```php
$wv->bind('getTodos', fn($args) => $db->getTodos());
```
The closure API already exists and works. Adding a class-per-command layer doubles the concepts a developer must learn for zero benefit until you have 50+ commands, which 95% of apps never will.

**The `phpgui.json` manifest is overloaded.**
It mixes concerns: app metadata (name, version), window configuration, build tooling, frontend bundler config, and plugin registration. That's 5 responsibilities in one file. When something breaks, the developer doesn't know which section to debug. And you must write and maintain a schema validator for all of it.

**Seven CLI commands at launch.**
`create`, `dev`, `build`, `serve`, `doctor`, `plugin add`, plus implicit `run`. Each command needs: implementation, error handling, help text, cross-platform testing. That's 7 x 3 platforms = 21 test surfaces for tooling alone. Most developers will use exactly 2: `create` and `build`.

### 3. What Will Kill Developer Adoption

**No working app in under 30 seconds.**
The proposed flow:
```bash
composer create-project phpgui/skeleton my-app
cd my-app
phpgui dev
```
This requires: Composer installed, `phpgui` CLI on PATH (how?), `phpgui.json` understood, project structure understood. Compare to the current `php-gui`:
```bash
composer require nicklasmoeller/php-gui
php my-app.php
```
One file. One command. It works. The framework adds 3 steps and a learning curve before "hello world." Every added step halves your conversion funnel.

**Forcing project structure on PHP developers.**
PHP devs are allergic to boilerplate. That's why Laravel succeeds — it has structure but you can ignore it. Your plan mandates `src/Commands/`, `src/Events/`, `src/Providers/`, `resources/views/`, `phpgui.json`. A developer who just wants a window with a button must create 5 directories and a JSON config. They will close the tab.

**Two mental models required.**
WebView mode requires knowing: PHP (backend), HTML/CSS/JS (frontend), the IPC bridge API (`invoke`/`emit`/`bind`), the command routing system, the state store, Vite configuration, and `phpgui.json`. That's 7 concepts before writing business logic. Tauri gets away with this because Rust devs expect complexity. PHP devs do not.

**No escape hatch.**
The framework wraps everything. If a developer needs to do something the framework doesn't support, they must: understand `ProcessTCL` internals, write raw Tcl strings, bypass the event dispatcher, and hope the framework doesn't fight them. There's no documented "drop down a level" path.

### 4. What Should Be Cut Immediately

| Cut | Reason |
|---|---|
| DI Container | Use constructor injection + factory functions. No container. |
| Plugin system | No ecosystem to support it. Add in V2 when users demand it. |
| Reactive State Store | Let developers manage their own state. Provide helpers, not a framework. |
| `NamedPipeBridge` | One bridge. Unix sockets on Linux/macOS, keep file bridge for Windows only. |
| CommandRouter + Command classes | Keep the closure-based `bind()` API. It works. |
| `phpgui serve` command | `php -S` exists. Don't wrap it. |
| `phpgui plugin add` command | No plugins, no command. |
| `phpgui doctor` command | Print requirements in README. |
| Vite HMR integration | Let devs use Vite themselves. Just point WebView at `localhost:5173`. |
| Seven lifecycle hooks | Keep 2: `onReady`, `onQuit`. |
| PHAR + php-micro dual strategy | Pick one. PHAR is simpler, ship that. |
| `phpgui.json` | Not needed for V1. Use `composer.json` `extra` block or just PHP config. |

---

## Part II: The Minimum Architecture for a Strong V1

### Design Principles

1. **Single-file apps must work.** If it can't run from one `.php` file, the framework has failed.
2. **Zero new dependencies.** The zero-dep story is the product. Protect it.
3. **Progressive complexity.** Simple things simple, complex things possible.
4. **One way to do each thing.** No bridge selection, no dual build strategies, no mode switches.
5. **Escape hatches everywhere.** Raw Tcl access. Raw WebView IPC. Always available.

### V1 Architecture

```
┌──────────────────────────────────────────────┐
│            User Application Code             │
│   (single file or structured project)        │
├──────────────────────────────────────────────┤
│              PhpGui\App                       │
│   Boot → Configure → Run (one class)         │
├──────────────┬───────────────────────────────┤
│  Tcl/Tk      │        WebView               │
│  Widgets     │   (HTML/CSS/JS + PHP)         │
│  (native)    │                               │
├──────────────┴───────────────────────────────┤
│           Event Loop (single)                │
│   Socket bridge (Linux/macOS)                │
│   File bridge (Windows fallback)             │
├──────────────────────────────────────────────┤
│         Platform Layer                       │
│   ProcessTCL (FFI) + ProcessWebView (IPC)    │
└──────────────────────────────────────────────┘
```

### What Ships in V1

#### A. Enhanced Core (patch the foundation)

**1. Replace file-based callbacks with Unix domain sockets.**

One implementation. Massive improvement. The current file bridge has real issues: race conditions under rapid clicks, file I/O latency, orphaned temp files on crash. Unix sockets solve all three. Keep the file bridge only as a Windows fallback until named pipes are implemented.

```php
// Internal — user never sees this
// Bridge auto-selected by platform
$bridge = match (PHP_OS_FAMILY) {
    'Windows' => new FileBridge(),
    default   => new SocketBridge(),
};
```

**2. Tcl command escaper.**

This is a security fix, not a feature. The current codebase interpolates user strings directly into `evalTcl()`. One curly brace in a label crashes the app. One semicolon enables Tcl injection.

```php
// Before (current, dangerous):
$this->tcl->evalTcl("button .{$id} -text \"{$userInput}\"");

// After (V1):
$this->tcl->evalTcl("button .{$id} -text " . Tcl::escape($userInput));
```

Single static class. ~40 lines. Non-negotiable for any production use.

**3. Proper error handling.**

Wrap `evalTcl()` errors with context. Add a `TclException` that includes the command that failed, the Tcl error message, and the Tcl stack trace (`errorInfo`). Currently errors throw a generic `Exception` with just the Tcl error string — useless for debugging.

#### B. Unified App Class

Replace `Application` + manual `ProcessTCL::getInstance()` + manual WebView setup with one entry point.

```php
use PhpGui\App;

// Simplest possible app — single file, no structure required
$app = App::create();

// Tcl/Tk mode
$window = $app->window('My App', 800, 600);
$window->button('Click me', fn() => $window->alert('Hello!'));

// WebView mode
$wv = $app->webview('My App', 800, 600);
$wv->serve(__DIR__ . '/ui');
$wv->bind('getData', fn($args) => ['items' => [1, 2, 3]]);

// Hybrid — both at once
$app->run();
```

**What `App` does internally:**
- Initializes ProcessTCL (Tcl mode) or ProcessWebView (WebView mode) or both (hybrid)
- Sets up the callback bridge (socket or file, auto-detected)
- Runs the event loop
- Handles cleanup on shutdown

**What `App` does NOT do:**
- No container. No service registration. No providers.
- No plugin loading. No hook system.
- No config files. Everything is code.

#### C. WebView Improvements

**1. Auto-return serialization.**

The current bind API requires manual `returnValue()` + `json_encode()`. Tedious and error-prone.

```php
// Current (verbose):
$wv->bind('getTodos', function($requestId, $argsJson) use ($wv, $db) {
    $args = json_decode($argsJson, true);
    $todos = $db->query('SELECT * FROM todos');
    $wv->returnValue($requestId, 0, json_encode($todos));
});

// V1 (clean):
$wv->bind('getTodos', function(array $args) use ($db) {
    return $db->query('SELECT * FROM todos');
    // Framework handles: json_encode, returnValue, error catching
});
```

Exceptions in the handler are caught and sent as error responses to JS. No more `try/catch` boilerplate in every bind.

**2. Built-in JS bridge (injected automatically).**

Currently the user must know the raw WebView IPC protocol. V1 injects a tiny JS helper:

```js
// Auto-injected into every WebView page
window.phpgui = {
    invoke(command, args = []),   // Returns Promise
    on(event, callback),          // Listen for PHP events
    off(event, callback),         // Remove listener
};
```

~30 lines of JS. Injected via `initJs()` on WebView ready. No npm package needed.

**3. `serveDirectory` simplification.**

Current `serveDirectory()` spawns a PHP built-in server. Keep this, but add automatic port selection and a readiness check that doesn't poll with `usleep`. Use the socket bridge to signal when the server is ready.

#### D. CLI Tool (Minimal)

Three commands. That's it.

```bash
# Scaffold a new project (interactive: tcl / webview / hybrid)
vendor/bin/phpgui new my-app

# Package for distribution
vendor/bin/phpgui build

# Check if system meets requirements
vendor/bin/phpgui check
```

**`phpgui new`** generates:
```
my-app/
├── app.php              # Entry point (single file, working app)
├── composer.json         # Requires phpgui/framework
└── ui/                   # Only for webview mode
    └── index.html
```

That's it. No `src/Commands/`. No `src/Providers/`. No `phpgui.json`. The app is `app.php`. A developer reads one file and understands the entire app.

**`phpgui build`** does:
1. Detect mode from `app.php` (does it use `webview()`? → bundle frontend assets)
2. Create PHAR archive
3. Embed platform-specific native libs
4. Output: `dist/my-app.phar` (or `dist/my-app` as self-executing binary if `static-php-cli` is available)

One build strategy. PHAR first, static binary as opt-in enhancement. No AppImage, no `.app` bundle, no NSIS installer in V1. Those are V2 problems.

**`phpgui check`** prints:
```
PHP 8.2+       ✓  (8.3.4)
ext-ffi        ✓
ext-sockets    ✓
Tcl/Tk         ✓  (8.6.13, bundled)
WebView helper ✓  (installed)
```

#### E. What the Widget API Looks Like in V1

**Tcl/Tk mode — enhanced, not replaced:**

```php
$app = App::create();
$win = $app->window('Todo App', 400, 500);

// Simple widgets (unchanged from current API, just cleaner)
$label = $win->label('My Todos', ['font' => 'Helvetica 16 bold']);
$label->pack(['pady' => 10]);

$input = $win->input(['width' => 30]);
$input->pack(['pady' => 5]);

$button = $win->button('Add', function() use ($input, $listbox) {
    $listbox->insert('end', $input->getValue());
    $input->clear();
});
$button->pack(['pady' => 5]);

// New convenience: window-level helpers
$win->onClose(fn() => $app->quit());

$app->run();
```

**WebView mode — the Tauri-for-PHP pitch:**

```php
$app = App::create();
$wv = $app->webview('Todo App', 800, 600);

$wv->serve(__DIR__ . '/ui');

$todos = [];

$wv->bind('addTodo', function(array $args) use (&$todos, $wv) {
    $todos[] = ['text' => $args[0], 'done' => false];
    $wv->emit('todosChanged', $todos);
    return ['ok' => true];
});

$wv->bind('getTodos', fn() => $todos);

$wv->bind('toggleTodo', function(array $args) use (&$todos, $wv) {
    $todos[$args[0]]['done'] = !$todos[$args[0]]['done'];
    $wv->emit('todosChanged', $todos);
    return ['ok' => true];
});

$app->run();
```

**Hybrid mode — both engines:**

```php
$app = App::create();

// Native Tk window for system controls
$control = $app->window('Controls', 300, 200);
$control->button('Refresh Data', fn() => $wv->evalJs('location.reload()'));
$control->button('Quit', fn() => $app->quit());

// WebView for the main UI
$wv = $app->webview('Dashboard', 1024, 768);
$wv->serve(__DIR__ . '/dashboard');

$app->run();
```

### V1 Scope Summary

| Ships | Deferred to V2+ |
|---|---|
| `App` unified entry point | DI container |
| Socket-based callback bridge | Plugin system |
| Tcl escaper (security) | Reactive state store |
| Better error handling | Vite/HMR integration |
| Auto-return in `bind()` | Command router classes |
| Injected JS bridge | Cross-platform installers |
| `phpgui new` scaffolding | `phpgui dev` with file watcher |
| `phpgui build` (PHAR) | Static binary builds |
| `phpgui check` | Platform-specific packaging |
| Clean WebView `serve()` | Custom themes/styling engine |
| All existing widgets (preserved) | New widget types |

### File Count

The entire framework addition is ~12 new files:

```
src/
├── App.php                      # Unified entry point (~150 lines)
├── Bridge/
│   ├── BridgeInterface.php      # Contract (~15 lines)
│   ├── SocketBridge.php         # Unix domain socket (~120 lines)
│   └── FileBridge.php           # Current logic, extracted (~60 lines)
├── Support/
│   ├── Tcl.php                  # Escaper (~40 lines)
│   └── TclException.php         # Enhanced errors (~30 lines)
├── WebView/
│   └── JsBridge.js              # Auto-injected JS (~30 lines)
└── Console/
    ├── NewCommand.php           # Scaffolding (~100 lines)
    ├── BuildCommand.php         # PHAR packaging (~150 lines)
    └── CheckCommand.php         # System check (~50 lines)
```

~750 lines of new code. That's it. The rest is improving what already exists.

---

## Part III: The Honest Assessment

### Why This Could Win

1. **The "Tauri for PHP" pitch is real.** There are ~5 million PHP developers with zero good options for desktop apps. Electron requires Node. Tauri requires Rust. NativePHP requires Laravel (heavy). This package requires nothing.

2. **The WebView IPC is already good.** The `invoke`/`emit`/`bind` pattern mirrors Tauri's API. PHP devs who've seen Tauri will feel at home. The stdio JSON protocol is clean and debuggable.

3. **Zero dependencies is a genuine moat.** No other PHP GUI framework can claim this. `composer require` + `php app.php` and you have a desktop app. That's a 10-second pitch.

4. **The dual-mode story is unique.** "Use Tk for quick scripts, WebView for real apps, mix both if you want." No other framework in any language offers this flexibility at this weight class.

### Why This Could Fail

1. **One maintainer, three platforms.** Cross-platform GUI is a full-time job for a team. macOS code signing alone takes weeks to get right. Windows DPI scaling will generate bugs forever. If this is a side project, it will fall behind platform updates within a year.

2. **The WebView helper binary is a distribution problem.** Auto-downloading a binary from GitHub Releases during `composer install` works for development. It will fail behind corporate firewalls, in Docker builds, in air-gapped environments, and on every CI system that restricts network access. This needs a fallback story.

3. **PHP's reputation kills adoption.** "Desktop apps in PHP" will be mocked on Hacker News, Reddit, and Twitter. The framework needs one killer demo app that makes people say "wait, that's actually good" — and it needs to ship with V1.

4. **The Tcl/Tk side will be abandoned.** Once WebView mode works well, nobody will choose Tcl/Tk widgets that look like 1998. Maintaining both rendering engines doubles the work for diminishing returns. Be honest about which engine is the future and allocate resources accordingly.

### The Funding Recommendation

**Fund it — with conditions:**

1. Ship V1 as described above (minimal scope, ~750 new lines). No plugin system, no state store, no container.
2. Ship one demo app that looks impressive (WebView mode, modern UI, <5MB).
3. Fix the security issue (Tcl escaper) before any V1 marketing.
4. Solve the binary distribution problem (vendor the helper binary in the Composer package, or compile from source on install).
5. Pick one hero platform (Linux) and make it bulletproof. Cross-platform is V2.

**Timeline:** 6-8 weeks for a credible V1 with the reduced scope.

**Kill criteria:** If after V1 launch, fewer than 100 GitHub stars in 60 days, the market has spoken. Pivot or stop.
