# Separator Widget

The **Separator** widget is a thin horizontal or vertical divider line — wraps Tk's `ttk::separator`. _New in v1.9._

---

### Constructor

```php
new Separator(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                              |
|-------------|----------|------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.          |
| `$options`  | `array`  | Configuration — see below.               |

| Option   | Type     | Description                                                          |
|----------|----------|----------------------------------------------------------------------|
| `orient` | `string` | `'horizontal'` (default) or `'vertical'`. Other values throw.        |

---

### Examples

**Horizontal divider between sections:**
```php
use PhpGui\Widget\Separator;

$header->pack(['fill' => 'x']);

(new Separator($window->getId()))->pack(['fill' => 'x', 'pady' => 6]);

$body->pack(['fill' => 'both', 'expand' => 1]);
```

**Vertical divider in a horizontal toolbar:**
```php
$toolbar = new Frame($window->getId());
$toolbar->pack(['fill' => 'x']);

$openBtn->pack(['side' => 'left']);
$saveBtn->pack(['side' => 'left']);

(new Separator($toolbar->getId(), ['orient' => 'vertical']))
    ->pack(['side' => 'left', 'fill' => 'y', 'padx' => 4]);

$cutBtn->pack(['side' => 'left']);
$copyBtn->pack(['side' => 'left']);
```

---

### Methods

The widget is purely visual — there is no per-widget API beyond what `AbstractWidget` provides (`pack`, `place`, `grid`, `destroy`).

---

### Notes

- For a horizontal separator inside a horizontally-packing container, `pack(['fill' => 'x'])` makes it span the available width.
- For a vertical separator inside a horizontally-packing container, `pack(['side' => 'left', 'fill' => 'y'])` is the typical incantation.
