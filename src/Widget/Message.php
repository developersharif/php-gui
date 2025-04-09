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
        $this->tcl->evalTcl("toplevel {$this->parentId}.{$this->id}");
        $this->tcl->evalTcl("wm title {$this->parentId}.{$this->id} \"Message\"");
        $this->tcl->evalTcl("label {$this->parentId}.{$this->id}.msg -text \"{$text}\"");
        $this->tcl->evalTcl("pack {$this->parentId}.{$this->id}.msg -padx 20 -pady 20");
        $this->tcl->evalTcl("button {$this->parentId}.{$this->id}.ok -text \"OK\" -command {destroy {$this->parentId}.{$this->id}}");
        $this->tcl->evalTcl("pack {$this->parentId}.{$this->id}.ok -pady 10");
        $this->tcl->evalTcl("update idletasks");
    }
    
    public function setText(string $text): void {
        $this->tcl->evalTcl("if {[winfo exists {$this->parentId}.{$this->id}.msg]} { {$this->parentId}.{$this->id}.msg configure -text \"{$text}\" }");
    }
}
