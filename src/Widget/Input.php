<?php

namespace PhpGui\Widget;

/**
 * Class Input
 * Represents an input widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Input extends AbstractWidget {
    public function __construct(string $parentId, array $options = []) {
        parent::__construct($parentId, $options);
        $this->create();
    }
    
    protected function create(): void {
        $defaultText = $this->options['text'] ?? '';
        $extra = $this->getOptionString();
        $this->tcl->evalTcl("entry .{$this->parentId}.{$this->id} -textvariable {$this->id} {$extra}");
        $this->tcl->evalTcl("set {$this->id} \"$defaultText\"");
    }
    
    protected function getOptionString(): string {
        $opts = "";
        foreach ($this->options as $key => $value) {
            // Skip default text configuration
            if (in_array($key, ['text', 'command'])) {
                continue;
            }
            $opts .= " -$key \"$value\"";
        }
        return $opts;
    }
    
    public function getValue(): string {
        $this->tcl->evalTcl("set _val [set {$this->id}]");
        return $this->tcl->getResult();
    }
    
    public function setValue(string $text): void {
        $this->tcl->evalTcl("set {$this->id} \"$text\"");
    }
    
 
    public function onEnter(callable $callback): void {
        \PhpGui\ProcessTCL::getInstance()->registerCallback($this->id, $callback);
        $this->tcl->evalTcl("bind .{$this->parentId}.{$this->id} <Return> {php::executeCallback {$this->id}}");
    }
}
