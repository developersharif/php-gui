<div align="center">

# PHP GUI

**Build native desktop apps with PHP — no Electron, no web server, no compromises.**

[![Latest Release](https://img.shields.io/github/v/release/developersharif/php-gui?style=flat-square)](https://github.com/developersharif/php-gui/releases)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue?style=flat-square&logo=php)](https://php.net)
[![License](https://img.shields.io/github/license/developersharif/php-gui?style=flat-square)](LICENSE)
[![Platform](https://img.shields.io/badge/platform-Linux%20%7C%20macOS%20%7C%20Windows-lightgrey?style=flat-square)](#platform-support)

</div>

---

https://github.com/user-attachments/assets/788e6124-5fe0-49c1-a222-c0bb432f509e

---

PHP GUI gives you two ways to build desktop applications from the same PHP codebase:

| Mode | Best for | Engine |
|------|----------|--------|
| **[Native Widgets](#native-widgets)** | System-style UIs — forms, dialogs, tools | Tcl/Tk via FFI |
| **[WebView](#webview-mode)** | Modern UIs with HTML/CSS/JS (like Tauri, but PHP) | WebKitGTK / WKWebView / WebView2 |

Both modes work on Linux, macOS, and Windows with **zero system dependencies** on Linux — libraries are bundled.

---

## Requirements

| | Minimum |
|---|---|
| PHP | 8.1+ |
| Extension | `ext-ffi` enabled (`ffi.enable=true` in `php.ini`) |
| Composer | any recent version |

**Linux** — no extra packages needed (Tcl/Tk is bundled).  
**macOS** — no extra packages needed.  
**Windows** — no extra packages needed.

> **Enable FFI** if not already on:
> ```ini
> ; php.ini
> extension=ffi
> ffi.enable=true
> ```

---

## Installation

```bash
composer require developersharif/php-gui
```

That's it. No system Tcl/Tk install, no native build steps.

---

## Quick Start

Create `app.php`:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Label;
use PhpGui\Widget\Button;

$app    = new Application();
$window = new Window(['title' => 'Hello PHP GUI', 'width' => 400, 'height' => 250]);

$label = new Label($window->getId(), ['text' => 'Hello, World!']);
$label->pack(['pady' => 20]);

$button = new Button($window->getId(), [
    'text'    => 'Click Me',
    'command' => fn() => $label->setText('You clicked it!'),
]);
$button->pack();

$app->run();
```

```bash
php app.php
```

A native window opens immediately. No compilation, no manifest files, no packaging step.

---

## Native Widgets

Native widgets render as real OS controls using Tcl/Tk under the hood. The PHP API is simple and consistent across all platforms.

### Available Widgets

| Widget | Description | Docs |
|--------|-------------|------|
| `Window` | Main application window | [→](docs/Window.md) |
| `TopLevel` | Secondary window / dialog launcher | [→](docs/TopLevel.md) |
| `Label` | Static or dynamic text display | [→](docs/Label.md) |
| `Button` | Clickable button with callback | [→](docs/Button.md) |
| `Input` / `Entry` | Single-line text field | [→](docs/Input.md) |
| `Checkbutton` | Checkbox with on/off state | [→](docs/Checkbutton.md) |
| `Combobox` | Dropdown selection | [→](docs/Combobox.md) |
| `Frame` | Container for grouping widgets | [→](docs/Frame.md) |
| `Menu` | Menu bar with submenus and commands | [→](docs/Menu.md) |
| `Menubutton` | Standalone menu button | [→](docs/Menubutton.md) |
| `Canvas` | Drawing surface for shapes and images | [→](docs/Canvas.md) |
| `Message` | Multi-line text display | [→](docs/Message.md) |
| `Image` | Display images inside windows | [→](docs/Image.md) |

### Layout

Every widget supports three layout managers. Mix them freely within a window.

```php
// Pack — flow layout (simplest)
$widget->pack(['side' => 'top', 'pady' => 10, 'fill' => 'x']);

// Grid — row/column table
$label->grid(['row' => 0, 'column' => 0, 'sticky' => 'w']);
$input->grid(['row' => 0, 'column' => 1]);

// Place — absolute position
$badge->place(['x' => 20, 'y' => 20]);
```

### Styling

Pass Tcl/Tk options directly in the constructor array or update them at runtime:

```php
$button = new Button($window->getId(), [
    'text'   => 'Save',
    'bg'     => '#4CAF50',
    'fg'     => 'white',
    'font'   => 'Helvetica 14 bold',
    'relief' => 'raised',
    'padx'   => 12,
    'pady'   => 6,
]);

// Update at runtime
$button->setBackground('#2196F3');
$label->setText('Saved!');
```

Common options: `bg`, `fg`, `font`, `relief` (`flat` `raised` `sunken` `groove` `ridge`), `padx`, `pady`, `width`, `height`, `cursor`.

### Dialogs

`TopLevel` provides native system dialogs — no extra packages:

```php
// File picker
$file = TopLevel::getOpenFile();

// Directory picker
$dir = TopLevel::chooseDirectory();

// Color picker
$color = TopLevel::chooseColor();

// Message box — returns 'ok', 'cancel', 'yes', 'no'
$result = TopLevel::messageBox('Are you sure?', 'yesno');
```

### Menus

```php
$menu     = new Menu($window->getId(), ['type' => 'main']);
$fileMenu = $menu->addSubmenu('File');

$fileMenu->addCommand('New',  fn() => newFile());
$fileMenu->addCommand('Open', fn() => openFile());
$fileMenu->addSeparator();
$fileMenu->addCommand('Exit', fn() => exit(), ['foreground' => 'red']);

$editMenu = $menu->addSubmenu('Edit');
$editMenu->addCommand('Copy',  fn() => copy());
$editMenu->addCommand('Paste', fn() => paste());
```

### Complete Example

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\{Window, Label, Button, Input, Menu, TopLevel};

$app    = new Application();
$window = new Window(['title' => 'Demo', 'width' => 500, 'height' => 400]);

// Menu bar
$menu     = new Menu($window->getId(), ['type' => 'main']);
$fileMenu = $menu->addSubmenu('File');
$fileMenu->addCommand('Open', function () use (&$status) {
    $file = TopLevel::getOpenFile();
    if ($file) $status->setText("Opened: " . basename($file));
});
$fileMenu->addSeparator();
$fileMenu->addCommand('Exit', fn() => exit());

// Input + button
$input = new Input($window->getId(), ['text' => 'Type something...']);
$input->pack(['pady' => 10, 'padx' => 20, 'fill' => 'x']);

$status = new Label($window->getId(), ['text' => 'Ready', 'fg' => '#666']);
$status->pack(['pady' => 5]);

$btn = new Button($window->getId(), [
    'text'    => 'Submit',
    'bg'      => '#2196F3',
    'fg'      => 'white',
    'command' => function () use ($input, $status) {
        $status->setText('You typed: ' . $input->getValue());
    },
]);
$btn->pack(['pady' => 10]);

$app->run();
```

---

## WebView Mode

WebView lets you build the UI with HTML, CSS, and JavaScript while keeping all your business logic in PHP. Think of it as [Tauri](https://tauri.app) for PHP.

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\WebView;

$app = new Application();
$wv  = new WebView(['title' => 'My App', 'width' => 900, 'height' => 600]);

$wv->setHtml('<h1 style="font-family:sans-serif">Hello from PHP + HTML!</h1>');

$wv->onClose(fn() => $app->quit());
$app->addWebView($wv);
$app->run();
```

### PHP ↔ JavaScript Bridge

**JS → PHP** — call PHP functions from the browser:

```php
// PHP: register a handler
$wv->bind('getUser', function (string $reqId, string $args) use ($wv): void {
    $id   = json_decode($args, true)[0];
    $user = getUserFromDatabase($id);
    $wv->returnValue($reqId, 0, json_encode($user));
});
```

```javascript
// JavaScript: call it like a local function
const user = await invoke('getUser', 42);
console.log(user.name);
```

**PHP → JS** — push events to the frontend:

```php
// PHP: emit an event
$wv->emit('orderUpdated', ['id' => 99, 'status' => 'shipped']);
```

```javascript
// JavaScript: listen for it
onPhpEvent('orderUpdated', (order) => {
    document.getElementById('status').textContent = order.status;
});
```

### Serving a Frontend App

Load a built frontend (React, Vue, Svelte, Vanilla — anything) directly from disk. No HTTP server, no open ports, no firewall prompts:

```php
$wv->serveFromDisk(__DIR__ . '/frontend/dist');
```

| Platform | Mechanism | URL |
|----------|-----------|-----|
| Linux | `phpgui://` custom URI scheme | `phpgui://app/index.html` |
| Windows | WebView2 virtual hostname | `https://phpgui.localhost/` |
| macOS | `loadFileURL:allowingReadAccess:` | `file:///path/to/dist/` |

### Vite Dev + Production in One Line

`serveVite()` auto-detects whether the dev server is running:

```php
// In dev: hot-reloads via the Vite dev server (HMR works)
// In prod: loads dist/ from disk — no server needed
$wv->serveVite(__DIR__ . '/frontend/dist');
```

Recommended `vite.config.js` for cross-platform builds:

```js
export default {
  base: './',        // required for macOS file:// serving
  build: { outDir: 'dist' },
}
```

### Bypass CORS — Transparent Fetch Proxy

Cross-origin API calls fail from `phpgui://` / `file://` origins. One call routes all `fetch()` requests through PHP:

```php
$wv->enableFetchProxy();  // add this before serveFromDisk() / serveVite()
```

```javascript
// Works identically on all platforms, no changes to your frontend code
const data = await fetch('https://api.example.com/data').then(r => r.json());
```

### Full Vite App Example

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\WebView;

$app = new Application();
$wv  = new WebView(['title' => 'My Vite App', 'width' => 1024, 'height' => 768]);

$wv->enableFetchProxy();
$wv->serveVite(__DIR__ . '/frontend/dist');

// Expose a PHP function to JavaScript
$wv->bind('readFile', function (string $reqId, string $args) use ($wv): void {
    $path    = json_decode($args, true)[0];
    $content = is_file($path) ? file_get_contents($path) : null;
    $wv->returnValue($reqId, 0, json_encode($content));
});

$wv->onClose(fn() => $app->quit());
$app->addWebView($wv);
$app->run();
```

> See the full [WebView documentation →](docs/WebView.md)

---

## Platform Support

| Platform | Native Widgets | WebView | Notes |
|----------|:--------------:|:-------:|-------|
| Linux (x86-64) | ✅ | ✅ | Tcl/Tk bundled. WebView needs `libwebkit2gtk-4.1-dev` |
| Linux (ARM64) | ✅ | — | Tcl/Tk bundled |
| macOS | ✅ | ✅ | No extra dependencies |
| Windows | ✅ | ✅ | No extra dependencies |

**Linux WebView dependency:**
```bash
sudo apt install libwebkit2gtk-4.1-dev   # Debian / Ubuntu
sudo dnf install webkit2gtk4.1-devel     # Fedora / RHEL
```

---

## Documentation

| Guide | |
|---|---|
| [Getting Started](docs/getting-started.md) | FFI setup, first app, layout, events |
| [Architecture](docs/architecture.md) | How the FFI bridge and event loop work |
| [WebView](docs/WebView.md) | Full WebView API reference |

**Widget reference:**
[Window](docs/Window.md) · [Button](docs/Button.md) · [Label](docs/Label.md) · [Input](docs/Input.md) · [Entry](docs/Entry.md) · [Checkbutton](docs/Checkbutton.md) · [Combobox](docs/Combobox.md) · [Frame](docs/Frame.md) · [Canvas](docs/Canvas.md) · [Menu](docs/Menu.md) · [Menubutton](docs/Menubutton.md) · [TopLevel](docs/TopLevel.md) · [Message](docs/Message.md)

---

## License

[MIT](LICENSE)
