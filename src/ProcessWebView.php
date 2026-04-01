<?php

namespace PhpGui;

/**
 * Manages the webview helper child process and IPC.
 *
 * Launches the prebuilt webview_helper binary via proc_open() and communicates
 * over JSON-over-stdio (newline-delimited JSON on stdin/stdout).
 *
 * Analogous to ProcessTCL but for the webview subprocess rather than FFI.
 */
class ProcessWebView
{
    /** @var resource|null proc_open handle */
    private $process = null;

    /** @var resource|null stdin pipe (write) */
    private $stdin = null;

    /** @var resource|null stdout pipe (read, non-blocking) */
    private $stdout = null;

    /** @var resource|null stderr pipe (read, non-blocking) */
    private $stderr = null;

    private bool $ready = false;
    private bool $closed = false;
    private string $readBuffer = '';
    private bool $debug = false;
    private ?int $pid = null;

    /**
     * @param array{
     *     title?: string,
     *     width?: int,
     *     height?: int,
     *     debug?: bool,
     *     url?: string,
     * } $options
     */
    public function __construct(array $options = [])
    {
        $this->debug = $options['debug'] ?? false;
        $binary = $this->findBinary();

        // Build argv
        $args = [];
        if (isset($options['title'])) {
            $args[] = '--title';
            $args[] = $options['title'];
        }
        if (isset($options['width'])) {
            $args[] = '--width';
            $args[] = (string)$options['width'];
        }
        if (isset($options['height'])) {
            $args[] = '--height';
            $args[] = (string)$options['height'];
        }
        if (!empty($options['debug'])) {
            $args[] = '--debug';
        }
        if (isset($options['url'])) {
            $args[] = '--url';
            $args[] = $options['url'];
        }

        // Build command
        $cmd = escapeshellarg($binary);
        foreach ($args as $arg) {
            $cmd .= ' ' . escapeshellarg($arg);
        }

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $this->process = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($this->process)) {
            throw new \RuntimeException("Failed to start webview helper: {$binary}");
        }

        $this->stdin  = $pipes[0];
        $this->stdout = $pipes[1];
        $this->stderr = $pipes[2];

        // Non-blocking reads so pollEvents() never blocks the Tk event loop
        stream_set_blocking($this->stdout, false);
        stream_set_blocking($this->stderr, false);

        // Track PID for orphan cleanup
        $status = proc_get_status($this->process);
        $this->pid = $status['pid'] ?? null;

