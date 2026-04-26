<?php

namespace PhpGui\Widget;

/**
 * Class Separator
 * Thin horizontal or vertical divider line — wraps Tk's `ttk::separator`.
 *
 *   new Separator($win->getId(), ['orient' => 'horizontal'])
 *       ->pack(['fill' => 'x', 'pady' => 6]);
 *
 * @package PhpGui\Widget
 */
class Separator extends AbstractWidget
{
    private const VALID_ORIENTS = ['horizontal', 'vertical'];

    public function __construct(string $parentId, array $options = [])
    {
        if (isset($options['orient'])
            && !in_array($options['orient'], self::VALID_ORIENTS, true)
        ) {
            throw new \InvalidArgumentException(
                "Separator orient must be 'horizontal' or 'vertical', got '{$options['orient']}'."
            );
        }
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void
    {
        $orient = (string) ($this->options['orient'] ?? 'horizontal');
        $extra  = $this->buildOptionString(['orient']);
        $this->tcl->evalTcl(
            "ttk::separator {$this->tclPath} -orient {$orient}{$extra}"
        );
    }
}
