# Frame Widget

The **Frame** widget is a plain rectangular container. It has no visible content of its own — its purpose is to group child widgets, apply padding, and control layout sections inside a window.

---

### Constructor

```php
new Frame(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                              |
|-------------|----------|------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.          |
| `$options`  | `array`  | Currently unused — passed to parent but not applied to the `frame` command in the current implementation. |

---

### Example

**Grouping buttons in a row using a Frame:**
```php
use PhpGui\Widget\Frame;
use PhpGui\Widget\Button;

$frame = new Frame($window->getId());
$frame->pack(['fill' => 'x', 'pady' => 10]);

$btn1 = new Button($frame->getId(), ['text' => 'OK']);
$btn1->pack(['side' => 'left', 'padx' => 5]);

$btn2 = new Button($frame->getId(), ['text' => 'Cancel']);
$btn2->pack(['side' => 'left', 'padx' => 5]);
```

**Using Frame as a section divider:**
```php
$topSection = new Frame($window->getId());
$topSection->pack(['fill' => 'both', 'expand' => 1]);

$bottomSection = new Frame($window->getId());
$bottomSection->pack(['fill' => 'x']);
```

---

### Methods

| Method      | Signature             | Description                                               |
|-------------|-----------------------|-----------------------------------------------------------|
| `getId()`   | `(): string`          | Returns the widget ID — use this as `$parentId` for children. |
| `pack()`    | `(array $opts): void` | Inherited. Pack layout manager.                           |
| `place()`   | `(array $opts): void` | Inherited. Place layout manager.                          |
| `grid()`    | `(array $opts): void` | Inherited. Grid layout manager.                           |
| `destroy()` | `(): void`            | Inherited. Removes the frame and all its children.        |

---

### Notes

- The current `create()` implementation calls `frame .{parentId}.{id}` with no extra options. The `$options` array is stored internally but not yet forwarded to the Tk `frame` command.
- Use `$frame->getId()` as the `$parentId` when adding child widgets inside the frame — the same pattern as with `Window`.
- `destroy()` on a frame will recursively destroy all child widgets Tk-side.
