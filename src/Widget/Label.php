<?php

namespace PhpGui\Widget;

/**
 * Class Label
 * Represents a label widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Label extends AbstractWidget
{
    public function __construct(string $parentId, array $options = [])
    {
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void
    {
        $text = (string) ($this->options['text'] ?? '');
        $extra = $this->buildOptionString(['text']);
        $this->tcl->evalTcl(
            "label {$this->tclPath} -text " . self::tclQuote($text) . $extra
        );
    }

    public function setText(string $text): void
    {
        $this->tcl->evalTcl(
            "{$this->tclPath} configure -text " . self::tclQuote($text)
        );
    }

    public function setFont(string $font): void
    {
        $this->tcl->evalTcl(
            "{$this->tclPath} configure -font " . self::tclQuote($font)
        );
    }

    public function setForeground(string $color): void
    {
        $this->tcl->evalTcl(
            "{$this->tclPath} configure -fg " . self::tclQuote($color)
        );
    }

    public function setBackground(string $color): void
    {
        $this->tcl->evalTcl(
            "{$this->tclPath} configure -bg " . self::tclQuote($color)
        );
    }

    public function setState(string $state): void
    {
        $this->tcl->evalTcl(
            "{$this->tclPath} configure -state " . self::tclQuote($state)
        );
    }
}
