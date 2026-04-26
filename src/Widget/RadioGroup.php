<?php

namespace PhpGui\Widget;

use PhpGui\ProcessTCL;

/**
 * Class RadioGroup
 * Represents a set of mutually-exclusive `Radiobutton` widgets that share
 * a single Tcl variable. Selecting one button updates the variable, which
 * Tk uses to deselect the others automatically.
 *
 * Build the group first, then pass it to each Radiobutton:
 *
 *   $group = new RadioGroup('low');
 *   new Radiobutton($win, $group, 'low',  ['text' => 'Low']);
 *   new Radiobutton($win, $group, 'high', ['text' => 'High']);
 *   $group->getValue();   // 'low'
 *   $group->setValue('high');
 *
 * @package PhpGui\Widget
 */
class RadioGroup
{
    private static int $counter = 0;

    private ProcessTCL $tcl;
    private string $varName;
    private string $defaultValue;

    /** @var list<callable> */
    private array $changeHandlers = [];

    public function __construct(string $defaultValue = '')
    {
        $this->tcl = ProcessTCL::getInstance();
        // Tcl variable name must be unique per group and free of special
        // characters; counter + uniqid covers both.
        $this->varName = 'phpgui_radio_' . (++self::$counter) . '_' . substr(uniqid(), -6);
        $this->defaultValue = $defaultValue;
        $this->tcl->setVar($this->varName, $defaultValue);
    }

    /** Tcl variable name backing this group. Used by Radiobutton::create(). */
    public function getVariableName(): string
    {
        return $this->varName;
    }

    /** Currently selected value; default value if nothing has been picked. */
    public function getValue(): string
    {
        return $this->tcl->getVar($this->varName);
    }

    /**
     * Programmatically select a button by value. Tk updates every radio
     * sharing this group automatically.
     */
    public function setValue(string $value): void
    {
        $this->tcl->setVar($this->varName, $value);
        // Tk fires -command on user clicks but not on programmatic var
        // changes, so we dispatch handlers manually for setValue() calls.
        foreach ($this->changeHandlers as $handler) {
            $handler($value);
        }
    }

    public function getDefault(): string
    {
        return $this->defaultValue;
    }

    /**
     * Register a callback fired whenever the group's selected value
     * changes — by user click on any member radio, or by setValue().
     * Handlers receive the new value as a string.
     *
     * Internal: each Radiobutton::__construct calls registerChangeHandler
     * to wire its own onChange forwards into here, so a single group-wide
     * onChange covers every radio button in the group.
     */
    public function onChange(callable $handler): void
    {
        $this->changeHandlers[] = $handler;
    }

    /** @internal Used by Radiobutton to forward its -command into the group. */
    public function dispatchChange(): void
    {
        $value = $this->getValue();
        foreach ($this->changeHandlers as $handler) {
            $handler($value);
        }
    }
}
