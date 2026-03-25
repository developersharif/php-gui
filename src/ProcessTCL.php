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
        $libDir = __DIR__ . '/lib/';

        // Set TCL_LIBRARY and TK_LIBRARY so Tcl_Init and "package require Tk"
        // find the bundled script libraries without needing system packages.
        $this->setupLibraryPaths($libDir);

        $cdef = "
            void* Tcl_CreateInterp(void);
            int Tcl_Init(void *interp);
            int Tcl_Eval(void *interp, const char *cmd);
            const char* Tcl_GetStringResult(void *interp);
            const char* Tcl_GetVar(void* interp, const char* varName, int flags);
            char* Tcl_SetVar(void* interp, const char* varName, const char* newValue, int flags);
        ";

        $this->ffi = $this->loadTclLibrary($cdef, $libDir);
    }

    /**
     * Tries to load the Tcl shared library, attempting bundled first then system paths.
     *
     * @throws \RuntimeException if no loadable Tcl library is found.
     */
    private function loadTclLibrary(string $cdef, string $libDir): FFI
    {
        $candidates = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates[] = str_replace('/', '\\', $libDir . 'windows/bin/tcl86t.dll');
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $candidates[] = $libDir . 'libtcl9.0.dylib';
            $candidates[] = $libDir . 'libtcl8.6.dylib';
        } else {
            $candidates[] = $libDir . 'libtcl8.6.so';
            $candidates[] = '/usr/lib/x86_64-linux-gnu/libtcl8.6.so';
            $candidates[] = '/usr/lib/aarch64-linux-gnu/libtcl8.6.so';
            $candidates[] = '/usr/lib64/libtcl8.6.so';
            $candidates[] = '/usr/lib/libtcl8.6.so';
            $candidates[] = '/usr/local/lib/libtcl8.6.so';
        }

        foreach ($candidates as $path) {
            if (!file_exists($path)) {
                continue;
            }
            try {
                return FFI::cdef($cdef, $path);
            } catch (\FFI\Exception $e) {
                // Bundled binary may be incompatible with this system, try next
                continue;
            }
        }

        throw new \RuntimeException(
            "TCL library could not be loaded. Install Tcl/Tk for your system:\n" .
            "  Debian/Ubuntu: sudo apt-get install libtcl8.6 libtk8.6\n" .
            "  RHEL/CentOS:   sudo yum install tcl tk\n" .
            "  macOS:         brew install tcl-tk"
        );
    }

    /**
     * Sets up TCL_LIBRARY, TK_LIBRARY, and TCLLIBPATH environment variables
     * to point at the bundled Tcl/Tk script libraries.
     */
    private function setupLibraryPaths(string $libDir): void
    {
        $tclScriptDir = $libDir . 'tcl8.6';
        $tkScriptDir = $libDir . 'tk8.6';

        if (is_dir($tclScriptDir)) {
            putenv('TCL_LIBRARY=' . $tclScriptDir);
        }
        if (is_dir($tkScriptDir)) {
            putenv('TK_LIBRARY=' . $tkScriptDir);
            // TCLLIBPATH tells Tcl where to search for package directories (like tk8.6/)
            putenv('TCLLIBPATH=' . $libDir);
        }

        // Add bundled X11 libraries to LD_LIBRARY_PATH so libtk can find them
        $x11Dir = $libDir . 'x11';
        if (is_dir($x11Dir)) {
            $current = getenv('LD_LIBRARY_PATH');
            $newPath = $current ? $x11Dir . ':' . $current : $x11Dir;
            putenv('LD_LIBRARY_PATH=' . $newPath);
        }
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
     * @return mixed The result of the command if successful
     * @throws \RuntimeException if the Tcl command returns an error.
     */
    public function evalTcl(string $command)
    {
        $interp = $this->getInterp();
        $result = $this->ffi->Tcl_Eval($interp, $command);
        if ($result !== 0) { // Check for errors
            $error = $this->ffi->Tcl_GetStringResult($interp);
            throw new \RuntimeException("Tcl Error: " . $error);
        }
        return $this->getResult();
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
     * Gets the value of a Tcl variable.
     *
     * @param string $varName The name of the Tcl variable to get
     * @return string The value of the Tcl variable
     */
    public function getVar(string $varName): string
    {
        $interp = $this->getInterp();
        $result = $this->ffi->Tcl_GetVar($interp, $varName, 0);
        if (is_string($result)) {
            return $result;
        }
        if ($result === null) {
            return "";
        }
        return FFI::string($result);
    }

    /**
     * Sets the value of a Tcl variable.
     *
     * @param string $varName The name of the Tcl variable to set
     * @param string $value The new value for the variable
     * @return string The new value of the variable
     */
    public function setVar(string $varName, string $value): string
    {
        $interp = $this->getInterp();
        $result = $this->ffi->Tcl_SetVar($interp, $varName, $value, 0);
        if (is_string($result)) {
            return $result;
        }
        if ($result === null) {
            return "";
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
        $tempDir = str_replace('\\', '/', sys_get_temp_dir());
        $callbackFile = $tempDir . "/phpgui_callback.txt";

        $this->evalTcl('
            namespace eval php {
                variable callbacks
                array set callbacks {}
            }
        ');
        $this->evalTcl("proc php::executeCallback {id} {
                set f [open \"{$callbackFile}\" w]
                puts \$f \$id
                close \$f
                update
            }");
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
