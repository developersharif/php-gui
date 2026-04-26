<?php

namespace PhpGui\Widget;

/**
 * Class Canvas
 * Represents a canvas widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Canvas extends AbstractWidget
{
    public function __construct(string $parentId, array $options = [])
    {
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void
    {
        $extra = $this->buildOptionString();
        $this->tcl->evalTcl("canvas {$this->tclPath}{$extra}");
    }

    public function drawLine(int $x1, int $y1, int $x2, int $y2, array $options = []): string
    {
        return (string) $this->tcl->evalTcl(
            "{$this->tclPath} create line {$x1} {$y1} {$x2} {$y2} "
                . $this->formatItemOptions($options)
        );
    }

    public function drawRectangle(int $x1, int $y1, int $x2, int $y2, array $options = []): string
    {
        return (string) $this->tcl->evalTcl(
            "{$this->tclPath} create rectangle {$x1} {$y1} {$x2} {$y2} "
                . $this->formatItemOptions($options)
        );
    }

    public function drawOval(int $x1, int $y1, int $x2, int $y2, array $options = []): string
    {
        return (string) $this->tcl->evalTcl(
            "{$this->tclPath} create oval {$x1} {$y1} {$x2} {$y2} "
                . $this->formatItemOptions($options)
        );
    }

    public function drawText(int $x, int $y, string $text, array $options = []): string
    {
        return (string) $this->tcl->evalTcl(
            "{$this->tclPath} create text {$x} {$y} -text "
                . self::tclQuote($text) . ' '
                . $this->formatItemOptions($options)
        );
    }

    public function delete(string $itemId): void
    {
        // Item IDs are integers Tcl returned to us, so direct interpolation
        // is safe — but cast defensively in case a caller passes something
        // exotic.
        $itemId = (int) $itemId;
        $this->tcl->evalTcl("{$this->tclPath} delete {$itemId}");
    }

    public function clear(): void
    {
        $this->tcl->evalTcl("{$this->tclPath} delete all");
    }

    /**
     * Format item-creation options like `-fill red -width 2`. All values
     * are run through tclQuote so user-supplied colours/fonts/etc. can't
     * inject Tcl.
     */
    private function formatItemOptions(array $options): string
    {
        $parts = [];
        foreach ($options as $key => $value) {
            $parts[] = '-' . $key . ' ' . self::tclQuote((string) $value);
        }
        return implode(' ', $parts);
    }
}
