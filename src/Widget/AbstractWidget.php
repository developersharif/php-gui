<?php

namespace PhpGui\Widget;

use PhpGui\ProcessTCL;

/**
 * Class AbstractWidget
 *
 * Serves as the base for all UI widgets. It manages unique IDs,
 * provides Tcl command execution, and common layout methods (pack, place, grid).
 */
abstract class AbstractWidget {
    protected ProcessTCL $tcl;
    protected string $id;
    protected ?string $parentId; // null for top-level widgets
    protected array $options;

    /**
     * AbstractWidget constructor.
     *
     * Initializes the Tcl interface and assigns a unique widget ID.
     * Normalizes the parent ID by removing any leading dot.
     *
     * @param string|null $parentId Parent widget ID or null for top-level widgets.
     * @param array       $options  Widget configuration options.
     */
    public function __construct(?string $parentId, array $options = []) {
        $this->tcl = ProcessTCL::getInstance();
        $this->id = uniqid('w');
        if ($parentId !== null) {
            $this->parentId = ltrim($parentId, '.');
        } else {
            $this->parentId = null;
        }
        $this->options = $options;
    }

    /**
     * Creates the widget.
     *
     * Must be implemented by subclasses to define widget-specific creation logic.
     *
     * @return void
     */
    abstract protected function create(): void;

    /**
     * Applies the pack layout to the widget.
     *
     * @param array $options Options for the pack geometry manager.
     * @throws \RuntimeException if the widget is top-level.
     * @return void
     */
    public function pack(array $options = []): void {
        if ($this->parentId === null) {
            throw new \RuntimeException("Cannot pack a top-level widget.");
        }
        $parent = '.' . $this->parentId;
        $packCmd = "pack {$parent}.{$this->id}";
        if (!empty($options)) {
            $packCmd .= ' ' . $this->formatOptions($options);
        }
        $this->tcl->evalTcl($packCmd);
    }

    /**
     * Positions the widget absolutely using the place geometry manager.
     *
     * @param array $options Options for absolute positioning.
     * @throws \RuntimeException if the widget is top-level.
     * @return void
     */
    public function place(array $options = []): void {
        if ($this->parentId === null) {
            throw new \RuntimeException("Cannot place a top-level widget.");
        }
        $parent = '.' . $this->parentId;
        $placeCmd = "place {$parent}.{$this->id}";
        if (!empty($options)) {
            $placeCmd .= ' ' . $this->formatOptions($options);
        }
        $this->tcl->evalTcl($placeCmd);
    }

    /**
     * Positions the widget using the grid geometry manager.
     *
     * @param array $options Options for the grid geometry manager.
     * @throws \RuntimeException if the widget is top-level.
     * @return void
     */
    public function grid(array $options = []): void {
        if ($this->parentId === null) {
            throw new \RuntimeException("Cannot grid a top-level widget.");
        }
        $parent = '.' . $this->parentId;
        $gridCmd = "grid {$parent}.{$this->id}";
        if (!empty($options)) {
            $gridCmd .= ' ' . $this->formatOptions($options);
        }
        $this->tcl->evalTcl($gridCmd);
    }

    /**
     * Destroys the widget.
     *
     * Removes the widget from the Tcl interpreter.
     *
     * @return void
     */
    public function destroy(): void {
        $parent = '.' . $this->parentId;
        $this->tcl->evalTcl("destroy {$parent}.{$this->id}");
    }

    /**
     * Returns the widget's unique identifier.
     *
     * @return string The widget ID.
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * Formats layout options into a Tcl-compatible string.
     *
     * @param array $options Key-value pairs of options.
     * @return string A space-separated string of formatted options.
     */
    protected function formatOptions(array $options): string {
        $formatted = [];
        foreach ($options as $key => $value) {
            $formatted[] = "-$key $value";
        }
        return implode(' ', $formatted);
    }
}
