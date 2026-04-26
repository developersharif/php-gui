<?php

namespace PhpGui\Widget;

/**
 * Class LabelFrame
 * Container with a titled border — wraps Tk's `ttk::labelframe`. Visually
 * a frame whose top edge is broken by a small title label, useful for
 * grouping related controls in a settings dialog.
 *
 *   $box = new LabelFrame($win->getId(), ['text' => 'Network']);
 *   $box->pack(['fill' => 'x', 'padx' => 8, 'pady' => 8]);
 *
 *   new Label($box->getId(), ['text' => 'Hostname:'])->pack();
 *   new Input($box->getId())->pack();
 *
 * @package PhpGui\Widget
 */
class LabelFrame extends AbstractWidget
{
    public function __construct(string $parentId, array $options = [])
    {
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void
    {
        $text  = (string) ($this->options['text'] ?? '');
        $extra = $this->buildOptionString(['text']);

        $textArg = $text === '' ? '' : ' -text ' . self::tclQuote($text);
        $this->tcl->evalTcl(
            "ttk::labelframe {$this->tclPath}{$textArg}{$extra}"
        );
    }

    /** Update the title shown along the frame's top border. */
    public function setText(string $text): void
    {
        $this->tcl->evalTcl(
            "{$this->tclPath} configure -text " . self::tclQuote($text)
        );
    }

    public function getText(): string
    {
        return trim($this->tcl->evalTcl("{$this->tclPath} cget -text"));
    }
}
