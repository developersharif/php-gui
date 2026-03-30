# Menubutton Widget

The **Menubutton** widget creates a button in the UI that, when clicked, drops down a menu. Internally it creates a Tk `menubutton` and automatically wires an associated `menu` widget (with `-tearoff 0`) to it.

---

### Constructor

```php
new Menubutton(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                              |
|-------------|----------|------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.          |
| `$options`  | `array`  | Configuration options — see table below. |

---

### Options

| Key    | Type     | Default        | Description                                 |
|--------|----------|----------------|---------------------------------------------|
| `text` | `string` | `'Menubutton'` | Label shown on the button.                  |

---

### Example

```php
use PhpGui\Widget\Menubutton;

$mb = new Menubutton($window->getId(), ['text' => 'Options']);
$mb->pack(['pady' => 10]);
```

> **Note:** `Menubutton` creates its associated `menu` at `.{parentId}.m_{id}` automatically. If you need to populate it with items, you currently need to interact directly with the underlying Tcl layer (e.g. via `ProcessTCL::getInstance()->evalTcl()`). For full item control, use the [`Menu`](Menu.md) widget instead.

---

### Methods

| Method      | Signature             | Description                                               |
|-------------|-----------------------|-----------------------------------------------------------|
| `getId()`   | `(): string`          | Returns the widget ID (inherited).                        |
| `pack()`    | `(array $opts): void` | Inherited. Pack layout manager.                           |
| `place()`   | `(array $opts): void` | Inherited. Place layout manager.                          |
| `grid()`    | `(array $opts): void` | Inherited. Grid layout manager.                           |
| `destroy()` | `(): void`            | Inherited. Removes the menubutton widget.                 |

---

### Notes

- Created Tk paths: the button is `.{parentId}.{id}` and its menu is `.{parentId}.m_{id}`.
- The associated menu is configured on the button automatically via `configure -menu`.
- This widget is a lightweight wrapper. For a fully-featured menu bar with cascades, separators, and PHP callbacks use [`Menu`](Menu.md).
