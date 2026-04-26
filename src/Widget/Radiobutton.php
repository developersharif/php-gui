<?php

namespace PhpGui\Widget;

use PhpGui\ProcessTCL;

/**
 * Class Radiobutton
 * Single radio button. Members of the same `RadioGroup` are mutually
 * exclusive — selecting one deselects the others.
 *
 *   $tier = new RadioGroup('basic');
 *   $r1 = new Radiobutton($win, $tier, 'basic', ['text' => 'Basic']);
 *   $r2 = new Radiobutton($win, $tier, 'pro',   ['text' => 'Pro']);
 *   $r1->pack(); $r2->pack();
 *
 *   $tier->onChange(fn($v) => echo "selected: {$v}\n");
 *
 * @package PhpGui\Widget
 */
class Radiobutton extends AbstractWidget
{
    private RadioGroup $group;
    private string $value;

    public function __construct(
        string $parentId,
        RadioGroup $group,
        string $value,
        array $options = []
    ) {
        parent::__construct($parentId, $options);
        $this->group = $group;
        $this->value = $value;
        $this->create();
    }

    protected function create(): void
    {
        $text  = (string) ($this->options['text'] ?? $this->value);
        $extra = $this->buildOptionString(['text', 'command']);

        // Each radio carries its own callback id so we can route the user's
        // -command and the group's onChange dispatch through the same path.
        ProcessTCL::getInstance()->registerCallback($this->id, function () {
            if (isset($this->options['command']) && is_callable($this->options['command'])) {
                ($this->options['command'])($this->value);
            }
            $this->group->dispatchChange();
        });

        $this->tcl->evalTcl(
            "radiobutton {$this->tclPath}"
                . ' -text ' . self::tclQuote($text)
                . ' -variable ' . $this->group->getVariableName()
                . ' -value ' . self::tclQuote($this->value)
                . ' -command {php::executeCallback ' . $this->id . '}'
                . $extra
        );
    }

    /** True when this radio is the currently-selected member of its group. */
    public function isSelected(): bool
    {
        return $this->group->getValue() === $this->value;
    }

    /** Select this radio (updates the shared group variable). */
    public function select(): void
    {
        $this->group->setValue($this->value);
    }

    /** The value this radio represents in its group. */
    public function getValue(): string
    {
        return $this->value;
    }

    /** The group this radio belongs to. */
    public function getGroup(): RadioGroup
    {
        return $this->group;
    }
}
