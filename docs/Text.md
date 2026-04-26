# Text Widget

The **Text** widget is a multi-line editable text area, backed by Tk's `text` command. Use it for editors, log views, message composers, code panes — anywhere a single-line `Input` is too small. _New in v1.9._

To get scrollbars, pair with [`Scrollbar`](Scrollbar.md) — `text` widgets do not display them on their own.

---

### Constructor

```php
new Text(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                                  |
|-------------|----------|----------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.              |
| `$options`  | `array`  | Configuration options — see below.           |

---

### Options

| Key      | Type     | Description                                                              |
|----------|----------|--------------------------------------------------------------------------|
| `text`   | `string` | Initial content. Defaults to empty.                                      |
| `width`  | `int`    | Width in characters.                                                     |
| `height` | `int`    | Height in lines.                                                         |
| `wrap`   | `string` | `none` (no wrap), `char` (default), or `word`.                           |
| `bg`     | `string` | Background color.                                                        |
| `fg`     | `string` | Foreground color.                                                        |
| `font`   | `string` | Font specification, e.g. `'Courier 11'`.                                 |
| `state`  | `string` | `normal` (editable, default) or `disabled` (read-only).                  |

Any other key is forwarded as a Tk `-key value` pair, run through the safe-quoting helper.

---

### Examples

**Editable composer:**
```php
use PhpGui\Widget\Text;

$compose = new Text($window->getId(), [
    'width'  => 60,
    'height' => 12,
    'wrap'   => 'word',
    'font'   => 'Helvetica 11',
]);
$compose->pack(['fill' => 'both', 'expand' => 1]);
```

**Read-only log view that still accepts `append()`:**
```php
$log = new Text($window->getId(), ['height' => 20, 'font' => 'Courier 10']);
$log->setState('disabled');                  // user can't type
$log->append("[INFO] server started\n");     // append() toggles state automatically
$log->append("[WARN] low disk space\n");
```

**With a scrollbar:**
```php
use PhpGui\Widget\{Frame, Text, Scrollbar};

$frame = new Frame($window->getId());
$frame->pack(['fill' => 'both', 'expand' => 1]);

$text = new Text($frame->getId(), ['width' => 50, 'height' => 10]);
$text->pack(['side' => 'left', 'fill' => 'both', 'expand' => 1]);

Scrollbar::attachTo($text, 'vertical');
```

---

### Methods

| Method            | Signature                          | Description                                                                                  |
|-------------------|------------------------------------|----------------------------------------------------------------------------------------------|
| `setText()`       | `(string $text): void`             | Replace all content. Honours disabled state by toggling around the operation.                |
| `getText()`       | `(): string`                       | Returns the current buffer contents (Tk's trailing newline is stripped).                     |
| `append()`        | `(string $text): void`             | Append at the end. Works on disabled widgets.                                                |
| `insertAt()`      | `(string $index, string $text): void` | Insert at a Tk text index: `'1.0'`, `'end'`, `'insert'`, or `'line.column'`. Other indices throw. |
| `clear()`         | `(): void`                         | Remove all content.                                                                          |
| `getLength()`     | `(): int`                          | Number of characters in the buffer (excluding the implicit trailing newline).                |
| `getLineCount()`  | `(): int`                          | Number of lines (always ≥ 1).                                                                |
| `setState()`      | `(string $state): void`            | `'normal'` or `'disabled'`. Other values throw.                                              |
| `isDisabled()`    | `(): bool`                         | Whether the widget is read-only.                                                             |
| `pack/place/grid` | `(array $opts = []): void`         | Inherited geometry managers.                                                                 |
| `destroy()`       | `(): void`                         | Removes the widget.                                                                          |

---

### Notes

- Tk's `text get 1.0 end` always appends a trailing newline. `getText()` strips one to give back exactly what you inserted.
- `insertAt()` only accepts `'end'`, `'insert'`, or a `line.col` literal. The free-form Tk index grammar (`"end-2c"`, `"insert wordstart"`) is rejected to keep user-supplied index strings from injecting Tcl.
- `setText()` and `append()` route the value through a Tcl variable rather than interpolating it into a command, so newlines, brackets, and quotes are always preserved literally.
- `setState('disabled')` makes the widget read-only **but `append()`/`setText()` still work** — they toggle to normal, modify, then toggle back. This is the common pattern for log views.
