# Installation & Getting Started

## Requirements

- **PHP 8.1+** with the [FFI](https://www.php.net/manual/en/book.ffi.php) extension enabled
- **[Composer](https://getcomposer.org/)** for dependency management

### Enabling FFI

Make sure FFI is enabled in your `php.ini`:

```ini
extension=ffi
ffi.enable=true
```

You can verify FFI is available:

```bash
php -m | grep FFI
```

## Installation

Install via Composer:

```bash
composer require developersharif/php-gui
```

## Your First Application

Create a file called `app.php`:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Label;
use PhpGui\Widget\Button;

// Initialize the application
$app = new Application();

// Create the main window
$window = new Window([
    'title'  => 'My First PHP GUI App',
    'width'  => 400,
    'height' => 300
]);

// Add a label
$label = new Label($window->getId(), [
    'text' => 'Welcome to PHP GUI!'
]);
$label->pack(['pady' => 20]);

// Add a button with a click handler
$button = new Button($window->getId(), [
    'text' => 'Click Me',
    'command' => function() use ($label) {
        $label->setText('You clicked the button!');
    }
]);
$button->pack(['pady' => 10]);

// Run the event loop
$app->run();
```

Run your application:

```bash
php app.php
```

## Layout Management

Widgets support three layout managers: `pack`, `grid`, and `place`.

### Pack

Arranges widgets in blocks before placing them in the parent widget:

```php
$label->pack(['side' => 'top', 'pady' => 10, 'padx' => 5]);
```

### Grid

Places widgets in a 2D grid:

```php
$label->grid(['row' => 0, 'column' => 0]);
$button->grid(['row' => 1, 'column' => 0]);
```

### Place

Positions widgets at absolute coordinates:

```php
$label->place(['x' => 50, 'y' => 100]);
```

## Styling Widgets

Most widgets accept styling options:

```php
$button = new Button($window->getId(), [
    'text' => 'Styled Button',
    'bg'   => 'blue',
    'fg'   => 'white',
    'font' => 'Helvetica 16 bold'
]);
```

Common styling options:
- `bg` — Background color
- `fg` — Foreground (text) color
- `font` — Font specification (family, size, style)
- `relief` — Border style (`flat`, `raised`, `sunken`, `groove`, `ridge`)
- `padx`, `pady` — Internal padding

## Event Handling

Interactive widgets support PHP callbacks:

```php
// Button click
$button = new Button($parent, [
    'text' => 'Click',
    'command' => function() {
        echo "Clicked!\n";
    }
]);

// Input enter key
$input = new Input($parent, ['text' => 'Type here...']);
$input->onEnter(function() use ($input) {
    echo "Entered: " . $input->getValue() . "\n";
});
```

## Next Steps

- [Architecture](architecture.md) — Learn how the FFI bridge works
- [Window](Window.md) — Start with the main window widget
- [Full Example](example.md) — See a complete application
