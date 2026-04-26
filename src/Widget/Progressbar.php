<?php

namespace PhpGui\Widget;

/**
 * Class Progressbar
 * Wraps Tk's `ttk::progressbar`. Two operating modes:
 *
 *   - 'determinate' (default) — shows progress from 0 to `maximum`.
 *     Use `setValue()` to update.
 *   - 'indeterminate' — animated bouncing bar for "doing something,
 *     can't measure how much". Drive with `start()` / `stop()`.
 *
 *   $bar = new Progressbar($win->getId(), ['maximum' => 100]);
 *   $bar->setValue(50);                    // determinate at 50%
 *
 *   $busy = new Progressbar($win->getId(), ['mode' => 'indeterminate']);
 *   $busy->start();
 *   // … later …
 *   $busy->stop();
 *
 * @package PhpGui\Widget
 */
class Progressbar extends AbstractWidget
{
    private const VALID_MODES   = ['determinate', 'indeterminate'];
    private const VALID_ORIENTS = ['horizontal', 'vertical'];

    public function __construct(string $parentId, array $options = [])
    {
        if (isset($options['mode']) && !in_array($options['mode'], self::VALID_MODES, true)) {
            throw new \InvalidArgumentException(
                "Progressbar mode must be 'determinate' or 'indeterminate', got '{$options['mode']}'."
            );
        }
        if (isset($options['orient']) && !in_array($options['orient'], self::VALID_ORIENTS, true)) {
            throw new \InvalidArgumentException(
                "Progressbar orient must be 'horizontal' or 'vertical', got '{$options['orient']}'."
            );
        }
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void
    {
        $extra = $this->buildOptionString();
        $this->tcl->evalTcl("ttk::progressbar {$this->tclPath}{$extra}");
    }

    /** Determinate-mode value (0 .. maximum). */
    public function getValue(): float
    {
        return (float) trim($this->tcl->evalTcl("{$this->tclPath} cget -value"));
    }

    /**
     * Set the determinate-mode bar position. Tk silently clamps to
     * `[0, maximum]`; we cast to float so callers can pass either type.
     */
    public function setValue(float $value): void
    {
        $this->tcl->evalTcl("{$this->tclPath} configure -value {$value}");
    }

    /** Add `$amount` to the current value. Convenient for "tick-by-tick" reporting. */
    public function step(float $amount = 1.0): void
    {
        $this->tcl->evalTcl("{$this->tclPath} step {$amount}");
    }

    public function getMaximum(): float
    {
        return (float) trim($this->tcl->evalTcl("{$this->tclPath} cget -maximum"));
    }

    public function setMaximum(float $maximum): void
    {
        $this->tcl->evalTcl("{$this->tclPath} configure -maximum {$maximum}");
    }

    /**
     * Switch between 'determinate' and 'indeterminate'. Switching to
     * indeterminate without starting the animation is a no-op visually.
     */
    public function setMode(string $mode): void
    {
        if (!in_array($mode, self::VALID_MODES, true)) {
            throw new \InvalidArgumentException(
                "Progressbar mode must be 'determinate' or 'indeterminate', got '{$mode}'."
            );
        }
        $this->tcl->evalTcl(
            "{$this->tclPath} configure -mode " . self::tclQuote($mode)
        );
    }

    public function getMode(): string
    {
        return trim($this->tcl->evalTcl("{$this->tclPath} cget -mode"));
    }

    /**
     * Begin animating an indeterminate bar. `$intervalMs` is the time
     * between frames (default 50ms — Tk's default). No-op in determinate
     * mode (Tk silently ignores).
     */
    public function start(int $intervalMs = 50): void
    {
        if ($intervalMs < 1) {
            throw new \InvalidArgumentException(
                "Progressbar start interval must be >= 1ms, got {$intervalMs}."
            );
        }
        $this->tcl->evalTcl("{$this->tclPath} start {$intervalMs}");
    }

    public function stop(): void
    {
        $this->tcl->evalTcl("{$this->tclPath} stop");
    }
}
