<?php
namespace PhpGui\Widget;

/**
 * Class Combobox
 * Represents a combobox widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Combobox extends AbstractWidget {
    protected function create(): void {
        $this->tcl->evalTcl("package require ttk");
        $values = $this->options['values'] ?? '';
        $this->tcl->evalTcl("ttk::combobox .{$this->parentId}.{$this->id} -values {{$values}}");
    }
    
    public function getValue(): string {
        $this->tcl->evalTcl("set _val [set {$this->id}]");
        return $this->tcl->getResult();
    }
    
    public function setValue(string $value): void {
        $this->tcl->evalTcl("set {$this->id} \"$value\"");
    }
}
