<?php

namespace PhpGui\Widget;

use PhpGui\ProcessWebView;

/**
 * WebView widget — opens a native webview window via a helper process.
 *
 * Does NOT extend AbstractWidget because it creates a separate native window
 * (not a Tcl/Tk widget). Managed by Application::run() via processEvents().
 *
 * Provides a Tauri-like API:
 *   - Commands: JS → PHP (like Tauri's invoke())
 *   - Events:  PHP → JS (like Tauri's emit())
 */
class WebView
{
    private ProcessWebView $process;
    private string $id;
    private bool $debug;

    /** @var resource|null PHP built-in server process for serveDirectory() */
    private $serverProcess = null;
    private ?int $serverPort = null;

    /** @var array<string, callable> JS→PHP command handlers: name => callback(string $id, string $args) */
    private array $commandHandlers = [];

    /** @var callable|null */
    private $onCloseCallback = null;

    /** @var callable|null */
    private $onErrorCallback = null;

    /** @var callable|null */
    private $onReadyCallback = null;

    /** @var callable|null Called when serveFromDisk() is ready: fn(string $url) */
    private $onServeDirReadyCallback = null;

    /**
     * @param array{
     *     title?: string,
     *     width?: int,
     *     height?: int,
     *     url?: string,
     *     html?: string,
     *     debug?: bool,
     * } $options
     */
    public function __construct(array $options = [])
    {
        $this->id = uniqid('wv');
        $this->debug = $options['debug'] ?? false;

        $this->process = new ProcessWebView([
            'title'  => $options['title'] ?? 'WebView',
            'width'  => $options['width'] ?? 800,
            'height' => $options['height'] ?? 600,
            'debug'  => $this->debug,
            'url'    => $options['url'] ?? null,
        ]);

        // If HTML content was provided, send it after construction
        if (isset($options['html'])) {
            $this->setHtml($options['html']);
        }
    }

    /* ── Content ─────────────────────────────────────────────────────────── */

    /**
     * Navigate to a URL.
     */
    public function navigate(string $url): void
    {
        $this->process->sendCommand(['cmd' => 'navigate', 'url' => $url]);
    }

    /**
     * Set the webview content to raw HTML.
     */
    public function setHtml(string $html): void
    {
        $this->process->sendCommand(['cmd' => 'set_html', 'html' => $html]);
    }

    /**
     * Serve a directory via PHP's built-in web server and navigate to it.
     *
     * Ideal for loading production frontend builds (e.g., Vite's dist/ folder).
     *
     * @param string $path Path to the directory containing index.html
     * @param int $port Port to use (0 = auto-pick a free port)
     */
    public function serveDirectory(string $path, int $port = 0): void
    {
        $path = realpath($path);
        if (!$path || !is_dir($path)) {
            throw new \RuntimeException("Directory not found: {$path}");
        }
        if (!file_exists($path . '/index.html')) {
            throw new \RuntimeException("No index.html found in: {$path}");
        }

        // Auto-pick a free port
        if ($port === 0) {
            $sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
            if (!$sock) {
                throw new \RuntimeException("Could not find a free port: {$errstr}");
            }
            $addr = stream_socket_get_name($sock, false);
            $port = (int) substr($addr, strrpos($addr, ':') + 1);
            fclose($sock);
        }

        // Start PHP built-in server
        $cmd = sprintf(
            '%s -S 127.0.0.1:%d -t %s',
            PHP_BINARY,
            $port,
            escapeshellarg($path)
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $this->serverProcess = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($this->serverProcess)) {
            throw new \RuntimeException("Failed to start local server on port {$port}");
        }
        $this->serverPort = $port;

        // Close stdin, we don't need it
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        // Wait for server to be ready (up to 3 seconds)
        $deadline = microtime(true) + 3.0;
        while (microtime(true) < $deadline) {
            $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($fp) {
                fclose($fp);
                break;
            }
            usleep(50000); // 50ms
        }

        $this->navigate("http://127.0.0.1:{$port}");
    }

    /**
     * Serve a directory directly via the native webview engine (no HTTP server).
     *
     * Uses platform-specific mechanisms to serve files without a local server:
     *   - Linux:   phpgui:// custom URI scheme (WebKitGTK)
     *   - Windows: https://phpgui.localhost/ virtual host (WebView2)
     *   - macOS:   file:// direct access (WKWebView) — requires Vite `base: './'`
     *
     * Includes SPA fallback on Linux (unknown paths serve index.html).
     * For hash-based routing on macOS (e.g. createHashRouter in React Router).
     *
     * This is the recommended method for production builds — no ports,
     * no firewall prompts, no extra processes.
     *
     * Listen for the effective URL via onServeDirReady().
     *
     * @param string $path Absolute path to directory containing index.html
     */
    public function serveFromDisk(string $path): void
    {
        $path = realpath($path);
        if (!$path || !is_dir($path)) {
            throw new \RuntimeException("Directory not found: {$path}");
        }
        if (!file_exists($path . '/index.html')) {
            throw new \RuntimeException("No index.html found in: {$path}");
        }

        $this->process->sendCommand(['cmd' => 'serve_dir', 'path' => $path]);
    }

