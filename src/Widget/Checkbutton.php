<?php

namespace PhpGui\Widget;

use PhpGui\ProcessTCL;

class Checkbutton extends AbstractWidget
{
    private $callback;
    private $variable;

    public function __construct(string $parentId, array $options = [])
    {
        parent::__construct($parentId, $options);
        $this->callback = $options['command'] ?? null;
        $this->variable = "cb_var_" . $this->id;
        $this->create();
    }

    protected function create(): void
    {
        $text = $this->options['text'] ?? 'Checkbutton';
        $extra = $this->getOptionString();

        // Initialize variable and register callback if provided
        $this->tcl->evalTcl("set {$this->variable} 0");

        if ($this->callback) {
            ProcessTCL::getInstance()->registerCallback($this->id, $this->callback);
            $this->tcl->evalTcl("checkbutton .{$this->parentId}.{$this->id} -text \"{$text}\" -variable {$this->variable} -command {php::executeCallback {$this->id}} {$extra}");
        } else {
            $this->tcl->evalTcl("checkbutton .{$this->parentId}.{$this->id} -text \"{$text}\" -variable {$this->variable} {$extra}");
        }
    }

    public function setChecked(bool $state): void
    {
        $value = $state ? 1 : 0;
        $this->tcl->evalTcl("set {$this->variable} $value");
    }

    public function isChecked(): bool
    {
        return (bool)$this->tcl->evalTcl("set {$this->variable}");
    }

    public function toggle(): void
    {
        $this->setChecked(!$this->isChecked());
    }

    protected function getOptionString(): string
    {
        $opts = "";
        foreach ($this->options as $key => $value) {
            if (in_array($key, ['text', 'command'])) continue;
            $opts .= " -$key \"$value\"";
        }
        return $opts;
    }
}
