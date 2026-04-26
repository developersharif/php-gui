# Radiobutton & RadioGroup

The **Radiobutton** widget represents one option in a mutually-exclusive set. Selecting one button automatically deselects every other Radiobutton sharing the same `RadioGroup`. _New in v1.9._

`RadioGroup` is the missing piece if you've used Tk before: instead of every radio button declaring `-variable` on its own and silently breaking when two buttons happen to land on the same name, the group is a first-class object you create up front.

---

### RadioGroup

```php
new RadioGroup(string $defaultValue = '')
```

A group owns a single Tcl variable. Every Radiobutton attached to the group shares that variable, so Tk handles the deselection logic for you.

| Method                       | Signature                       | Description                                                              |
|------------------------------|----------------------------------|--------------------------------------------------------------------------|
| `getValue()`                 | `(): string`                     | Currently selected value (the `$value` of the active radio).             |
| `setValue()`                 | `(string $value): void`          | Programmatically pick a value. Fires every registered `onChange` handler. |
| `getDefault()`               | `(): string`                     | The default passed to the constructor.                                   |
| `getVariableName()`          | `(): string`                     | Underlying Tcl variable name. Mostly internal; useful for advanced uses. |
| `onChange()`                 | `(callable $h): void`            | Register `$h(string $value)` — fires on every change (user click + `setValue`). |

---

### Radiobutton

```php
new Radiobutton(
    string     $parentId,
    RadioGroup $group,
    string     $value,
    array      $options = []
)
```

| Parameter   | Type         | Description                                                                                |
|-------------|--------------|--------------------------------------------------------------------------------------------|
| `$parentId` | `string`     | `getId()` of the parent widget.                                                            |
| `$group`    | `RadioGroup` | The group this radio belongs to.                                                           |
| `$value`    | `string`     | The value the group reports when this radio is selected.                                   |
| `$options`  | `array`      | Tk options. `text` defaults to `$value`; `command` receives the value when this radio is picked. |

| Method          | Signature                | Description                                       |
|-----------------|--------------------------|---------------------------------------------------|
| `select()`      | `(): void`               | Programmatically activate this radio.             |
| `isSelected()`  | `(): bool`               | True if this radio is the currently-active one.   |
| `getValue()`    | `(): string`             | The value this radio represents.                  |
| `getGroup()`    | `(): RadioGroup`         | The owning group.                                 |

---

### Examples

**Basic group with three buttons:**
```php
use PhpGui\Widget\{RadioGroup, Radiobutton, Label};

$tier  = new RadioGroup('basic');
$basic = new Radiobutton($window->getId(), $tier, 'basic', ['text' => 'Basic']);
$pro   = new Radiobutton($window->getId(), $tier, 'pro',   ['text' => 'Pro']);
$ent   = new Radiobutton($window->getId(), $tier, 'ent',   ['text' => 'Enterprise']);

$basic->pack(['anchor' => 'w']);
$pro->pack(['anchor' => 'w']);
$ent->pack(['anchor' => 'w']);

$status = new Label($window->getId(), ['text' => "tier: {$tier->getValue()}"]);
$status->pack();

$tier->onChange(function (string $value) use ($status) {
    $status->setText("tier: {$value}");
});
```

**Per-radio command:**
```php
new Radiobutton($window->getId(), $tier, 'pro', [
    'text'    => 'Pro',
    'command' => function (string $value) {
        echo "user picked: {$value}\n";
    },
]);
```

The per-radio `command` receives the radio's value as a string. The group-level `onChange` also fires for every change — use whichever fits your model.

**Two independent groups:**
```php
$tier  = new RadioGroup('basic');
$theme = new RadioGroup('light');

new Radiobutton($win, $tier, 'basic', ['text' => 'Basic'])->pack();
new Radiobutton($win, $tier, 'pro',   ['text' => 'Pro'])->pack();

new Radiobutton($win, $theme, 'light', ['text' => 'Light'])->pack();
new Radiobutton($win, $theme, 'dark',  ['text' => 'Dark'])->pack();
```

The two groups don't interact — each manages its own Tcl variable.

---

### Notes

- All radios in a group must share the **same** `RadioGroup` instance. Two separately-constructed groups are independent even if their default values are identical.
- `RadioGroup::setValue()` fires every registered `onChange` handler. Tk's native `-command` option fires only on user clicks, so to keep both code paths consistent the framework dispatches handlers on `setValue()` itself.
- Radiobutton labels are run through the safe-quoting helper, so user-supplied text (e.g. translated strings) cannot inject Tcl.
