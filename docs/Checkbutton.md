# Checkbutton Widget

The **Checkbutton** widget renders a labelled checkbox. It uses a Tk `checkbutton` linked to an internal Tcl variable (`cb_var_{id}`) so the checked state is always readable and writable from PHP.

---

### Constructor

```php
new Checkbutton(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                              |
|-------------|----------|------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.          |
| `$options`  | `array`  | Configuration options — see table below. |

---

### Options

| Key       | Type       | Description                                                              |
|-----------|------------|--------------------------------------------------------------------------|
| `text`    | `string`   | Label shown next to the checkbox. Defaults to `'Checkbutton'`.           |
| `command` | `callable` | PHP closure called each time the checkbox is toggled.                    |
| `bg`      | `string`   | Background color.                                                        |
| `fg`      | `string`   | Foreground (text) color.                                                 |
| `font`    | `string`   | Font string, e.g. `'Arial 12'`.                                          |

Options other than `text` and `command` are forwarded as Tk `-key "value"` pairs.

---

### Examples

**Basic checkbox:**
```php
use PhpGui\Widget\Checkbutton;

$check = new Checkbutton($window->getId(), ['text' => 'Accept Terms']);
$check->pack(['pady' => 5]);

// Read state
echo $check->isChecked() ? 'Checked' : 'Unchecked';
```

**Checkbox with toggle callback:**
```php
$check = new Checkbutton($window->getId(), [
    'text'    => 'Enable notifications',
    'command' => function () use ($check) {
        echo $check->isChecked() ? "ON\n" : "OFF\n";
    }
]);
$check->pack(['pady' => 5]);
```

**Setting state programmatically:**
```php
$check->setChecked(true);   // check it
$check->setChecked(false);  // uncheck it
$check->toggle();           // flip current state
```

---

### Methods

| Method          | Signature               | Description                                             |
|-----------------|-------------------------|---------------------------------------------------------|
| `setChecked()`  | `(bool $state): void`   | Sets the internal Tcl variable to `1` (true) or `0` (false). |
| `isChecked()`   | `(): bool`              | Reads the internal Tcl variable and returns `true`/`false`.  |
| `toggle()`      | `(): void`              | Calls `setChecked(!isChecked())` to flip the current state.  |
| `pack()`        | `(array $opts): void`   | Inherited. Pack layout manager.                         |
| `place()`       | `(array $opts): void`   | Inherited. Place layout manager.                        |
| `grid()`        | `(array $opts): void`   | Inherited. Grid layout manager.                         |
| `destroy()`     | `(): void`              | Inherited. Removes the widget.                          |

---

### Notes

- The internal Tcl variable is initialised to `0` on creation.
- The `command` closure is registered via `ProcessTCL::registerCallback()` and fired via `php::executeCallback` from the Tk side.
- Unlike `Button`, the callback for `Checkbutton` is **not** followed by an `update` call — the Tcl variable change itself triggers the visual update.
