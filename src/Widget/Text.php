<?php

namespace PhpGui\Widget;

/**
 * Class Text
 * Multi-line text widget — wraps Tk's `text` command. Useful for editors,
 * log views, message composers, and anywhere a single-line `Input` is too
 * small.
 *
 * Pair with `Scrollbar::attachTo()` to add scrollbars; `text` widgets do
 * not display them on their own.
 *
 * @package PhpGui\Widget
 */
class Text extends AbstractWidget
{
    public function __construct(string $parentId, array $options = [])
    {
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void
    {
        $extra = $this->buildOptionString(['text']);
        $this->tcl->evalTcl("text {$this->tclPath}{$extra}");

        if (isset($this->options['text'])) {
            $this->setText((string) $this->options['text']);
        }
    }

    /**
     * Replace the entire contents with `$text`. Existing content is
     * deleted first; the cursor is left at the end of the new content.
     */
    public function setText(string $text): void
    {
        $wasDisabled = $this->isDisabled();
        if ($wasDisabled) {
            $this->setState('normal');
        }
        $this->tcl->evalTcl("{$this->tclPath} delete 1.0 end");
        // Route the value through a Tcl variable to bypass Tcl string
        // parsing for arbitrary user content (newlines, brackets, dollars).
        $var = 'phpgui_text_buf_' . $this->id;
        $this->tcl->setVar($var, $text);
        $this->tcl->evalTcl("{$this->tclPath} insert end \$" . $var);
        if ($wasDisabled) {
            $this->setState('disabled');
        }
    }

    /**
     * Returns the full text content. Tk's `text get 1.0 end` always
     * appends a trailing newline; we strip exactly one to match what the
     * user actually inserted.
     */
    public function getText(): string
    {
        $var = 'phpgui_text_buf_' . $this->id;
        $this->tcl->evalTcl("set {$var} [{$this->tclPath} get 1.0 end]");
        $value = $this->tcl->getVar($var);
        if ($value !== '' && substr($value, -1) === "\n") {
            $value = substr($value, 0, -1);
        }
        return $value;
    }

    /**
     * Append `$text` at the end without disturbing the rest of the buffer.
     * Honours the disabled state by toggling normal/disabled around the
     * insert so log-style read-only views can still be appended to.
     */
    public function append(string $text): void
    {
        $wasDisabled = $this->isDisabled();
        if ($wasDisabled) {
            $this->setState('normal');
        }
        $var = 'phpgui_text_buf_' . $this->id;
        $this->tcl->setVar($var, $text);
        $this->tcl->evalTcl("{$this->tclPath} insert end \$" . $var);
        if ($wasDisabled) {
            $this->setState('disabled');
        }
    }

    /**
     * Insert `$text` at a Tk text index (e.g. `"1.0"`, `"end"`, `"insert"`).
     * Validates the index is one of the safe forms or a `line.col` literal —
     * arbitrary index expressions could otherwise be a code-injection vector.
     */
    public function insertAt(string $index, string $text): void
    {
        if (!preg_match('/^(end|insert|\d+\.\d+)$/', $index)) {
            throw new \InvalidArgumentException(
                "Invalid Tk text index '{$index}'. "
                . "Expected 'end', 'insert', or 'line.column' (e.g. '1.0')."
            );
        }
        $var = 'phpgui_text_buf_' . $this->id;
        $this->tcl->setVar($var, $text);
        $this->tcl->evalTcl("{$this->tclPath} insert {$index} \$" . $var);
    }

    /** Remove all content. */
    public function clear(): void
    {
        $wasDisabled = $this->isDisabled();
        if ($wasDisabled) {
            $this->setState('normal');
        }
        $this->tcl->evalTcl("{$this->tclPath} delete 1.0 end");
        if ($wasDisabled) {
            $this->setState('disabled');
        }
    }

    /** Number of characters in the buffer (including newlines). */
    public function getLength(): int
    {
        $result = $this->tcl->evalTcl(
            "{$this->tclPath} count -chars 1.0 end"
        );
        return max(0, (int) trim($result) - 1); // Tk includes the trailing \n
    }

    /** Number of lines in the buffer. */
    public function getLineCount(): int
    {
        $result = $this->tcl->evalTcl(
            "{$this->tclPath} count -lines 1.0 end"
        );
        return max(1, (int) trim($result));
    }

    /** Switch to `normal` (editable) or `disabled` (read-only). */
    public function setState(string $state): void
    {
        if ($state !== 'normal' && $state !== 'disabled') {
            throw new \InvalidArgumentException(
                "Text state must be 'normal' or 'disabled', got '{$state}'."
            );
        }
        $this->tcl->evalTcl(
            "{$this->tclPath} configure -state " . self::tclQuote($state)
        );
    }

    public function isDisabled(): bool
    {
        return trim($this->tcl->evalTcl("{$this->tclPath} cget -state")) === 'disabled';
    }
}