    /**
     * Serve a Vite frontend: dev server in development, disk in production.
     *
     * - Dev:  if the Vite dev server is reachable at $devUrl, navigate to it
     *         (supports HMR / hot reload).
     * - Prod: if dev server is not reachable, serve $buildDir via
     *         serveFromDisk() — no HTTP server, no ports, no firewall prompts.
     *
     * Typical usage:
     *   $webview->serveVite(__DIR__ . '/../frontend/dist');
     *
     * For macOS compatibility, add `base: './'` to your vite.config.js.
     *
     * @param string $buildDir Absolute path to Vite build output (dist/ folder)
     * @param string $devUrl   Vite dev server URL (default: http://localhost:5173)
     * @param float  $timeout  Seconds to wait when probing the dev server
     */
    public function serveVite(
        string $buildDir,
        string $devUrl = 'http://localhost:5173',
        float $timeout = 0.3,
    ): void {
        $parts = parse_url($devUrl);
        $host  = $parts['host'] ?? 'localhost';
        $port  = $parts['port'] ?? (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);

        $fp = @fsockopen($host, (int)$port, $errno, $errstr, $timeout);
        if ($fp !== false) {
            fclose($fp);
            $this->navigate($devUrl);
            return;
        }

        $this->serveFromDisk($buildDir);
    }

    /**
     * Enable transparent fetch() proxying through PHP.
     *
     * Intercepts all window.fetch() calls to absolute http(s) URLs and routes
     * them through PHP — bypassing CORS entirely, since PHP makes the request
     * server-side. Same-origin requests (relative URLs, phpgui://, file://)
     * are passed through to the native fetch unchanged.
     *
     * Solves the CORS problem on all platforms:
     *   - macOS: file:// origin is "null" — blocked by most APIs
     *   - Linux: phpgui:// origin unknown — not in any server's allowlist
     *   - Windows: https://phpgui.localhost — same issue
     *
     * Call this once after construction. Works with any frontend framework.
     * The frontend code needs zero changes — fetch() behaves normally.
     *
     * Uses cURL if available, falls back to stream_context (file_get_contents).
     * Response body is base64-encoded over the IPC bridge for binary safety.
     *
     * Example:
     *   $webview = new WebView([...]);
     *   $webview->enableFetchProxy();  // ← one line, fetch() just works
     *   $webview->serveFromDisk(__DIR__ . '/dist');
     */
    public function enableFetchProxy(): void
    {
        // PHP side: handle __phpFetch commands from JS
        $this->bind('__phpFetch', function (string $id, string $args): void {
            $params = json_decode($args, true);
            $req    = $params[0] ?? [];

            $url     = $req['url']     ?? '';
            $method  = strtoupper($req['method'] ?? 'GET');
            $headers = $req['headers'] ?? [];
            $body    = $req['body']    ?? null;

            if (function_exists('curl_init')) {
                [$status, $resHeaders, $resBody] = $this->curlRequest($url, $method, $headers, $body);
            } else {
                [$status, $resHeaders, $resBody] = $this->streamRequest($url, $method, $headers, $body);
            }

            $this->returnValue($id, 0, json_encode([
                'status'  => $status,
                'headers' => $resHeaders,
                'body'    => base64_encode($resBody),
                'ok'      => $status >= 200 && $status < 300,
            ], JSON_UNESCAPED_SLASHES));
        });

        // JS side: override window.fetch for http(s) URLs only
        $this->initJs(<<<'JS'
(function () {
    var _nativeFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        var url = typeof input === 'string' ? input : input.url;
        // Only intercept absolute http(s) — let same-origin assets through
        if (!/^https?:\/\//i.test(url)) {
            return _nativeFetch(input, init);
        }
        init = init || {};
        var hdrs = {};
        if (init.headers) {
            if (typeof init.headers.forEach === 'function') {
                init.headers.forEach(function (v, k) { hdrs[k] = v; });
            } else {
                hdrs = Object.assign({}, init.headers);
            }
        }
        return window.invoke('__phpFetch', {
            url:     url,
            method:  (init.method || 'GET').toUpperCase(),
            headers: hdrs,
            body:    (init.body != null ? String(init.body) : null),
        }).then(function (r) {
            // Decode base64 body back to binary
            var raw   = atob(r.body);
            var bytes = new Uint8Array(raw.length);
            for (var i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
            return new Response(bytes.buffer, {
                status:  r.status,
                headers: new Headers(r.headers),
            });
        });
    };
})();
JS);
    }

