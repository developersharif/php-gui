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
        $text = $this->options['text'] ?? '';
        $extra = $this->getOptionString();
        $this->tcl->evalTcl("label .{$this->parentId}.{$this->id} -text \"{$text}\" {$extra}");
    }

    protected function getOptionString(): string
    {
        $opts = "";
        foreach ($this->options as $key => $value) {
            if ($key === 'text') continue;
            $opts .= " -$key \"$value\"";
        }
        return $opts;
    }

    public function setText(string $text): void
    {
        $this->tcl->evalTcl(".{$this->parentId}.{$this->id} configure -text \"{$text}\"");
    }

    public function setFont(string $font): void
    {
        $this->tcl->evalTcl(".{$this->parentId}.{$this->id} configure -font \"{$font}\"");
    }

    public function setForeground(string $color): void
    {
        $this->tcl->evalTcl(".{$this->parentId}.{$this->id} configure -fg \"{$color}\"");
    }

    public function setBackground(string $color): void
    {
        $this->tcl->evalTcl(".{$this->parentId}.{$this->id} configure -bg \"{$color}\"");
    }

    public function setState(string $state): void
    {
        $this->tcl->evalTcl(".{$this->parentId}.{$this->id} configure -state \"{$state}\"");
    }
}
