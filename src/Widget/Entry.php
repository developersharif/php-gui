<?php
namespace PhpGui\Widget;

/**
 * Class Entry
 * Represents an entry widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Entry extends AbstractWidget {
    protected function create(): void {
        $defaultText = $this->options['text'] ?? '';
        $this->tcl->evalTcl("entry .{$this->parentId}.{$this->id}");
        $this->tcl->evalTcl("set {$this->id} \"$defaultText\"");
    }
    
    public function getValue(): string {
        $this->tcl->evalTcl("set _val [set {$this->id}]");
        return $this->tcl->getResult();
    }
    
    public function setValue(string $value): void {
        $this->tcl->evalTcl("set {$this->id} \"$value\"");
    }
}
