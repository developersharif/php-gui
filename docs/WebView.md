# WebView Widget <sup>Beta</sup>

> **Status: Beta** — The WebView API is functional and tested across Linux, macOS, and Windows, but may change before the next stable release.

The **WebView** widget opens a **native browser window** powered by the platform's built-in engine — WebKitGTK on Linux, WKWebView on macOS, and WebView2 on Windows. It lets you build desktop apps with **HTML/CSS/JS frontends and a PHP backend**, similar to [Tauri](https://tauri.app) but for PHP.

Unlike other widgets, WebView does **not** extend `AbstractWidget`. It creates a separate native window and communicates with PHP through a JSON-over-stdio IPC bridge.

---

## How It Works

```
┌──────────────┐    JSON/stdio    ┌──────────────────┐
│  PHP Process  │ ◄──────────────► │  WebView Helper  │
│  (your code)  │   IPC bridge     │  (native binary) │
└──────────────┘                  └──────────────────┘
                                          │
                                   Platform Engine
                                  ┌───────┼───────┐
                                  │       │       │
                               WebKitGTK WKWebView WebView2
                               (Linux)   (macOS)  (Windows)
```

- **Commands** (JS → PHP): JavaScript calls PHP functions via `invoke()`. PHP returns values asynchronously.
- **Events** (PHP → JS): PHP pushes data to the frontend via `emit()`. JavaScript listens with `onPhpEvent()`.

The helper binary is **auto-downloaded** on first use — no manual build steps required.

---

## Requirements

- PHP 8.1+ with `ext-ffi`
- **Linux**: WebKitGTK (`sudo apt install libwebkit2gtk-4.1-dev`)
- **macOS**: No extra dependencies (WKWebView is built-in)
- **Windows**: No extra dependencies (WebView2 is pre-installed on Windows 10/11)

---

## Constructor

```php
new WebView(array $options = [])
```

| Option   | Type     | Default      | Description                             |
|----------|----------|--------------|-----------------------------------------|
| `title`  | `string` | `'WebView'`  | Window title                            |
| `width`  | `int`    | `800`        | Window width in pixels                  |
| `height` | `int`    | `600`        | Window height in pixels                 |
| `url`    | `string` | `null`       | URL to navigate to on startup           |
| `html`   | `string` | `null`       | Raw HTML content to display on startup  |
| `debug`  | `bool`   | `false`      | Enable DevTools / browser inspector     |

---

## Methods

### Content

