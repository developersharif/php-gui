# Input Widget

The **Input** widget is a single-line editable text field. It uses a Tk `entry` widget internally with a bound `textvariable`, which means `getValue()` and `setValue()` always reflect the current content. It also supports an `onEnter` event that fires when the user presses **Enter**.

---

### Constructor

```php
new Input(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                              |
|-------------|----------|------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.          |
| `$options`  | `array`  | Configuration options — see table below. |

---

### Options

| Key    | Type     | Description                                                        |
|--------|----------|--------------------------------------------------------------------|
| `text` | `string` | Default text pre-filled in the field. Defaults to `''`.            |
| `bg`   | `string` | Background color of the input field.                               |
| `fg`   | `string` | Foreground (text) color.                                           |
| `font` | `string` | Font string, e.g. `'Arial 14'`.                                    |
| `show` | `string` | If set to `'*'`, masks characters (useful for password fields).    |

Any option other than `text` and `command` is forwarded as a Tk `-key "value"` pair to the underlying `entry` command.

---

### Examples

**Basic input with Enter key handler:**
```php
use PhpGui\Widget\Input;

$input = new Input($window->getId(), ['text' => 'Type here...']);
$input->pack(['pady' => 10]);

$input->onEnter(function () use ($input) {
    echo "Entered: " . $input->getValue() . "\n";
});
```

**Styled input:**
```php
$input = new Input($window->getId(), [
    'text' => 'Type here...',
    'bg'   => 'lightyellow',
    'fg'   => 'black',
    'font' => 'Arial 14'
]);
$input->pack(['pady' => 10]);
```

**Reading and writing the value programmatically:**
```php
$input->setValue('Hello!');
$current = $input->getValue(); // returns 'Hello!'
```

---

### Methods

| Method       | Signature                      | Description                                                      |
|--------------|--------------------------------|------------------------------------------------------------------|
| `getValue()` | `(): string`                   | Reads current field content via a Tcl variable evaluation.      |
| `setValue()` | `(string $text): void`         | Sets the field content by updating the bound Tcl variable.       |
| `onEnter()`  | `(callable $callback): void`   | Registers a PHP callback for the `<Return>` key binding.         |
| `pack()`     | `(array $opts = []): void`     | Inherited. Pack layout manager.                                  |
| `place()`    | `(array $opts = []): void`     | Inherited. Place layout manager.                                 |
| `grid()`     | `(array $opts = []): void`     | Inherited. Grid layout manager.                                  |
| `destroy()`  | `(): void`                     | Inherited. Removes the widget.                                   |

---

### Notes

- `getValue()` internally runs `set _val [set {$id}]` and then reads the result — this guarantees the live widget value is returned.
- `onEnter()` binds the `<Return>` event via `ProcessTCL::registerCallback()` and Tk's `bind` command.
- Both `Input` and `Entry` wrap a Tk `entry`. The key difference is that `Input` accepts additional style options (`bg`, `fg`, `font`, etc.) and exposes `onEnter()`.
