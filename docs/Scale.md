# Scale Widget

The **Scale** widget is a slider — the user drags a thumb along a track to pick a numeric value within `[from, to]`. _New in v1.9._

---

### Constructor

```php
new Scale(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                              |
|-------------|----------|------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.          |
| `$options`  | `array`  | Configuration options — see below.       |

---

### Options

| Key            | Type             | Description                                                          |
|----------------|------------------|----------------------------------------------------------------------|
| `from`         | `int`/`float`    | Lower bound (default `0`).                                          |
| `to`           | `int`/`float`    | Upper bound (default `100`).                                        |
| `orient`       | `string`         | `'horizontal'` (default) or `'vertical'`. Other values throw.        |
| `length`       | `int`            | Length of the track in pixels.                                       |
| `resolution`   | `float`          | Step granularity (e.g. `0.1` for one-decimal precision).             |
| `tickinterval` | `float`          | If non-zero, draw tick marks every N units.                          |
| `label`        | `string`         | Caption shown alongside the slider.                                 |
| `showvalue`    | `bool`           | Whether the current value is drawn above the thumb.                  |

Any other option is forwarded to Tk's `scale` command (run through safe-quoting).

---

### Examples

**Volume slider:**
```php
use PhpGui\Widget\{Scale, Label};

$readout = new Label($window->getId(), ['text' => 'volume: 0']);
$readout->pack();

$vol = new Scale($window->getId(), [
    'from'   => 0,
    'to'     => 100,
    'orient' => 'horizontal',
    'length' => 240,
    'label'  => 'Volume',
]);
$vol->pack(['fill' => 'x', 'padx' => 12]);

$vol->onChange(function (float $value) use ($readout) {
    $readout->setText('volume: ' . (int) $value);
});
```

**Signed range:**
```php
$balance = new Scale($window->getId(), [
    'from'         => -50,
    'to'           => 50,
    'tickinterval' => 25,
]);
$balance->pack();
```

---

### Methods

| Method        | Signature                          | Description                                                                                       |
|---------------|------------------------------------|---------------------------------------------------------------------------------------------------|
| `getValue()`  | `(): float`                        | Current numeric value.                                                                            |
| `setValue()`  | `(float $value): void`             | Move the thumb. Tk clamps to `[from, to]`. Fires `onChange` if registered.                        |
| `getFrom()`   | `(): float`                        | Lower bound.                                                                                      |
| `getTo()`     | `(): float`                        | Upper bound.                                                                                      |
| `onChange()`  | `(callable $h): void`              | `$h(float $value)` fires on every change — user drag and programmatic `setValue()` alike.        |
| `pack/place/grid` | `(array $opts = []): void`     | Inherited.                                                                                        |
| `destroy()`   | `(): void`                         | Removes the widget and frees the change callback.                                                 |

---

### Notes

- The value is backed by a Tcl variable, so reads (`getValue`) and writes (`setValue`) are direct FFI calls — no string parsing involved.
- Tk's native `-command` option fires only on user interaction, not on programmatic variable changes. The framework dispatches `onChange` manually on `setValue()` to keep both code paths consistent.
- `int` and `float` inputs are both accepted by `setValue()`; `getValue()` always returns a `float`.
