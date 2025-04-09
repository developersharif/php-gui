<?php
namespace PhpGui\Widget;

/**
 * Class Menubutton
 * Represents a menubutton widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Menubutton extends AbstractWidget {
    protected function create(): void {
        $text = $this->options['text'] ?? 'Menubutton';
        $this->tcl->evalTcl("menubutton .{$this->parentId}.{$this->id} -text \"{$text}\"");
        $this->tcl->evalTcl("menu .{$this->parentId}.m_{$this->id} -tearoff 0");
        $this->tcl->evalTcl(".{$this->parentId}.{$this->id} configure -menu .{$this->parentId}.m_{$this->id}");
    }
}
