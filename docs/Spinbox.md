# Spinbox Widget

The **Spinbox** widget is a bounded numeric (or enumerated) input with up/down stepper buttons. Wraps Tk's `ttk::spinbox`. _New in v1.9._

Two modes:
- **Numeric range** — pass `from` / `to` / `increment`. The user can type or click up/down to step within the range.
- **Enumeration** — pass `values` as a list of strings; up/down cycles through them. Mutually exclusive with the numeric range options.

---

### Constructor

```php
new Spinbox(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                              |
|-------------|----------|------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.          |
| `$options`  | `array`  | Configuration options — see below.       |

---

### Options

| Key         | Type             | Description                                                           |
|-------------|------------------|-----------------------------------------------------------------------|
| `from`      | `int`/`float`    | Lower bound (numeric mode).                                          |
| `to`        | `int`/`float`    | Upper bound.                                                          |
| `increment` | `int`/`float`    | Step size (default `1`).                                             |
| `values`    | `list<string>`   | Enumeration mode — list of selectable strings.                        |
| `value`     | `string`/`int`   | Initial value. Falls back to `from` (numeric) or first item (enum).   |
| `width`     | `int`            | Width in average characters.                                          |
| `format`    | `string`         | `printf`-style format applied to displayed value (e.g. `"%05.2f"`).  |
| `wrap`      | `bool`           | Whether stepping past the bounds wraps around.                        |

Any other option is forwarded to `ttk::spinbox` (run through safe-quoting).

---

### Examples

**Numeric quantity input:**
```php
use PhpGui\Widget\Spinbox;

$qty = new Spinbox($window->getId(), [
    'from'      => 1,
    'to'        => 99,
    'increment' => 1,
    'value'     => 1,
]);
$qty->pack();

$qty->onChange(function (string $v) {
    echo "qty = {$v}\n";
});
```

**Enumeration:**
```php
$priority = new Spinbox($window->getId(), [
    'values' => ['low', 'medium', 'high', 'critical'],
    'value'  => 'medium',
]);
$priority->pack();
```

---

### Methods

| Method                | Signature                | Description                                                                                       |
|-----------------------|--------------------------|---------------------------------------------------------------------------------------------------|
| `getValue()`          | `(): string`             | Raw current value (preserves leading zeros, formatting).                                          |
| `getNumericValue()`   | `(): float`              | `getValue()` cast to float — convenient for numeric mode.                                         |
| `setValue()`          | `(string $value): void`  | Update programmatically. Fires `onChange` if registered.                                          |
| `onChange()`          | `(callable $h): void`    | `$h(string $value)` fires on user typing (committed via Enter or focus-out), the up/down buttons, and programmatic `setValue()`. |
| `pack/place/grid`     | `(array $opts = []): void`| Inherited.                                                                                        |
| `destroy()`           | `(): void`               | Removes the widget and frees the change callback.                                                 |

---

### Notes

- `getValue()` returns a string deliberately — it preserves user input that wouldn't survive a numeric round-trip (e.g. `"007"`). Use `getNumericValue()` when you need a `float`.
- For enumeration mode, every entry is run through the safe-quoting helper, so list items can contain Tcl-special characters without breaking parsing.
- Tk's `-command` fires only for the up/down buttons. To also catch direct typing the framework binds `<Return>` and `<FocusOut>` to the same handler — so the user pressing Enter or tabbing out triggers `onChange`.
