<?php

namespace PhpGui\Widget;

use PhpGui\ProcessTCL;

class Menu extends AbstractWidget
{
    private $type;
    private $menuItems = [];

    public function __construct(string $parentId, array $options = [])
    {
        $this->type = $options['type'] ?? 'normal';
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void
    {
        $extra = $this->getOptionString();

        // Menus always live at the root path `.{$id}` regardless of where
        // they are attached, so override the inherited tclPath.
        $this->tclPath = ".{$this->id}";

        if ($this->type === 'main') {
            // Attach to the parent window/toplevel using its full Tcl path.
            $this->tcl->evalTcl("menu {$this->tclPath} -tearoff 0 {$extra}");
            $this->tcl->evalTcl("{$this->parentTclPath} configure -menu {$this->tclPath}");
        } else {
            $this->tcl->evalTcl("menu {$this->tclPath} -tearoff 0 {$extra}");
        }
    }

    protected function getOptionString(): string
    {
        $opts = "";
        foreach ($this->options as $key => $value) {
            if (in_array($key, ['type'])) continue;
            $opts .= " -$key \"$value\"";
        }
        return $opts;
    }

    public function addCommand(string $label, callable $callback = null, array $options = []): void
    {
        $cmdOpts = ["-label \"{$label}\""];

        if ($callback) {
            $callbackId = $this->id . '_cmd_' . count($this->menuItems);
            ProcessTCL::getInstance()->registerCallback($callbackId, $callback);
            $cmdOpts[] = "-command {php::executeCallback $callbackId}";
        }

        foreach ($options as $key => $value) {
            $cmdOpts[] = "-$key \"{$value}\"";
        }

        $this->tcl->evalTcl(".{$this->id} add command " . implode(' ', $cmdOpts));
        $this->menuItems[] = ['type' => 'command', 'label' => $label];
    }

    public function addSubmenu(string $label, array $options = []): Menu
    {
        $submenuId = $this->id . '_sub_' . count($this->menuItems);
        $submenu = new Menu($this->id, ['type' => 'submenu'] + $options);

        $this->tcl->evalTcl(".{$this->id} add cascade -label \"{$label}\" -menu .{$submenu->getId()}");

        $this->menuItems[] = ['type' => 'submenu', 'label' => $label, 'menu' => $submenu];
        return $submenu;
    }

    public function addSeparator(): void
    {
        $this->tcl->evalTcl(".{$this->id} add separator");
        $this->menuItems[] = ['type' => 'separator'];
    }

    protected function formatOptions(array $options): string
    {
        $result = [];
        foreach ($options as $key => $value) {
            if ($key === 'command') {
                $result[] = "-$key {$value}";
            } else {
                $result[] = "-$key \"$value\"";
            }
        }
        return implode(' ', $result);
    }

}
