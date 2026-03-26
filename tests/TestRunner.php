<?php

namespace PhpGuiTest;

use PhpGui\ProcessTCL;

/**
 * Minimal test runner for php-gui.
 * Each test file calls TestRunner::summary() at the end, which exits with
 * code 1 if any assertion failed — making CI failures visible.
 */
class TestRunner
{
    private static int $passed = 0;
    private static int $failed = 0;
    private static string $suite  = '';

    public static function suite(string $name): void
    {
        self::$suite = $name;
        echo "\n[SUITE] {$name}\n";
    }

    public static function assert(bool $condition, string $message): void
    {
        if ($condition) {
            self::$passed++;
            echo "  [PASS] {$message}\n";
        } else {
            self::$failed++;
            echo "  [FAIL] {$message}\n";
        }
    }

    /** @param mixed $expected */
    /** @param mixed $actual */
    public static function assertEqual($expected, $actual, string $message): void
    {
        $ok = $expected === $actual;
        if (!$ok) {
            $message .= sprintf(
                ' (expected %s, got %s)',
                var_export($expected, true),
                var_export($actual, true)
            );
        }
        self::assert($ok, $message);
    }

    /** @param mixed $value */
    public static function assertNotEmpty($value, string $message): void
    {
        self::assert(!empty($value), $message);
    }

    /**
     * Assert that the Tcl widget at $path exists (winfo exists returns 1).
     */
    public static function assertWidgetExists(string $path, string $message): void
    {
        $result = trim(ProcessTCL::getInstance()->evalTcl("winfo exists {$path}"));
        self::assert($result === '1', $message . " (winfo exists {$path} = {$result})");
    }

    /**
     * Assert that the Tcl widget at $path no longer exists.
     */
    public static function assertWidgetGone(string $path, string $message): void
    {
        $result = trim(ProcessTCL::getInstance()->evalTcl("winfo exists {$path}"));
        self::assert($result === '0', $message . " (winfo exists {$path} = {$result})");
    }

    /**
     * Assert that $fn throws an instance of $exceptionClass.
     */
    public static function assertThrows(callable $fn, string $exceptionClass, string $message): void
    {
        try {
            $fn();
            self::$failed++;
            echo "  [FAIL] {$message} (no exception thrown)\n";
        } catch (\Throwable $e) {
            if ($e instanceof $exceptionClass) {
                self::$passed++;
                echo "  [PASS] {$message}\n";
            } else {
                self::$failed++;
                echo "  [FAIL] {$message} (got " . get_class($e) . ': ' . $e->getMessage() . ")\n";
            }
        }
    }

    /**
     * Assert that $fn does NOT throw.
     */
    public static function assertNoThrow(callable $fn, string $message): void
    {
        try {
            $fn();
            self::$passed++;
            echo "  [PASS] {$message}\n";
        } catch (\Throwable $e) {
            self::$failed++;
            echo "  [FAIL] {$message} (threw " . get_class($e) . ': ' . $e->getMessage() . ")\n";
        }
    }

    /**
     * Print results and exit with code 1 if any test failed.
     */
    public static function summary(): void
    {
        $total = self::$passed + self::$failed;
        echo "\n--- Results: " . self::$passed . "/{$total} passed";
        if (self::$failed > 0) {
            echo ', ' . self::$failed . " FAILED";
            echo " ---\n";
            exit(1);
        }
        echo " ---\n";
    }
}
