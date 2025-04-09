<?php

namespace PhpGui\Widget;

/**
 * Class Label
 * Represents a label widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Label extends AbstractWidget {
    public function __construct(string $parentId, array $options = []) {
        parent::__construct($parentId, $options); 
        $this->create();
    }

    protected function create(): void {
        $text = $this->options['text'] ?? '';
        $this->tcl->evalTcl("label .{$this->parentId}.{$this->id} -text \"{$text}\""); // Correct Tcl path
    }

    public function setText(string $text): void {
        $this->tcl->evalTcl(".{$this->parentId}.{$this->id} configure -text \"{$text}\""); // Correct Tcl path
    }
}
