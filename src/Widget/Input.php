<?php

namespace PhpGui\Widget;

/**
 * Class Input
 * Represents an input widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Input extends AbstractWidget
{
    public function __construct(string $parentId, array $options = [])
    {
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void
    {
        $defaultText = (string) ($this->options['text'] ?? '');
        $extra = $this->buildOptionString(['text', 'command']);
        $this->tcl->evalTcl(
            "entry {$this->tclPath} -textvariable {$this->id}{$extra}"
        );
        $this->tcl->setVar($this->id, $defaultText);
    }

    public function getValue(): string
    {
        return $this->tcl->getVar($this->id);
    }

    public function setValue(string $text): void
    {
        $this->tcl->setVar($this->id, $text);
    }

    public function onEnter(callable $callback): void
    {
        \PhpGui\ProcessTCL::getInstance()->registerCallback($this->id, $callback);
        $this->tcl->evalTcl(
            "bind {$this->tclPath} <Return> {php::executeCallback {$this->id}}"
        );
    }
}
