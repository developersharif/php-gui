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
        $extra = $this->buildOptionString(['type']);

        // Menus always live at the root path `.{$id}` regardless of where
        // they are attached, so override the inherited tclPath.
        $this->tclPath = ".{$this->id}";

        if ($this->type === 'main') {
            $this->tcl->evalTcl("menu {$this->tclPath} -tearoff 0{$extra}");
            $this->tcl->evalTcl("{$this->parentTclPath} configure -menu {$this->tclPath}");
        } else {
            $this->tcl->evalTcl("menu {$this->tclPath} -tearoff 0{$extra}");
        }
    }

    public function addCommand(string $label, ?callable $callback = null, array $options = []): void
    {
        $cmdOpts = ['-label ' . self::tclQuote($label)];

        if ($callback) {
            $callbackId = $this->id . '_cmd_' . count($this->menuItems);
            ProcessTCL::getInstance()->registerCallback($callbackId, $callback);
            $cmdOpts[] = '-command {php::executeCallback ' . $callbackId . '}';
        }

        foreach ($options as $key => $value) {
            $cmdOpts[] = '-' . $key . ' ' . self::tclQuote((string) $value);
        }

        $this->tcl->evalTcl("{$this->tclPath} add command " . implode(' ', $cmdOpts));
        $this->menuItems[] = ['type' => 'command', 'label' => $label];
    }

    public function addSubmenu(string $label, array $options = []): Menu
    {
        $submenu = new Menu($this->id, ['type' => 'submenu'] + $options);

        $this->tcl->evalTcl(
            "{$this->tclPath} add cascade -label " . self::tclQuote($label)
                . ' -menu .' . $submenu->getId()
        );

        $this->menuItems[] = ['type' => 'submenu', 'label' => $label, 'menu' => $submenu];
        return $submenu;
    }

    public function addSeparator(): void
    {
        $this->tcl->evalTcl("{$this->tclPath} add separator");
        $this->menuItems[] = ['type' => 'separator'];
    }

    public function destroy(): void
    {
        // Free the per-command callbacks we registered under derived ids
        // (`{menuId}_cmd_0`, `..._cmd_1`, …) before the parent runs the
        // generic `unregisterCallback($this->id)`.
        foreach (array_keys($this->menuItems) as $idx) {
            $this->tcl->unregisterCallback($this->id . '_cmd_' . $idx);
        }
        // Submenus are destroyed automatically via Tk's hierarchy; clean
        // their PHP-side state as well.
        foreach ($this->menuItems as $item) {
            if (($item['type'] ?? '') === 'submenu' && isset($item['menu'])) {
                $item['menu']->destroy();
            }
        }
        parent::destroy();
    }
}
