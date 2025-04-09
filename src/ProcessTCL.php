<?php

namespace PhpGui;

use FFI;

/**
 * Class ProcessTCL
 * 
 * A singleton wrapper for the Tcl interpreter using PHP's FFI.
 * Provides methods for evaluating Tcl commands, managing callbacks,
 * and bridging Tcl events back to PHP.
 * 
 * @author developersharif
 */
class ProcessTCL
{
    private FFI $ffi;
    private static ?ProcessTCL $instance = null;
    private array $callbacks = []; // registered callbacks

    /**
     * Private constructor.
     *
     * Loads the Tcl library and initializes the FFI interface.
     *
     * @throws \RuntimeException if the Tcl library cannot be found.
     */
    private function __construct()
    {
        $libDir = dirname(__DIR__) . '/src/lib/';
        if (PHP_OS_FAMILY === 'Windows') {
            $libPath = $libDir . 'tcl86t.dll';
            $libPath = str_replace('/', '\\', $libPath);
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $libPath = $libDir . 'libtcl9.0.dylib';
        } else {
            $libPath = $libDir . 'libtcl8.6.so';
        }
        if (!file_exists($libPath)) {
            throw new \RuntimeException("TCL library not found at: $libPath");
        }

        $this->ffi = FFI::cdef("
            void* Tcl_CreateInterp(void);
            int Tcl_Init(void *interp);
            int Tcl_Eval(void *interp, const char *cmd);
            const char* Tcl_GetStringResult(void *interp);
        ", $libPath);
    }

    /**
     * Returns the singleton instance of ProcessTCL.
     *
     * @return ProcessTCL The instance of the Tcl interpreter wrapper.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Evaluates a given Tcl command.
     *
     * @param string $command The Tcl command to execute.
     * @throws \RuntimeException if the Tcl command returns an error.
     */
    public function evalTcl(string $command): void
    {
        $interp = $this->getInterp();
        $result = $this->ffi->Tcl_Eval($interp, $command);
        if ($result !== 0) { // Check for errors
            $error = $this->ffi->Tcl_GetStringResult($interp);
            throw new \RuntimeException("Tcl Error: " . $error);
        }
    }

    /**
     * Retrieves the result of the last evaluated Tcl command.
     *
     * @return string The Tcl command result.
     */
    public function getResult(): string
    {
        $interp = $this->getInterp();
        $result = $this->ffi->Tcl_GetStringResult($interp);
        if (is_string($result)) {
            return $result;
        }
        return FFI::string($result);
    }

    /**
     * Returns the Tcl interpreter instance.
     *
     * Lazily initializes the interpreter, performs Tcl_Init,
     * and sets up the PHP callback bridge.
     *
     * @return mixed The Tcl interpreter instance.
     * @throws \RuntimeException if Tcl initialization fails.
     */
    private function getInterp()
    {
        static $interp = null;
        if ($interp === null) {
            $interp = $this->ffi->Tcl_CreateInterp();
            $init_status = $this->ffi->Tcl_Init($interp);
            if ($init_status !== 0) {
                throw new \RuntimeException("Failed to initialize Tcl interpreter.");
            }
            $this->definePhpCallbackBridge($interp);
        }
        return $interp;
    }

    /**
     * Defines the Tcl procedures required to call back into PHP.
     *
     * Sets up the PHP namespace in Tcl and registers the procedure that writes
     * the callback id to a temporary file.
     *
     * @param mixed $interp The Tcl interpreter instance.
     */
    private function definePhpCallbackBridge($interp): void
    {
        $this->evalTcl('
            namespace eval php {
                variable callbacks
                array set callbacks {}
            }
        ');
        $this->evalTcl('
            proc php::executeCallback {id} {
                set f [open "/tmp/phpgui_callback.txt" w]
                puts $f $id
                close $f
                update
            }
        ');
    }

    /**
     * Static helper to execute a PHP callback by id.
     *
     * @param string $id The identifier of the callback.
     */
    public static function callCallback(string $id): void
    {
        self::getInstance()->executeCallback($id);
    }

    /**
     * Registers a PHP callback with a specific id.
     *
     * @param string   $id       The identifier to associate with the callback.
     * @param callable $callback The PHP callback function.
     */
    public function registerCallback(string $id, callable $callback): void
    {
        $this->callbacks[$id] = $callback;
        $this->evalTcl("set php::callbacks($id) 1");
    }

    /**
     * Executes the registered PHP callback associated with the specified id.
     *
     * @param string $id The identifier of the callback.
     */
    public function executeCallback(string $id): void
    {
        if (isset($this->callbacks[$id])) {
            try {
                ($this->callbacks[$id])();
            } catch (\Throwable $e) {
                error_log("PHP Callback error: " . $e->getMessage());
            }
        }
    }

    /**
     * Cleans up the Tcl interpreter and resets callbacks.
     *
     * Called on object destruction to clean up Tcl resources.
     */
    public function cleanup(): void
    {
        try {
            if ($this->ffi !== null) {
                $this->evalTcl('if {[winfo exists .]} { destroy . }');
                $this->callbacks = [];
            }
        } catch (\Throwable $e) {
            // Ignore cleanup errors during shutdown
        }
    }

    /**
     * Destructor.
     *
     * Ensures proper cleanup of Tcl resources when the object is destructed.
     */
    public function __destruct()
    {
        $this->cleanup();
    }
}