        // Prevent orphan helper processes
        $pid = $this->pid;
        register_shutdown_function(function () use ($pid) {
            if ($pid === null) return;
            if (PHP_OS_FAMILY === 'Windows') {
                @exec("taskkill /PID {$pid} /F 2>NUL");
            } else {
                @posix_kill($pid, 15); // SIGTERM
            }
        });
    }

    /**
     * Send a JSON command to the helper process.
     *
     * @param array<string, mixed> $cmd Command data (must include 'cmd' key)
     */
    public function sendCommand(array $cmd): void
    {
        if ($this->closed || !is_resource($this->stdin)) {
            throw new \RuntimeException("Cannot send command: webview process is closed");
        }

        $cmd['version'] = 1;
        $json = json_encode($cmd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($this->debug) {
            error_log("[WebView IPC ->] {$json}");
        }

        $written = @fwrite($this->stdin, $json . "\n");
        if ($written === false) {
            $this->closed = true;
            throw new \RuntimeException("Failed to write to webview helper stdin");
        }
        @fflush($this->stdin);
    }

    /**
     * Non-blocking poll for events from the helper process.
     *
     * @return array<int, array<string, mixed>> Array of parsed event objects
     */
    public function pollEvents(): array
    {
        if ($this->closed) {
            return [];
        }

        $events = [];
        $lines = $this->readLines();

        foreach ($lines as $line) {
            if ($this->debug) {
                error_log("[WebView IPC <-] {$line}");
            }

            $event = json_decode($line, true);
            if (!is_array($event) || !isset($event['event'])) {
                continue; // skip malformed
            }

            if ($event['event'] === 'ready') {
                $this->ready = true;
            } elseif ($event['event'] === 'closed') {
                $this->closed = true;
            }

            $events[] = $event;
        }

        // Detect unexpected process death
        if (!$this->closed && is_resource($this->process)) {
            $status = proc_get_status($this->process);
            if (!$status['running']) {
                $this->closed = true;
                $events[] = [
                    'version' => 1,
                    'event' => 'closed',
                    'reason' => 'process_exited',
                    'exit_code' => $status['exitcode'],
                ];
            }
        }

        return $events;
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function getProcessId(): ?int
    {
        return $this->pid;
    }

    /**
     * Gracefully shut down the helper process.
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        // Try graceful shutdown
        try {
            $this->sendCommand(['cmd' => 'destroy']);
        } catch (\Throwable $e) {
            // Pipe may already be broken
        }

        // Close pipes
        if (is_resource($this->stdin))  @fclose($this->stdin);
        if (is_resource($this->stdout)) @fclose($this->stdout);
        if (is_resource($this->stderr)) @fclose($this->stderr);

        $this->stdin = null;
        $this->stdout = null;
        $this->stderr = null;

        // Wait for process to exit, then force-kill
        if (is_resource($this->process)) {
            $deadline = microtime(true) + 0.5; // 500ms grace period
            while (microtime(true) < $deadline) {
                $status = proc_get_status($this->process);
                if (!$status['running']) break;
                usleep(20000); // 20ms
            }

            $status = proc_get_status($this->process);
            if ($status['running']) {
                proc_terminate($this->process);
            }
            proc_close($this->process);
        }

        $this->process = null;
        $this->closed = true;
    }

    public function __destruct()
    {
        if (!$this->closed) {
            $this->close();
        }
    }

    /**
     * Locate the platform-appropriate helper binary.
     */
    private function findBinary(): string
    {
        $libDir = dirname(__DIR__) . '/src/lib/';

        // Also check relative to this file (when installed via composer)
        if (!is_dir($libDir)) {
            $libDir = __DIR__ . '/lib/';
        }

        $os = match (PHP_OS_FAMILY) {
            'Darwin'  => 'darwin',
            'Windows' => 'windows',
            default   => 'linux',
        };

        $arch = php_uname('m');
        // Normalize architecture names
        if ($arch === 'AMD64') $arch = 'x86_64';
        if ($os === 'darwin' && $arch === 'aarch64') $arch = 'arm64';

        $ext = $os === 'windows' ? '.exe' : '';
        $binary = $libDir . "webview_helper_{$os}_{$arch}{$ext}";

        if (!file_exists($binary)) {
            // Try auto-downloading the binary
            $installer = dirname(__DIR__) . '/scripts/install-webview-helper.php';
            if (file_exists($installer)) {
                $exitCode = 0;
                passthru('php ' . escapeshellarg($installer), $exitCode);
                if ($exitCode === 0 && file_exists($binary)) {
                    if ($os !== 'windows' && !is_executable($binary)) {
                        chmod($binary, 0755);
                    }
                    return $binary;
                }
            }

            $msg = "WebView helper binary not found: {$binary}\n";
            $msg .= "Run: composer install-webview\n";
            $msg .= "Or build from source: cd src/lib/webview_helper && bash build.sh\n";
            if ($os === 'linux') {
                $msg .= "Requires: sudo apt-get install -y libgtk-3-dev libwebkit2gtk-4.1-dev\n";
            }
            throw new \RuntimeException($msg);
        }

        if ($os !== 'windows' && !is_executable($binary)) {
            chmod($binary, 0755);
        }

        return $binary;
    }

    /**
     * Non-blocking read of complete lines from stdout.
     * Accumulates partial reads in $readBuffer.
     *
     * @return string[] Complete JSON lines
     */
    private function readLines(): array
    {
        $lines = [];

        if (!is_resource($this->stdout)) {
            return $lines;
        }

        // Read all available data (non-blocking)
        while (true) {
            $chunk = @fread($this->stdout, 8192);
            if ($chunk === false || $chunk === '') {
                // On Windows, non-blocking fread may not work — try stream_select
                if (PHP_OS_FAMILY === 'Windows') {
                    $read = [$this->stdout];
                    $write = $except = null;
                    if (@stream_select($read, $write, $except, 0, 0) > 0) {
                        $chunk = @fread($this->stdout, 8192);
                        if ($chunk === false || $chunk === '') break;
                        $this->readBuffer .= $chunk;
                        continue;
                    }
                }
                break;
            }
            $this->readBuffer .= $chunk;
        }

        // Extract complete lines from buffer
        while (($pos = strpos($this->readBuffer, "\n")) !== false) {
            $line = substr($this->readBuffer, 0, $pos);
            $this->readBuffer = substr($this->readBuffer, $pos + 1);
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