    /**
     * Get the port of the local server started by serveDirectory().
     */
    public function getServerPort(): ?int
    {
        return $this->serverPort;
    }

    /* ── Window ──────────────────────────────────────────────────────────── */

    /**
     * Set the window title.
     */
    public function setTitle(string $title): void
    {
        $this->process->sendCommand(['cmd' => 'set_title', 'title' => $title]);
    }

    /**
     * Set the window size.
     *
     * @param int $hint 0=NONE, 1=MIN, 2=MAX, 3=FIXED
     */
    public function setSize(int $width, int $height, int $hint = 0): void
    {
        $this->process->sendCommand([
            'cmd' => 'set_size',
            'width' => $width,
            'height' => $height,
            'hint' => $hint,
        ]);
    }

    /* ── JavaScript ──────────────────────────────────────────────────────── */

    /**
     * Execute JavaScript in the webview.
     */
    public function evalJs(string $js): void
    {
        $this->process->sendCommand(['cmd' => 'eval', 'js' => $js]);
    }

    /**
     * Add JavaScript that runs before every page load.
     */
    public function initJs(string $js): void
    {
        $this->process->sendCommand(['cmd' => 'init', 'js' => $js]);
    }

    /* ── Commands: JS → PHP (Tauri invoke() equivalent) ──────────────────── */

    /**
     * Bind a JS function name to a PHP callback.
     *
     * When JS calls `functionName(args...)` or `invoke('functionName', args...)`,
     * the callback receives (string $requestId, string $argsJson).
     * Call returnValue() to resolve the JS Promise.
     */
    public function bind(string $name, callable $callback): void
    {
        $this->commandHandlers[$name] = $callback;
        $this->process->sendCommand(['cmd' => 'bind', 'name' => $name]);
    }

    /**
     * Remove a previously bound JS function.
     */
    public function unbind(string $name): void
    {
        unset($this->commandHandlers[$name]);
        $this->process->sendCommand(['cmd' => 'unbind', 'name' => $name]);
    }

    /**
     * Return a value to a JS Promise from a bound function call.
     *
     * @param string $id     The request ID from the command event
     * @param int    $status 0 = resolve, non-zero = reject
     * @param string $result JSON-encoded result value
     */
    public function returnValue(string $id, int $status, string $result): void
    {
        $this->process->sendCommand([
            'cmd' => 'return',
            'id' => $id,
            'status' => $status,
            'result' => $result,
        ]);
    }

    /* ── Events: PHP → JS (Tauri emit() equivalent) ──────────────────────── */

