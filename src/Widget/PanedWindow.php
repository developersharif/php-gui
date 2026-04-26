<?php

namespace PhpGui\Widget;

/**
 * Class PanedWindow
 * Resizable split container — wraps Tk's `ttk::panedwindow`. Each pane
 * hosts a child widget; the user drags the divider between panes to
 * resize them.
 *
 *   $split = new PanedWindow($win->getId(), ['orient' => 'horizontal']);
 *   $split->pack(['fill' => 'both', 'expand' => 1]);
 *
 *   $left  = new Frame($split->getId());
 *   $right = new Frame($split->getId());
 *   $split->addPane($left,  ['weight' => 1]);
 *   $split->addPane($right, ['weight' => 3]);
 *
 * `weight` controls how leftover space is distributed when the user
 * resizes the parent. Equal weights = equal split.
 *
 * @package PhpGui\Widget
 */
class PanedWindow extends AbstractWidget
{
    private const VALID_ORIENTS = ['horizontal', 'vertical'];

    /** @var list<AbstractWidget> Panes in display order. */
    private array $panes = [];

    public function __construct(string $parentId, array $options = [])
    {
        if (isset($options['orient'])
            && !in_array($options['orient'], self::VALID_ORIENTS, true)
        ) {
            throw new \InvalidArgumentException(
                "PanedWindow orient must be 'horizontal' or 'vertical', got '{$options['orient']}'."
            );
        }
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void
    {
        $extra = $this->buildOptionString();
        $orient = (string) ($this->options['orient'] ?? 'horizontal');
        $this->tcl->evalTcl(
            "ttk::panedwindow {$this->tclPath} -orient {$orient}{$extra}"
        );
    }

    /**
     * Add `$child` as a new pane. The child must be a child of this
     * PanedWindow (constructed with `$paned->getId()` as its parent).
     *
     * `$options` accepts pane-level Tk options, the most useful being
     * `weight` (int, share of leftover space when the parent resizes).
     */
    public function addPane(AbstractWidget $child, array $options = []): void
    {
        if ($child->getParentId() !== $this->id) {
            throw new \InvalidArgumentException(
                'PanedWindow::addPane child must be a child of this PanedWindow '
                . '(construct it with $paned->getId() as the parent).'
            );
        }

        $cmd = "{$this->tclPath} add {$child->getTclPath()}";
        foreach ($options as $key => $value) {
            $cmd .= ' -' . $key . ' ' . self::tclQuote((string) $value);
        }
        $this->tcl->evalTcl($cmd);
        $this->panes[] = $child;
    }

    /**
     * Remove a pane (the child widget is detached but not destroyed).
     */
    public function removePane(int $index): void
    {
        $this->assertIndexInRange($index);
        $child = $this->panes[$index];
        $this->tcl->evalTcl("{$this->tclPath} forget {$child->getTclPath()}");
        array_splice($this->panes, $index, 1);
    }

    /** Number of panes. */
    public function getPaneCount(): int
    {
        return count($this->panes);
    }

    /** Pane widget at `$index`, or null if out of range. */
    public function getPane(int $index): ?AbstractWidget
    {
        return $this->panes[$index] ?? null;
    }

    /**
     * Update pane-level options for an existing pane, e.g. weight.
     */
    public function configurePane(int $index, array $options): void
    {
        $this->assertIndexInRange($index);
        $child = $this->panes[$index];
        $cmd = "{$this->tclPath} pane {$child->getTclPath()}";
        foreach ($options as $key => $value) {
            $cmd .= ' -' . $key . ' ' . self::tclQuote((string) $value);
        }
        $this->tcl->evalTcl($cmd);
    }

    /**
     * Move the sash (divider) at `$index` to the absolute pixel position
     * `$position`. Index 0 is the divider after the first pane.
     */
    public function setSashPosition(int $index, int $position): void
    {
        $this->tcl->evalTcl("{$this->tclPath} sashpos {$index} {$position}");
    }

    /** Current pixel position of the sash at `$index`. */
    public function getSashPosition(int $index): int
    {
        return (int) trim($this->tcl->evalTcl("{$this->tclPath} sashpos {$index}"));
    }

    private function assertIndexInRange(int $index): void
    {
        $count = count($this->panes);
        if ($index < 0 || $index >= $count) {
            throw new \OutOfRangeException(
                "PanedWindow pane index {$index} out of range (have {$count} panes)."
            );
        }
    }

    public function destroy(): void
    {
        $this->panes = [];
        parent::destroy();
    }
}
