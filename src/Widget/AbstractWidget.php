<?php

namespace PhpGui\Widget;

use PhpGui\ProcessTCL;

/**
 * Class AbstractWidget
 *
 * Serves as the base for all UI widgets. It manages unique IDs, full Tcl
 * widget paths (so widgets can be nested arbitrarily deep), the Tcl
 * interpreter handle, and the common layout methods (pack, place, grid).
 *
 * Tcl/Tk identifies widgets by dotted path (`.parent.child.grandchild`),
 * not by ID alone. Each widget records its own full path on construction
 * by looking up its parent in a process-wide registry; that lets a child
 * built under a Frame which is itself under a Window receive the correct
 * `.{window}.{frame}.{child}` path automatically.
 */
abstract class AbstractWidget
{
    /** @var array<string, AbstractWidget> id → widget instance, used to resolve parent paths. */
    private static array $registry = [];

    protected ProcessTCL $tcl;
    protected string $id;

    /** Bare ID of the parent widget, or null for top-level widgets. Kept for back-compat. */
    protected ?string $parentId;

    /** Full Tcl path of the parent (e.g. `.w123.w456`). Empty string for top-level widgets. */
    protected string $parentTclPath;

    /** Full Tcl path of this widget (e.g. `.w123.w456.w789`). */
    protected string $tclPath;

    protected array $options;

    public function __construct(?string $parentId, array $options = [])
    {
        $this->tcl = ProcessTCL::getInstance();
        $this->id = uniqid('w');
        $this->options = $options;

        if ($parentId === null) {
            $this->parentId      = null;
            $this->parentTclPath = '';
            $this->tclPath       = '.' . $this->id;
        } else {
            $this->parentId = ltrim($parentId, '.');
            // Look up the parent in the registry to get its full Tcl path.
            // If the parent isn't registered (e.g. a synthetic ID passed by
            // older callers), fall back to treating the ID as a single-level
            // path so existing single-nesting code keeps working.
            $parent = self::$registry[$this->parentId] ?? null;
            $this->parentTclPath = $parent !== null
                ? $parent->tclPath
                : '.' . $this->parentId;
            $this->tclPath = $this->parentTclPath . '.' . $this->id;
        }

        self::$registry[$this->id] = $this;
    }

    abstract protected function create(): void;

    public function pack(array $options = []): void
    {
        $this->requireNonTopLevel('pack');
        $cmd = "pack {$this->tclPath}";
        if (!empty($options)) {
            $cmd .= ' ' . $this->formatOptions($options);
        }
        $this->tcl->evalTcl($cmd);
    }

    public function place(array $options = []): void
    {
        $this->requireNonTopLevel('place');
        $cmd = "place {$this->tclPath}";
        if (!empty($options)) {
            $cmd .= ' ' . $this->formatOptions($options);
        }
        $this->tcl->evalTcl($cmd);
    }

    public function grid(array $options = []): void
    {
        $this->requireNonTopLevel('grid');
        $cmd = "grid {$this->tclPath}";
        if (!empty($options)) {
            $cmd .= ' ' . $this->formatOptions($options);
        }
        $this->tcl->evalTcl($cmd);
    }

    public function destroy(): void
    {
        $this->tcl->evalTcl("destroy {$this->tclPath}");
        // Best-effort: a widget that registered a callback under its own id
        // (Button, Checkbutton, Input via onEnter) is freed here. Widgets
        // that register additional ids (Menu's per-item ids) override this
        // to clean those up too.
        $this->tcl->unregisterCallback($this->id);
        unset(self::$registry[$this->id]);
    }

    /** Bare unique ID (used as a child's `$parentId` argument). */
    public function getId(): string
    {
        return $this->id;
    }

    /** Full Tcl widget path, e.g. `.w123.w456.w789`. */
    public function getTclPath(): string
    {
        return $this->tclPath;
    }

    /** Bare ID of the parent widget, or null for top-level widgets. */
    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    protected function formatOptions(array $options): string
    {
        $formatted = [];
        foreach ($options as $key => $value) {
            $formatted[] = "-$key $value";
        }
        return implode(' ', $formatted);
    }

    /**
     * Quote a string for safe inclusion inside a Tcl command.
     *
     * Tcl interprets four characters inside a `"`-quoted word: backslash
     * (escape), dollar (variable substitution), open bracket (command
     * substitution), and the closing quote. Escape exactly those and the
     * value can never break out of its quoting, so user-supplied text like
     * `Hello"; destroy .; "` becomes a literal label rather than executing
     * the embedded `destroy` command.
     *
     * Brace-quoting was rejected because a value ending in a backslash
     * produces `{value\}` where `\}` reads as a literal `}` and never
     * closes the group, raising "unbalanced braces" instead.
     */
    public static function tclQuote(string $value): string
    {
        $escaped = str_replace(
            ['\\', '$', '[', '"'],
            ['\\\\', '\\$', '\\[', '\\"'],
            $value
        );
        return '"' . $escaped . '"';
    }

    /**
     * Build the trailing `-key "value"` portion of a Tcl widget command
     * from `$this->options`, skipping keys the caller handles separately.
     * Every value is run through tclQuote() so user-supplied data can't
     * inject arbitrary Tcl.
     *
     * @param list<string> $skip option keys handled by the caller
     */
    protected function buildOptionString(array $skip = []): string
    {
        $parts = [];
        foreach ($this->options as $key => $value) {
            if (in_array($key, $skip, true)) {
                continue;
            }
            $parts[] = '-' . $key . ' ' . self::tclQuote((string) $value);
        }
        return $parts === [] ? '' : ' ' . implode(' ', $parts);
    }

    private function requireNonTopLevel(string $manager): void
    {
        if ($this->parentId === null) {
            throw new \RuntimeException("Cannot {$manager} a top-level widget.");
        }
    }
}
