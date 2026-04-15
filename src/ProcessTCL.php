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
    private bool $isTcl9;
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

        $this->ffi = $this->loadTclLibrary($libDir);
    }

    /**
     * Returns the FFI C declarations for Tcl 8.6 (Linux/Windows).
     */
    private function getCdefTcl8(): string
    {
        return "
            void* Tcl_CreateInterp(void);
            int Tcl_Init(void *interp);
            int Tcl_Eval(void *interp, const char *cmd);
            const char* Tcl_GetStringResult(void *interp);
            const char* Tcl_GetVar(void* interp, const char* varName, int flags);
            char* Tcl_SetVar(void* interp, const char* varName, const char* newValue, int flags);
        ";
    }

    /**
     * Returns the FFI C declarations for Tcl 9.0 (macOS).
     * Tcl 9 removed Tcl_Eval/Tcl_GetStringResult/Tcl_GetVar/Tcl_SetVar.
     */
    private function getCdefTcl9(): string
    {
        return "
            void* Tcl_CreateInterp(void);
            int Tcl_Init(void *interp);
            int Tcl_EvalEx(void *interp, const char *cmd, int numBytes, int flags);
            void* Tcl_GetObjResult(void *interp);
            const char* Tcl_GetString(void *objPtr);
            const char* Tcl_GetVar2(void* interp, const char* name1, const char* name2, int flags);
            char* Tcl_SetVar2(void* interp, const char* name1, const char* name2, const char* newValue, int flags);
        ";
    }

    /**
     * Tries to load the Tcl shared library, attempting bundled first then system paths.
     *
     * @throws \RuntimeException if no loadable Tcl library is found.
     */
    /**
     * Tries to load the Tcl shared library, attempting bundled first then system paths.
     *
     * @throws \RuntimeException if no loadable Tcl library is found.
     */
    private function loadTclLibrary(string $libDir): FFI
    {
        // Each candidate: [path, isTcl9]
        $candidates = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates[] = [str_replace('/', '\\', $libDir . 'windows/bin/tcl86t.dll'), false];
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $candidates[] = [$libDir . 'libtcl9.0.dylib', true];
            $candidates[] = [$libDir . 'libtcl8.6.dylib', false];
        } else {
            // Bundled library first (zero-dependency).
            // The bundled libtcl8.6.so was rebuilt on Ubuntu 24.04 (glibc 2.39) so it
            // links directly against libc.so.6 — no separate libpthread.so.0/libdl.so.2.
            // On older glibc (< 2.34) FFI::cdef() will throw FFI\Exception because the
            // library requires newer symbol versions; that is caught below and the system
            // library is tried instead.
            $candidates[] = [$libDir . 'libtcl8.6.so', false];

            // System library fallback (multi-arch + common paths)
            $candidates[] = ['/usr/lib/x86_64-linux-gnu/libtcl8.6.so', false];
            $candidates[] = ['/usr/lib/aarch64-linux-gnu/libtcl8.6.so', false];
            $candidates[] = ['/usr/lib64/libtcl8.6.so', false];
            $candidates[] = ['/usr/lib/libtcl8.6.so', false];
            $candidates[] = ['/usr/local/lib/libtcl8.6.so', false];
        }

        foreach ($candidates as [$path, $isTcl9]) {
            if (!file_exists($path)) {
                continue;
            }
            $cdef = $isTcl9 ? $this->getCdefTcl9() : $this->getCdefTcl8();
            try {
                $ffi = FFI::cdef($cdef, $path);
                $this->isTcl9 = $isTcl9;
                return $ffi;
            } catch (\FFI\Exception $e) {
                continue;
            }
        }

        $msg = "Tcl library could not be loaded.\n";
        if (PHP_OS_FAMILY === 'Windows') {
            $msg .= "Ensure PHP is 64-bit and matches the bundled DLL architecture.\n";
            $msg .= "If the issue persists, install Tcl/Tk from https://www.activestate.com/products/tcl/";
        } elseif (PHP_OS_FAMILY !== 'Darwin') {
            $msg .= "Install Tcl/Tk:\n";
            $msg .= "  Debian/Ubuntu: sudo apt-get install tcl8.6 tk8.6\n";
            $msg .= "  RHEL/Fedora:   sudo dnf install tcl tk";
        } else {
            $msg .= "Install Tcl/Tk: brew install tcl-tk";
        }
        throw new \RuntimeException($msg);
    }

    /**
     * Sets up TCL_LIBRARY, TK_LIBRARY, and TCLLIBPATH environment variables
     * to point at the bundled Tcl/Tk script libraries.
     */
    private function setupLibraryPaths(string $libDir): void
    {
        // macOS uses Tcl/Tk 9.0, Linux uses 8.6
        if (PHP_OS_FAMILY === 'Darwin') {
            $tclScriptDir = $libDir . 'tcl9.0';
            $tkScriptDir = $libDir . 'tk9.0';
        } else {
            $tclScriptDir = $libDir . 'tcl8.6';
            $tkScriptDir = $libDir . 'tk8.6';
        }

        if (is_dir($tclScriptDir)) {
            putenv('TCL_LIBRARY=' . $tclScriptDir);
        }
        if (is_dir($tkScriptDir)) {
            putenv('TK_LIBRARY=' . $tkScriptDir);
            // TCLLIBPATH tells Tcl where to search for package directories (like tk9.0/ or tk8.6/)
            putenv('TCLLIBPATH=' . $libDir);
        }

        // Windows: add bundled DLL directory to PATH so tcl86t.dll can find zlib1.dll etc.
        if (PHP_OS_FAMILY === 'Windows') {
            $winBinDir = str_replace('/', '\\', $libDir . 'windows/bin');
            if (is_dir($winBinDir)) {
                $current = getenv('PATH');
                $newPath = $current ? $winBinDir . ';' . $current : $winBinDir;
                putenv('PATH=' . $newPath);
            }
        }

        // Add bundled X11 libraries to LD_LIBRARY_PATH so libtk can find them (Linux)
        $x11Dir = $libDir . 'x11';
        if (is_dir($x11Dir)) {
            $current = getenv('LD_LIBRARY_PATH');
            $newPath = $current ? $x11Dir . ':' . $current : $x11Dir;
            putenv('LD_LIBRARY_PATH=' . $newPath);
        }

        // macOS: ensure bundled dylibs (libtommath) can be found at runtime
        if (PHP_OS_FAMILY === 'Darwin') {
            $current = getenv('DYLD_FALLBACK_LIBRARY_PATH');
            $newPath = $current ? $libDir . ':' . $current : $libDir;
            putenv('DYLD_FALLBACK_LIBRARY_PATH=' . $newPath);
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
        if ($this->isTcl9) {
            $result = $this->ffi->Tcl_EvalEx($interp, $command, -1, 0);
        } else {
            $result = $this->ffi->Tcl_Eval($interp, $command);
        }
        if ($result !== 0) {
            $error = $this->getResult();
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
        if ($this->isTcl9) {
            $objPtr = $this->ffi->Tcl_GetObjResult($interp);
            $result = $this->ffi->Tcl_GetString($objPtr);
        } else {
            $result = $this->ffi->Tcl_GetStringResult($interp);
        }
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
        if ($this->isTcl9) {
            $result = $this->ffi->Tcl_GetVar2($interp, $varName, null, 0);
        } else {
            $result = $this->ffi->Tcl_GetVar($interp, $varName, 0);
        }
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
        if ($this->isTcl9) {
            $result = $this->ffi->Tcl_SetVar2($interp, $varName, null, $value, 0);
        } else {
            $result = $this->ffi->Tcl_SetVar($interp, $varName, $value, 0);
        }
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
