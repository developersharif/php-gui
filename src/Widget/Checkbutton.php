<?php
namespace PhpGui\Widget;

class Checkbutton extends AbstractWidget {
    protected function create(): void {
        $text = $this->options['text'] ?? 'Checkbutton';
        $this->tcl->evalTcl("checkbutton .{$this->parentId}.{$this->id} -text \"{$text}\"");
    }
    
    public function setChecked(bool $state): void {
        $value = $state ? 1 : 0;
        $this->tcl->evalTcl("set {$this->id} $value");
    }
    
    public function isChecked(): bool {
        $this->tcl->evalTcl("set _val [set {$this->id}]");
        return (bool)$this->tcl->getResult();
    }
}
