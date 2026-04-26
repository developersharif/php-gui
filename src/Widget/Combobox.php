<?php
namespace PhpGui\Widget;

/**
 * Class Combobox
 * Represents a combobox widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Combobox extends AbstractWidget {
    public function __construct(string $parentId, array $options = []) {
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void {
        // ttk is built into Tk 8.5+; no separate package require needed after "package require Tk".
        $values = $this->options['values'] ?? '';
        // Use -textvariable so getValue/setValue reflect the actual widget content.
        $this->tcl->evalTcl(
            "ttk::combobox {$this->tclPath} -textvariable {$this->id} -values {{$values}}"
        );
    }

    public function getValue(): string {
        return $this->tcl->getVar($this->id);
    }

    public function setValue(string $value): void {
        $this->tcl->evalTcl("set {$this->id} \"$value\"");
    }
}
