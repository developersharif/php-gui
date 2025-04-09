<?php

namespace PhpGui\Widget;

/**
 * Class Window
 * Represents a window widget in the GUI.
 *
 * @package PhpGui\Widget
 */
class Window extends AbstractWidget {
    public function __construct(array $options = []) {
        parent::__construct(null, $options); 
        $this->create();
    }

    protected function create(): void {
        $title = $this->options['title'] ?? 'Window';
        $width = $this->options['width'] ?? 300;
        $height = $this->options['height'] ?? 200;
        
        $this->tcl->evalTcl("toplevel .{$this->id}");
        $this->tcl->evalTcl("wm title .{$this->id} \"{$title}\"");
        $this->tcl->evalTcl("wm geometry .{$this->id} {$width}x{$height}");
        $this->tcl->evalTcl("wm protocol .{$this->id} WM_DELETE_WINDOW {
            if {[winfo exists .]} {
                ::exit_app
            }
        }");
        $this->tcl->evalTcl("wm deiconify .{$this->id}");
    }

    public function getTcl(): \PhpGui\ProcessTCL {
        return $this->tcl;
    }
}
