<?php

namespace PhpGui\Widget;

use PhpGui\ProcessTCL;

/**
 * Class Button
 * Represents a button widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Button extends AbstractWidget
{
    private $callback;

    public function __construct(string $parentId, array $options = [])
    {
        parent::__construct($parentId, $options);
        $this->callback = $options['command'] ?? null;
        $this->create();
    }

    protected function create(): void
    {
        $text = $this->options['text'] ?? 'Button';
        $extra = $this->getOptionString();

        if ($this->callback) {
            ProcessTCL::getInstance()->registerCallback($this->id, function () {
                call_user_func($this->callback);
                $this->tcl->evalTcl("update"); // Force widget updates
            });
            $this->tcl->evalTcl("button .{$this->parentId}.{$this->id} -text \"{$text}\" {$extra} -command {php::executeCallback {$this->id}}");
        } else {
            $this->tcl->evalTcl("button .{$this->parentId}.{$this->id} -text \"{$text}\" {$extra}");
        }
    }

    protected function getOptionString(): string
    {
        $opts = "";
        foreach ($this->options as $key => $value) {
            if (in_array($key, ['text', 'command'])) {
                continue;
            }
            $opts .= " -$key \"$value\"";
        }
        return $opts;
    }
}
