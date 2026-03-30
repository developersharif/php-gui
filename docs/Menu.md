# Menu Widget

The **Menu** widget creates a Tk menu bar or submenu. It supports two modes controlled by the `type` option: `'main'` (attached to the window's menu bar) and `'normal'`/`'submenu'` (nested menus). Tear-off is disabled (`-tearoff 0`) by default.

---

### Constructor

```php
new Menu(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                              |
|-------------|----------|------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget — usually a `Window` ID for the main menu. |
| `$options`  | `array`  | Configuration options — see table below. |

---

### Options

| Key    | Type     | Default    | Description                                                                      |
|--------|----------|------------|----------------------------------------------------------------------------------|
| `type` | `string` | `'normal'` | `'main'` attaches the menu to the parent window's menu bar. Other values create a standalone or nested menu. |

---

### Examples

**Full menu bar with submenus and separators (from `example.php`):**
```php
use PhpGui\Widget\Menu;

// Create the main menu bar and attach it to the window
$mainMenu = new Menu($window->getId(), ['type' => 'main']);

// File submenu
$fileMenu = $mainMenu->addSubmenu('File');
$fileMenu->addCommand('New', function () use ($label) {
    $label->setText('New File Selected');
});
$fileMenu->addCommand('Open', function () use ($label) {
    $label->setText('Open Selected');
});
$fileMenu->addSeparator();
$fileMenu->addCommand('Exit', function () {
    exit();
}, ['foreground' => 'red']);

// Edit submenu
$editMenu = $mainMenu->addSubmenu('Edit');
$editMenu->addCommand('Copy', function () { /* ... */ });
$editMenu->addCommand('Paste', function () { /* ... */ });

// Nested submenu (Help > About > Version)
$helpMenu = $mainMenu->addSubmenu('Help');
$aboutMenu = $helpMenu->addSubmenu('About');
$aboutMenu->addCommand('Version', function () use ($label) {
    $label->setText('Version 1.0');
});
```

---

### Methods

| Method          | Signature                                                        | Returns | Description                                                                 |
|-----------------|------------------------------------------------------------------|---------|-----------------------------------------------------------------------------|
| `addCommand()`  | `(string $label, callable $cb = null, array $opts = []): void`  | —       | Adds a clickable menu item. `$opts` are forwarded as Tk item options (e.g. `['foreground' => 'red']`). |
| `addSubmenu()`  | `(string $label, array $opts = []): Menu`                        | `Menu`  | Creates and returns a new `Menu` instance nested under this one.            |
| `addSeparator()`| `(): void`                                                        | —       | Adds a horizontal separator line.                                           |
| `destroy()`     | `(): void`                                                        | —       | Overrides parent — destroys `.{id}` (not `.{parentId}.{id}`) since menus are registered differently. |

---

### Notes

- When `type = 'main'`, the menu is created at `.{id}` and attached to the parent window via `.{parentId} configure -menu .{id}`.
- `addSubmenu()` internally creates a new `Menu` with `type = 'submenu'` and adds it as a `cascade` entry.
- Each `addCommand()` callback is auto-registered via `ProcessTCL::registerCallback()` using a unique ID composed of the menu ID and item index.
- `addCommand()` extra options (third argument) are merged as `-key "value"` pairs alongside the label and command. Example use: setting `foreground` to red for a destructive entry.
