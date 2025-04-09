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

class Application {
    private ProcessTCL $tcl;
    private bool $running = false;
    private string $appId;

    /**
     * Application constructor.
     *
     * Initializes the Tcl interpreter, configures the root window,
     * and sets up the exit procedure using a quit signal file.
     */
    public function __construct() {
        $this->tcl = ProcessTCL::getInstance();
        $this->appId = uniqid('app_');
        $this->tcl->evalTcl("package require Tk");
        $this->tcl->evalTcl("wm withdraw ."); // Suppress the default root window
        // Update exit_app to write a quit signal file
        $this->tcl->evalTcl("proc ::exit_app {} { set ::forever 1; set f [open \"/tmp/phpgui_quit.txt\" w]; puts \$f 1; close \$f }");
    }

    /**
     * Runs the main event loop.
     *
     * Processes Tcl events continuously, checks for callback and quit signals,
     * and terminates the loop when a quit signal is received.
     */
    public function run(): void {
        if ($this->running) {
            return;
        }

        $this->running = true;
        // Main loop: process Tcl events, callback file, and quit file
        while ($this->running) {
            $this->tcl->evalTcl("update");
            $callbackFile = '/tmp/phpgui_callback.txt';
            if (file_exists($callbackFile)) {
                $id = trim(file_get_contents($callbackFile));
                unlink($callbackFile);
                ProcessTCL::getInstance()->executeCallback($id);
            }
            $quitFile = '/tmp/phpgui_quit.txt';
            if (file_exists($quitFile)) {
                unlink($quitFile);
                $this->running = false;
            }
            usleep(100000); // sleep for 0.1 second
        }
        $this->quit();
    }

    /**
     * Quits the application.
     *
     * Stops the main event loop and exits the application.
     */
    public function quit(): void {
        if (!$this->running) {
            return;
        }
        
        $this->running = false;
        exit(0); 
    }
}
