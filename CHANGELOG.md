# Changelog

All notable changes to **php-gui** are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to semantic versioning.

---

## [v1.9] — 2026-04-26

A foundation release. Closes the four production blockers from the v1.0 plan and ships twelve new essential widgets. Tests grew from 159 → **453 passing assertions** across 30 files.

If you're upgrading from v1.8, the changes are **backwards-compatible at the public API level** — all v1.8 code continues to work, but you also get safer defaults, a faster event loop, and a much larger widget toolbox.

### Highlights

- **Twelve new widgets** — `Text`, `Scrollbar`, `Listbox`, `Radiobutton` (with `RadioGroup`), `Scale`, `Spinbox`, `Progressbar`, `Notebook`, `Treeview`, `PanedWindow`, `LabelFrame`, `Separator`. The library now covers the full table-stakes set for desktop apps.
- **Animated GIFs** — the `Image` widget now plays multi-frame GIFs with proper disposal and transparency compositing.
- **JPEG + BMP via GD** — `Image` transcodes formats Tk core can't read into temp PNGs.
- **Arbitrary widget nesting** — Frames inside Frames inside Windows now work the way you'd expect.
- **Tcl-injection eliminated** — every widget now safely quotes user-supplied strings.
- **Event loop is in-process** — no more `/tmp/phpgui_callback.txt`, no 100 ms input lag, no cross-process collisions.

---

### Added — New widgets

#### `Text`
Multi-line editor with safe value routing through Tcl variables, `setText` / `getText` / `append` / `insertAt` / `clear`, length and line counts, `setState('disabled')` for read-only log views (still accepts `append()`). Strict index validation on `insertAt()` blocks Tcl injection through index expressions.

#### `Scrollbar`
Wraps `ttk::scrollbar` with manual `bindTo($target)` two-way wiring, plus a one-call `Scrollbar::attachTo($target, $orient)` factory that creates, binds, and packs in one shot. Both orientations.

#### `Listbox`
Selectable list with all four Tk select modes (`browse`, `single`, `multiple`, `extended`). Full add/remove/replace/clear, all selection accessors, `<<ListboxSelect>>` virtual-event binding via `onSelect()`. Items round-trip Tcl-special characters literally.

#### `Radiobutton` + `RadioGroup`
First-class `RadioGroup` object backing a single Tcl variable — solves the silent foot-gun where two radios accidentally share `-variable` names. Per-radio `command` callback receives the value; group-level `onChange` fires for both user clicks and programmatic `setValue()`.

#### `Scale`
Slider input with `from`/`to`/`orient`, `getValue`/`setValue`, and `onChange` that fires uniformly for user drag and programmatic updates.

#### `Spinbox`
Bounded numeric input or fixed enumeration via `values`. `onChange` is wired to `<Return>` and `<FocusOut>` in addition to Tk's `-command`, so direct typing fires the handler.

#### `Progressbar`
Both determinate (`setValue` / `step`) and indeterminate (`start(intervalMs)` / `stop`) modes; `setMode()` switches at runtime; all inputs validated.

#### `Notebook`
Tabbed container wrapping `ttk::notebook`. `addTab` / `selectTab` / `selectPage` / `setTabState` (`normal` / `disabled` / `hidden`); `onTabChange` bound to `<<NotebookTabChanged>>`. Enforces page-is-child invariant at `addTab()` time so the silent "empty pane" Tk failure mode can't happen.

#### `Treeview`
Hierarchical list / multi-column table — the single biggest missing widget for desktop apps. Two operating modes:
- Flat tables with named columns and headings
- Hierarchies (folder trees, outlines)

Positional and keyed value APIs, full selection management (single + multi), `onSelect` and `onDoubleClick` (with click-coordinate-to-row resolution), column / heading configuration, `delete` cascades to descendants. All values go through safe-quoting; row IDs are deterministic and Tcl-safe.

#### `PanedWindow`
Resizable split container; `addPane` with `weight`-based space distribution, `setSashPosition` / `getSashPosition`, both orientations.

#### `LabelFrame`
Titled bordered container — a `Frame` whose top edge is broken by a small caption. Optional title (empty produces a plain bordered frame).

#### `Separator`
Thin horizontal or vertical divider line.

---

### Added — Image widget improvements

- **Animated GIFs** — full multi-frame playback with disposal handling, on-load frame compositing onto a logical-screen-sized canvas, and per-frame snapshot pre-decoding so frame swaps never flash transparent gaps. New `getFrameCount()` / `isAnimated()`.
- **JPEG + BMP support** — transparent transcoding through PHP's GD extension to a temp PNG before Tk loads it. Original path is preserved by `getPath()`; the temp PNG is unlinked automatically on `setPath()` and `destroy()`.
- **Safe Tcl quoting** for all option values; paths route through Tcl variables so spaces, brackets, `$`, and quotes never break command parsing.

---

### Added — Window event hooks

