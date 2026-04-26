<?php
namespace PhpGui\Widget;

/**
 * Class Menubutton
 * Represents a menubutton widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Menubutton extends AbstractWidget {
    public function __construct(string $parentId, array $options = []) {
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void {
        $text = $this->options['text'] ?? 'Menubutton';
        $menuPath = "{$this->parentTclPath}.m_{$this->id}";
        $this->tcl->evalTcl("menubutton {$this->tclPath} -text \"{$text}\"");
        $this->tcl->evalTcl("menu {$menuPath} -tearoff 0");
        $this->tcl->evalTcl("{$this->tclPath} configure -menu {$menuPath}");
    }
}
