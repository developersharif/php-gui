<?php
namespace PhpGui\Widget;

/**
 * Class Combobox
 * Represents a combobox widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Combobox extends AbstractWidget
{
    public function __construct(string $parentId, array $options = [])
    {
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void
    {
        // Accept both an array of items and a pre-formatted Tcl-list string,
        // so old callers passing "a b c" still work.
        $values = $this->options['values'] ?? [];
        if (is_string($values)) {
            $values = strlen($values) === 0 ? [] : preg_split('/\s+/', $values);
        }

        $valuesList = $this->buildTclList((array) $values);

        // ttk is built into Tk 8.5+; no separate package require needed.
        $this->tcl->evalTcl(
            "ttk::combobox {$this->tclPath} -textvariable {$this->id} -values {$valuesList}"
        );
    }

    public function getValue(): string
    {
        return $this->tcl->getVar($this->id);
    }

    public function setValue(string $value): void
    {
        $this->tcl->setVar($this->id, $value);
    }

    /**
     * Build a `[list "a" "b" "c"]` Tcl substitution that produces a proper
     * Tcl list with every element safely quoted. Using the `list` command
     * is the only injection-proof way to construct a multi-item list when
     * the elements may contain arbitrary user text.
     */
    private function buildTclList(array $items): string
    {
        if ($items === []) {
            return '{}';
        }
        $quoted = array_map(
            fn($v) => self::tclQuote((string) $v),
            $items
        );
        return '[list ' . implode(' ', $quoted) . ']';
    }
}
