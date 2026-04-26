# PanedWindow Widget

The **PanedWindow** widget is a resizable split container — wraps Tk's `ttk::panedwindow`. Each pane hosts a child widget; the user drags the divider between panes to resize them. _New in v1.9._

Like [`Notebook`](Notebook.md), each pane must be a **child of the PanedWindow itself**. The typical pattern is to make each pane a `Frame` parented to the PanedWindow, populate the frame, then call `addPane()`.

---

### Constructor

```php
new PanedWindow(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                                                                       |
|-------------|----------|-----------------------------------------------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.                                                   |
| `$options`  | `array`  | Configuration — see below.                                                        |

| Option   | Type     | Description                                                          |
|----------|----------|----------------------------------------------------------------------|
| `orient` | `string` | `'horizontal'` (default) or `'vertical'`. Other values throw.        |

Any other option is forwarded to `ttk::panedwindow` (run through safe-quoting).

---

### Examples

**Sidebar + main content (3:1 split):**
```php
use PhpGui\Widget\{PanedWindow, Frame};

$split = new PanedWindow($window->getId(), ['orient' => 'horizontal']);
$split->pack(['fill' => 'both', 'expand' => 1]);

$sidebar = new Frame($split->getId());
$content = new Frame($split->getId());

$split->addPane($sidebar, ['weight' => 1]);
$split->addPane($content, ['weight' => 3]);
```

**Stacked vertical panes:**
```php
$split = new PanedWindow($window->getId(), ['orient' => 'vertical']);
$split->pack(['fill' => 'both', 'expand' => 1]);

$top    = new Frame($split->getId());
$bottom = new Frame($split->getId());
$split->addPane($top,    ['weight' => 2]);
$split->addPane($bottom, ['weight' => 1]);

// Programmatically move the divider 200px down from the top.
$split->setSashPosition(0, 200);
```

---

### Methods

| Method                | Signature                                                  | Description                                                                                                          |
|-----------------------|------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|
| `addPane()`           | `(AbstractWidget $child, array $options = []): void`       | Attach `$child` as a new pane. Throws if `$child` isn't a child of this PanedWindow.                                 |
| `removePane()`        | `(int $index): void`                                       | Detach the pane at `$index`. The child widget is **not** destroyed.                                                  |
| `getPaneCount()`      | `(): int`                                                  | Total number of panes.                                                                                               |
| `getPane()`           | `(int $index): ?AbstractWidget`                            | Pane widget at `$index`, or `null` if out of range.                                                                  |
| `configurePane()`     | `(int $index, array $options): void`                       | Update pane-level options (e.g. `weight`).                                                                           |
| `setSashPosition()`   | `(int $index, int $position): void`                        | Move the divider at `$index` to absolute pixel position `$position`.                                                 |
| `getSashPosition()`   | `(int $index): int`                                        | Read the divider position.                                                                                           |
| `pack/place/grid`     | `(array $opts = []): void`                                 | Inherited.                                                                                                           |
| `destroy()`           | `(): void`                                                 | Removes the PanedWindow.                                                                                             |

#### `addPane()` and `configurePane()` options

| Key      | Description                                                      |
|----------|------------------------------------------------------------------|
| `weight` | `int` — share of leftover space when the parent resizes.         |

---

### Notes

- The framework checks at `addPane()` time that the child's parent is actually this PanedWindow. Without this guard, Tk would silently render an empty pane.
- `removePane()` calls Tk's `forget`, which detaches the pane but leaves the child widget alive. Call `destroy()` on the child yourself if you want it gone.
- Sash indices are 0-based: index 0 is the divider after the first pane, index 1 is the divider between the second and third pane, and so on.
