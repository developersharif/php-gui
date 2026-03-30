# Canvas Widget

The **Canvas** widget provides a 2D drawing area where you can programmatically render lines, rectangles, ovals, and text. Every draw method returns a Tk item ID (as a `string`) that can be used to delete specific items later.

---

### Constructor

```php
new Canvas(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                              |
|-------------|----------|------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.          |
| `$options`  | `array`  | Configuration options — see table below. |

---

### Options

| Key      | Type     | Description                        |
|----------|----------|------------------------------------|
| `width`  | `int`    | Canvas width in pixels.            |
| `height` | `int`    | Canvas height in pixels.           |
| `bg`     | `string` | Background color of the canvas.    |

All options are forwarded directly to the Tk `canvas` command.

---

### Example

```php
use PhpGui\Widget\Canvas;

$canvas = new Canvas($window->getId(), [
    'width'  => 400,
    'height' => 300,
    'bg'     => 'white'
]);
$canvas->pack(['pady' => 10]);

// Draw shapes
$lineId = $canvas->drawLine(10, 10, 200, 10, ['fill' => 'black', 'width' => 2]);
$rectId = $canvas->drawRectangle(50, 50, 200, 150, ['fill' => 'lightblue', 'outline' => 'navy']);
$ovalId = $canvas->drawOval(220, 50, 370, 150, ['fill' => 'salmon']);
$textId = $canvas->drawText(100, 180, 'Hello Canvas', ['fill' => 'darkgreen', 'font' => 'Arial 14 bold']);

// Delete a specific item by its returned ID
$canvas->delete($rectId);

// Clear the entire canvas
$canvas->clear();
```

---

### Methods

| Method              | Signature                                                            | Returns  | Description                                                   |
|---------------------|----------------------------------------------------------------------|----------|---------------------------------------------------------------|
| `drawLine()`        | `(int $x1, int $y1, int $x2, int $y2, array $options = []): string` | item ID  | Draws a line between two points.                              |
| `drawRectangle()`   | `(int $x1, int $y1, int $x2, int $y2, array $options = []): string` | item ID  | Draws a rectangle from top-left to bottom-right.              |
| `drawOval()`        | `(int $x1, int $y1, int $x2, int $y2, array $options = []): string` | item ID  | Draws an oval/ellipse bounded by the given rectangle.         |
| `drawText()`        | `(int $x, int $y, string $text, array $options = []): string`        | item ID  | Draws text anchored at the given coordinates.                 |
| `delete()`          | `(string $itemId): void`                                             | —        | Deletes a single item by the ID returned from a draw method.  |
| `clear()`           | `(): void`                                                            | —        | Deletes all items on the canvas (`delete all`).               |
| `pack()`            | `(array $opts = []): void`                                           | —        | Inherited. Pack layout manager.                               |
| `place()`           | `(array $opts = []): void`                                           | —        | Inherited. Place layout manager.                              |
| `grid()`            | `(array $opts = []): void`                                           | —        | Inherited. Grid layout manager.                               |
| `destroy()`         | `(): void`                                                            | —        | Inherited. Removes the canvas widget.                         |

---

### Draw Options Reference

Options arrays for draw methods accept standard Tk item configuration keys:

| Key       | Applicable to         | Description                        |
|-----------|-----------------------|------------------------------------|
| `fill`    | all shapes, text      | Fill color.                        |
| `outline` | rectangle, oval       | Border color.                      |
| `width`   | line, rectangle, oval | Border/stroke width in pixels.     |
| `font`    | text                  | Font string, e.g. `'Arial 14 bold'`.|
| `anchor`  | text                  | Anchor point: `center`, `nw`, etc. |

---

### Notes

- `drawText()` uses `-text {text}` so the text string is passed as a Tk list element — it can contain spaces.
- All coordinate values are in pixels, measured from the top-left corner of the canvas.
- `Canvas` overrides `formatOptions()` from `AbstractWidget` to produce `-key value` without wrapping values in quotes (important for numeric coordinates).
