<?php

namespace PhpGui\Widget;

use PhpGui\ProcessTCL;

/**
 * Class Notebook
 * Tabbed container — wraps Tk's `ttk::notebook`. Each tab hosts a child
 * widget (typically a `Frame`) that becomes visible when its tab is
 * selected.
 *
 *   $nb = new Notebook($win->getId());
 *   $nb->pack(['fill' => 'both', 'expand' => 1]);
 *
 *   $page1 = new Frame($nb->getId());
 *   $nb->addTab($page1, 'General');
 *   // …populate $page1 with widgets…
 *
 *   $page2 = new Frame($nb->getId());
 *   $nb->addTab($page2, 'Advanced');
 *
 *   $nb->onTabChange(fn(int $idx) => echo "now on tab {$idx}\n");
 *
 * @package PhpGui\Widget
 */
class Notebook extends AbstractWidget
{
    /** @var list<AbstractWidget> Tab pages, in display order. */
    private array $pages = [];

    public function __construct(string $parentId, array $options = [])
    {
        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void
    {
        $extra = $this->buildOptionString();
        $this->tcl->evalTcl("ttk::notebook {$this->tclPath}{$extra}");
    }

    /**
     * Attach `$page` as a new tab. The page must be a child of this
     * Notebook (i.e. constructed with `$notebook->getId()` as its parent).
     *
     * `$options` accepts Tk tab options like `state` ('normal' | 'disabled'
     * | 'hidden'), `image`, `compound`, `underline`, `padding`, `sticky`.
     */
    public function addTab(AbstractWidget $page, string $title, array $options = []): void
    {
        if ($page->getParentId() !== $this->id) {
            throw new \InvalidArgumentException(
                'Notebook::addTab page must be a child of this Notebook '
                . '(construct it with $notebook->getId() as the parent).'
            );
        }

        $tabOpts = '-text ' . self::tclQuote($title);
        foreach ($options as $key => $value) {
            $tabOpts .= ' -' . $key . ' ' . self::tclQuote((string) $value);
        }

        $this->tcl->evalTcl(
            "{$this->tclPath} add {$page->getTclPath()} {$tabOpts}"
        );
        $this->pages[] = $page;
    }

    /**
     * Switch to the tab at `$index` (0-based). Throws if out of range.
     */
    public function selectTab(int $index): void
    {
        $this->assertIndexInRange($index);
        $this->tcl->evalTcl("{$this->tclPath} select {$index}");
    }

    /** Switch to the tab whose page is `$page`. Throws if not in this notebook. */
    public function selectPage(AbstractWidget $page): void
    {
        $idx = $this->indexOfPage($page);
        if ($idx === null) {
            throw new \InvalidArgumentException(
                'Notebook::selectPage given a widget that is not a tab in this Notebook.'
            );
        }
        $this->selectTab($idx);
    }

    /** Currently-selected tab index, or -1 if the notebook is empty. */
    public function getSelectedIndex(): int
    {
        if ($this->pages === []) {
            return -1;
        }
        $selectedPath = trim($this->tcl->evalTcl("{$this->tclPath} select"));
        if ($selectedPath === '') {
            return -1;
        }
        foreach ($this->pages as $idx => $page) {
            if ($page->getTclPath() === $selectedPath) {
                return $idx;
            }
        }
        return -1;
    }

    /** Currently-selected page widget, or null if the notebook is empty. */
    public function getSelectedPage(): ?AbstractWidget
    {
        $idx = $this->getSelectedIndex();
        return $idx >= 0 ? $this->pages[$idx] : null;
    }

    /** Total tab count. */
    public function getTabCount(): int
    {
        return count($this->pages);
    }

    /**
     * Remove the tab at `$index`. The tab's page widget is detached but
     * not destroyed — the caller can re-attach it elsewhere or call
     * `destroy()` on it explicitly.
     */
    public function removeTab(int $index): void
    {
        $this->assertIndexInRange($index);
        $this->tcl->evalTcl("{$this->tclPath} forget {$index}");
        array_splice($this->pages, $index, 1);
    }

    /** Update an existing tab's title. */
    public function setTabTitle(int $index, string $title): void
    {
        $this->assertIndexInRange($index);
        $this->tcl->evalTcl(
            "{$this->tclPath} tab {$index} -text " . self::tclQuote($title)
        );
    }

    /** Read an existing tab's title. */
    public function getTabTitle(int $index): string
    {
        $this->assertIndexInRange($index);
        return trim($this->tcl->evalTcl("{$this->tclPath} tab {$index} -text"));
    }

    /**
     * Set a tab's state — 'normal' (default), 'disabled' (greyed out, can't
     * be selected), or 'hidden' (not shown at all). Any other value throws.
     */
    public function setTabState(int $index, string $state): void
    {
        $this->assertIndexInRange($index);
        if (!in_array($state, ['normal', 'disabled', 'hidden'], true)) {
            throw new \InvalidArgumentException(
                "Tab state must be 'normal', 'disabled', or 'hidden', got '{$state}'."
            );
        }
        $this->tcl->evalTcl(
            "{$this->tclPath} tab {$index} -state " . self::tclQuote($state)
        );
    }

    /**
     * Register a handler for the `<<NotebookTabChanged>>` virtual event —
     * fires whenever the selected tab changes (via the user clicking a
     * tab strip, keyboard, or `selectTab()`/`selectPage()`).
     *
     * The handler receives the new tab index as an int.
     */
    public function onTabChange(callable $handler): void
    {
        $cbId = $this->id . '_tab_change';
        ProcessTCL::getInstance()->registerCallback($cbId, function () use ($handler) {
            $handler($this->getSelectedIndex());
        });
        $this->tcl->evalTcl(
            "bind {$this->tclPath} <<NotebookTabChanged>> "
                . '{php::executeCallback ' . $cbId . '}'
        );
    }

    public function destroy(): void
    {
        $this->tcl->unregisterCallback($this->id . '_tab_change');
        $this->pages = [];
        parent::destroy();
    }

    private function assertIndexInRange(int $index): void
    {
        $count = count($this->pages);
        if ($index < 0 || $index >= $count) {
            throw new \OutOfRangeException(
                "Notebook tab index {$index} out of range (have {$count} tabs)."
            );
        }
    }

    private function indexOfPage(AbstractWidget $page): ?int
    {
        foreach ($this->pages as $idx => $candidate) {
            if ($candidate === $page) {
                return $idx;
            }
        }
        return null;
    }
}