- `Window::onClose(callable)` — runs your handler before the application exits. Returning `false` vetoes the close. Useful for "save unsaved changes?" prompts.
- `Window::onResize(callable)` — fires `(int $width, int $height)` on actual resizes (descendant `<Configure>` events and same-size duplicates are filtered out).

---

### Added — Public API additions

- `AbstractWidget::tclQuote(string)` — central safe-quoting helper.
- `AbstractWidget::buildOptionString(array $skip)` — replaces every per-widget option-formatter; routes every value through `tclQuote()`.
- `AbstractWidget::getParentId()` / `getTclPath()` — used internally by factories like `Scrollbar::attachTo`, also useful for advanced custom widgets.
- `ProcessTCL::drainPendingCallbacks()` — drains the new in-process callback queue (returns count for tests).
- `ProcessTCL::shouldQuit()` — reads the new `::phpgui_quit` flag.
- `ProcessTCL::unregisterCallback($id)` — releases a previously-registered callback.

---

### Changed

- **Event loop replaced** — `/tmp/phpgui_callback.txt` polling is gone. Tcl-side handlers now `lappend ::phpgui_pending $id`; the PHP event loop drains that list every tick. Loop cadence dropped from 100 ms to 10 ms (~100 Hz responsiveness). Lost-callback bug fixed (queue holds an arbitrary number of IDs); cross-process collision fixed (no shared filesystem state).
- **Quit signal** — `/tmp/phpgui_quit.txt` removed; `::exit_app` now sets `::phpgui_quit 1`.
- **`Application::quit()`** — no longer calls `exit(0)`. Flips the run flag and tears down WebViews, then `Application::run()` returns to the caller. You can finalise (logging, cleanup) before the script ends.
- **Widget destruction** — `AbstractWidget::destroy()` now also calls `unregisterCallback($this->id)`. `Menu::destroy()` cascades through its per-command callback IDs and submenus. `Window::destroy()` frees the `onClose` / `onResize` IDs.
- **Widget nesting** — `AbstractWidget` now tracks the full Tk path (`getTclPath()`) instead of assuming one level under root. Frames inside Frames inside Windows finally work as advertised.

---

### Fixed

- **Tcl injection across all widgets.** Before v1.9, a label whose text contained `Hello"; destroy .; "` would execute the embedded `destroy` command. Now every widget runs user-supplied strings through `tclQuote()` (or, where possible, sets a Tcl variable directly via FFI). Documented in `docs/security.md`. Regression suite in `tests/widgets_test/TclInjectionTest.php`.
- **Lost callbacks.** Two events fired between event-loop ticks no longer collapse to one.
- **Callback memory leaks.** Closures registered by destroyed widgets are now released.
- **`docs/Frame.md` was aspirational.** The Tcl-path refactor makes its nesting examples actually work.
- **Animated GIFs flickered transparent dots.** Pre-decoded frame snapshots remove the per-tick reload flash.
- **GIF `setPath` after animation produced "couldn't recognize data" errors.** Reset `-format` to `{}` when swapping to non-GIFs.
- **Spinbox `value` shorthand was clobbering `-values`.** Tk partial-matched `-value` to `-values` and overwrote the enumeration list.

---

### Documentation

New pages in `docs/`:

- `security.md` — Tcl-quoting policy and threat model.
- `event-loop.md` — IPC redesign, test patterns, callback lifecycle.
- `Image.md`, `Text.md`, `Scrollbar.md`, `Listbox.md`, `Radiobutton.md`, `Scale.md`, `Spinbox.md`, `Progressbar.md`, `Notebook.md`, `Treeview.md`, `PanedWindow.md`, `LabelFrame.md`, `Separator.md`.

`docs/Window.md` updated with `onClose` / `onResize` examples. `docs/_sidebar.md` updated to list every new page.

---

### Tests

- 159 → **453 passing assertions** across 30 files.
- New suites: `EventLoopTest`, `ImageTest`, `LabelFrameTest`, `ListboxTest`, `NestingTest`, `NotebookTest`, `PanedWindowTest`, `ProgressbarTest`, `RadiobuttonTest`, `ScaleTest`, `ScrollbarTest`, `SeparatorTest`, `SpinboxTest`, `TclInjectionTest`, `TextTest`, `TreeviewTest`, `WindowEventsTest`.

Run any suite with `php tests/widgets_test/<Name>Test.php` — exits non-zero on failure.

---

### Upgrade notes

No breaking changes. If you were relying on the temp-file behaviour (`/tmp/phpgui_callback.txt`, `/tmp/phpgui_quit.txt`) from outside the library, those files no longer exist. If you called `Application::quit()` and counted on `exit(0)`, add an explicit `exit;` at the end of your script.

---

## [v1.8] — earlier

See `git log v1.7..v1.8`.

## Earlier releases

Earlier release tags (`v1.0` through `v1.7`) are in the git history.
