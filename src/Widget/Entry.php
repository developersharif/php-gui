<?php
namespace PhpGui\Widget;

/**
 * Class Entry
 * Represents an entry widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Entry extends AbstractWidget
{
    public function __construct(string $parentId, array $options = [])
    {
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void
    {
        $defaultText = (string) ($this->options['text'] ?? '');
        // -textvariable uses the bare uniqid, which is alphanumeric and safe.
        $this->tcl->evalTcl("entry {$this->tclPath} -textvariable {$this->id}");
        // Tcl_SetVar via FFI bypasses Tcl string parsing entirely, so user
        // text never has to be escaped.
        $this->tcl->setVar($this->id, $defaultText);
    }

    public function getValue(): string
    {
        return $this->tcl->getVar($this->id);
    }

    public function setValue(string $value): void
    {
        $this->tcl->setVar($this->id, $value);
    }
}
