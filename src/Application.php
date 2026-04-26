<?php

/**
 * Class Application
 *
 * Main application class that initializes the Tcl/Tk environment,
 * manages the event loop, and dispatches PHP callbacks.
 *
 * Integrates the Tcl/Tk event processing with PHP application logic via FFI.
 */

namespace PhpGui;

class Application
{
    private ProcessTCL $tcl;
    private bool $running = false;
    private string $appId;

    /** @var \PhpGui\Widget\WebView[] */
    private array $webviews = [];

    public function __construct()
    {
        $this->tcl = ProcessTCL::getInstance();
        $this->appId = uniqid('app_');
        $this->tcl->evalTcl('package require Tk');
        $this->tcl->evalTcl('wm withdraw .');

        // Quit signal lives in a Tcl variable. The temp-file approach used
        // before v1.9 added 100ms of polling latency and collided across
        // concurrent php-gui processes (shared /tmp/phpgui_quit.txt).
        $this->tcl->evalTcl('proc ::exit_app {} { set ::phpgui_quit 1 }');
    }

    /**
     * Runs the main event loop.
     *
     * Each iteration: pump Tcl events, drain queued PHP callbacks, drive
     * any active WebView subprocesses, then sleep briefly. The sleep is
     * deliberately short — a 100ms loop felt sluggish and dropped events
     * when two fired within one tick.
     */
    public function run(): void
    {
        if ($this->running) {
            return;
        }

        $this->running = true;

        while ($this->running) {
            $this->tick();
            // 10ms gives ~100Hz responsiveness without busy-looping. WebView
            // mode polls subprocess pipes and benefits from the same cadence.
            usleep(10000);
        }
        $this->quit();
    }

    public function addWebView(\PhpGui\Widget\WebView $wv): void
    {
        $this->webviews[$wv->getId()] = $wv;
    }

    public function removeWebView(\PhpGui\Widget\WebView $wv): void
    {
        unset($this->webviews[$wv->getId()]);
    }

    /**
     * Run a single iteration of the event loop. Public for tests so they
     * can step the loop deterministically without sleeping.
     */
    public function tick(): void
    {
        $this->tcl->evalTcl('update');

        // Drain every queued callback, in order. The drain resets the
        // queue first, so closures that fire new events don't race with us.
        $this->tcl->drainPendingCallbacks();

        if ($this->tcl->shouldQuit()) {
            $this->running = false;
        }

        foreach ($this->webviews as $key => $wv) {
            if ($wv->isClosed()) {
                unset($this->webviews[$key]);
                continue;
            }
            $wv->processEvents();
        }
    }

    /**
     * Tear down the application. Closes WebViews and flips the run flag
     * so the loop exits at its next iteration. Does NOT call `exit()` —
     * `run()` returns to the caller, which can perform its own shutdown
     * work (logging, cleanup) before the script ends.
     */
    public function quit(): void
    {
        if (!$this->running) {
            return;
        }

        foreach ($this->webviews as $wv) {
            if (!$wv->isClosed()) {
                $wv->destroy();
            }
        }
        $this->webviews = [];

        $this->running = false;
    }
}
