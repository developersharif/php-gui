# WebView Widget <sup>Beta</sup>

> **Status: Beta** — The WebView API is functional and tested across Linux, macOS, and Windows, but may change before the next stable release.

The **WebView** widget opens a **native browser window** powered by the platform's built-in engine — WebKitGTK on Linux, WKWebView on macOS, and WebView2 on Windows. It lets you build desktop apps with **HTML/CSS/JS frontends and a PHP backend**, similar to [Tauri](https://tauri.app) but for PHP.

Unlike other widgets, WebView does **not** extend `AbstractWidget`. It creates a separate native window and communicates with PHP through a JSON-over-stdio IPC bridge.

---

### How It Works

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
                               (Linux)   (macOS)   (Windows)
```

- **Commands** (JS → PHP): JavaScript calls PHP functions via `invoke()`, PHP returns values asynchronously.
- **Events** (PHP → JS): PHP pushes data to the frontend via `emit()`, JavaScript listens with `onPhpEvent()`.

The helper binary is **auto-downloaded** on first use — no manual build steps required.

---

### Requirements

- PHP 8.1+ with `ext-ffi`
- **Linux**: WebKitGTK (`sudo apt install libwebkit2gtk-4.1-dev`)
- **macOS**: No extra dependencies (WKWebView is built-in)
- **Windows**: No extra dependencies (WebView2 is pre-installed on Windows 10/11)

---

### Constructor

```php
new WebView(array $options = [])
```

| Option   | Type     | Default      | Description                            |
|----------|----------|--------------|----------------------------------------|
| `title`  | `string` | `'WebView'`  | Window title                           |
| `width`  | `int`    | `800`        | Window width in pixels                 |
| `height` | `int`    | `600`        | Window height in pixels                |
| `url`    | `string` | `null`       | URL to navigate to on startup          |
| `html`   | `string` | `null`       | Raw HTML content to display on startup |
| `debug`  | `bool`   | `false`      | Enable DevTools / inspector            |

---

### Methods

#### Content

| Method                        | Description                          |
|-------------------------------|--------------------------------------|
| `navigate(string $url)`      | Navigate to a URL                    |
| `setHtml(string $html)`      | Set the page to raw HTML content     |

#### Window

| Method                                           | Description                                                    |
|--------------------------------------------------|----------------------------------------------------------------|
| `setTitle(string $title)`                        | Change the window title                                        |
| `setSize(int $w, int $h, int $hint = 0)`         | Resize the window. Hint: `0` = none, `1` = min, `2` = max, `3` = fixed |

#### JavaScript

| Method                     | Description                                        |
|----------------------------|----------------------------------------------------|
| `evalJs(string $js)`      | Execute JavaScript in the webview                  |
| `initJs(string $js)`      | Inject JS that runs automatically before each page load |

#### Commands (JS → PHP)

| Method                                                 | Description                                          |
|--------------------------------------------------------|------------------------------------------------------|
| `bind(string $name, callable $callback)`               | Register a PHP function callable from JavaScript     |
| `unbind(string $name)`                                 | Remove a binding                                     |
| `returnValue(string $id, int $status, string $result)` | Return a value to JS. Status `0` = success, non-zero = error |

#### Events (PHP → JS)

| Method                                     | Description                              |
|--------------------------------------------|------------------------------------------|
| `emit(string $event, mixed $payload = null)` | Send an event to the JavaScript frontend |

#### Lifecycle

| Method                            | Description                              |
|-----------------------------------|------------------------------------------|
| `onReady(callable $callback)`     | Called when the webview is ready          |
| `onClose(callable $callback)`     | Called when the webview is closed         |
| `onError(callable $callback)`     | Called on error (fallback: `error_log`)   |
| `destroy()`                       | Close the webview and terminate helper    |
| `isReady(): bool`                 | Check if the webview is ready             |
| `isClosed(): bool`                | Check if the webview has been closed      |
| `getId(): string`                 | Get the unique instance ID                |

---

### JavaScript Bridge API

These functions are automatically available inside the webview:

```javascript
// Call a PHP-bound function (returns a Promise)
invoke('functionName', arg1, arg2, ...)

// Listen for events from PHP
onPhpEvent('eventName', function(payload) {
    console.log(payload);
})
```

---

### Examples

#### Minimal WebView

```php
<?php
require_once 'vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\WebView;

$app = new Application();

$wv = new WebView([
    'title' => 'Hello WebView',
    'width' => 800,
    'height' => 600,
]);

$wv->setHtml('<h1>Hello from PHP!</h1>');

$wv->onClose(fn() => $app->quit());
$app->addWebView($wv);
$app->run();
```

#### Load a URL

```php
$wv = new WebView(['title' => 'Browser']);
$wv->navigate('https://example.com');