    /**
     * Push an event from PHP to JavaScript.
     *
     * JS listens via: onPhpEvent('eventName', function(data) { ... })
     */
    public function emit(string $event, mixed $payload = null): void
    {
        $this->process->sendCommand([
            'cmd' => 'emit',
            'event' => $event,
            'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    /* ── Lifecycle ───────────────────────────────────────────────────────── */

    /**
     * Destroy the webview and terminate the helper process.
     */
    public function destroy(): void
    {
        $this->stopServer();
        $this->process->close();
    }

    public function isClosed(): bool
    {
        return $this->process->isClosed();
    }

    public function isReady(): bool
    {
        return $this->process->isReady();
    }

    public function getId(): string
    {
        return $this->id;
    }

    /* ── Lifecycle callbacks ─────────────────────────────────────────────── */

    /**
     * Register a callback for when the webview window is closed.
     */
    public function onClose(callable $callback): void
    {
        $this->onCloseCallback = $callback;
    }

    /**
     * Register a callback for webview errors.
     */
    public function onError(callable $callback): void
    {
        $this->onErrorCallback = $callback;
    }

    /**
     * Register a callback for when the webview is ready.
     */
    public function onReady(callable $callback): void
    {
        $this->onReadyCallback = $callback;
    }

    /**
     * Register a callback for when serveFromDisk() / serveVite() has loaded.
     *
     * Receives the effective URL the webview navigated to:
     *   - Linux:   "phpgui://app/index.html"
     *   - Windows: "https://phpgui.localhost/index.html"
     *   - macOS:   "file:///path/to/dist/index.html"
     *
     * @param callable(string $url): void $callback
     */
    public function onServeDirReady(callable $callback): void
    {
        $this->onServeDirReadyCallback = $callback;
    }

    /* ── Event processing (called by Application::run() each tick) ───────── */

    /**
     * Poll and dispatch events from the helper process.
     * Must be called regularly (typically by Application::run()).
     */
    public function processEvents(): void
    {
        $events = $this->process->pollEvents();

        foreach ($events as $event) {
            $type = $event['event'] ?? '';

            switch ($type) {
                case 'ready':
                    if ($this->onReadyCallback) {
                        ($this->onReadyCallback)();
                    }
                    break;

                case 'command':
                    $this->handleCommand($event);
                    break;

                case 'closed':
                    if ($this->onCloseCallback) {
                        ($this->onCloseCallback)();
                    }
                    break;

                case 'error':
                    $message = $event['message'] ?? 'Unknown error';
                    if ($this->onErrorCallback) {
                        ($this->onErrorCallback)($message);
                    } else {
                        error_log("[WebView error] {$message}");
                    }
                    break;

                case 'serve_dir_ready':
                    if ($this->onServeDirReadyCallback) {
                        $url = $event['url'] ?? '';
                        ($this->onServeDirReadyCallback)($url);
                    }
                    break;

                case 'pong':
                    // Health check response — could track last pong time
                    break;
            }
        }
    }

    /**
     * Dispatch a JS→PHP command to the registered handler.
     */
    private function handleCommand(array $event): void
    {
        $name = $event['name'] ?? '';
        $id   = $event['id'] ?? '';
        $args = $event['args'] ?? '[]';

        if (!isset($this->commandHandlers[$name])) {
            $this->returnValue($id, 1, json_encode("Unknown command: {$name}"));
            return;
        }

        try {
            ($this->commandHandlers[$name])($id, $args);
        } catch (\Throwable $e) {
            $this->returnValue($id, 1, json_encode($e->getMessage()));
            if ($this->onErrorCallback) {
                ($this->onErrorCallback)("Command '{$name}' threw: " . $e->getMessage());
            }
        }
    }

    public function __destruct()
    {
        $this->stopServer();
        if (!$this->isClosed()) {
            $this->destroy();
        }
    }

    /**
     * @return array{int, array<string,string>, string}  [status, headers, body]
     */
    private function curlRequest(string $url, string $method, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HEADER         => true,
        ]);

        if ($headers) {
            $lines = [];
            foreach ($headers as $k => $v) {
                $lines[] = "{$k}: {$v}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $lines);
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw        = (string)curl_exec($ch);
        $status     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $resHeaders = [];
        foreach (explode("\r\n", substr($raw, 0, $headerSize)) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $resHeaders[trim($k)] = trim($v);
            }
        }

        return [$status, $resHeaders, substr($raw, $headerSize)];
    }

    /**
     * @return array{int, array<string,string>, string}  [status, headers, body]
     */
    private function streamRequest(string $url, string $method, array $headers, ?string $body): array
    {
        $opts = ['method' => $method, 'ignore_errors' => true, 'timeout' => 30];

        if ($headers) {
            $lines = [];
            foreach ($headers as $k => $v) {
                $lines[] = "{$k}: {$v}";
            }
            $opts['header'] = implode("\r\n", $lines);
        }

        if ($body !== null) {
            $opts['content'] = $body;
        }

        $resBody    = (string)@file_get_contents($url, false, stream_context_create(['http' => $opts]));
        $status     = 200;
        $resHeaders = [];

        // $http_response_header is set by file_get_contents in the local scope
        $responseHeaders = $http_response_header ?? [];
        if ($responseHeaders) {
            if (preg_match('/HTTP\/[\d.]+\s+(\d+)/', $responseHeaders[0], $m)) {
                $status = (int)$m[1];
            }
            foreach (array_slice($responseHeaders, 1) as $h) {
                if (str_contains($h, ':')) {
                    [$k, $v] = explode(':', $h, 2);
                    $resHeaders[trim($k)] = trim($v);
                }
            }
        }

        return [$status, $resHeaders, $resBody];
    }

    private function stopServer(): void
    {
        if ($this->serverProcess && is_resource($this->serverProcess)) {
            $status = proc_get_status($this->serverProcess);
            if ($status['running']) {
                // Kill the server process tree
                $pid = $status['pid'];
                if (PHP_OS_FAMILY === 'Windows') {
                    exec("taskkill /F /T /PID {$pid} 2>NUL");
                } else {
                    exec("kill {$pid} 2>/dev/null");
                }
            }
            proc_close($this->serverProcess);
            $this->serverProcess = null;
            $this->serverPort = null;
        }
    }
}
