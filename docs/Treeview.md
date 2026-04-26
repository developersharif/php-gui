# Treeview Widget

The **Treeview** widget is a hierarchical list / multi-column table — wraps Tk's `ttk::treeview`. _New in v1.9._

Two main use cases:

- **Flat tables** with named columns (file lists, log records, query results, settings tables). Pass `columns` and `headings`; insert rows with `insert(null, ...)`.
- **Hierarchies** (folder trees, outlines, scene graphs). Pass parent IDs to nest rows.

Pair with [`Scrollbar::attachTo()`](Scrollbar.md) for tall content.

---

### Constructor

```php
new Treeview(string $parentId, array $options = [])
```

| Parameter   | Type     | Description                              |
|-------------|----------|------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.          |
| `$options`  | `array`  | Configuration options — see below.       |

---

### Options

| Key        | Type             | Description                                                                                       |
|------------|------------------|---------------------------------------------------------------------------------------------------|
| `columns`  | `list<string>`   | Logical column names. Used everywhere you reference a column.                                     |
| `headings` | `list<string>`   | Column header titles, positionally aligned with `columns`.                                        |
| `show`     | `string`         | `'headings'` (default when columns are set), `'tree'` (hierarchy only), or `'tree headings'` (both). |
| `height`   | `int`            | Visible rows.                                                                                     |
| `selectmode` | `string`       | `'extended'` (default), `'browse'`, or `'none'`.                                                  |

Any other option is forwarded to `ttk::treeview` (run through safe-quoting).

---

### Examples

**File table:**
```php
use PhpGui\Widget\Treeview;

$tv = new Treeview($window->getId(), [
    'columns'  => ['name', 'size', 'modified'],
    'headings' => ['Name', 'Size', 'Modified'],
    'show'     => 'headings',
    'height'   => 12,
]);
$tv->pack(['fill' => 'both', 'expand' => 1]);

$tv->setColumn('name',     ['width' => 240, 'anchor' => 'w']);
$tv->setColumn('size',     ['width' =>  90, 'anchor' => 'e']);
$tv->setColumn('modified', ['width' => 140, 'anchor' => 'w']);

foreach ($files as $f) {
    $tv->insert(null, ['name' => $f->name, 'size' => $f->size, 'modified' => $f->modified]);
}

$tv->onSelect(function (array $rowIds) use ($tv, $detail) {
    if ($rowIds === []) return;
    $row = $tv->getValues($rowIds[0]);
    $detail->setText("selected: {$row['name']}");
});
```

**Folder tree:**
```php
$tree = new Treeview($window->getId(), ['show' => 'tree']);
$tree->pack(['fill' => 'both', 'expand' => 1]);

$root  = $tree->insert(null, [], ['text' => 'project']);
$src   = $tree->insert($root, [], ['text' => 'src',   'open' => true]);
$tests = $tree->insert($root, [], ['text' => 'tests']);

$tree->insert($src, [], ['text' => 'main.php']);
$tree->insert($src, [], ['text' => 'helpers.php']);

$tree->onDoubleClick(function (?string $rowId) use ($tree) {
    if ($rowId !== null) {
        echo "double-clicked: ", $tree->getText($rowId), "\n";
    }
});
```

---

### Inserting rows

```php
insert(?string $parentId, array $values = [], array $options = []): string
```

- `$parentId` — `null` for top-level rows; pass another row's id (returned from a previous `insert()`) to nest.
- `$values` — positional list aligned with `columns`, or associative `[column => value]`. Missing columns become empty strings.
- `$options` — supports:
  - `text`: tree-column label (only meaningful when `show` includes `'tree'`).
  - `image`: Tk image name to show alongside the text.
  - `open`: whether children are initially expanded.
  - `tags`: array of tag names to attach (for styling via `tag configure`).

Returns the new row id.

---

### Methods

| Method                | Signature                                                                       | Description                                                                                                          |
|-----------------------|---------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|
| `insert()`            | `(?string $parentId, array $values = [], array $options = []): string`          | Add a row. Returns its id. See above.                                                                                |
| `getValue()`          | `(string $rowId, string $column): ?string`                                      | Read one column of `$rowId`.                                                                                         |
| `getValues()`         | `(string $rowId): array<string,string>`                                         | Read every column as `[column => value]`.                                                                            |
| `setValue()`          | `(string $rowId, string $column, string $value): void`                          | Update one column.                                                                                                   |
| `setValues()`         | `(string $rowId, array $values): void`                                          | Update multiple columns. Positional or keyed; omitted keyed columns clear to empty.                                  |
| `setText()`           | `(string $rowId, string $text): void`                                           | Set the tree-column label (only meaningful when `show` includes `'tree'`).                                           |
| `getText()`           | `(string $rowId): string`                                                       | Read the tree-column label.                                                                                          |
| `delete()`            | `(string $rowId): void`                                                         | Remove a row and all its descendants.                                                                                |
| `clear()`             | `(): void`                                                                      | Remove every row.                                                                                                    |
| `exists()`            | `(string $rowId): bool`                                                         | Whether the row is still in the tree.                                                                                |
| `getTopLevelCount()`  | `(): int`                                                                       | Top-level row count (does not recurse into children).                                                                |
| `setHeading()`        | `(string $column, string $title, array $options = []): void`                    | Set heading text, plus options like `anchor` or a sort `command`.                                                    |
| `setColumn()`         | `(string $column, array $options): void`                                        | Configure column display: `width`, `minwidth`, `anchor`, `stretch`.                                                  |
| `getSelected()`       | `(): list<string>`                                                              | All currently-selected row ids.                                                                                      |
| `getSelectedRow()`    | `(): ?string`                                                                   | First selected id, or `null`.                                                                                        |
| `setSelected()`       | `(list<string> $rowIds): void`                                                  | Replace the selection. Empty array clears.                                                                           |
| `onSelect()`          | `(callable $h): void`                                                           | `$h(list<string> $rowIds)` fires on `<<TreeviewSelect>>`.                                                            |
| `onDoubleClick()`     | `(callable $h): void`                                                           | `$h(?string $rowId)` fires on double-click. `$rowId` is `null` if the user double-clicked empty space.               |
| `pack/place/grid`     | `(array $opts = []): void`                                                      | Inherited.                                                                                                           |
| `destroy()`           | `(): void`                                                                      | Removes the widget and frees its callbacks.                                                                          |

---

### Notes

- All values are routed through Tcl variables and safe-quoting, so newlines, brackets, dollars, and quotes pass through verbatim.
- Row ids returned by `insert()` are deterministic, Tcl-safe strings — re-use them directly in subsequent calls.
- For sortable columns, pass a `-command` to `setHeading()` that runs your sort logic and re-inserts rows in the new order.
- `selectmode` defaults to `'extended'` (Shift/Ctrl multi-select). Pass `'browse'` for single-select.
- Tk fires `<<TreeviewSelect>>` for both user actions and programmatic `setSelected()`. The Tk event loop must be pumping for the virtual event to dispatch — `Application::run()` handles this; in tests, call `evalTcl('update')` if needed.