$app->addWebView($wv);
```

#### Commands: Calling PHP from JavaScript

Bind PHP functions that JavaScript can call. Each bound function receives a request ID and JSON-encoded arguments.

**PHP (backend):**
```php
$wv->bind('greet', function (string $reqId, string $args) use ($wv) {
    $data = json_decode($args, true);
    $name = $data[0] ?? 'World';

    $wv->returnValue($reqId, 0, json_encode("Hello, {$name}!"));
});
```

**JavaScript (frontend):**
```javascript
const message = await invoke('greet', 'Alice');
console.log(message); // "Hello, Alice!"
```

> Always call `returnValue()` inside your bind callback. Status `0` resolves the JS Promise, non-zero rejects it.

#### Events: Pushing Data from PHP to JavaScript

**PHP (backend):**
```php
$wv->emit('userUpdated', ['name' => 'Alice', 'role' => 'admin']);
```

**JavaScript (frontend):**
```javascript
onPhpEvent('userUpdated', function (user) {
    document.getElementById('name').textContent = user.name;
});
```

#### Inject Startup JavaScript

Use `initJs()` to inject JS that runs before every page load — useful for configuration or polyfills:

```php
$wv->initJs('
    window.AppConfig = { version: "1.0.0", debug: true };
');
```

#### Full Example: Todo App

This example shows all the key patterns — commands, events, lifecycle hooks, and HTML rendering:

**PHP:**
```php
<?php
require_once 'vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\WebView;

$app = new Application();
$wv = new WebView([
    'title' => 'Todo App',
    'width' => 900,
    'height' => 700,
    'debug' => true,
]);

$todos = [];

// JS → PHP: Get all todos
$wv->bind('getTodos', function (string $reqId, string $args) use ($wv, &$todos) {
    $wv->returnValue($reqId, 0, json_encode($todos));
});

// JS → PHP: Add a todo
$wv->bind('addTodo', function (string $reqId, string $args) use ($wv, &$todos) {
    $data = json_decode($args, true);
    $text = trim($data[0] ?? '');
    if ($text !== '') {
        $todos[] = ['id' => uniqid(), 'text' => $text, 'completed' => false];
        $wv->emit('todosUpdated', $todos); // PHP → JS
    }
    $wv->returnValue($reqId, 0, json_encode(true));
});

// JS → PHP: Toggle completion
$wv->bind('toggleTodo', function (string $reqId, string $args) use ($wv, &$todos) {
    $id = json_decode($args, true)[0] ?? '';
    foreach ($todos as &$todo) {
        if ($todo['id'] === $id) {
            $todo['completed'] = !$todo['completed'];
            break;
        }
    }
    $wv->emit('todosUpdated', $todos);
    $wv->returnValue($reqId, 0, json_encode(true));
});

$wv->setHtml('
    <h1>Todos</h1>
    <input id="input" placeholder="Add a todo..." />
    <button onclick="addTodo()">Add</button>
    <ul id="list"></ul>
    <script>
        async function addTodo() {
            const input = document.getElementById("input");
            await invoke("addTodo", input.value);
            input.value = "";
        }
        function render(todos) {
            const list = document.getElementById("list");
            list.innerHTML = todos.map(t =>
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

#### Running Alongside Tcl/Tk Widgets

WebView windows coexist with native Tk widgets in the same event loop:

```php
$app = new Application();

// Native Tk window
$win = new \PhpGui\Widget\Window(['title' => 'Control Panel']);
$btn = new \PhpGui\Widget\Button($win->getId(), [
    'text' => 'Send to WebView',
    'command' => fn() => $wv->emit('message', 'Hello from Tk!'),
]);
$btn->pack();

// WebView window
$wv = new WebView(['title' => 'Web Frontend']);
$wv->setHtml('<h1>Waiting...</h1>
    <script>onPhpEvent("message", m => document.querySelector("h1").textContent = m);</script>
');

$app->addWebView($wv);
$app->run();
```

---

### Size Hints

The `setSize()` method accepts a hint parameter:

| Value | Constant | Behavior                                        |
|-------|----------|-------------------------------------------------|
| `0`   | NONE     | Default — user can resize freely                |
| `1`   | MIN      | Sets minimum size                               |
| `2`   | MAX      | Sets maximum size                               |
| `3`   | FIXED    | Fixed size — user cannot resize                 |

```php
$wv->setSize(1024, 768, 3); // Fixed size, not resizable
```

---

### Error Handling

Exceptions thrown inside `bind()` callbacks are automatically caught and returned to JavaScript as rejected Promises:

```php
$wv->bind('riskyOperation', function (string $reqId, string $args) use ($wv) {
    // If this throws, JS gets a rejected Promise with the error message
    $data = json_decode($args, true);
    if (empty($data[0])) {
        throw new \InvalidArgumentException('Missing required argument');
    }
    $wv->returnValue($reqId, 0, json_encode('Success'));
});
```

Register a global error handler for IPC-level errors:

```php
$wv->onError(function (string $message) {
    error_log("WebView error: {$message}");
});
```

---

### Debug Mode

Pass `'debug' => true` to enable the browser's built-in developer tools:

```php
$wv = new WebView(['debug' => true]);
```

- **Linux/macOS**: Right-click → Inspect Element
- **Windows**: F12 or right-click → Inspect

---

### Platform Notes

| Platform | Engine      | Notes                                                   |
|----------|-------------|---------------------------------------------------------|
| Linux    | WebKitGTK   | Requires `libwebkit2gtk-4.1-dev`. Needs display (X11 or Wayland). |
| macOS    | WKWebView   | Built-in, no extra dependencies.                        |
| Windows  | WebView2    | Pre-installed on Windows 10/11. Falls back to Edge.     |

### Helper Binary

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

**Windows:** Requires CMake and Visual Studio Build Tools.

---

### Notes

- WebView does **not** extend `AbstractWidget` — it is a separate native window, not a Tcl/Tk widget.
- Multiple WebView instances can run simultaneously, each in their own process.
- The event loop in `Application::run()` polls all registered WebViews automatically.
- Call `$app->addWebView($wv)` to register a WebView with the event loop.
- Closing a WebView window triggers the `onClose` callback and auto-removes it from the event loop.
- The helper binary is platform-specific and named `webview_helper_{os}_{arch}` (e.g., `webview_helper_linux_x86_64`).
