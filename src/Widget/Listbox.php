<?php

namespace PhpGui\Widget;

use PhpGui\ProcessTCL;

/**
 * Class Listbox
 * Selectable list of strings — wraps Tk's `listbox` command. Pair with
 * `Scrollbar::attachTo()` for tall content.
 *
 * Three selection modes:
 *   - 'browse'   (default) — single-select, navigate with arrow keys.
 *   - 'single'   — single-select, click only.
 *   - 'multiple' — multi-select with toggle on click.
 *   - 'extended' — Shift/Ctrl multi-select like a file picker.
 *
 * @package PhpGui\Widget
 */
class Listbox extends AbstractWidget
{
    private const VALID_SELECT_MODES = ['browse', 'single', 'multiple', 'extended'];

    public function __construct(string $parentId, array $options = [])
    {
        parent::__construct($parentId, $options);

        if (isset($options['selectmode'])
            && !in_array($options['selectmode'], self::VALID_SELECT_MODES, true)
        ) {
            throw new \InvalidArgumentException(
                "Listbox selectmode must be one of "
                . implode(', ', self::VALID_SELECT_MODES)
                . ", got '{$options['selectmode']}'."
            );
        }
        $this->create();
    }

    protected function create(): void
    {
        $extra = $this->buildOptionString(['items']);
        $this->tcl->evalTcl("listbox {$this->tclPath}{$extra}");

        if (isset($this->options['items']) && is_array($this->options['items'])) {
            foreach ($this->options['items'] as $item) {
                $this->addItem((string) $item);
            }
        }
    }

    /**
     * Append `$item` to the end of the list. Routes through a Tcl variable
     * so user content can't inject Tcl regardless of which characters it
     * contains.
     */
    public function addItem(string $item): void
    {
        $var = 'phpgui_lb_item_' . $this->id;
        $this->tcl->setVar($var, $item);
        $this->tcl->evalTcl("{$this->tclPath} insert end \$" . $var);
    }

    /**
     * Replace every item in one call. More efficient than `clear()` +
     * looped `addItem()` because each insert/delete forces a Tk reflow.
     *
     * @param list<string> $items
     */
    public function setItems(array $items): void
    {
        $this->clear();
        foreach ($items as $item) {
            $this->addItem((string) $item);
        }
    }

    /**
     * Remove the item at `$index` (0-based). Negative or out-of-range
     * indices are silently ignored, matching Tk's behaviour.
     */
    public function removeItem(int $index): void
    {
        if ($index < 0) {
            return;
        }
        $this->tcl->evalTcl("{$this->tclPath} delete {$index}");
    }

    /** Remove every item. */
    public function clear(): void
    {
        $this->tcl->evalTcl("{$this->tclPath} delete 0 end");
    }

    /** Total item count. */
    public function size(): int
    {
        return (int) trim($this->tcl->evalTcl("{$this->tclPath} size"));
    }

    /**
     * Get the item at `$index`, or null if out of range.
     */
    public function getItem(int $index): ?string
    {
        if ($index < 0 || $index >= $this->size()) {
            return null;
        }
        $var = 'phpgui_lb_get_' . $this->id;
        $this->tcl->evalTcl("set {$var} [{$this->tclPath} get {$index}]");
        return $this->tcl->getVar($var);
    }

    /** All items as a flat list (in display order). */
    public function getAllItems(): array
    {
        $count = $this->size();
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = $this->getItem($i) ?? '';
        }
        return $items;
    }

    /**
     * Indices of currently selected items (0-based, ascending). Empty
     * array if nothing is selected. Works for all selection modes.
     *
     * @return list<int>
     */
    public function getSelectedIndices(): array
    {
        $raw = trim($this->tcl->evalTcl("{$this->tclPath} curselection"));
        if ($raw === '') {
            return [];
        }
        return array_map('intval', preg_split('/\s+/', $raw) ?: []);
    }

    /**
     * Index of the first selected item, or null if nothing selected.
     * Convenient shorthand for browse/single mode.
     */
    public function getSelectedIndex(): ?int
    {
        $indices = $this->getSelectedIndices();
        return $indices === [] ? null : $indices[0];
    }

    /**
     * Selected items as strings. Same ordering as `getSelectedIndices()`.
     *
     * @return list<string>
     */
    public function getSelectedItems(): array
    {
        $items = [];
        foreach ($this->getSelectedIndices() as $idx) {
            $value = $this->getItem($idx);
            if ($value !== null) {
                $items[] = $value;
            }
        }
        return $items;
    }

    /**
     * Replace the selection with `$indices` (any out-of-range entries are
     * silently dropped). Pass `[]` to clear.
     *
     * @param list<int> $indices
     */
    public function setSelection(array $indices): void
    {
        $this->tcl->evalTcl("{$this->tclPath} selection clear 0 end");
        $size = $this->size();
        foreach ($indices as $idx) {
            $idx = (int) $idx;
            if ($idx < 0 || $idx >= $size) {
                continue;
            }
            $this->tcl->evalTcl("{$this->tclPath} selection set {$idx}");
        }
    }

    /**
     * Register a handler for the `<<ListboxSelect>>` virtual event — fires
     * whenever the selection changes (mouse, keyboard, or programmatic).
     * The handler receives the Listbox instance so it can pull the
     * current selection without re-capturing it.
     */
    public function onSelect(callable $callback): void
    {
        $cbId = $this->id . '_select';
        ProcessTCL::getInstance()->registerCallback($cbId, function () use ($callback) {
            $callback($this);
        });
        $this->tcl->evalTcl(
            "bind {$this->tclPath} <<ListboxSelect>> {php::executeCallback {$cbId}}"
        );
    }

    public function destroy(): void
    {
        $this->tcl->unregisterCallback($this->id . '_select');
        parent::destroy();
    }
}
