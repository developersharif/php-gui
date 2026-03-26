<?php
namespace PhpGui\Widget;

/**
 * Class Entry
 * Represents an entry widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Entry extends AbstractWidget {
    public function __construct(string $parentId, array $options = []) {
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void {
        $defaultText = $this->options['text'] ?? '';
        // Use -textvariable so getValue/setValue reflect the actual widget content.
        $this->tcl->evalTcl("entry .{$this->parentId}.{$this->id} -textvariable {$this->id}");
        $this->tcl->evalTcl("set {$this->id} \"$defaultText\"");
    }

    public function getValue(): string {
        return $this->tcl->getVar($this->id);
    }

    public function setValue(string $value): void {
        $this->tcl->evalTcl("set {$this->id} \"$value\"");
    }
}
