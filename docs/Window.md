# Window Widget

The **Window** widget is the primary application window. It is always the first widget you create and acts as the `$parentId` for all other widgets placed inside it. Internally it creates a Tk `toplevel` window and hooks into the application's quit mechanism.

---

### Constructor

```php
new Window(array $options = [])
```

`Window` takes **no parent** — pass options directly. Default values: `title = 'Window'`, `width = 300`, `height = 200`.

| Option   | Type     | Default     | Description                              |
|----------|----------|-------------|------------------------------------------|
| `title`  | `string` | `'Window'`  | Title bar text.                          |
| `width`  | `int`    | `300`       | Window width in pixels.                  |
| `height` | `int`    | `200`       | Window height in pixels.                 |

---

### Example

```php
use PhpGui\Widget\Window;

$window = new Window([
    'title'  => 'My App',
    'width'  => 800,
    'height' => 600,
]);
```

After creating the window, use `$window->getId()` as the `$parentId` for all child widgets:

```php
use PhpGui\Widget\Label;

$label = new Label($window->getId(), ['text' => 'Hello, World!']);
$label->pack(['pady' => 20]);
```

---

### Methods

| Method       | Signature            | Description                                      |
|--------------|----------------------|--------------------------------------------------|
| `getId()`    | `(): string`         | Returns the unique widget ID (inherited).        |
| `getTcl()`   | `(): ProcessTCL`     | Returns the underlying `ProcessTCL` instance.   |
| `pack()`     | `(array $opts): void`| Pack layout (inherited — throws on top-level).  |
| `destroy()`  | `(): void`           | Removes the window from Tk (inherited).          |

> **Note:** `pack()`, `place()`, and `grid()` will throw a `RuntimeException` on `Window` because it has no parent. Use the geometry managers on **child** widgets only.

---

### Notes

- The window registers a `WM_DELETE_WINDOW` protocol handler that calls `::exit_app`, which signals the `Application` event loop to stop cleanly.
- `wm deiconify` is called automatically on creation, so the window appears immediately.
- `Window` passes `null` as the parent to `AbstractWidget`. Its Tk path is `.{id}` (e.g. `.w63f1a2b`).
