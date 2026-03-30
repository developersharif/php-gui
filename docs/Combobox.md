# Combobox Widget

The **Combobox** widget renders a `ttk::combobox` — a dropdown list combined with a text field. The list values are space-separated and bound to a Tcl variable so `getValue()` and `setValue()` always reflect the live selection.

---

### Constructor

```php
new Combobox(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                              |
|-------------|----------|------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.          |
| `$options`  | `array`  | Configuration options — see table below. |

---

### Options

| Key      | Type     | Description                                                                          |
|----------|----------|--------------------------------------------------------------------------------------|
| `values` | `string` | **Space-separated** list of items shown in the dropdown. Defaults to `''` (empty).  |

---

### Example

```php
use PhpGui\Widget\Combobox;

$combo = new Combobox($window->getId(), [
    'values' => 'Option1 Option2 Option3'
]);
$combo->pack(['pady' => 10]);

// Read the currently selected / typed value
$selected = $combo->getValue();

// Pre-select a value programmatically
$combo->setValue('Option2');
```

---

### Methods

| Method       | Signature              | Description                                                           |
|--------------|------------------------|-----------------------------------------------------------------------|
| `getValue()` | `(): string`           | Returns the current value via `ProcessTCL::getVar($id)`.              |
| `setValue()` | `(string $value): void`| Sets the bound Tcl variable, updating both the text field and dropdown selection. |
| `pack()`     | `(array $opts): void`  | Inherited. Pack layout manager.                                       |
| `place()`    | `(array $opts): void`  | Inherited. Place layout manager.                                      |
| `grid()`     | `(array $opts): void`  | Inherited. Grid layout manager.                                       |
| `destroy()`  | `(): void`             | Inherited. Removes the widget.                                        |

---

### Notes

- The `values` string is passed directly as a Tcl list `{Option1 Option2 Option3}`. Each space separates an item. If an option contains spaces, you must wrap it in curly braces within the string (e.g. `'{First Item} {Second Item}'`).
- The combobox uses `ttk::combobox`, which is part of the themed Tk widgets (available in Tk 8.5+). No separate `package require` is needed beyond `package require Tk`.
- The `-textvariable` is the widget's unique ID, so `getValue()` simply reads that variable.
