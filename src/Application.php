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

    /**
     * Application constructor.
     *
     * Initializes the Tcl interpreter, configures the root window,
     * and sets up the exit procedure using a quit signal file.
     */
    public function __construct()
    {
        $this->tcl = ProcessTCL::getInstance();
        $this->appId = uniqid('app_');
        $this->tcl->evalTcl("package require Tk");
        $this->tcl->evalTcl("wm withdraw .");

        $tempDir = str_replace('\\', '/', sys_get_temp_dir());
        $quitFile = $tempDir . "/phpgui_quit.txt";
        $this->tcl->evalTcl("proc ::exit_app {} { set ::forever 1; set f [open \"$quitFile\" w]; puts \$f 1; close \$f }");
    }

    /**
     * Runs the main event loop.
     *
     * Processes Tcl events continuously, checks for callback and quit signals,
     * and terminates the loop when a quit signal is received.
     */
    public function run(): void
    {
        if ($this->running) {
            return;
        }

        $this->running = true;
        $tempDir = str_replace('\\', '/', sys_get_temp_dir());
        $callbackFile = $tempDir . "/phpgui_callback.txt";
        $quitFile = $tempDir . "/phpgui_quit.txt";

        while ($this->running) {
            $this->tcl->evalTcl("update");
            if (file_exists($callbackFile)) {
                $id = trim(file_get_contents($callbackFile));
                unlink($callbackFile);
                ProcessTCL::getInstance()->executeCallback($id);
            }
            if (file_exists($quitFile)) {
                unlink($quitFile);
                $this->running = false;
            }

            // Poll all active WebView instances
            $hasActiveWebViews = false;
            foreach ($this->webviews as $key => $wv) {
                if ($wv->isClosed()) {
                    unset($this->webviews[$key]);
                    continue;
                }
                $wv->processEvents();
                $hasActiveWebViews = true;
            }

            // Adaptive sleep: faster when WebViews are active for better IPC responsiveness
            usleep($hasActiveWebViews ? 20000 : 100000);
        }
        $this->quit();
    }

    /**
     * Quits the application.
     *
     * Stops the main event loop and exits the application.
     */
    /**
     * Register a WebView to be polled in the event loop.
     */
    public function addWebView(\PhpGui\Widget\WebView $wv): void
    {
        $this->webviews[$wv->getId()] = $wv;
    }

    /**
     * Remove a WebView from the event loop.
     */
    public function removeWebView(\PhpGui\Widget\WebView $wv): void
    {
        unset($this->webviews[$wv->getId()]);
    }

    /**
     * Run a single iteration of the event loop (for testing).
     */
    public function tick(): void
    {
        $tempDir = str_replace('\\', '/', sys_get_temp_dir());
        $callbackFile = $tempDir . "/phpgui_callback.txt";
        $quitFile = $tempDir . "/phpgui_quit.txt";

        $this->tcl->evalTcl("update");

        if (file_exists($callbackFile)) {
            $id = trim(file_get_contents($callbackFile));
            unlink($callbackFile);
            ProcessTCL::getInstance()->executeCallback($id);
        }
        if (file_exists($quitFile)) {
            unlink($quitFile);
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

    public function quit(): void
    {
        if (!$this->running) {
            return;
        }

        // Close all WebView instances
        foreach ($this->webviews as $wv) {
            if (!$wv->isClosed()) {
                $wv->destroy();
            }
        }
        $this->webviews = [];

        $this->running = false;
        exit(0);
    }
}
