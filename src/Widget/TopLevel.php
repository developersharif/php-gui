<?php
namespace PhpGui\Widget;

/**
 * Class TopLevel
 * Represents a top-level window in the GUI.
 *
 * @package PhpGui\Widget
 */
class TopLevel extends AbstractWidget {
    public function __construct(array $options = []) {
        parent::__construct(null, $options); // null parent for top-level widget
        $this->create();
    }
    
    protected function create(): void {
        $text = $this->options['text'] ?? 'Top Level';
        $this->tcl->evalTcl("toplevel .{$this->id}");
        $this->tcl->evalTcl("label .{$this->id}.child -text \"{$text}\"");
    }
    
    public function chooseColor(): string {
        $this->tcl->evalTcl("set chosen [tk_chooseColor -initialcolor red]");
        return $this->tcl->getResult();
    }
    
    public function chooseDirectory(): string {
        $this->tcl->evalTcl("set dir [tk_chooseDirectory -initialdir \"/tmp\"]");
        return $this->tcl->getResult();
    }
    
    public function dialog(string $title, string $message, string $icon, string $option1, string $option2, string $extra): string {
        $this->tcl->evalTcl("set result [tk_dialog .dialog \"$title\" \"$message\" \"$icon\" \"$option1\" \"$option2\" \"$extra\"]");
        return $this->tcl->getResult();
    }
    
    public function getOpenFile(): string {
        $this->tcl->evalTcl("set open [tk_getOpenFile -initialdir \"/tmp\"]");
        return $this->tcl->getResult();
    }
    
    public function getSaveFile(): string {
        $this->tcl->evalTcl("set save [tk_getSaveFile -initialdir \"/tmp\"]");
        return $this->tcl->getResult();
    }
    
    public function messageBox(string $message, string $type): string {
        $this->tcl->evalTcl("set mresult [tk_messageBox -message \"$message\" -type $type]");
        return $this->tcl->getResult();
    }
    
    public function popupMenu(int $x, int $y): string {
        $this->tcl->evalTcl("menu .popup -tearoff 0");
        $this->tcl->evalTcl("set presult [tk_popup .popup $x $y]");
        $result = $this->tcl->getResult();
        $this->tcl->evalTcl("destroy .popup");
        return $result;
    }
    
    public function setText(string $text): void {
        $this->tcl->evalTcl(".{$this->id}.child configure -text \"{$text}\"");
    }
    
    public function destroy(): void {
        $this->tcl->evalTcl("destroy .{$this->id}");
    }
}
