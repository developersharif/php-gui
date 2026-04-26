# Listbox Widget

The **Listbox** widget displays a scrollable list of strings the user can select from. _New in v1.9._

For tall content, pair with [`Scrollbar::attachTo()`](Scrollbar.md).

---

### Constructor

```php
new Listbox(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                               |
|-------------|----------|-------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.           |
| `$options`  | `array`  | Configuration options — see below.        |

---

### Options

| Key          | Type             | Description                                                                                  |
|--------------|------------------|----------------------------------------------------------------------------------------------|
| `items`      | `list<string>`   | Initial items. Equivalent to calling `addItem()` for each one after construction.            |
| `selectmode` | `string`         | `'browse'` (default), `'single'`, `'multiple'`, or `'extended'`. Other values throw.         |
| `height`     | `int`            | Visible rows. Defaults to 10.                                                                |
| `width`      | `int`            | Width in average characters.                                                                 |
| `bg`/`fg`    | `string`         | Background / foreground colours.                                                             |
| `font`       | `string`         | Font specification.                                                                          |

Any other option is forwarded to Tk's `listbox` command (run through safe-quoting).

#### Select modes

| Mode       | Behaviour                                                       |
|------------|-----------------------------------------------------------------|
| `browse`   | Single-select; arrow keys move and select.                      |
| `single`   | Single-select; click only.                                      |
| `multiple` | Toggle on click; multi-select.                                  |
| `extended` | Shift/Ctrl multi-select like a file picker.                     |

---

### Examples

**Static list with selection callback:**
```php
use PhpGui\Widget\{Listbox, Label};

$status = new Label($window->getId(), ['text' => 'pick something']);
$status->pack();

$cities = new Listbox($window->getId(), [
    'items'  => ['Berlin', 'Tokyo', 'Lagos', 'São Paulo'],
    'height' => 6,
]);
$cities->pack(['fill' => 'x', 'padx' => 12, 'pady' => 6]);

$cities->onSelect(function (Listbox $lb) use ($status) {
    $status->setText('selected: ' . ($lb->getItem($lb->getSelectedIndex()) ?? '(none)'));
});
```

**Multi-select with a scrollbar:**
```php
use PhpGui\Widget\{Frame, Listbox, Scrollbar};

$frame = new Frame($window->getId());
$frame->pack(['fill' => 'both', 'expand' => 1]);

$list = new Listbox($frame->getId(), [
    'selectmode' => 'extended',
    'height'     => 12,
]);
$list->pack(['side' => 'left', 'fill' => 'both', 'expand' => 1]);
foreach (range(1, 100) as $i) {
    $list->addItem("Row {$i}");
}

Scrollbar::attachTo($list, 'vertical');
```

---

### Methods

| Method                  | Signature                              | Description                                                                                                          |
|-------------------------|----------------------------------------|----------------------------------------------------------------------------------------------------------------------|
| `addItem()`             | `(string $item): void`                 | Append to the end. Tcl-special characters preserved literally.                                                       |
| `setItems()`            | `(list<string> $items): void`          | Replace every item in one call. More efficient than `clear()` + loop.                                                |
| `removeItem()`          | `(int $index): void`                   | Remove by 0-based index. Negative or out-of-range indices are silently ignored.                                      |
| `clear()`               | `(): void`                             | Remove every item.                                                                                                   |
| `size()`                | `(): int`                              | Total item count.                                                                                                    |
| `getItem()`             | `(int $index): ?string`                | Item at `$index`, or `null` if out of range.                                                                         |
| `getAllItems()`         | `(): list<string>`                     | All items in display order.                                                                                          |
| `getSelectedIndices()`  | `(): list<int>`                        | All selected indices (ascending). Empty array if no selection.                                                       |
| `getSelectedIndex()`    | `(): ?int`                             | First selected index, or `null`. Convenient for browse/single mode.                                                  |
| `getSelectedItems()`    | `(): list<string>`                     | Selected items as strings.                                                                                           |
| `setSelection()`        | `(list<int> $indices): void`           | Replace the selection. Out-of-range indices are silently dropped; pass `[]` to clear.                                |
| `onSelect()`            | `(callable $cb): void`                 | Fire `$cb($listbox)` on `<<ListboxSelect>>`. Note: programmatic `setSelection()` does NOT fire this event.           |
| `pack/place/grid`       | `(array $opts = []): void`             | Inherited geometry managers.                                                                                         |
| `destroy()`             | `(): void`                             | Removes the widget and frees the select callback.                                                                    |

---

### Notes

- Tk fires the `<<ListboxSelect>>` virtual event on user-driven selection changes (mouse, keyboard). Programmatic changes via `setSelection()` do **not** fire it — invoke your handler manually if you need that behaviour.
- Items are inserted via Tcl variables, so newlines, brackets, dollars, and quotes pass through verbatim. No escaping needed at the call site.
- A listbox without an attached scrollbar still scrolls via mouse wheel and arrow keys; `Scrollbar::attachTo()` adds the visual indicator.
