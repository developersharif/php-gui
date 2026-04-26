# Progressbar Widget

The **Progressbar** widget shows progress for long-running tasks. Wraps Tk's `ttk::progressbar`. _New in v1.9._

Two modes:

- **`determinate`** (default) — bar fills from 0 to `maximum`. Drive with `setValue()` or `step()` as work completes.
- **`indeterminate`** — animated bouncing bar for "doing something, can't measure how much". Drive with `start()` / `stop()`.

---

### Constructor

```php
new Progressbar(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                              |
|-------------|----------|------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.          |
| `$options`  | `array`  | Configuration options — see below.       |

---

### Options

| Key       | Type             | Description                                                           |
|-----------|------------------|-----------------------------------------------------------------------|
| `mode`    | `string`         | `'determinate'` (default) or `'indeterminate'`. Other values throw.   |
| `orient`  | `string`         | `'horizontal'` (default) or `'vertical'`. Other values throw.         |
| `maximum` | `float`          | Determinate mode end value (default `100`).                          |
| `value`   | `float`          | Initial position (default `0`).                                       |
| `length`  | `int`            | Length of the bar in pixels.                                          |

---

### Examples

**Determinate, ticking up as work happens:**
```php
use PhpGui\Widget\Progressbar;

$bar = new Progressbar($window->getId(), ['maximum' => $totalRows]);
$bar->pack(['fill' => 'x', 'padx' => 12]);

foreach ($rows as $i => $row) {
    process($row);
    $bar->setValue($i + 1);
    // give Tk a chance to repaint between iterations
    \PhpGui\ProcessTCL::getInstance()->evalTcl('update');
}
```

**Indeterminate "busy" bar:**
```php
$busy = new Progressbar($window->getId(), [
    'mode'   => 'indeterminate',
    'length' => 240,
]);
$busy->pack();
$busy->start();          // animate
// … later, when the task finishes …
$busy->stop();
```

---

### Methods

| Method            | Signature                          | Description                                                                                       |
|-------------------|------------------------------------|---------------------------------------------------------------------------------------------------|
| `getValue()`      | `(): float`                        | Determinate-mode current value.                                                                   |
| `setValue()`      | `(float $value): void`             | Set the position. Tk clamps to `[0, maximum]`.                                                    |
| `step()`          | `(float $amount = 1.0): void`      | Add `$amount` to the current value.                                                               |
| `getMaximum()`    | `(): float`                        | Determinate-mode end value.                                                                       |
| `setMaximum()`    | `(float $maximum): void`           | Update the end value.                                                                             |
| `getMode()`       | `(): string`                       | `'determinate'` or `'indeterminate'`.                                                             |
| `setMode()`       | `(string $mode): void`             | Switch modes at runtime. Invalid modes throw.                                                     |
| `start()`         | `(int $intervalMs = 50): void`     | Begin indeterminate animation. `$intervalMs` < 1 throws.                                          |
| `stop()`          | `(): void`                         | Stop indeterminate animation.                                                                     |
| `pack/place/grid` | `(array $opts = []): void`         | Inherited.                                                                                        |
| `destroy()`       | `(): void`                         | Removes the widget.                                                                               |

---

### Notes

- A long-running PHP loop blocks Tk's event queue. If your work happens in PHP between `setValue()` calls, the bar won't repaint — call `ProcessTCL::evalTcl('update')` periodically to flush pending paints.
- `start()` / `stop()` are no-ops in determinate mode; switch with `setMode('indeterminate')` first.
- `step()` is convenient for "tick by tick" reporting where you don't track the absolute value yourself.
