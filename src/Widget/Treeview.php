<?php

namespace PhpGui\Widget;

use PhpGui\ProcessTCL;

/**
 * Class Treeview
 * Hierarchical list / multi-column table — wraps Tk's `ttk::treeview`.
 *
 * Use it for:
 *   - **Flat tables** with named columns (file lists, log records, query
 *     results). Pass `columns` and call `insert(null, ['Alice', 30, '...'])`.
 *   - **Hierarchies** (folder trees, outlines). Pass parent IDs to nest
 *     rows: a non-null `$parentId` makes the new row a child of that row.
 *
 *   $tv = new Treeview($win->getId(), [
 *       'columns'  => ['name', 'size', 'modified'],
 *       'headings' => ['Name', 'Size', 'Modified'],
 *       'show'     => 'headings',          // or 'tree headings' for trees
 *   ]);
 *   $row1 = $tv->insert(null, ['report.pdf', '1.2MB', '2026-01-15']);
 *   $tv->onSelect(fn(array $rows) => print_r($rows));
 *
 * @package PhpGui\Widget
 */
class Treeview extends AbstractWidget
{
    /** Auto-incrementing counter for generated row ids. */
    private int $rowCounter = 0;

    /** Real Tk-iid used for each logical row id we hand back. */
    private array $rowIds = [];

    /** @var list<string> Column logical names, in display order. */
    private array $columns;

