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

    /** @var array<string, callable> JS→PHP command handlers: name => callback(string $id, string $args) */
    private array $commandHandlers = [];

    /** @var callable|null */
    private $onCloseCallback = null;

    /** @var callable|null */
    private $onErrorCallback = null;

    /** @var callable|null */
    private $onReadyCallback = null;

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
        if (!$this->isClosed()) {
            $this->destroy();
        }
    }
}
