# LabelFrame Widget

The **LabelFrame** widget is a frame with a titled border — wraps Tk's `ttk::labelframe`. Visually a [`Frame`](Frame.md) whose top edge is broken by a small title label, useful for grouping related controls in a settings dialog. _New in v1.9._

---

### Constructor

```php
new LabelFrame(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                              |
|-------------|----------|------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.          |
| `$options`  | `array`  | Configuration — see below.               |

| Option       | Type     | Description                                                  |
|--------------|----------|--------------------------------------------------------------|
| `text`       | `string` | Title shown along the top border. Optional.                  |
| `labelanchor`| `string` | Title position: `'nw'`, `'n'`, `'ne'`, `'w'`, `'e'`, `'sw'`, `'s'`, `'se'`. |
| `padding`    | `int`/`string` | Inner padding around children.                          |
| `borderwidth`| `int`    | Border thickness.                                            |
| `relief`     | `string` | `'flat'`, `'raised'`, `'sunken'`, `'groove'`, `'ridge'`.    |

---

### Examples

**Grouping related inputs:**
```php
use PhpGui\Widget\{LabelFrame, Label, Input};

$net = new LabelFrame($window->getId(), ['text' => 'Network']);
$net->pack(['fill' => 'x', 'padx' => 8, 'pady' => 8]);

(new Label($net->getId(), ['text' => 'Hostname:']))->pack(['anchor' => 'w']);
(new Input($net->getId()))->pack(['fill' => 'x']);

(new Label($net->getId(), ['text' => 'Port:']))->pack(['anchor' => 'w']);
(new Input($net->getId()))->pack(['fill' => 'x']);
```

**Updating the title at runtime:**
```php
$box = new LabelFrame($window->getId(), ['text' => 'Loading...']);
$box->pack(['fill' => 'both', 'expand' => 1]);
// later...
$box->setText('Results (12 rows)');
```

---

### Methods

| Method        | Signature              | Description                                          |
|---------------|------------------------|------------------------------------------------------|
| `setText()`   | `(string $text): void` | Update the title shown along the top border.         |
| `getText()`   | `(): string`           | Read the current title.                              |
| `pack/place/grid` | `(array $opts = []): void` | Inherited.                                       |
| `destroy()`   | `(): void`             | Removes the frame and all its children.              |

---

### Notes

- Children are placed inside a LabelFrame the same way as a regular [`Frame`](Frame.md): construct them with `$labelFrame->getId()` as the parent.
- The title is run through the safe-quoting helper, so user-supplied (e.g. translated) titles cannot inject Tcl.
- Construct without `text` for a plain bordered frame — useful when you want the visual border but no caption.
