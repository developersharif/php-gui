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
        $extra = $this->getOptionString();
        $this->tcl->evalTcl("canvas .{$this->parentId}.{$this->id} {$extra}");
    }

    protected function getOptionString(): string
    {
        $opts = "";
        foreach ($this->options as $key => $value) {
            $opts .= " -$key \"$value\"";
        }
        return $opts;
    }

    public function drawLine(int $x1, int $y1, int $x2, int $y2, array $options = []): string
    {
        $optStr = $this->formatOptions($options);
        return (string) $this->tcl->evalTcl(".{$this->parentId}.{$this->id} create line $x1 $y1 $x2 $y2 $optStr");
    }

    public function drawRectangle(int $x1, int $y1, int $x2, int $y2, array $options = []): string
    {
        $optStr = $this->formatOptions($options);
        return (string) $this->tcl->evalTcl(".{$this->parentId}.{$this->id} create rectangle $x1 $y1 $x2 $y2 $optStr");
    }

    public function drawOval(int $x1, int $y1, int $x2, int $y2, array $options = []): string
    {
        $optStr = $this->formatOptions($options);
        return (string) $this->tcl->evalTcl(".{$this->parentId}.{$this->id} create oval $x1 $y1 $x2 $y2 $optStr");
    }

    public function drawText(int $x, int $y, string $text, array $options = []): string
    {
        $optStr = $this->formatOptions($options);
        return (string) $this->tcl->evalTcl(".{$this->parentId}.{$this->id} create text $x $y -text {$text} $optStr");
    }

    public function delete(string $itemId): void
    {
        $this->tcl->evalTcl(".{$this->parentId}.{$this->id} delete $itemId");
    }

    public function clear(): void
    {
        $this->tcl->evalTcl(".{$this->parentId}.{$this->id} delete all");
    }

    protected function formatOptions(array $options): string
    {
        $result = [];
        foreach ($options as $key => $value) {
            $result[] = "-$key {$value}";
        }
        return implode(' ', $result);
    }
}
