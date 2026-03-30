# Entry Widget

The **Entry** widget is a minimal single-line text input field. It uses a Tk `entry` with a bound `textvariable`, so `getValue()` and `setValue()` always reflect the live widget state.

> **Entry vs Input:** `Entry` is the simpler variant — it only accepts a `text` default value as an option. For additional styling (`bg`, `fg`, `font`) or the `onEnter` event, use the [`Input`](Input.md) widget instead.

---

### Constructor

```php
new Entry(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                              |
|-------------|----------|------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.          |
| `$options`  | `array`  | Configuration options — see table below. |

---

### Options

| Key    | Type     | Description                                              |
|--------|----------|----------------------------------------------------------|
| `text` | `string` | Default text pre-filled in the entry. Defaults to `''`. |

---

### Example

```php
use PhpGui\Widget\Entry;

$entry = new Entry($window->getId(), ['text' => 'Default value']);
$entry->pack(['pady' => 10]);

// Read the current value
$value = $entry->getValue();

// Update the value programmatically
$entry->setValue('New value');
```

---

### Methods

| Method       | Signature              | Description                                                  |
|--------------|------------------------|--------------------------------------------------------------|
| `getValue()` | `(): string`           | Reads the current entry content via the bound Tcl variable.  |
| `setValue()` | `(string $value): void`| Writes to the bound Tcl variable, updating the displayed text.|
| `pack()`     | `(array $opts): void`  | Inherited. Pack layout manager.                              |
| `place()`    | `(array $opts): void`  | Inherited. Place layout manager.                             |
| `grid()`     | `(array $opts): void`  | Inherited. Grid layout manager.                              |
| `destroy()`  | `(): void`             | Inherited. Removes the widget.                               |

---

### Notes

- The bound Tcl variable name is the widget's unique ID (from `uniqid('w')`). Both `getValue()` and `setValue()` operate on this variable via `ProcessTCL::getVar()` and `evalTcl("set ...")`.
- The `text` option is applied as the initial variable value via `set {$id} "..."` after the widget is created.
