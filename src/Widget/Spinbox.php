<?php

namespace PhpGui\Widget;

use PhpGui\ProcessTCL;

/**
 * Class Spinbox
 * Bounded numeric input — wraps Tk's `ttk::spinbox`. The user can type a
 * value or use the up/down buttons to step within `[from, to]` by
 * `increment`. For finite enumerations, pass `values` instead of from/to.
 *
 *   $qty = new Spinbox($win->getId(), [
 *       'from'      => 1,
 *       'to'        => 99,
 *       'increment' => 1,
 *   ]);
 *   $qty->onChange(fn($v) => echo "qty = {$v}\n");
 *
 * @package PhpGui\Widget
 */
class Spinbox extends AbstractWidget
{
    private string $variable;

    public function __construct(string $parentId, array $options = [])
    {
        parent::__construct($parentId, $options);
        $this->variable = 'phpgui_spin_' . $this->id;
        $this->create();
    }

    protected function create(): void
    {
        // Skip `value` — that's our initial-value shorthand, not a real Tk
        // option. Tk's -value would be partial-matched to -values and clobber
        // the enumeration list when both are set.
        $extra = $this->buildOptionString(['command', 'values', 'value']);

        // Initial value: explicit `value` option > `from` > 0
        $initial = (string) ($this->options['value'] ?? $this->options['from'] ?? '0');
        $this->tcl->setVar($this->variable, $initial);

        // -values takes a Tcl list. Build it through tclQuote so each
        // enumeration entry can contain arbitrary characters safely.
        $valuesArg = '';
        if (isset($this->options['values']) && is_array($this->options['values'])) {
            $quoted = array_map(
                fn($v) => self::tclQuote((string) $v),
                $this->options['values']
            );
            $valuesArg = ' -values [list ' . implode(' ', $quoted) . ']';
        }

        $this->tcl->evalTcl(
            "ttk::spinbox {$this->tclPath} -textvariable {$this->variable}{$valuesArg}{$extra}"
        );
    }

    /** Current value as a string (preserves user typing — "01" stays "01"). */
    public function getValue(): string
    {
        return $this->tcl->getVar($this->variable);
    }

    /** Same as `getValue()` but cast to float. */
    public function getNumericValue(): float
    {
        return (float) $this->getValue();
    }

    public function setValue(string $value): void
    {
        $this->tcl->setVar($this->variable, $value);
        if ($this->onChangeHandler !== null) {
            ($this->onChangeHandler)($this->getValue());
        }
    }

    /** @var callable|null */
    private $onChangeHandler = null;

    /**
     * Register a handler for value changes. Fires on user typing (after
     * the field commits, e.g. focus-out or Enter), the up/down buttons,
     * and programmatic `setValue()`. Receives the new value as a string.
     */
    public function onChange(callable $handler): void
    {
        $this->onChangeHandler = $handler;
        $cbId = $this->id . '_change';
        ProcessTCL::getInstance()->registerCallback($cbId, function () use ($handler) {
            $handler($this->getValue());
        });
        // ttk::spinbox fires <<Increment>> / <<Decrement>> for the buttons
        // and -command on each. Bind the same callback to <Return> and
        // <FocusOut> so direct typing also triggers it.
        $this->tcl->evalTcl(
            "{$this->tclPath} configure -command {php::executeCallback {$cbId}}"
        );
        $this->tcl->evalTcl(
            "bind {$this->tclPath} <Return>   {php::executeCallback {$cbId}}"
        );
        $this->tcl->evalTcl(
            "bind {$this->tclPath} <FocusOut> {php::executeCallback {$cbId}}"
        );
    }

    public function destroy(): void
    {
        $this->tcl->unregisterCallback($this->id . '_change');
        parent::destroy();
    }
}
