# Notebook Widget

The **Notebook** widget is a tabbed container — each tab hosts a child widget that becomes visible when its tab is selected. Wraps Tk's `ttk::notebook`. _New in v1.9._

Pages must be **children of the notebook itself**. The typical pattern is to make each page a `Frame` parented to the notebook, populate the frame, then call `addTab()`.

---

### Constructor

```php
new Notebook(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                              |
|-------------|----------|------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.          |
| `$options`  | `array`  | Tk options forwarded to `ttk::notebook`. |

---

### Examples

**Two simple tabs:**
```php
use PhpGui\Widget\{Notebook, Frame, Label};

$nb = new Notebook($window->getId());
$nb->pack(['fill' => 'both', 'expand' => 1]);

// Page 1
$general = new Frame($nb->getId());
(new Label($general->getId(), ['text' => 'General settings here']))
    ->pack(['padx' => 20, 'pady' => 20]);
$nb->addTab($general, 'General');

// Page 2
$advanced = new Frame($nb->getId());
(new Label($advanced->getId(), ['text' => 'Advanced settings here']))
    ->pack(['padx' => 20, 'pady' => 20]);
$nb->addTab($advanced, 'Advanced');
```

**Reacting to tab changes:**
```php
$nb->onTabChange(function (int $idx) use ($status) {
    $status->setText("now on tab {$idx}");
});
```

**Disabling a tab:**
```php
$nb->setTabState(1, 'disabled');   // tab 1 greyed out, can't be selected
$nb->setTabState(2, 'hidden');     // tab 2 not shown at all
```

---

### Methods

| Method                    | Signature                                                                        | Description                                                                                                          |
|---------------------------|----------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|
| `addTab()`                | `(AbstractWidget $page, string $title, array $options = []): void`               | Attach `$page` as a new tab. Throws if `$page` isn't a child of this Notebook.                                       |
| `selectTab()`             | `(int $index): void`                                                             | Switch to the tab at 0-based `$index`. `OutOfRangeException` on bad index.                                            |
| `selectPage()`            | `(AbstractWidget $page): void`                                                   | Switch to the tab whose page is `$page`. `InvalidArgumentException` if not in this notebook.                          |
| `getSelectedIndex()`      | `(): int`                                                                        | Active tab index, or `-1` if the notebook is empty.                                                                  |
| `getSelectedPage()`       | `(): ?AbstractWidget`                                                            | Active page widget, or `null`.                                                                                       |
| `getTabCount()`           | `(): int`                                                                        | Total number of tabs.                                                                                                |
| `removeTab()`             | `(int $index): void`                                                             | Detach the tab at `$index`. Page widget is **not** destroyed — caller can re-attach or destroy explicitly.            |
| `setTabTitle()`           | `(int $index, string $title): void`                                              | Update an existing tab's title.                                                                                      |
| `getTabTitle()`           | `(int $index): string`                                                           | Read an existing tab's title.                                                                                        |
| `setTabState()`           | `(int $index, string $state): void`                                              | `'normal'`, `'disabled'`, or `'hidden'`. Other values throw.                                                         |
| `onTabChange()`           | `(callable $h): void`                                                            | `$h(int $index)` fires on `<<NotebookTabChanged>>` — user click and programmatic `selectTab()` alike.                |
| `pack/place/grid`         | `(array $opts = []): void`                                                       | Inherited.                                                                                                           |
| `destroy()`               | `(): void`                                                                       | Removes the notebook and frees the tab-change callback.                                                              |

#### `addTab()` options

The third arg accepts tab-level Tk options:

| Key       | Description                                                |
|-----------|------------------------------------------------------------|
| `state`   | `'normal'`, `'disabled'`, `'hidden'`                       |
| `image`   | Tk image name to show alongside the text                   |
| `compound`| Image+text layout (`'left'`, `'right'`, `'top'`, `'none'`) |
| `underline` | Index of the character to underline (for keyboard mnemonic) |
| `padding` | Internal padding around the tab title                      |
| `sticky`  | How the page widget fills its slot                          |

---

### Notes

- The framework checks at `addTab()` time that the page widget's parent is actually this notebook. Without that guard, Tk would create the tab and silently render an empty pane (the page widget lives elsewhere in the widget tree).
- `removeTab()` calls Tk's `forget`, which detaches the tab but leaves the page widget alive. Call `destroy()` on the page yourself if you want it gone.
- The `<<NotebookTabChanged>>` virtual event needs Tk's event loop to dispatch — in `Application::run()` this happens automatically; in tests, call `evalTcl('update')` after `selectTab()` if you need the handler to fire synchronously.
- Tab titles are run through the safe-quoting helper, so user-supplied (e.g. translated) titles cannot inject Tcl.
