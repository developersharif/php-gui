<?php
namespace PhpGui\Widget;

/**
 * Class Canvas
 * Represents a canvas widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Canvas extends AbstractWidget {
    protected function create(): void {
        $this->tcl->evalTcl("canvas .{$this->parentId}.{$this->id}");
    }
}
