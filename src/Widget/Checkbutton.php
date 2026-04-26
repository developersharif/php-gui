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
        $this->variable = 'cb_var_' . $this->id;
        $this->create();
    }

    protected function create(): void
    {
        $text  = (string) ($this->options['text'] ?? 'Checkbutton');
        $extra = $this->buildOptionString(['text', 'command']);

        $this->tcl->evalTcl("set {$this->variable} 0");

        $base = "checkbutton {$this->tclPath}"
            . ' -text ' . self::tclQuote($text)
            . " -variable {$this->variable}"
            . $extra;

        if ($this->callback) {
            ProcessTCL::getInstance()->registerCallback($this->id, $this->callback);
            $this->tcl->evalTcl(
                $base . ' -command {php::executeCallback ' . $this->id . '}'
            );
        } else {
            $this->tcl->evalTcl($base);
        }
    }

    public function setChecked(bool $state): void
    {
        $value = $state ? 1 : 0;
        $this->tcl->evalTcl("set {$this->variable} {$value}");
    }

    public function isChecked(): bool
    {
        return (bool) $this->tcl->evalTcl("set {$this->variable}");
    }

    public function toggle(): void
    {
        $this->setChecked(!$this->isChecked());
    }
}
