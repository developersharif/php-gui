# WebView Widget

The WebView widget opens a **native webview window** powered by the platform's built-in browser engine (WebKitGTK on Linux, WKWebView on macOS, WebView2 on Windows). It enables building desktop apps with HTML/CSS/JS frontends and PHP backends — like [Tauri](https://tauri.app) but for PHP.

> **Note:** The WebView opens as a **separate native window**, not embedded inside a Tk window. You can use both Tk widgets and WebViews in the same application.

## Requirements

- PHP 8.1+ with `ext-ffi`
- The prebuilt `webview_helper` binary (see [Building](#building-the-helper-binary))
- **Linux:** `libwebkit2gtk-4.1` runtime library
- **macOS:** Built-in (WebKit ships with macOS)
- **Windows:** WebView2 runtime (pre-installed on Windows 10/11)

## Constructor

```php
$webview = new \PhpGui\Widget\WebView(array $options = []);
```

### Options

| Option   | Type   | Default     | Description                        |
|----------|--------|-------------|------------------------------------|
| `title`  | string | `'WebView'` | Window title                       |
| `width`  | int    | `800`       | Window width in pixels             |
| `height` | int    | `600`       | Window height in pixels            |
| `url`    | string | `null`      | Initial URL to navigate to         |
| `html`   | string | `null`      | Initial HTML content               |
| `debug`  | bool   | `false`     | Enable DevTools and IPC logging    |

## Methods

### Content

```php
$webview->navigate(string $url): void
```
Navigate to a URL.

```php
$webview->setHtml(string $html): void
```
Set the webview content to raw HTML.

### Window

```php
$webview->setTitle(string $title): void
```
Change the window title.

```php
$webview->setSize(int $width, int $height, int $hint = 0): void
```
Resize the window. Hint values: `0` = default, `1` = minimum, `2` = maximum, `3` = fixed.

### JavaScript

```php
$webview->evalJs(string $js): void
```
Execute JavaScript in the webview.

```php
$webview->initJs(string $js): void
```
Add JavaScript that runs before every page load (persistent).

### Commands: JS to PHP (like Tauri's `invoke()`)

```php
$webview->bind(string $name, callable $callback): void
```
Bind a PHP function to be callable from JavaScript. The callback receives `(string $requestId, string $argsJson)`. You must call `returnValue()` to resolve the JS Promise.

```php
$webview->unbind(string $name): void
```
Remove a previously bound function.

```php
$webview->returnValue(string $id, int $status, string $result): void
```
Return a value to JavaScript. `$status = 0` resolves the Promise, non-zero rejects it. `$result` must be a JSON-encoded string.

### Events: PHP to JS (like Tauri's `emit()`)

```php
$webview->emit(string $event, mixed $payload = null): void
```
Push an event to JavaScript. Listen in JS with:
```javascript
onPhpEvent('eventName', function(data) { console.log(data); });
```

### Lifecycle

```php
$webview->destroy(): void
$webview->isClosed(): bool
$webview->isReady(): bool
$webview->getId(): string
```

### Callbacks

```php
$webview->onReady(callable $callback): void
$webview->onClose(callable $callback): void
$webview->onError(callable $callback): void
```

## Usage with Application

Register WebViews with the Application so they get polled in the event loop:

```php
$app = new \PhpGui\Application();
$app->addWebView($webview);
$app->run();
```

Remove with `$app->removeWebView($webview)`.

## Examples

### Basic: Open a webpage

```php
<?php
require_once 'vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\WebView;

$app = new Application();

$webview = new WebView([
    'title' => 'My Browser',
    'width' => 1024,
    'height' => 768,
    'url' => 'https://example.com',
]);
$app->addWebView($webview);

$app->run();
```

### Full App: HTML/CSS/JS frontend + PHP backend

```php
<?php
require_once 'vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\WebView;

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
    body { font-family: system-ui; background: #1a1a2e; color: #eee; padding: 2rem; }
    button { background: #0f3460; color: #eee; border: none; padding: 10px 20px;
             border-radius: 8px; cursor: pointer; }
    button:hover { background: #e94560; }
    input { padding: 10px; border-radius: 8px; border: 1px solid #333;
            background: #0f3460; color: #eee; width: 200px; }
    #result { margin-top: 1rem; color: #e94560; }
</style>
</head>
<body>
    <h1>PHP GUI App</h1>
    <input id="name" placeholder="Enter your name" />
    <button onclick="greet()">Greet</button>
    <div id="result"></div>

    <script>
    async function greet() {
        const name = document.getElementById("name").value;
        const result = await invoke("greet", name);
        document.getElementById("result").textContent = result;
    }

    onPhpEvent("statusUpdate", function(data) {
        document.getElementById("result").textContent = "Status: " + data.message;
    });
    </script>
</body>
</html>
');

// Handle JS -> PHP command
$webview->bind('greet', function(string $id, string $args) use ($webview) {
    $data = json_decode($args, true);
    $name = $data[0] ?? 'World';
    $webview->returnValue($id, 0, json_encode("Hello, {$name}! From PHP " . PHP_VERSION));
});

// Push event from PHP -> JS
$webview->emit('statusUpdate', ['message' => 'App initialized']);

$app->run();
```

### Hybrid: Tk Controls + WebView Display

```php
<?php
require_once 'vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Button;
use PhpGui\Widget\Label;
use PhpGui\Widget\WebView;

$app = new Application();
$window = new Window(['title' => 'Control Panel', 'width' => 300, 'height' => 200]);

$label = new Label($window->getId(), ['text' => 'Click to load a page']);
$label->pack(['pady' => 10]);

$webview = new WebView([
    'title' => 'Web Display',
    'width' => 800,
    'height' => 600,
]);
$app->addWebView($webview);

$button = new Button($window->getId(), [
    'text' => 'Load Example.com',
    'command' => function() use ($webview, $label) {
        $webview->navigate('https://example.com');
        $label->setText('Loaded!');
    }
]);
$button->pack(['pady' => 5]);

$app->run();
```

## JavaScript Bridge API

These functions are available in every WebView page:

### `invoke(name, ...args)`
Call a PHP-bound function. Returns a Promise.
```javascript
const result = await invoke('myFunction', arg1, arg2);
```

### `onPhpEvent(event, callback)`
Listen for events pushed from PHP via `$webview->emit()`.
```javascript
onPhpEvent('dataUpdated', function(data) {
    console.log('Received:', data);
});
```

### `window.__phpEmit(event, payload)`
Low-level event dispatch (used internally by `onPhpEvent`). Fires a `CustomEvent` named `php:<event>`.

## Building the Helper Binary

The WebView widget requires a prebuilt helper binary. To build from source:

```bash
# Install dependencies (Linux only)
sudo apt-get install -y cmake libgtk-3-dev libwebkit2gtk-4.1-dev

# Build
cd src/lib/webview_helper
bash build.sh
```

The binary is placed in `src/lib/` as `webview_helper_{platform}_{arch}`.
