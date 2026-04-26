<?php

namespace PhpGui\Widget;

/**
 * Class Scrollbar
 * Wraps Tk's `ttk::scrollbar`. The widget itself is just a thin slider —
 * to be useful it must be wired to a scrollable target (Text, Listbox,
 * Canvas, Treeview, …). Use the `attachTo()` factory to skip the wiring
 * boilerplate.
 *
 *   $text = new Text($win->getId());
 *   $text->pack(['side' => 'left', 'fill' => 'both', 'expand' => 1]);
 *   Scrollbar::attachTo($text, 'vertical');
 *
 * @package PhpGui\Widget
 */
class Scrollbar extends AbstractWidget
{
    public const VERTICAL   = 'vertical';
    public const HORIZONTAL = 'horizontal';

    private string $orient;

    public function __construct(string $parentId, array $options = [])
    {
        $orient = $options['orient'] ?? self::VERTICAL;
        if ($orient !== self::VERTICAL && $orient !== self::HORIZONTAL) {
            throw new \InvalidArgumentException(
                "Scrollbar orient must be 'vertical' or 'horizontal', got '{$orient}'."
            );
        }
        $this->orient = $orient;
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void
    {
        $extra = $this->buildOptionString(['orient', 'target']);
        $this->tcl->evalTcl(
            "ttk::scrollbar {$this->tclPath} -orient {$this->orient}{$extra}"
        );
    }

    /**
     * Wire this scrollbar to a target widget. Two-way: the scrollbar's
     * `-command` drives the target's view, and the target's `…scrollcommand`
     * updates the scrollbar slider as content scrolls.
     */
    public function bindTo(AbstractWidget $target): void
    {
        $axis = $this->orient === self::VERTICAL ? 'y' : 'x';
        $this->tcl->evalTcl(
            "{$this->tclPath} configure -command [list {$target->getTclPath()} {$axis}view]"
        );
        $this->tcl->evalTcl(
            "{$target->getTclPath()} configure -{$axis}scrollcommand "
                . "[list {$this->tclPath} set]"
        );
    }

    public function getOrient(): string
    {
        return $this->orient;
    }

    /**
     * Convenience factory: create a scrollbar bound to `$target`, packed
     * along the appropriate side, and return it. Caller is expected to
     * have already packed `$target` itself.
     *
     * For more complex layouts (grid, place, dual scrollbars) build the
     * Scrollbar manually and call `bindTo()`.
     */
    public static function attachTo(
        AbstractWidget $target,
        string $orient = self::VERTICAL
    ): self {
        $parent = $target->getParentId();
        if ($parent === null) {
            throw new \InvalidArgumentException(
                'Scrollbar::attachTo cannot wrap a top-level widget; '
                . 'wrap it in a Frame and pass the inner widget instead.'
            );
        }
        $sb = new self($parent, ['orient' => $orient]);
        $sb->bindTo($target);
        $sb->pack([
            'side'   => $orient === self::VERTICAL ? 'right' : 'bottom',
            'fill'   => $orient === self::VERTICAL ? 'y' : 'x',
            // -before puts the scrollbar earlier in pack order so the target
            // expands into the remaining space rather than getting clipped.
            'before' => $target->getTclPath(),
        ]);
        return $sb;
    }
}
