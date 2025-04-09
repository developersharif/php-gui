<?php
namespace PhpGui\Widget;

/**
 * Class Frame
 * Represents a frame widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Frame extends AbstractWidget {
    protected function create(): void {
        $this->tcl->evalTcl("frame .{$this->parentId}.{$this->id}");
    }
}
