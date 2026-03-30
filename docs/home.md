# PHP GUI with Tcl/Tk

A robust cross-platform GUI toolkit for PHP that leverages Tcl/Tk via PHP's FFI extension. Build desktop applications in PHP with an intuitive, object-oriented API.

## Features

- **Cross-platform** — Works on Linux, Windows, and macOS
- **Zero dependencies** — Tcl/Tk libraries are bundled, no system packages needed
- **Modern PHP** — Requires PHP 8.1+ with FFI extension
- **Simple API** — Clean widget classes that abstract Tcl/Tk complexity
- **Event-driven** — PHP callbacks for interactive widgets
- **14 Widgets** — Window, Button, Label, Input, Entry, Frame, Canvas, Menu, Menubutton, Checkbutton, Combobox, Message, TopLevel, and Image

## Quick Start

```bash
composer require developersharif/php-gui
```

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Label;
use PhpGui\Widget\Button;

$app = new Application();
$window = new Window(['title' => 'My App', 'width' => 500, 'height' => 300]);

$label = new Label($window->getId(), ['text' => 'Hello, PHP GUI!']);
$label->pack(['pady' => 20]);

$button = new Button($window->getId(), [
    'text' => 'Click Me',
    'command' => function() use ($label) {
        $label->setText('Button clicked!');
    }
]);
$button->pack(['pady' => 10]);

$app->run();
```

## Supported Platforms

| Platform | Status |
|----------|--------|
| Linux    | ✅ Supported |
| Windows  | ✅ Supported |
| macOS    | ✅ Supported |

## Next Steps

- [Installation & Setup](getting-started.md) — Detailed installation guide
- [Architecture](architecture.md) — How the library works under the hood
- [Widgets](Window.md) — Explore all available widgets
- [Full Example](example.md) — Complete application example
