<?php

namespace PhpGui\Widget;

use PhpGui\ProcessTCL;

/**
 * Class TopLevel
 * Represents a top-level window in the GUI.
 *
 * @package PhpGui\Widget
 */
class TopLevel extends AbstractWidget
{
    public function __construct(array $options = [])
    {
        parent::__construct(null, $options); // null parent for top-level widget
        $this->create();
    }

    protected function create(): void
    {
        $extra = $this->getOptionString();
        $this->tcl->evalTcl("toplevel .{$this->id} {$extra}");

        // Set window title if provided
        if (isset($this->options['title'])) {
            $this->tcl->evalTcl("wm title .{$this->id} \"{$this->options['title']}\"");
        }

        // Set window size if provided
        if (isset($this->options['width'], $this->options['height'])) {
            $this->tcl->evalTcl("wm geometry .{$this->id} {$this->options['width']}x{$this->options['height']}");
        }
    }

    protected function getOptionString(): string
    {
        $opts = "";
        foreach ($this->options as $key => $value) {
            if (in_array($key, ['title', 'width', 'height'])) continue;
            $opts .= " -$key \"$value\"";
        }
        return $opts;
    }

    public function setTitle(string $title): void
    {
        $this->tcl->evalTcl("wm title .{$this->id} \"{$title}\"");
    }

    public function setGeometry(int $width, int $height, ?int $x = null, ?int $y = null): void
    {
        $geometry = "{$width}x{$height}";
        if ($x !== null && $y !== null) {
            $geometry .= "+{$x}+{$y}";
        }
        $this->tcl->evalTcl("wm geometry .{$this->id} {$geometry}");
    }

    public function iconify(): void
    {
        $this->tcl->evalTcl("wm iconify .{$this->id}");
    }

    public function deiconify(): void
    {
        $this->tcl->evalTcl("wm deiconify .{$this->id}");
    }

    public function withdraw(): void
    {
        $this->tcl->evalTcl("wm withdraw .{$this->id}");
    }

    public function focus(): void
    {
        $this->tcl->evalTcl("focus .{$this->id}");
    }

    public function setResizable(bool $width, bool $height): void
    {
        $w = $width ? "1" : "0";
        $h = $height ? "1" : "0";
        $this->tcl->evalTcl("wm resizable .{$this->id} {$w} {$h}");
    }

    public function setMinsize(int $width, int $height): void
    {
        $this->tcl->evalTcl("wm minsize .{$this->id} {$width} {$height}");
    }

    public function setMaxsize(int $width, int $height): void
    {
        $this->tcl->evalTcl("wm maxsize .{$this->id} {$width} {$height}");
    }

    public static function chooseColor(string $initialColor = 'red'): ?string
    {
        $tcl = ProcessTCL::getInstance();
        $tcl->evalTcl("set ::chosen [tk_chooseColor -parent . -initialcolor {$initialColor}]");
        $tcl->evalTcl("update idletasks");
        $result = trim($tcl->getVar("::chosen"));
        return ($result === "" || $result === "none") ? null : $result;
    }

    public static function chooseDirectory(string $initialDir = "."): ?string
    {
        $tcl = ProcessTCL::getInstance();
        $tcl->evalTcl("set ::chosen [tk_chooseDirectory -parent . -initialdir \"$initialDir\"]");
        $tcl->evalTcl("update idletasks");
        $result = trim($tcl->getVar("::chosen"));
        return ($result === "" || $result === "none") ? null : $result;
    }

    public static function getOpenFile(string $initialDir = "."): ?string
    {
        $tcl = ProcessTCL::getInstance();
        $tcl->evalTcl("set ::chosen [tk_getOpenFile -parent . -initialdir \"$initialDir\"]");
        $tcl->evalTcl("update idletasks");
        $result = trim($tcl->getVar("::chosen"));
        return ($result === "" || $result === "none") ? null : $result;
    }

    public static function getSaveFile(string $initialDir = "."): ?string
    {
        $tcl = ProcessTCL::getInstance();
        $tcl->evalTcl("set ::chosen [tk_getSaveFile -parent . -initialdir \"$initialDir\"]");
        $tcl->evalTcl("update idletasks");
        $result = trim($tcl->getVar("::chosen"));
        return ($result === "" || $result === "none") ? null : $result;
    }

    public static function messageBox(string $message, string $type = "ok"): string
    {
        $tcl = ProcessTCL::getInstance();
        $tcl->evalTcl("set ::chosen [tk_messageBox -parent . -message \"$message\" -type $type]");
        $tcl->evalTcl("update idletasks");
        $result = trim($tcl->getVar("::chosen"));
        return $result;
    }

    public function dialog(string $title, string $message, string $icon, string $option1, string $option2 = "", string $extra = ""): string
    {
        $this->tcl->evalTcl("set ::chosen [tk_dialog .dialog \"$title\" \"$message\" \"$icon\" \"$option1\" \"$option2\" \"$extra\"]");
        $this->tcl->evalTcl("update idletasks");
        $result = trim($this->tcl->getVar("::chosen"));
        return $result;
    }

    public function popupMenu(int $x, int $y): string
    {
        $this->tcl->evalTcl("menu .popup -tearoff 0");
        $this->tcl->evalTcl("set ::chosen [tk_popup .popup $x $y]");
        $this->tcl->evalTcl("update idletasks");
        $result = trim($this->tcl->getVar("::chosen"));
        $this->tcl->evalTcl("destroy .popup");
        return $result;
    }

    public function getText(): string
    {
        $this->tcl->evalTcl("set ::childtext [.{$this->id}.child cget -text]");
        return trim($this->tcl->getVar("::childtext"));
    }

    public function setText(string $text): void
    {
        $this->tcl->evalTcl(".{$this->id}.child configure -text \"{$text}\"");
    }

    public function destroy(): void
    {
        $this->tcl->evalTcl("destroy .{$this->id}");
    }
}