| Method                           | Description                            |
|----------------------------------|----------------------------------------|
| `navigate(string $url)`          | Navigate to a URL                      |
| `setHtml(string $html)`          | Replace the page with raw HTML content |
| `serveFromDisk(string $path)`    | Serve a built frontend with no HTTP server — see [Serving a Frontend](#serving-a-frontend) |
| `serveVite(string $buildDir, string $devUrl, float $timeout)` | Auto-detect dev vs production — see [Vite Integration](#vite-integration) |
| `serveDirectory(string $path, int $port)` | Serve a directory via PHP's built-in HTTP server |

### Window

| Method                                      | Description                                                             |
|---------------------------------------------|-------------------------------------------------------------------------|
| `setTitle(string $title)`                   | Change the window title                                                 |
| `setSize(int $w, int $h, int $hint = 0)`    | Resize the window. Hint: `0`=none, `1`=min, `2`=max, `3`=fixed         |

### JavaScript

| Method                      | Description                                           |
|-----------------------------|-------------------------------------------------------|
| `evalJs(string $js)`        | Execute JavaScript in the webview                     |
| `initJs(string $js)`        | Inject JS that runs automatically before each page load |

### Commands (JS → PHP)

| Method                                                  | Description                                               |
|---------------------------------------------------------|-----------------------------------------------------------|
| `bind(string $name, callable $callback)`                | Register a PHP function callable from JavaScript          |
| `unbind(string $name)`                                  | Remove a binding                                          |
| `returnValue(string $id, int $status, string $result)`  | Return a value to JS. Status `0` = resolve, non-zero = reject |

### Events (PHP → JS)

| Method                                       | Description                              |
|----------------------------------------------|------------------------------------------|
| `emit(string $event, mixed $payload = null)` | Push an event to the JavaScript frontend |

### Lifecycle

| Method                                 | Description                                                           |
|----------------------------------------|-----------------------------------------------------------------------|
| `onReady(callable $callback)`          | Called when the webview window is ready                               |
| `onClose(callable $callback)`          | Called when the webview window is closed by the user                  |
| `onError(callable $callback)`          | Called on IPC-level errors (fallback: `error_log`)                    |
| `onServeDirReady(callable $callback)`  | Called when `serveFromDisk()` or `serveVite()` (prod mode) is loaded — receives the effective URL |
| `destroy()`                            | Close the window and terminate the helper process                     |
| `isReady(): bool`                      | Whether the webview is ready                                          |
| `isClosed(): bool`                     | Whether the webview has been closed                                   |
| `getId(): string`                      | Unique instance ID                                                    |
| `getServerPort(): ?int`                | Port used by `serveDirectory()`, or `null`                            |

---

## JavaScript Bridge API

These functions are automatically injected into every page:

```javascript
// Call a PHP-bound function — returns a Promise
invoke('functionName', arg1, arg2, ...)

// Listen for events emitted by PHP
onPhpEvent('eventName', function(payload) {
    console.log(payload);
})
```

---

## Serving a Frontend

For production builds (e.g., a Vite app), load the frontend directly from disk — no HTTP server, no open ports, no firewall prompts.

### `serveFromDisk(string $path)`

Serves a built frontend using a platform-native mechanism:

| Platform | Mechanism                              | URL                                     |
|----------|----------------------------------------|-----------------------------------------|
| Linux    | `phpgui://` custom URI scheme (WebKitGTK) | `phpgui://app/index.html`              |
| Windows  | Virtual hostname (WebView2)            | `https://phpgui.localhost/index.html`   |
| macOS    | File URL with directory read access    | `file:///path/to/dist/index.html`       |

Linux and Windows have SPA-friendly origins — absolute asset paths (`/assets/index.js`) work correctly. **On macOS**, WKWebView loads via `file://`, which requires relative asset paths. Set `base: './'` in your `vite.config.js`:

```js
// vite.config.js
export default {
  base: './',  // required for macOS file:// serving
}
```

Use `onServeDirReady()` to be notified of the effective URL after the content loads:

```php
$wv->onServeDirReady(function (string $url): void {
    echo "Serving from: {$url}\n";
    // Linux:   "phpgui://app/index.html"
    // macOS:   "file:///Users/you/app/dist/index.html"
    // Windows: "https://phpgui.localhost/index.html"
});

$wv->serveFromDisk(__DIR__ . '/dist');
```

**Requires** a directory containing `index.html`. Throws `RuntimeException` if the path is invalid or `index.html` is missing.

---

## Vite Integration

### `serveVite(string $buildDir, string $devUrl = 'http://localhost:5173', float $timeout = 0.3)`

Smart frontend serving that automatically switches between dev and production:

- **Dev**: If the Vite dev server is reachable at `$devUrl`, navigate to it. Hot Module Replacement (HMR) works.
- **Prod**: If the dev server is not reachable, call `serveFromDisk($buildDir)`. No HTTP server, no ports.

```php
// One line — works in dev and production
$wv->serveVite(__DIR__ . '/../frontend/dist');
```

With a custom dev URL:

```php
$wv->serveVite(
    buildDir: __DIR__ . '/../frontend/dist',
    devUrl:   'http://localhost:5174',   // custom Vite port
);
```

The dev server detection uses a lightweight TCP probe with a configurable timeout. A timeout of `0.3` seconds (default) adds no perceptible startup delay.

**Recommended Vite config** for cross-platform compatibility:

```js
// vite.config.js
export default {
  base: './',       // required for macOS production builds (file:// serving)
  build: {
    outDir: 'dist',
  },
}
```

---

## Transparent Fetch Proxy

### `enableFetchProxy()`

Intercepts all `window.fetch()` calls to absolute `http://` and `https://` URLs and routes them through PHP, **bypassing CORS entirely**.

Without this, cross-origin API requests fail on all platforms:

| Platform | Origin seen by external APIs | fetch() to API |
|----------|------------------------------|----------------|
| Linux    | `phpgui://app`               | Blocked        |
| macOS    | `null` (file://)             | Blocked        |
| Windows  | `https://phpgui.localhost`   | Blocked        |

After calling `enableFetchProxy()`, the same `fetch()` calls succeed transparently — PHP makes the HTTP request server-side and returns the result to JS as a proper `Response` object.

```php
$wv = new WebView(['title' => 'My App', ...]);
$wv->enableFetchProxy();  // ← one line
$wv->serveFromDisk(__DIR__ . '/dist');
```

```js
// Frontend code — unchanged, works on all platforms
const res  = await fetch('https://api.example.com/data');
const data = await res.json();
```

**How it works:**

```
JS: fetch('https://api.example.com/data')
  └─ interceptor: absolute http(s) URL detected
       └─ invoke('__phpFetch', { url, method, headers, body })
            └─ PHP: cURL (or stream_context fallback) makes the real request
                 └─ returns { status, headers, body (base64) }
                      └─ JS: new Response(decodedBody, { status, headers })
```

**What is proxied:**
- Any `fetch()` call to an absolute `http://` or `https://` URL

**What passes through natively:**
- Relative URLs (`/api/data`, `./config.json`)
- Same-origin asset URLs (`phpgui://`, `file://`, `https://phpgui.localhost`)

**Notes:**
- Uses cURL when available, falls back to PHP stream context
- Response body is base64-encoded over the IPC bridge for binary safety — binary responses (images, PDFs) work correctly
- `enableFetchProxy()` can safely be called multiple times
- Call it before `serveFromDisk()` / `serveVite()` so the JS interceptor is injected on the first page load

---

## Examples

### Minimal WebView

```php
<?php
require_once 'vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\WebView;

$app = new Application();

$wv = new WebView([
    'title'  => 'Hello WebView',
    'width'  => 800,
    'height' => 600,
]);

$wv->setHtml('<h1>Hello from PHP!</h1>');
$wv->onClose(fn() => $app->quit());

$app->addWebView($wv);
$app->run();
```

### Load a URL

```php
$wv = new WebView(['title' => 'Browser']);
$wv->navigate('https://example.com');

$app->addWebView($wv);
```

### Commands: Calling PHP from JavaScript

Bind PHP functions that JavaScript can call. Each callback receives a request ID and the JSON-encoded argument list.

**PHP:**
```php
$wv->bind('greet', function (string $reqId, string $args) use ($wv): void {
    $data = json_decode($args, true);
    $name = $data[0] ?? 'World';
    $wv->returnValue($reqId, 0, json_encode("Hello, {$name}!"));
});
```

**JavaScript:**
```javascript
const message = await invoke('greet', 'Alice');
console.log(message); // "Hello, Alice!"
```

> Always call `returnValue()` inside your bind callback. Skipping it leaves the JS Promise pending indefinitely.

### Events: Pushing Data from PHP to JavaScript

**PHP:**
```php
$wv->emit('userUpdated', ['name' => 'Alice', 'role' => 'admin']);
```

**JavaScript:**
```javascript
onPhpEvent('userUpdated', function (user) {
    document.getElementById('name').textContent = user.name;
});
```

### Vite App: Dev + Production in One File

A complete setup that hot-reloads in development and serves from disk in production:

**PHP (`app.php`):**
```php
<?php
require_once 'vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\WebView;

$app = new Application();
$wv  = new WebView(['title' => 'My App', 'width' => 1024, 'height' => 768]);

// Transparent fetch() proxy — required for cross-origin API calls
$wv->enableFetchProxy();

// Dev: navigate to Vite dev server (HMR)
// Prod: serve dist/ via phpgui:// custom URI scheme (no HTTP server)
$wv->serveVite(__DIR__ . '/frontend/dist');

$wv->onServeDirReady(function (string $url): void {
    echo "Serving from: {$url}\n";
});

// JS → PHP: proxy an API call
$wv->bind('getUser', function (string $id, string $args) use ($wv): void {
    $userId = json_decode($args, true)[0] ?? 1;
    // fetch() from JS would be blocked by CORS — PHP has no such restriction
    $data = file_get_contents("https://jsonplaceholder.typicode.com/users/{$userId}");
    $wv->returnValue($id, 0, $data);
});

$wv->onClose(fn() => $app->quit());
$app->addWebView($wv);
$app->run();
```

**JavaScript (frontend):**
```javascript
// Works identically in dev and production — no code changes needed
const res  = await fetch('https://jsonplaceholder.typicode.com/todos/1');
const todo = await res.json();

const user = await invoke('getUser', 1);
console.log(user);
```

**`vite.config.js`:**
```js
export default {
  base: './',       // required for macOS production builds
  build: {
    outDir: 'dist',
  },
}
```

### Todo App

A complete example showing commands, events, lifecycle hooks, and HTML rendering:

```php
<?php
require_once 'vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\WebView;

$app   = new Application();
$wv    = new WebView(['title' => 'Todo App', 'width' => 900, 'height' => 700, 'debug' => true]);
$todos = [];

$wv->bind('getTodos', function (string $id, string $args) use ($wv, &$todos): void {
    $wv->returnValue($id, 0, json_encode($todos));
});

$wv->bind('addTodo', function (string $id, string $args) use ($wv, &$todos): void {
    $text = trim(json_decode($args, true)[0] ?? '');
    if ($text !== '') {
        $todos[] = ['id' => uniqid(), 'text' => $text, 'completed' => false];
        $wv->emit('todosUpdated', $todos);
    }
    $wv->returnValue($id, 0, json_encode(true));
});

$wv->bind('toggleTodo', function (string $id, string $args) use ($wv, &$todos): void {
    $todoId = json_decode($args, true)[0] ?? '';
    foreach ($todos as &$t) {
        if ($t['id'] === $todoId) { $t['completed'] = !$t['completed']; break; }
    }
    $wv->emit('todosUpdated', $todos);
    $wv->returnValue($id, 0, json_encode(true));
});

$wv->setHtml('
    <h1>Todos</h1>
    <input id="in" placeholder="New task…" />
    <button onclick="add()">Add</button>
    <ul id="list"></ul>
    <script>
        async function add() {
            const el = document.getElementById("in");
            await invoke("addTodo", el.value);
            el.value = "";
        }
        function render(todos) {
            document.getElementById("list").innerHTML = todos.map(t =>
                `<li onclick="invoke(\'toggleTodo\', \'${t.id}\')"
                     style="${t.completed ? \'text-decoration:line-through\' : \'\'}">
                    ${t.text}
                </li>`
            ).join("");
        }
        onPhpEvent("todosUpdated", render);
        invoke("getTodos").then(render);
    </script>
');

$wv->onClose(fn() => $app->quit());
$app->addWebView($wv);
$app->run();
```

### Running Alongside Tcl/Tk Widgets

WebView windows coexist with native Tk widgets in the same event loop:

```php
$app = new Application();

// Native Tk control panel
$win = new \PhpGui\Widget\Window(['title' => 'Control Panel']);
$btn = new \PhpGui\Widget\Button($win->getId(), [
    'text'    => 'Send to WebView',
    'command' => fn() => $wv->emit('message', 'Hello from Tk!'),
]);
$btn->pack();

// WebView window
$wv = new WebView(['title' => 'Web Frontend']);
$wv->setHtml('
    <h1>Waiting…</h1>
    <script>onPhpEvent("message", m => document.querySelector("h1").textContent = m);</script>
');

$app->addWebView($wv);
$app->run();
```

---

## Size Hints

The `setSize()` hint controls the resize behaviour:

| Value | Behaviour                    |
|-------|------------------------------|
| `0`   | Default — freely resizable   |
| `1`   | Minimum size                 |
| `2`   | Maximum size                 |
| `3`   | Fixed — not resizable        |

```php
$wv->setSize(1024, 768, 3); // Fixed, not resizable
```

---

## Debug Mode

```php
$wv = new WebView(['debug' => true]);
```

- **Linux / macOS**: Right-click → Inspect Element
- **Windows**: F12 or right-click → Inspect

---

## Error Handling

Exceptions thrown inside `bind()` callbacks are automatically caught and returned to JavaScript as rejected Promises:

```php
$wv->bind('riskyOp', function (string $id, string $args) use ($wv): void {
    $data = json_decode($args, true);
    if (empty($data[0])) {
        throw new \InvalidArgumentException('Missing required argument');
        // JS: await invoke('riskyOp') rejects with "Missing required argument"
    }
    $wv->returnValue($id, 0, json_encode('ok'));
});
```

Register a global handler for IPC-level errors:

```php
$wv->onError(function (string $message): void {
    error_log("WebView error: {$message}");
});
```

---

## Platform Notes

| Platform | Engine    | Notes                                                              |
|----------|-----------|--------------------------------------------------------------------|
| Linux    | WebKitGTK | Requires `libwebkit2gtk-4.1-dev`. Needs a display (X11/Wayland).  |
| macOS    | WKWebView | Built-in, no extra dependencies. Set `base: './'` in Vite config for `serveFromDisk()`. |
| Windows  | WebView2  | Pre-installed on Windows 10/11.                                    |

---

## Helper Binary

The WebView widget relies on a small native helper binary that hosts the browser engine. It is automatically downloaded from GitHub Releases on first use.

If auto-download fails (offline, firewall), build from source:

```bash
cd vendor/developersharif/php-gui/src/lib/webview_helper
bash build.sh
```

**Linux build dependencies:**
```bash
sudo apt install cmake libgtk-3-dev libwebkit2gtk-4.1-dev
```

**macOS:**
```bash
brew install cmake
```

**Windows:** CMake + Visual Studio Build Tools.

---

## Notes

- WebView does **not** extend `AbstractWidget` — it is a separate native window, not a Tcl/Tk widget.
- Multiple WebView instances can run simultaneously, each with their own helper process.
- Register a WebView with the event loop via `$app->addWebView($wv)`.
- Closing the window triggers `onClose` and auto-removes the WebView from the event loop.
- The helper binary is named `webview_helper_{os}_{arch}` (e.g., `webview_helper_linux_x86_64`).
- `enableFetchProxy()` only intercepts absolute `http://`/`https://` URLs — relative paths and same-origin assets go through the native fetch unmodified.
