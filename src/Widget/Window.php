<?php

namespace PhpGui\Widget;

/**
 * Class Window
 * Represents a window widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Window extends AbstractWidget
{
    public function __construct(array $options = [])
    {
        parent::__construct(null, $options);
        $this->create();
    }

    /** @var callable|null User onClose handler; null = default (quit app). */
    private $onCloseHandler = null;

    /** @var callable|null User onResize handler. */
    private $onResizeHandler = null;

    protected function create(): void
    {
        $title  = (string) ($this->options['title'] ?? 'Window');
        $width  = (int) ($this->options['width']  ?? 300);
        $height = (int) ($this->options['height'] ?? 200);

        $this->tcl->evalTcl("toplevel {$this->tclPath}");
        $this->tcl->evalTcl(
            "wm title {$this->tclPath} " . self::tclQuote($title)
        );
        $this->tcl->evalTcl("wm geometry {$this->tclPath} {$width}x{$height}");
        $this->tcl->evalTcl("wm protocol {$this->tclPath} WM_DELETE_WINDOW {
            if {[winfo exists .]} {
                ::exit_app
            }
        }");
        $this->tcl->evalTcl("wm deiconify {$this->tclPath}");
    }

    /**
     * Intercept the window-close event. The handler runs when the user
     * clicks the OS-level close button (or invokes any path that fires
     * `WM_DELETE_WINDOW`). Returning `false` from the handler keeps the
     * window open; returning anything else (including `null`) lets the
     * application exit.
     *
     * Useful for "save unsaved changes?" prompts.
     */
    public function onClose(callable $handler): void
    {
        $this->onCloseHandler = $handler;
        $closeId = $this->id . '_close';
        $this->tcl->registerCallback($closeId, function () {
            $result = ($this->onCloseHandler)();
            if ($result === false) {
                return; // Handler vetoed close; keep window alive.
            }
            $this->tcl->evalTcl('::exit_app');
        });
        $this->tcl->evalTcl(
            "wm protocol {$this->tclPath} WM_DELETE_WINDOW "
                . '{php::executeCallback ' . $closeId . '}'
        );
    }

    /**
     * Run a handler when the window is resized. Tk fires `<Configure>` for
     * every move and resize; we filter to size-only changes by comparing
     * against the previous w/h and ignoring duplicates.
     */
    public function onResize(callable $handler): void
    {
        $this->onResizeHandler = $handler;
        $resizeId = $this->id . '_resize';
        $lastW = -1;
        $lastH = -1;
        $this->tcl->registerCallback($resizeId, function () use (&$lastW, &$lastH) {
            $w = (int) trim($this->tcl->evalTcl("winfo width {$this->tclPath}"));
            $h = (int) trim($this->tcl->evalTcl("winfo height {$this->tclPath}"));
            if ($w === $lastW && $h === $lastH) {
                return;
            }
            $lastW = $w;
            $lastH = $h;
            ($this->onResizeHandler)($w, $h);
        });
        // Bind only on the toplevel itself, not its descendants — without
        // the explicit-only filter Tk would fire the handler on every child
        // widget reflow.
        $this->tcl->evalTcl(
            "bind {$this->tclPath} <Configure> "
                . '{ if {"%W" eq "' . $this->tclPath . '"} '
                . '{php::executeCallback ' . $resizeId . '} }'
        );
    }

    public function destroy(): void
    {
        // Clean up the auxiliary callback ids we may have registered.
        $this->tcl->unregisterCallback($this->id . '_close');
        $this->tcl->unregisterCallback($this->id . '_resize');
        parent::destroy();
    }

    public function getTcl(): \PhpGui\ProcessTCL
    {
        return $this->tcl;
    }
}
