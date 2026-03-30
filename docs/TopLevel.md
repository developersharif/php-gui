# TopLevel Widget

The **TopLevel** widget creates a secondary top-level window (separate from the main `Window`). It is also the home of all native dialog helpers — color picker, file browser, directory browser, message box, and custom dialog — available as static methods callable from anywhere.

---

### Constructor

```php
new TopLevel(array $options = [])
```

`TopLevel` has **no parent** — it creates a Tk `toplevel` directly at `.{id}`.

| Option   | Type     | Description                                              |
|----------|----------|----------------------------------------------------------|
| `title`  | `string` | Window title. Set via `wm title`.                        |
| `width`  | `int`    | Width in pixels. Combined with `height` for `wm geometry`.|
| `height` | `int`    | Height in pixels.                                        |

All other options are forwarded as `-key "value"` to the `toplevel` command.

---

### Examples

**Opening a secondary window from a button:**
```php
use PhpGui\Widget\TopLevel;
use PhpGui\Widget\Button;
use PhpGui\Widget\Label;

$btn = new Button($window->getId(), [
    'text'    => 'Open New Window',
    'command' => function () {
        $top = new TopLevel([
            'title'  => 'Settings',
            'width'  => 300,
            'height' => 200,
        ]);

        $label = new Label($top->getId(), ['text' => 'Settings Window']);
        $label->pack(['pady' => 20]);

        $closeBtn = new Button($top->getId(), [
            'text'    => 'Close',
            'command' => function () use ($top) {
                $top->destroy();
            }
        ]);
        $closeBtn->pack(['pady' => 10]);

        $minimizeBtn = new Button($top->getId(), [
            'text'    => 'Minimize',
            'command' => function () use ($top) {
                $top->iconify();
            }
        ]);
        $minimizeBtn->pack(['pady' => 10]);
    }
]);
$btn->pack(['pady' => 10]);
```

---

### Instance Methods

| Method            | Signature                                                    | Description                                                                |
|-------------------|--------------------------------------------------------------|----------------------------------------------------------------------------|
| `setTitle()`      | `(string $title): void`                                      | Updates the window title via `wm title`.                                   |
| `setGeometry()`   | `(int $w, int $h, ?int $x, ?int $y): void`                  | Sets size and optionally position (`WxH+X+Y` format).                      |
| `iconify()`       | `(): void`                                                   | Minimises the window to the taskbar (`wm iconify`).                        |
| `deiconify()`     | `(): void`                                                   | Restores the window from minimised state (`wm deiconify`).                 |
| `withdraw()`      | `(): void`                                                   | Hides the window without minimising it (`wm withdraw`).                    |
| `focus()`         | `(): void`                                                   | Brings the window to the foreground.                                        |
| `setResizable()`  | `(bool $w, bool $h): void`                                   | Controls whether the user can resize the window in each direction.          |
| `setMinsize()`    | `(int $w, int $h): void`                                     | Sets the minimum allowed size.                                              |
| `setMaxsize()`    | `(int $w, int $h): void`                                     | Sets the maximum allowed size.                                              |
| `getText()`       | `(): string`                                                 | Reads `-text` from the child widget at `.{id}.child` (internal use).        |
| `setText()`       | `(string $text): void`                                       | Sets `-text` on `.{id}.child configure` (internal use).                    |
| `destroy()`       | `(): void`                                                   | Destroys the window at `.{id}` (overrides default path).                   |
| `popupMenu()`     | `(int $x, int $y): string`                                   | Shows a transient popup menu at screen coordinates and returns the result.  |
| `dialog()`        | `(string $title, string $msg, string $icon, string $opt1, string $opt2, string $extra): string` | Shows a `tk_dialog` and returns the clicked option string. |

---

### Static Dialog Methods

These can be called without a `TopLevel` instance — they use `ProcessTCL::getInstance()` directly:

```php
// Color picker — returns a hex color string or null if cancelled
$color = TopLevel::chooseColor('#ff0000');

// File open dialog — returns the selected path or null
$file = TopLevel::getOpenFile('/home/user/documents');

// File save dialog — returns the path entered or null
$save = TopLevel::getSaveFile('/home/user/documents');

// Directory picker — returns the selected directory or null
$dir = TopLevel::chooseDirectory('/home/user');

// Message box — returns the clicked button label ('ok', 'cancel', 'yes', 'no', etc.)
$result = TopLevel::messageBox('Are you sure?', 'okcancel');
```

| Static Method       | Signature                                          | Returns         |
|---------------------|----------------------------------------------------|-----------------|
| `chooseColor()`     | `(string $initialColor = 'red'): ?string`          | hex string or `null` |
| `getOpenFile()`     | `(string $initialDir = '.'): ?string`              | file path or `null`  |
| `getSaveFile()`     | `(string $initialDir = '.'): ?string`              | file path or `null`  |
| `chooseDirectory()` | `(string $initialDir = '.'): ?string`              | dir path or `null`   |
| `messageBox()`      | `(string $message, string $type = 'ok'): string`   | button label string  |

`$type` for `messageBox()` accepts: `ok`, `okcancel`, `yesno`, `yesnocancel`, `retrycancel`, `abortretryignore`.

---

### Notes

- All static dialogs return `null` (or empty string for `messageBox`) if the user cancels.
- All static methods call `update idletasks` after the dialog closes to keep the UI responsive.
- `TopLevel` passes `null` as `$parentId` to `AbstractWidget` — its Tk path is `.{id}`, not `.{parent}.{id}`. That is why both `destroy()` and `popupMenu()` override the default path logic.
