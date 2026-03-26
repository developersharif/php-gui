<?php
namespace PhpGui\Widget;

/**
 * Class Frame
 * Represents a frame widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Frame extends AbstractWidget {
    public function __construct(string $parentId, array $options = []) {
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void {
        $this->tcl->evalTcl("frame .{$this->parentId}.{$this->id}");
    }
}
