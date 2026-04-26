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
        parent::__construct(null, $options);
        $this->create();
    }

    protected function create(): void
    {
        $extra = $this->buildOptionString(['title', 'width', 'height']);
        $this->tcl->evalTcl("toplevel {$this->tclPath}{$extra}");

        if (isset($this->options['title'])) {
            $this->tcl->evalTcl(
                "wm title {$this->tclPath} "
                    . self::tclQuote((string) $this->options['title'])
            );
        }

        if (isset($this->options['width'], $this->options['height'])) {
            $w = (int) $this->options['width'];
            $h = (int) $this->options['height'];
            $this->tcl->evalTcl("wm geometry {$this->tclPath} {$w}x{$h}");
        }
    }

    public function setTitle(string $title): void
    {
        $this->tcl->evalTcl(
            "wm title {$this->tclPath} " . self::tclQuote($title)
        );
    }

    public function setGeometry(int $width, int $height, ?int $x = null, ?int $y = null): void
    {
        $geometry = "{$width}x{$height}";
        if ($x !== null && $y !== null) {
            $geometry .= "+{$x}+{$y}";
        }
        $this->tcl->evalTcl("wm geometry {$this->tclPath} {$geometry}");
    }

    public function iconify(): void
    {
        $this->tcl->evalTcl("wm iconify {$this->tclPath}");
    }

    public function deiconify(): void
    {
        $this->tcl->evalTcl("wm deiconify {$this->tclPath}");
    }

    public function withdraw(): void
    {
        $this->tcl->evalTcl("wm withdraw {$this->tclPath}");
    }

    public function focus(): void
    {
        $this->tcl->evalTcl("focus {$this->tclPath}");
    }

    public function setResizable(bool $width, bool $height): void
    {
        $w = $width ? '1' : '0';
        $h = $height ? '1' : '0';
        $this->tcl->evalTcl("wm resizable {$this->tclPath} {$w} {$h}");
    }

    public function setMinsize(int $width, int $height): void
    {
        $this->tcl->evalTcl("wm minsize {$this->tclPath} {$width} {$height}");
    }

    public function setMaxsize(int $width, int $height): void
    {
        $this->tcl->evalTcl("wm maxsize {$this->tclPath} {$width} {$height}");
    }

    public static function chooseColor(string $initialColor = 'red'): ?string
    {
        $tcl = ProcessTCL::getInstance();
        $tcl->evalTcl(
            'set ::chosen [tk_chooseColor -parent . -initialcolor '
                . AbstractWidget::tclQuote($initialColor) . ']'
        );
        $tcl->evalTcl('update idletasks');
        $result = trim($tcl->getVar('::chosen'));
        return ($result === '' || $result === 'none') ? null : $result;
    }

    public static function chooseDirectory(string $initialDir = '.'): ?string
    {
        $tcl = ProcessTCL::getInstance();
        $tcl->evalTcl(
            'set ::chosen [tk_chooseDirectory -parent . -initialdir '
                . AbstractWidget::tclQuote($initialDir) . ']'
        );
        $tcl->evalTcl('update idletasks');
        $result = trim($tcl->getVar('::chosen'));
        return ($result === '' || $result === 'none') ? null : $result;
    }

    public static function getOpenFile(string $initialDir = '.'): ?string
    {
        $tcl = ProcessTCL::getInstance();
        $tcl->evalTcl(
            'set ::chosen [tk_getOpenFile -parent . -initialdir '
                . AbstractWidget::tclQuote($initialDir) . ']'
        );
        $tcl->evalTcl('update idletasks');
        $result = trim($tcl->getVar('::chosen'));
        return ($result === '' || $result === 'none') ? null : $result;
    }

    public static function getSaveFile(string $initialDir = '.'): ?string
    {
        $tcl = ProcessTCL::getInstance();
        $tcl->evalTcl(
            'set ::chosen [tk_getSaveFile -parent . -initialdir '
                . AbstractWidget::tclQuote($initialDir) . ']'
        );
        $tcl->evalTcl('update idletasks');
        $result = trim($tcl->getVar('::chosen'));
        return ($result === '' || $result === 'none') ? null : $result;
    }

    public static function messageBox(string $message, string $type = 'ok'): string
    {
        $tcl = ProcessTCL::getInstance();
        $tcl->evalTcl(
            'set ::chosen [tk_messageBox -parent . -message '
                . AbstractWidget::tclQuote($message)
                . ' -type ' . AbstractWidget::tclQuote($type) . ']'
        );
        $tcl->evalTcl('update idletasks');
        return trim($tcl->getVar('::chosen'));
    }

    public function dialog(
        string $title,
        string $message,
        string $icon,
        string $option1,
        string $option2 = '',
        string $extra = ''
    ): string {
        $this->tcl->evalTcl(
            'set ::chosen [tk_dialog .dialog '
                . AbstractWidget::tclQuote($title) . ' '
                . AbstractWidget::tclQuote($message) . ' '
                . AbstractWidget::tclQuote($icon) . ' '
                . AbstractWidget::tclQuote($option1) . ' '
                . AbstractWidget::tclQuote($option2) . ' '
                . AbstractWidget::tclQuote($extra) . ']'
        );
        $this->tcl->evalTcl('update idletasks');
        return trim($this->tcl->getVar('::chosen'));
    }

    public function popupMenu(int $x, int $y): string
    {
        $this->tcl->evalTcl('menu .popup -tearoff 0');
        $this->tcl->evalTcl("set ::chosen [tk_popup .popup {$x} {$y}]");
        $this->tcl->evalTcl('update idletasks');
        $result = trim($this->tcl->getVar('::chosen'));
        $this->tcl->evalTcl('destroy .popup');
        return $result;
    }

    public function getText(): string
    {
        $this->tcl->evalTcl(
            "set ::childtext [{$this->tclPath}.child cget -text]"
        );
        return trim($this->tcl->getVar('::childtext'));
    }

    public function setText(string $text): void
    {
        $this->tcl->evalTcl(
            "{$this->tclPath}.child configure -text " . self::tclQuote($text)
        );
    }
}
