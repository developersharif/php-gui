# Button Widget

The **Button** widget renders a clickable button and executes a PHP callback when clicked. It extends `AbstractWidget`, so it inherits `pack()`, `place()`, `grid()`, and `destroy()`.

---

### Constructor

```php
new Button(string $parentId, array $options = [])
```

| Parameter  | Type     | Description                                         |
|------------|----------|-----------------------------------------------------|
| `$parentId`| `string` | The `getId()` result of the parent widget (e.g. `Window`) |
| `$options` | `array`  | Configuration options — see table below             |

---

### Options

| Key       | Type       | Description                                                    |
|-----------|------------|----------------------------------------------------------------|
| `text`    | `string`   | Label shown on the button. Defaults to `'Button'`.             |
| `command` | `callable` | PHP closure called when the button is clicked.                 |
| `bg`      | `string`   | Background color (name or hex, e.g. `'blue'`, `'#ff0000'`).   |
| `fg`      | `string`   | Foreground (text) color.                                       |
| `font`    | `string`   | Font string, e.g. `'Helvetica 16 bold'`.                       |
| `relief`  | `string`   | Border style: `flat`, `raised`, `sunken`, `groove`, `ridge`.   |
| `padx`    | `int`      | Horizontal internal padding in pixels.                         |
| `pady`    | `int`      | Vertical internal padding in pixels.                           |

Any option other than `text` and `command` is passed directly to the underlying Tk `button` command.

---

### Examples

**Basic button:**
```php
use PhpGui\Widget\Button;

$btn = new Button($window->getId(), [
    'text'    => 'Click Me',
    'command' => function () {
        echo "Button clicked!\n";
    }
]);
$btn->pack(['pady' => 10]);
```

**Styled button with custom colors and font:**
```php
$styledButton = new Button($window->getId(), [
    'text'    => 'Styled Button',
    'command' => function () use ($label) {
        $label->setText('Styled Button clicked!');
    },
    'bg'   => 'blue',
    'fg'   => 'white',
    'font' => 'Helvetica 16 bold'
]);
$styledButton->pack(['pady' => 10]);
```

**Button without a callback (display-only):**
```php
$btn = new Button($window->getId(), ['text' => 'No Action']);
$btn->pack();
```

---

### Layout

All three geometry managers are supported (inherited from `AbstractWidget`):

```php
$btn->pack(['side' => 'left', 'padx' => 5]);
$btn->place(['x' => 100, 'y' => 50]);
$btn->grid(['row' => 0, 'column' => 1]);
```

---

### Notes

- The `command` option is extracted from `$options` and registered via `ProcessTCL::registerCallback()`. The PHP closure is invoked each time the button is clicked, then `update` is called to force widget refresh.
- All other keys in `$options` are forwarded as Tk options using `-key "value"` formatting.
- `destroy()` removes the button from the Tk interpreter immediately.
