<?php

namespace PhpGui\Widget;

use PhpGui\ProcessTCL;

/**
 * Class Scale
 * Slider input — wraps Tk's `scale` command. The user drags a thumb along
 * a track to pick a numeric value within `[from, to]`.
 *
 *   $vol = new Scale($win->getId(), [
 *       'from'   => 0,
 *       'to'     => 100,
 *       'orient' => 'horizontal',
 *       'label'  => 'Volume',
 *   ]);
 *   $vol->onChange(fn($v) => echo "volume = {$v}\n");
 *
 * @package PhpGui\Widget
 */
class Scale extends AbstractWidget
{
    private const VALID_ORIENTS = ['horizontal', 'vertical'];

    private string $variable;

    public function __construct(string $parentId, array $options = [])
    {
        if (isset($options['orient']) && !in_array($options['orient'], self::VALID_ORIENTS, true)) {
            throw new \InvalidArgumentException(
                "Scale orient must be 'horizontal' or 'vertical', got '{$options['orient']}'."
            );
        }
        parent::__construct($parentId, $options);
        $this->variable = 'phpgui_scale_' . $this->id;
        $this->create();
    }

    protected function create(): void
    {
        $extra = $this->buildOptionString(['command']);

        // -variable lets us read/write the value via Tcl_GetVar/Tcl_SetVar
        // without parsing strings. Initialise to `from` (Tk's default would
        // do the same, but being explicit makes getValue() correct before
        // the user has touched the slider).
        $from = (float) ($this->options['from'] ?? 0);
        $this->tcl->setVar($this->variable, (string) $from);

        $this->tcl->evalTcl(
            "scale {$this->tclPath} -variable {$this->variable}{$extra}"
        );
    }

    /** Current numeric value. Floats are returned as floats; ints stay ints. */
    public function getValue(): float
    {
        return (float) $this->tcl->getVar($this->variable);
    }

    /**
     * Move the thumb to `$value`. Clamped to `[from, to]` by Tk; we keep the
     * float as-is so callers can pass either ints or floats.
     */
    public function setValue(float $value): void
    {
        $this->tcl->setVar($this->variable, (string) $value);
        // Programmatic var changes don't fire Tk's -command, so dispatch
        // any registered onChange handler manually.
        if ($this->onChangeHandler !== null) {
            ($this->onChangeHandler)($this->getValue());
        }
    }

    /** @var callable|null */
    private $onChangeHandler = null;

    /**
     * Register a handler fired on every value change — both user drag
     * (Tk's -command path) and programmatic `setValue()`. Receives the
     * new value as a float.
     */
    public function onChange(callable $handler): void
    {
        $this->onChangeHandler = $handler;
        $cbId = $this->id . '_change';
        ProcessTCL::getInstance()->registerCallback($cbId, function () use ($handler) {
            $handler($this->getValue());
        });
        // Tk's -command sends the new value as a Tcl arg, but we ignore
        // that and read the variable instead — it's already in sync.
        $this->tcl->evalTcl(
            "{$this->tclPath} configure -command {php::executeCallback {$cbId}}"
        );
    }

    public function getFrom(): float
    {
        return (float) trim($this->tcl->evalTcl("{$this->tclPath} cget -from"));
    }

    public function getTo(): float
    {
        return (float) trim($this->tcl->evalTcl("{$this->tclPath} cget -to"));
    }

    public function destroy(): void
    {
        $this->tcl->unregisterCallback($this->id . '_change');
        parent::destroy();
    }
}