    public function __construct(string $parentId, array $options = [])
    {
        $columns = $options['columns'] ?? [];
        if (!is_array($columns)) {
            throw new \InvalidArgumentException(
                'Treeview "columns" option must be a list of column names.'
            );
        }
        $this->columns = array_values(array_map('strval', $columns));

        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void
    {
        $extra = $this->buildOptionString(['columns', 'headings', 'show']);

        // Build the -columns list safely. Column names show up in cget
        // queries and column commands, so they should be Tcl-safe.
        $colArg = '';
        if ($this->columns !== []) {
            $quoted = array_map(fn($c) => self::tclQuote($c), $this->columns);
            $colArg = ' -columns [list ' . implode(' ', $quoted) . ']';
        }

        // Default `show` to 'headings' when columns are given (table-style).
        // Pass 'tree headings' for a tree with column headings, or 'tree'
        // for a plain hierarchy.
        $show = (string) ($this->options['show'] ?? ($this->columns !== [] ? 'headings' : 'tree'));
        $showArg = ' -show ' . self::tclQuote($show);

        $this->tcl->evalTcl(
            "ttk::treeview {$this->tclPath}{$colArg}{$showArg}{$extra}"
        );

        // Apply column headings if provided.
        if (isset($this->options['headings']) && is_array($this->options['headings'])) {
            foreach ($this->options['headings'] as $i => $title) {
                if (!isset($this->columns[$i])) {
                    continue;
                }
                $this->setHeading($this->columns[$i], (string) $title);
            }
        }
    }

    /**
     * Insert a row.
     *
     * - `$parentId` is `null` for top-level rows; pass another row's id to
     *   nest. Hierarchical mode requires `show` to include `'tree'`.
     * - `$values` may be a positional list aligned with `columns`, or an
     *   associative array keyed by column name. Extra/missing columns are
     *   filled with empty strings.
     * - `$options` supports `text` (the tree-column label, distinct from
     *   column values), `image`, `open`, `tags`.
     *
     * Returns the row id (a string) for use as `$parentId` in further
     * inserts, or with `select()`, `delete()`, `setValue()`, etc.
     */
    public function insert(?string $parentId, array $values = [], array $options = []): string
    {
        $parentArg = $parentId === null ? '{}' : self::tclQuote($this->resolveRowId($parentId));

        $rowId = $this->generateRowId();
        $this->rowIds[$rowId] = $rowId;

        $valArgs = '';
        if ($values !== []) {
            $ordered = $this->orderValues($values);
            $quoted  = array_map(fn($v) => self::tclQuote((string) $v), $ordered);
            $valArgs = ' -values [list ' . implode(' ', $quoted) . ']';
        }

        $optArgs = '';
        $allowedOpts = ['text', 'image', 'open', 'tags'];
        foreach ($options as $key => $value) {
            if (!in_array($key, $allowedOpts, true)) {
                continue;
            }
            if ($key === 'tags' && is_array($value)) {
                $tagList = array_map(fn($t) => self::tclQuote((string) $t), $value);
                $optArgs .= ' -tags [list ' . implode(' ', $tagList) . ']';
            } else {
                $optArgs .= ' -' . $key . ' ' . self::tclQuote((string) $value);
            }
        }

        $this->tcl->evalTcl(
            "{$this->tclPath} insert {$parentArg} end -id "
                . self::tclQuote($rowId)
                . $valArgs . $optArgs
        );

        return $rowId;
    }

    /** Read a single column's value for `$rowId`. Returns `null` if missing. */
    public function getValue(string $rowId, string $column): ?string
    {
        $resolved = $this->resolveRowId($rowId);
        if (!in_array($column, $this->columns, true)) {
            throw new \InvalidArgumentException("Unknown column '{$column}'.");
        }
        $var = 'phpgui_tv_get_' . $this->id;
        $this->tcl->evalTcl(
            "set {$var} [{$this->tclPath} set " . self::tclQuote($resolved)
                . ' ' . self::tclQuote($column) . ']'
        );
        $result = $this->tcl->getVar($var);
        return $result;
    }

    /**
     * Read every column for `$rowId` as `[column => value]`.
     *
     * @return array<string,string>
     */
    public function getValues(string $rowId): array
    {
        $out = [];
        foreach ($this->columns as $col) {
            $out[$col] = (string) $this->getValue($rowId, $col);
        }
        return $out;
    }

    /** Update one column's value. */
    public function setValue(string $rowId, string $column, string $value): void
    {
        $resolved = $this->resolveRowId($rowId);
        if (!in_array($column, $this->columns, true)) {
            throw new \InvalidArgumentException("Unknown column '{$column}'.");
        }
        $this->tcl->setVar('phpgui_tv_set_val_' . $this->id, $value);
        $this->tcl->evalTcl(
            "{$this->tclPath} set " . self::tclQuote($resolved)
                . ' ' . self::tclQuote($column)
                . ' $phpgui_tv_set_val_' . $this->id
        );
    }

    /** Replace all column values for a row at once (positional or keyed). */
    public function setValues(string $rowId, array $values): void
    {
        foreach ($this->orderValues($values, true) as $col => $val) {
            $this->setValue($rowId, $col, (string) $val);
        }
    }

    /**
     * Set the tree-column label for a row (only meaningful when `show`
     * includes `'tree'`).
     */
    public function setText(string $rowId, string $text): void
    {
        $resolved = $this->resolveRowId($rowId);
        $this->tcl->evalTcl(
            "{$this->tclPath} item " . self::tclQuote($resolved)
                . ' -text ' . self::tclQuote($text)
        );
    }

    public function getText(string $rowId): string
    {
        $resolved = $this->resolveRowId($rowId);
        return trim($this->tcl->evalTcl(
            "{$this->tclPath} item " . self::tclQuote($resolved) . ' -text'
        ));
    }

    /** Remove a row (and any descendants). */
    public function delete(string $rowId): void
    {
        $resolved = $this->resolveRowId($rowId);
        $this->tcl->evalTcl(
            "{$this->tclPath} delete " . self::tclQuote($resolved)
        );
        unset($this->rowIds[$rowId]);
    }

    /** Remove every row. */
    public function clear(): void
    {
        // `treeview delete` takes a SINGLE list argument (not multiple
        // args), so we hand the result of `children` directly without
        // splatting. Skipping when empty avoids "wrong # args".
        $children = trim($this->tcl->evalTcl("{$this->tclPath} children {}"));
        if ($children !== '') {
            $this->tcl->evalTcl(
                "{$this->tclPath} delete [{$this->tclPath} children {}]"
            );
        }
        $this->rowIds = [];
    }

    /** Total row count at the top level (does not recurse). */
    public function getTopLevelCount(): int
    {
        $children = trim($this->tcl->evalTcl("{$this->tclPath} children {}"));
        if ($children === '') {
            return 0;
        }
        return count(preg_split('/\s+/', $children) ?: []);
    }

    /**
     * Configure a column heading and optionally its width / anchor / sort
     * behaviour.
     */
    public function setHeading(string $column, string $title, array $options = []): void
    {
        if (!in_array($column, $this->columns, true)) {
            throw new \InvalidArgumentException("Unknown column '{$column}'.");
        }
        $cmd = "{$this->tclPath} heading " . self::tclQuote($column)
            . ' -text ' . self::tclQuote($title);
        foreach ($options as $key => $value) {
            $cmd .= ' -' . $key . ' ' . self::tclQuote((string) $value);
        }
        $this->tcl->evalTcl($cmd);
    }

    /**
     * Configure column display: width (px), minwidth, anchor, stretch.
     */
    public function setColumn(string $column, array $options): void
    {
        if (!in_array($column, $this->columns, true)) {
            throw new \InvalidArgumentException("Unknown column '{$column}'.");
        }
        $cmd = "{$this->tclPath} column " . self::tclQuote($column);
        foreach ($options as $key => $value) {
            $cmd .= ' -' . $key . ' ' . self::tclQuote((string) $value);
        }
        $this->tcl->evalTcl($cmd);
    }

    /** Currently-selected row ids (multi-select capable). Empty if none. */
    public function getSelected(): array
    {
        $raw = trim($this->tcl->evalTcl("{$this->tclPath} selection"));
        if ($raw === '') {
            return [];
        }
        return preg_split('/\s+/', $raw) ?: [];
    }

    /** First selected row id, or null. */
    public function getSelectedRow(): ?string
    {
        $rows = $this->getSelected();
        return $rows === [] ? null : $rows[0];
    }

    /** Replace the selection with `$rowIds`. Empty array clears it. */
    public function setSelected(array $rowIds): void
    {
        if ($rowIds === []) {
            $this->tcl->evalTcl("{$this->tclPath} selection set {}");
            return;
        }
        $resolved = array_map(
            fn($id) => self::tclQuote($this->resolveRowId((string) $id)),
            $rowIds
        );
        $this->tcl->evalTcl(
            "{$this->tclPath} selection set [list " . implode(' ', $resolved) . ']'
        );
    }

    /**
     * Register a handler for the `<<TreeviewSelect>>` virtual event.
     * Receives the list of selected row ids.
     */
    public function onSelect(callable $handler): void
    {
        $cbId = $this->id . '_select';
        ProcessTCL::getInstance()->registerCallback($cbId, function () use ($handler) {
            $handler($this->getSelected());
        });
        $this->tcl->evalTcl(
            "bind {$this->tclPath} <<TreeviewSelect>> "
                . '{php::executeCallback ' . $cbId . '}'
        );
    }

    /**
     * Register a handler for double-click on a row. Receives the row id
     * the user clicked on (or null if they double-clicked empty space).
     */
    public function onDoubleClick(callable $handler): void
    {
        $cbId = $this->id . '_dblclick';
        ProcessTCL::getInstance()->registerCallback($cbId, function () use ($handler) {
            // Tk stores the most recent identify-row in our temp variable
            // via the bind script; read it back here.
            $rowId = trim($this->tcl->getVar('phpgui_tv_dbl_' . $this->id));
            $handler($rowId !== '' ? $rowId : null);
        });
        // Capture the row id at click time (focus may shift before our PHP
        // callback runs).
        $this->tcl->evalTcl(
            "bind {$this->tclPath} <Double-Button-1> "
                . '{ set ::phpgui_tv_dbl_' . $this->id
                . ' [' . $this->tclPath . ' identify row %x %y]; '
                . 'php::executeCallback ' . $cbId . ' }'
        );
    }

    /** True if `$rowId` exists in this tree. */
    public function exists(string $rowId): bool
    {
        if (!isset($this->rowIds[$rowId])) {
            return false;
        }
        return trim($this->tcl->evalTcl(
            "{$this->tclPath} exists " . self::tclQuote($rowId)
        )) === '1';
    }

    public function destroy(): void
    {
        $this->tcl->unregisterCallback($this->id . '_select');
        $this->tcl->unregisterCallback($this->id . '_dblclick');
        $this->rowIds = [];
        parent::destroy();
    }

    /** Generate a deterministic, Tcl-safe row id. */
    private function generateRowId(): string
    {
        return 'phpgui_row_' . $this->id . '_' . (++$this->rowCounter);
    }

    private function resolveRowId(string $rowId): string
    {
        // Allow callers to pass the id back verbatim. We don't enforce
        // membership in $this->rowIds because Tk row ids can also come
        // from user-driven events (e.g. identify row).
        return $rowId;
    }

    /**
     * Normalise a values array to either an ordered list (positional) or
     * to `[column => value]` (when `$keyed` is true). Missing columns are
     * filled with empty strings.
     */
    private function orderValues(array $values, bool $keyed = false)
    {
        $isAssoc = $values !== [] && array_keys($values) !== range(0, count($values) - 1);

        $out = [];
        if ($isAssoc) {
            foreach ($this->columns as $col) {
                $out[$col] = $values[$col] ?? '';
            }
        } else {
            $i = 0;
            foreach ($this->columns as $col) {
                $out[$col] = $values[$i] ?? '';
                $i++;
            }
        }

        return $keyed ? $out : array_values($out);
    }
}
