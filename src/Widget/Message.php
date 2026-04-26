<?php
namespace PhpGui\Widget;

/**
 * Class Message
 * Represents a message dialog in the GUI.
 *
 * @package PhpGui\Widget
 */
class Message extends AbstractWidget {

    public function __construct(?string $parentId, array $options = []) {
        parent::__construct($parentId, $options);
        $this->create();
    }
    
    protected function create(): void {
        $text = $this->options['text'] ?? 'Message';
        $path = $this->tclPath;
        $this->tcl->evalTcl("toplevel {$path}");
        $this->tcl->evalTcl("wm title {$path} \"Message\"");
        $this->tcl->evalTcl("label {$path}.msg -text \"{$text}\"");
        $this->tcl->evalTcl("pack {$path}.msg -padx 20 -pady 20");
        $this->tcl->evalTcl("button {$path}.ok -text \"OK\" -command {destroy {$path}}");
        $this->tcl->evalTcl("pack {$path}.ok -pady 10");
        $this->tcl->evalTcl("update idletasks");
    }

    public function setText(string $text): void {
        $path = $this->tclPath;
        $this->tcl->evalTcl("if {[winfo exists {$path}.msg]} { {$path}.msg configure -text \"{$text}\" }");
    }
}
