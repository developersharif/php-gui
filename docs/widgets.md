# Widget Common Behaviours

Every widget in php-gui extends `AbstractWidget`, which provides a shared set of behaviours. This page documents what all widgets have in common so individual widget pages don't have to repeat it.

---

## Widget ID and Parent ID

Every widget is assigned a unique string ID via `uniqid('w')` at construction time. This ID is used as the Tk widget path component.

```php
$widget->getId(); // e.g. "w63f1a2b4c5d6"
```

The **parent ID** is passed as the first constructor argument for all child widgets. You obtain it by calling `getId()` on the parent:

```php
$window = new Window(['title' => 'My App', 'width' => 800, 'height' => 600]);

$label = new Label($window->getId(), ['text' => 'Hello']);
$frame = new Frame($window->getId());
$button = new Button($frame->getId(), ['text' => 'OK']); // nested inside frame
```

**Top-level widgets** (`Window`, `TopLevel`) pass `null` internally and must not be used with layout managers.

---

## Layout Managers

Three geometry managers are available on every widget (except top-level ones):

### `pack(array $options = []): void`

Stacks widgets sequentially. Use `side`, `fill`, `expand`, `padx`, `pady`.

```php
$label->pack(['pady' => 20]);
$btn->pack(['side' => 'left', 'padx' => 5]);
$frame->pack(['fill' => 'x']);
```

### `place(array $options = []): void`

Positions the widget at absolute pixel coordinates.

```php
$btn->place(['x' => 100, 'y' => 50]);
```

### `grid(array $options = []): void`

Places the widget in a row/column table layout.

```php
$label->grid(['row' => 0, 'column' => 0]);
$input->grid(['row' => 0, 'column' => 1]);
```

> **Do not mix** `pack`, `place`, and `grid` for widgets sharing the same parent. Tk will deadlock.

Calling any layout manager on a top-level widget (`Window`, `TopLevel`) throws a `RuntimeException`.

---

## Destroying Widgets

```php
$widget->destroy(); // void
```

Removes the widget from the Tk interpreter immediately. For `Menu` and `TopLevel`, `destroy()` is overridden because their Tk path is `.{id}` rather than `.{parentId}.{id}`.

---

## Common Style Options

Most widgets accept these Tk options as keys in the `$options` array:

| Key      | Type     | Description                                                       |
|----------|----------|-------------------------------------------------------------------|
| `bg`     | `string` | Background color. Accepts color names (`'blue'`) or hex (`'#336699'`). |
| `fg`     | `string` | Foreground (text) color.                                          |
| `font`   | `string` | Font specification, e.g. `'Arial 14 bold'`, `'Helvetica 16'`.    |
| `padx`   | `int`    | Horizontal internal padding (inside the widget border).           |
| `pady`   | `int`    | Vertical internal padding.                                        |
| `relief` | `string` | Border style: `flat`, `raised`, `sunken`, `groove`, `ridge`.      |
| `width`  | `int`    | Widget width (in characters for text widgets, pixels for canvas). |
| `height` | `int`    | Widget height.                                                    |

Not every widget supports every option — consult the individual widget page. Options not explicitly handled by PHP are forwarded verbatim to the underlying Tk command.

---

## Callbacks

Widgets that accept a `command` option (`Button`, `Checkbutton`) register the PHP closure via `ProcessTCL::registerCallback()`. The closure is invoked by the event loop in `Application::run()` whenever the widget triggers it.

```php
$btn = new Button($window->getId(), [
    'text'    => 'Go',
    'command' => function () {
        echo "clicked\n";
    }
]);
```

The `Input` widget's `onEnter()` follows the same pattern but binds to the `<Return>` key event rather than a `-command` option.
