# Event Loop & Callback Lifecycle

How php-gui dispatches user events into your PHP closures, how to drive the loop in tests, and what gets cleaned up when widgets are destroyed.

---

## How callbacks reach PHP

A button click, an Enter key in an `Input`, a menu pick — each is a Tk event bound to a small Tcl command:

```tcl
button .w1.w2 -command {php::executeCallback w2}
```

When the user clicks, Tk runs that command, which invokes the global `php::executeCallback` proc. From v1.9 onward that proc just appends the callback id to a Tcl list:

```tcl
proc php::executeCallback {id} {
    lappend ::phpgui_pending $id
}
```

The PHP-side event loop drains that list every iteration:

```
Application::run() loop
├─ tick()
│   ├─ tcl evalTcl "update"             ← Tk processes its own event queue
│   ├─ ProcessTCL::drainPendingCallbacks()
│   │   └─ for each id in ::phpgui_pending: invoke the registered closure
│   ├─ if ProcessTCL::shouldQuit(): set running = false
│   └─ pump WebView subprocesses
└─ usleep(10000)                          ← 10ms cadence
```

### Why an in-process queue (and not a temp file)

Versions ≤ 1.8 wrote each callback id to `/tmp/phpgui_callback.txt` and polled the file every 100ms. That had three failure modes:

| Symptom | Cause |
|---|---|
| 100ms perceived lag on every click | poll interval |
| Lost callbacks when two events fired between ticks | the file held one id at a time, so the second write overwrote the first |
| Two php-gui apps running simultaneously routed events to the wrong process | the file path is global to the host |

The Tcl-list approach removes all three: every call to `php::executeCallback` `lappend`s, the queue holds an arbitrary number of ids, and there is no shared filesystem state.

---

## Driving the loop in tests

`Application::run()` blocks. For unit tests, call `tick()` instead — it's the same single iteration the loop runs, but synchronous and returns immediately. Pair it with the deterministic-test helper, `ProcessTCL::drainPendingCallbacks()`:

```php
$app    = new Application();
$button = new Button($win->getId(), [
    'text'    => 'Go',
    'command' => function () use (&$fired) { $fired = true; },
]);

// Synthesize a click on the Tcl side without actually rendering.
$tcl->evalTcl("php::executeCallback {$button->getId()}");

// Either:
$app->tick();              // full tick: update + drain + quit-check + WebViews
// or, more focused:
$tcl->drainPendingCallbacks();
```

`drainPendingCallbacks()` returns the number of callbacks dispatched, which is convenient as an assertion target.

---

## Quit signal

The Tcl proc `::exit_app` sets `::phpgui_quit 1`. Each tick checks `ProcessTCL::shouldQuit()` and breaks out of the loop. There's no temp file.

`Application::quit()` performs cleanup (closes WebViews, flips the run flag) and **returns**. It does **not** call `exit()` — your script keeps running after `$app->run()` returns, so you can write final logs, sync state, etc.

---

## Callback lifecycle

Every widget that registers a closure via `ProcessTCL::registerCallback($id, $callable)` is responsible for releasing it on destroy. Since v1.9, `AbstractWidget::destroy()` does this automatically by calling `unregisterCallback($this->id)`.

Widgets that register callbacks under derived ids (e.g. `Menu` uses `{menuId}_cmd_0`, `_cmd_1`, …) override `destroy()` to free those too. `Window::onClose` and `Window::onResize` register `{windowId}_close` and `{windowId}_resize` and free them on `Window::destroy()`.

If you build a custom widget on top of `AbstractWidget`, follow the same pattern:

```php
public function destroy(): void
{
    foreach ($this->extraCallbackIds as $cbId) {
        $this->tcl->unregisterCallback($cbId);
    }
    parent::destroy();
}
```

A callback id that's been deregistered but is still queued in `::phpgui_pending` is a safe no-op — `ProcessTCL::executeCallback($id)` skips ids it doesn't recognise.

---

## Performance notes

- The 10ms tick cadence yields ~100Hz responsiveness, comfortable for any UI without burning CPU.
- Tk's own event loop runs inside `update`; the PHP-side drain is just bookkeeping.
- Long-running PHP work inside a callback **blocks the loop** — Tk events queue up but don't render until the callback returns. For long tasks, use `evalTcl('update')` periodically inside the callback to give Tk a chance to redraw.
