<?php
namespace PhpGui\Widget;
/**
 * Class Menu
 * Represents a menu widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Menu extends AbstractWidget {
    protected function create(): void {
        $this->tcl->evalTcl("menu .{$this->parentId}.{$this->id} -tearoff 0");
    }
}
