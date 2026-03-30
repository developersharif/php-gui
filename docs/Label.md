# Label Widget

The **Label** widget displays a static or dynamically-updated text string inside a parent window. It extends `AbstractWidget` and supports all three layout managers (`pack`, `place`, `grid`).

---

### Constructor

```php
new Label(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                                  |
|-------------|----------|----------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.              |
| `$options`  | `array`  | Configuration options — see table below.     |

---

### Options

| Key      | Type     | Description                                                          |
|----------|----------|----------------------------------------------------------------------|
| `text`   | `string` | The text content to display. Defaults to `''`.                       |
| `fg`     | `string` | Foreground (text) color. Name or hex (e.g. `'white'`, `'#666666'`). |
| `bg`     | `string` | Background color.                                                    |
| `font`   | `string` | Font string, e.g. `'Arial 12 bold'`, `'Arial 11 italic'`.           |
| `padx`   | `int`    | Horizontal internal padding.                                         |
| `pady`   | `int`    | Vertical internal padding.                                           |
| `relief` | `string` | Border style: `flat`, `raised`, `sunken`, `groove`, `ridge`.        |

All options except `text` are forwarded as Tk `-key "value"` pairs.

---

### Examples

**Simple label:**
```php
use PhpGui\Widget\Label;

$label = new Label($window->getId(), ['text' => 'Hello, PHP GUI World!']);
$label->pack(['pady' => 20]);
```

**Styled label with color and font:**
```php
$styledLabel = new Label($window->getId(), [
    'text'    => 'Styled label',
    'fg'      => 'white',
    'bg'      => '#4CAF50',
    'font'    => 'Arial 12',
    'padx'    => 10,
    'pady'    => 5,
    'relief'  => 'raised'
]);
$styledLabel->pack(['pady' => 5]);
```

**Dynamically updating a label from a button click:**
```php
$label = new Label($window->getId(), ['text' => 'Original text']);
$label->pack();

$btn = new Button($window->getId(), [
    'text'    => 'Update',
    'command' => function () use ($label) {
        $label->setText('Updated!');
        $label->setForeground('#009688');
        $label->setBackground('#f0f0f0');
    }
]);
$btn->pack();
```

---

### Methods

| Method              | Signature                     | Description                              |
|---------------------|-------------------------------|------------------------------------------|
| `setText()`         | `(string $text): void`        | Updates the label's `-text` via `configure`. |
| `setFont()`         | `(string $font): void`        | Updates the label's `-font`.             |
| `setForeground()`   | `(string $color): void`       | Updates the label's `-fg`.               |
| `setBackground()`   | `(string $color): void`       | Updates the label's `-bg`.               |
| `setState()`        | `(string $state): void`       | Sets the label's `-state` (`normal`, `disabled`). |
| `pack()`            | `(array $opts = []): void`    | Inherited. Pack layout manager.          |
| `place()`           | `(array $opts = []): void`    | Inherited. Place layout manager.         |
| `grid()`            | `(array $opts = []): void`    | Inherited. Grid layout manager.          |
| `destroy()`         | `(): void`                    | Inherited. Removes the widget.           |

---

### Notes

- All setter methods use `configure` under the hood, so changes are visible immediately without restarting the app.
- `setState('disabled')` greys out the label text.
