# Scrollbar Widget

The **Scrollbar** widget wraps Tk's `ttk::scrollbar` and wires it to a scrollable target (Text, Listbox, Canvas, Treeview, …). _New in v1.9._

A scrollbar by itself does nothing — it must be bound to a target's `xview` / `yview` interface. The `attachTo()` factory does the wiring and packing in a single call.

---

### Constructor

```php
new Scrollbar(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                              |
|-------------|----------|------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.          |
| `$options`  | `array`  | Configuration options — see below.       |

---

### Options

| Key      | Type     | Description                                                          |
|----------|----------|----------------------------------------------------------------------|
| `orient` | `string` | `'vertical'` (default) or `'horizontal'`. Other values throw.        |

Any other key is forwarded to `ttk::scrollbar` (run through the safe-quoting helper).

---

### Examples

**Quick wiring with `attachTo()`:**
```php
use PhpGui\Widget\{Frame, Text, Scrollbar};

$frame = new Frame($window->getId());
$frame->pack(['fill' => 'both', 'expand' => 1]);

$text = new Text($frame->getId(), ['width' => 50, 'height' => 12]);
$text->pack(['side' => 'left', 'fill' => 'both', 'expand' => 1]);

Scrollbar::attachTo($text, 'vertical');
```

**Manual `bindTo()` for custom layouts (e.g. dual scrollbars):**
```php
$canvas = new Canvas($frame->getId(), ['width' => 400, 'height' => 300]);
$canvas->grid(['row' => 0, 'column' => 0, 'sticky' => 'nsew']);

$vsb = new Scrollbar($frame->getId(), ['orient' => 'vertical']);
$vsb->grid(['row' => 0, 'column' => 1, 'sticky' => 'ns']);
$vsb->bindTo($canvas);

$hsb = new Scrollbar($frame->getId(), ['orient' => 'horizontal']);
$hsb->grid(['row' => 1, 'column' => 0, 'sticky' => 'ew']);
$hsb->bindTo($canvas);
```

---

### Methods

| Method              | Signature                                                  | Description                                                                                                          |
|---------------------|------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|
| `bindTo()`          | `(AbstractWidget $target): void`                           | Two-way wiring: scrollbar's `-command` drives the target's view, and the target's `xscrollcommand`/`yscrollcommand` updates the slider. |
| `getOrient()`       | `(): string`                                               | Returns `'vertical'` or `'horizontal'`.                                                                              |
| `pack/place/grid`   | `(array $opts = []): void`                                 | Inherited geometry managers.                                                                                         |
| `destroy()`         | `(): void`                                                 | Removes the widget.                                                                                                  |

---

### Static factories

| Factory                                             | Description                                                                                          |
|-----------------------------------------------------|------------------------------------------------------------------------------------------------------|
| `Scrollbar::attachTo($target, $orient = 'vertical')`| Create a scrollbar in the target's parent, call `bindTo($target)`, and pack it on the right (vertical) or bottom (horizontal). Throws if `$target` is a top-level widget — wrap it in a Frame first. |

---

### Notes

- `attachTo()` packs the scrollbar with `-before $target` so the target widget expands into the remaining space rather than being clipped behind the scrollbar. This means **you should `pack()` the target *before* calling `attachTo()`**.
- For grid-based layouts, build the Scrollbar manually and call `bindTo()` — `attachTo()`'s pack call will conflict with grid management on the same parent.
- Both orientations of `bindTo()` work on any widget that exposes `xview`/`yview` — Text, Listbox, Canvas, Treeview, Entry (horizontal only).
