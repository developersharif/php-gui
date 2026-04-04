/*
 * webview_helper.c — Lightweight helper binary for php-gui WebView widget.
 *
 * Hosts a native webview (via webview/webview library) and communicates with
 * the parent PHP process over JSON-over-stdio IPC.
 *
 * Architecture:
 *   Main thread  — runs webview_create() + webview_run() (blocking event loop)
 *   Reader thread — reads JSON commands from stdin, dispatches to main thread
 *
 * Part of php-gui: https://github.com/developersharif/php-gui
 * License: MIT
 */

#include "webview.h"

extern "C" {
#include "cJSON.h"
}

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#ifdef _WIN32
#include <windows.h>
#include <process.h>
#include <io.h>
#include <fcntl.h>
#else
#include <pthread.h>
#include <unistd.h>
#endif

/* Platform-specific includes for custom URI scheme (serve_dir command) */
#if defined(__linux__)
#include <limits.h>
#include <webkit2/webkit2.h>
#elif defined(__APPLE__)
#include <limits.h>
#import <AppKit/AppKit.h>
#import <Foundation/Foundation.h>
#import <WebKit/WebKit.h>
#endif

/* ── Globals ─────────────────────────────────────────────────────────────── */

static webview_t w = NULL;
static volatile int g_shutdown = 0;

/* Registry for binding name allocations (so unbind can free them) */
#define MAX_BINDINGS 256
static char *binding_names[MAX_BINDINGS];
static int binding_count = 0;

static void binding_registry_add(char *name) {
    if (binding_count < MAX_BINDINGS) {
        binding_names[binding_count++] = name;
    }
}

static void binding_registry_remove(const char *name) {
    for (int i = 0; i < binding_count; i++) {
        if (strcmp(binding_names[i], name) == 0) {
            free(binding_names[i]);
            binding_names[i] = binding_names[--binding_count];
            return;
        }
    }
}

static void binding_registry_free_all(void) {
    for (int i = 0; i < binding_count; i++) {
        free(binding_names[i]);
    }
    binding_count = 0;
}

#ifdef _WIN32
static CRITICAL_SECTION stdout_cs;
#define STDOUT_LOCK()   EnterCriticalSection(&stdout_cs)
#define STDOUT_UNLOCK() LeaveCriticalSection(&stdout_cs)
#else
static pthread_mutex_t stdout_mutex = PTHREAD_MUTEX_INITIALIZER;
#define STDOUT_LOCK()   pthread_mutex_lock(&stdout_mutex)
#define STDOUT_UNLOCK() pthread_mutex_unlock(&stdout_mutex)
#endif

/* ── File serving (serve_dir) ──────────────────────────────────────────── */

static char g_serve_dir[4096] = {0};
static int  g_scheme_registered = 0;  /* guard: register phpgui:// scheme only once */

static const char *get_mime_type(const char *path) {
    const char *dot = strrchr(path, '.');
    if (!dot) return "application/octet-stream";
    if (strcmp(dot, ".html") == 0 || strcmp(dot, ".htm") == 0) return "text/html";
    if (strcmp(dot, ".js") == 0 || strcmp(dot, ".mjs") == 0) return "application/javascript";
    if (strcmp(dot, ".css") == 0) return "text/css";
    if (strcmp(dot, ".json") == 0) return "application/json";
    if (strcmp(dot, ".png") == 0) return "image/png";
    if (strcmp(dot, ".jpg") == 0 || strcmp(dot, ".jpeg") == 0) return "image/jpeg";
    if (strcmp(dot, ".gif") == 0) return "image/gif";
    if (strcmp(dot, ".svg") == 0) return "image/svg+xml";
    if (strcmp(dot, ".ico") == 0) return "image/x-icon";
    if (strcmp(dot, ".webp") == 0) return "image/webp";
    if (strcmp(dot, ".woff") == 0) return "font/woff";
    if (strcmp(dot, ".woff2") == 0) return "font/woff2";
    if (strcmp(dot, ".ttf") == 0) return "font/ttf";
    if (strcmp(dot, ".otf") == 0) return "font/otf";
    if (strcmp(dot, ".wasm") == 0) return "application/wasm";
    if (strcmp(dot, ".mp3") == 0) return "audio/mpeg";
    if (strcmp(dot, ".mp4") == 0) return "video/mp4";
    if (strcmp(dot, ".webm") == 0) return "video/webm";
    if (strcmp(dot, ".ogg") == 0) return "audio/ogg";
    if (strcmp(dot, ".txt") == 0) return "text/plain";
    if (strcmp(dot, ".xml") == 0) return "application/xml";
    if (strcmp(dot, ".pdf") == 0) return "application/pdf";
    return "application/octet-stream";
}

/* ── JSON output helpers ─────────────────────────────────────────────────── */

static void write_json(cJSON *root) {
    char *str = cJSON_PrintUnformatted(root);
    if (!str) return;
    STDOUT_LOCK();
    fprintf(stdout, "%s\n", str);
    fflush(stdout);
    STDOUT_UNLOCK();
    free(str);
}

static void write_event(const char *event) {
    cJSON *root = cJSON_CreateObject();
    cJSON_AddNumberToObject(root, "version", 1);
    cJSON_AddStringToObject(root, "event", event);
    write_json(root);
    cJSON_Delete(root);
}

static void write_error(const char *message) {
    cJSON *root = cJSON_CreateObject();
    cJSON_AddNumberToObject(root, "version", 1);
    cJSON_AddStringToObject(root, "event", "error");
    cJSON_AddStringToObject(root, "message", message);
    write_json(root);
    cJSON_Delete(root);
}

/* ── Bridge JavaScript ───────────────────────────────────────────────────── */

static const char *BRIDGE_JS =
    "window.__phpEmit = function(event, payload) {"
    "  window.dispatchEvent(new CustomEvent('php:' + event, { detail: payload }));"
    "};"
    "window.onPhpEvent = function(event, callback) {"
    "  window.addEventListener('php:' + event, function(e) { callback(e.detail); });"
    "};"
    "window.invoke = function(name) {"
    "  var args = Array.prototype.slice.call(arguments, 1);"
    "  return __phpInvoke(name, JSON.stringify(args));"
    "};";

/* ── Binding callback: JS → PHP command relay ────────────────────────────── */

static void on_invoke(const char *id, const char *req, void *arg) {
    (void)arg;
    cJSON *arr = cJSON_Parse(req);
    if (!arr) {
        write_error("Failed to parse invoke arguments");
        return;
    }

    const char *name = cJSON_GetStringValue(cJSON_GetArrayItem(arr, 0));
    const char *args = cJSON_GetStringValue(cJSON_GetArrayItem(arr, 1));

    cJSON *root = cJSON_CreateObject();
    cJSON_AddNumberToObject(root, "version", 1);
    cJSON_AddStringToObject(root, "event", "command");
    cJSON_AddStringToObject(root, "name", name ? name : "");
    cJSON_AddStringToObject(root, "id", id);
    cJSON_AddStringToObject(root, "args", args ? args : "[]");
    write_json(root);
    cJSON_Delete(root);
    cJSON_Delete(arr);
}

/* ── Per-binding callback (for named bindings via "bind" command) ────────── */

static void on_bound_call(const char *id, const char *req, void *arg) {
    const char *name = (const char *)arg;

    cJSON *root = cJSON_CreateObject();
    cJSON_AddNumberToObject(root, "version", 1);
    cJSON_AddStringToObject(root, "event", "command");
    cJSON_AddStringToObject(root, "name", name);
    cJSON_AddStringToObject(root, "id", id);
    cJSON_AddStringToObject(root, "args", req ? req : "[]");
    write_json(root);
    cJSON_Delete(root);
}

/* ── Platform-specific file serving for serve_dir ───────────────────────── */

#if defined(__linux__)

static void on_phpgui_uri_request(WebKitURISchemeRequest *request,
                                   gpointer user_data) {
    (void)user_data;
    const char *path = webkit_uri_scheme_request_get_path(request);

    /* Default to index.html */
    if (!path || path[0] == '\0' || strcmp(path, "/") == 0) {
        path = "/index.html";
    }

    /* Build absolute file path.
     * g_serve_dir always ends with '/' (ensured by serve_dir handler).
     * WebKit always provides path starting with '/'. Skip its leading slash
     * to avoid double-slash in the concatenated path. */
    char filepath[4096 + 256];
    const char *rel = (path[0] == '/') ? path + 1 : path;
    snprintf(filepath, sizeof(filepath), "%s%s", g_serve_dir, rel);

    /* Resolve symlinks for security check */
    char *resolved = realpath(filepath, NULL);
    if (!resolved) {
        /* SPA fallback: serve index.html for any unknown path (e.g. React Router) */
        char fallback[4096 + 32];
        snprintf(fallback, sizeof(fallback), "%sindex.html", g_serve_dir);
        resolved = realpath(fallback, NULL);
        if (!resolved) {
            GError *err = g_error_new_literal(
                g_io_error_quark(), G_IO_ERROR_NOT_FOUND, "File not found");
            webkit_uri_scheme_request_finish_error(request, err);
            g_error_free(err);
            return;
        }
        /* Use index.html for MIME type detection */
        snprintf(filepath, sizeof(filepath), "%sindex.html", g_serve_dir);
    }

    /* Security: resolved path must stay inside the serve directory.
     * g_serve_dir ends with '/', so a prefix match is sufficient — a sibling
     * directory like /srv/app-evil/ cannot match /srv/app/ at dir_len chars. */
    size_t dir_len = strlen(g_serve_dir);
    if (strncmp(resolved, g_serve_dir, dir_len) != 0) {
        free(resolved);
        GError *err = g_error_new_literal(
            g_io_error_quark(), G_IO_ERROR_PERMISSION_DENIED, "Access denied");
        webkit_uri_scheme_request_finish_error(request, err);
        g_error_free(err);
        return;
    }

    /* Read file via GIO */
    GFile *gfile = g_file_new_for_path(resolved);
    free(resolved);

    GError *err = NULL;
    GFileInputStream *stream = g_file_read(gfile, NULL, &err);
    g_object_unref(gfile);

    if (err) {
        webkit_uri_scheme_request_finish_error(request, err);
        g_error_free(err);
        return;
    }

    const char *mime = get_mime_type(filepath);
    webkit_uri_scheme_request_finish(request, G_INPUT_STREAM(stream), -1, mime);
    g_object_unref(stream);
}

static void setup_phpgui_scheme(webview_t wv) {
    /* Idempotent: WebKitGTK throws a warning if the same scheme is registered
     * twice on the same WebKitWebContext. Guard against repeated calls (e.g.
     * if the user calls serveFromDisk() more than once). */
    if (g_scheme_registered) return;

    void *native = webview_get_native_handle(
        wv, WEBVIEW_NATIVE_HANDLE_KIND_BROWSER_CONTROLLER);
    WebKitWebView *wk = (WebKitWebView *)native;
    WebKitWebContext *ctx = webkit_web_view_get_context(wk);
    webkit_web_context_register_uri_scheme(
        ctx, "phpgui", on_phpgui_uri_request, NULL, NULL);
    g_scheme_registered = 1;
}

#endif /* __linux__ */

#if defined(_WIN32)

/* Returns 1 on success, 0 on failure (emits error event to PHP). */
static int setup_virtual_host(webview_t wv) {
    void *native = webview_get_native_handle(
        wv, WEBVIEW_NATIVE_HANDLE_KIND_BROWSER_CONTROLLER);
    ICoreWebView2Controller *controller =
        static_cast<ICoreWebView2Controller *>(native);

    ICoreWebView2 *core = nullptr;
    if (FAILED(controller->get_CoreWebView2(&core)) || !core) {
        write_error("serve_dir: failed to obtain ICoreWebView2 interface");
        return 0;
    }

    ICoreWebView2_3 *core3 = nullptr;
    if (FAILED(core->QueryInterface(__uuidof(ICoreWebView2_3),
                                     reinterpret_cast<void **>(&core3))) ||
        !core3) {
        core->Release();
        /* ICoreWebView2_3 was added in WebView2 SDK 0.9.538 / Runtime 86.0.
         * Consumer PCs with a very old Edge install may hit this path. */
        write_error("serve_dir: ICoreWebView2_3 unavailable — "
                    "update the WebView2 Runtime at microsoft.com/edge");
        return 0;
    }

    /* Convert UTF-8 path to wide string */
    wchar_t wpath[MAX_PATH];
    MultiByteToWideChar(CP_UTF8, 0, g_serve_dir, -1, wpath, MAX_PATH);

    HRESULT hr = core3->SetVirtualHostNameToFolderMapping(
        L"phpgui.localhost", wpath,
        COREWEBVIEW2_HOST_RESOURCE_ACCESS_KIND_ALLOW);

    core3->Release();
    core->Release();

    if (FAILED(hr)) {
        write_error("serve_dir: SetVirtualHostNameToFolderMapping failed");
        return 0;
    }
    return 1;
}

#endif /* _WIN32 */

#if defined(__APPLE__)

/*
 * Load a file:// URL using the WKWebView-native API that grants the webview
 * read access to the entire serve directory, not just the single file.
 *
 * webview_navigate() with a file:// URL only grants access to the specific
 * file being loaded — sibling assets (JS, CSS, images) fail to load.
 * loadFileURL:allowingReadAccessToURL: grants directory-wide read access,
 * which is required for any SPA with multiple asset files.
 */
static void macos_load_file_url(webview_t wv, const char *dir_path) {
    void *native = webview_get_native_handle(
        wv, WEBVIEW_NATIVE_HANDLE_KIND_UI_WIDGET);
    WKWebView *wkview = (__bridge WKWebView *)native;

    NSString *dir   = [NSString stringWithUTF8String:dir_path];
    NSString *index = [dir stringByAppendingPathComponent:@"index.html"];

    NSURL *fileURL = [NSURL fileURLWithPath:index isDirectory:NO];
    NSURL *dirURL  = [NSURL fileURLWithPath:dir  isDirectory:YES];

    [wkview loadFileURL:fileURL allowingReadAccessToURL:dirURL];
}

/* ── JavaScript dialog delegate ───────────────────────────────────────────
 *
 * WKWebView silently drops all JavaScript dialogs (alert, confirm, prompt)
 * unless a WKUIDelegate is installed. This delegate shows native NSAlert
 * panels so the behaviour matches WebKitGTK on Linux.
 *
 * All three delegate methods are called on the main thread by WebKit, so
 * calling [NSAlert runModal] (which blocks the main thread) is safe and
 * gives the correct blocking behaviour that JS dialogs require.
 */
@interface PhpGuiUIDelegate : NSObject <WKUIDelegate>
@end

@implementation PhpGuiUIDelegate

/* alert() */
- (void)webView:(WKWebView *)webView
    runJavaScriptAlertPanelWithMessage:(NSString *)message
    initiatedByFrame:(WKFrameInfo *)frame
    completionHandler:(void (^)(void))completionHandler
{
    NSAlert *alert = [[NSAlert alloc] init];
    alert.messageText     = message;
    alert.alertStyle      = NSAlertStyleInformational;
    [alert addButtonWithTitle:@"OK"];
    [alert runModal];
    completionHandler();
}

/* confirm() — returns true if user clicks OK */
- (void)webView:(WKWebView *)webView
    runJavaScriptConfirmPanelWithMessage:(NSString *)message
    initiatedByFrame:(WKFrameInfo *)frame
    completionHandler:(void (^)(BOOL result))completionHandler
{
    NSAlert *alert = [[NSAlert alloc] init];
    alert.messageText = message;
    [alert addButtonWithTitle:@"OK"];
    [alert addButtonWithTitle:@"Cancel"];
    completionHandler([alert runModal] == NSAlertFirstButtonReturn);
}

/* prompt() — returns the input string, or nil if user cancels */
- (void)webView:(WKWebView *)webView
    runJavaScriptTextInputPanelWithPrompt:(NSString *)prompt
    defaultText:(nullable NSString *)defaultText
    initiatedByFrame:(WKFrameInfo *)frame
    completionHandler:(void (^)(NSString * _Nullable result))completionHandler
{
    NSAlert *alert = [[NSAlert alloc] init];
    alert.messageText = prompt;

    NSTextField *input = [[NSTextField alloc]
                          initWithFrame:NSMakeRect(0, 0, 220, 24)];
    input.stringValue  = defaultText ?: @"";
    alert.accessoryView = input;

    [alert addButtonWithTitle:@"OK"];
    [alert addButtonWithTitle:@"Cancel"];
    [alert layout];
    [alert.window makeFirstResponder:input];

    NSModalResponse response = [alert runModal];
    completionHandler(response == NSAlertFirstButtonReturn
                      ? input.stringValue
                      : nil);
}

@end

/* Strong reference keeps the delegate alive for the webview's lifetime
 * (UIDelegate on WKWebView is a weak property). */
static PhpGuiUIDelegate *g_ui_delegate = nil;

static void setup_ui_delegate(webview_t wv) {
    void *native = webview_get_native_handle(
        wv, WEBVIEW_NATIVE_HANDLE_KIND_UI_WIDGET);
    WKWebView *wkview = (__bridge WKWebView *)native;
    if (!g_ui_delegate) {
        g_ui_delegate = [[PhpGuiUIDelegate alloc] init];
    }
    wkview.UIDelegate = g_ui_delegate;
}

#endif /* __APPLE__ */

/* ── Command dispatch (runs on main thread via webview_dispatch) ─────────── */

typedef struct {
    char *json_str;
} dispatch_ctx;

static void dispatch_command(webview_t wv, void *arg) {
    dispatch_ctx *ctx = (dispatch_ctx *)arg;
    cJSON *root = cJSON_Parse(ctx->json_str);
    if (!root) {
        write_error("Failed to parse command JSON");
        free(ctx->json_str);
        free(ctx);
        return;
    }

    const char *cmd = cJSON_GetStringValue(cJSON_GetObjectItem(root, "cmd"));
    if (!cmd) {
        write_error("Missing 'cmd' field");
        cJSON_Delete(root);
        free(ctx->json_str);
        free(ctx);
        return;
    }

    if (strcmp(cmd, "navigate") == 0) {
        const char *url = cJSON_GetStringValue(cJSON_GetObjectItem(root, "url"));
        if (url) webview_navigate(wv, url);

    } else if (strcmp(cmd, "set_html") == 0) {
        const char *html = cJSON_GetStringValue(cJSON_GetObjectItem(root, "html"));
        if (html) webview_set_html(wv, html);

    } else if (strcmp(cmd, "set_title") == 0) {
        const char *title = cJSON_GetStringValue(cJSON_GetObjectItem(root, "title"));
        if (title) webview_set_title(wv, title);

    } else if (strcmp(cmd, "set_size") == 0) {
        cJSON *jw = cJSON_GetObjectItem(root, "width");
        cJSON *jh = cJSON_GetObjectItem(root, "height");
        cJSON *jt = cJSON_GetObjectItem(root, "hint");
        int width  = jw ? jw->valueint : 800;
        int height = jh ? jh->valueint : 600;
        int hint   = jt ? jt->valueint : 0;
        webview_set_size(wv, width, height, static_cast<webview_hint_t>(hint));

    } else if (strcmp(cmd, "eval") == 0) {
        const char *js = cJSON_GetStringValue(cJSON_GetObjectItem(root, "js"));
        if (js) webview_eval(wv, js);

    } else if (strcmp(cmd, "init") == 0) {
        const char *js = cJSON_GetStringValue(cJSON_GetObjectItem(root, "js"));
        if (js) webview_init(wv, js);

    } else if (strcmp(cmd, "bind") == 0) {
        const char *name = cJSON_GetStringValue(cJSON_GetObjectItem(root, "name"));
        if (name) {
            char *name_copy = strdup(name);
            binding_registry_add(name_copy);
            webview_bind(wv, name, on_bound_call, name_copy);
        }

    } else if (strcmp(cmd, "unbind") == 0) {
        const char *name = cJSON_GetStringValue(cJSON_GetObjectItem(root, "name"));
        if (name) {
            webview_unbind(wv, name);
            binding_registry_remove(name);
        }

    } else if (strcmp(cmd, "return") == 0) {
        const char *id     = cJSON_GetStringValue(cJSON_GetObjectItem(root, "id"));
        cJSON *jstatus     = cJSON_GetObjectItem(root, "status");
        const char *result = cJSON_GetStringValue(cJSON_GetObjectItem(root, "result"));
        int status = jstatus ? jstatus->valueint : 0;
        if (id && result) {
            webview_return(wv, id, status, result);
        }

    } else if (strcmp(cmd, "emit") == 0) {
        const char *event   = cJSON_GetStringValue(cJSON_GetObjectItem(root, "event"));
        const char *payload = cJSON_GetStringValue(cJSON_GetObjectItem(root, "payload"));
        if (event) {
            /* JSON-encode the event name to prevent JS injection via quotes/backslashes */
            cJSON *event_json = cJSON_CreateString(event);
            char *event_safe = cJSON_PrintUnformatted(event_json);
            /* event_safe is already a quoted JSON string, e.g. "\"my-event\"" */
            char *js = NULL;
            if (payload) {
                size_t len = strlen(event_safe) + strlen(payload) + 64;
                js = (char *)malloc(len);
                snprintf(js, len, "window.__phpEmit(%s, %s);", event_safe, payload);
            } else {
                size_t len = strlen(event_safe) + 64;
                js = (char *)malloc(len);
                snprintf(js, len, "window.__phpEmit(%s, null);", event_safe);
            }
            webview_eval(wv, js);
            free(js);
            free(event_safe);
            cJSON_Delete(event_json);
        }

    } else if (strcmp(cmd, "serve_dir") == 0) {
        const char *path = cJSON_GetStringValue(cJSON_GetObjectItem(root, "path"));
        if (path) {
            /* Resolve and store the directory path */
#ifdef _WIN32
            char resolved[MAX_PATH];
            if (_fullpath(resolved, path, MAX_PATH)) {
                strncpy(g_serve_dir, resolved, sizeof(g_serve_dir) - 1);
            } else {
                strncpy(g_serve_dir, path, sizeof(g_serve_dir) - 1);
            }
#else
            char *resolved = realpath(path, NULL);
            if (resolved) {
                strncpy(g_serve_dir, resolved, sizeof(g_serve_dir) - 1);
                free(resolved);
            } else {
                strncpy(g_serve_dir, path, sizeof(g_serve_dir) - 1);
            }
#endif
            g_serve_dir[sizeof(g_serve_dir) - 1] = '\0';

            /* Normalize: ensure g_serve_dir always ends with '/'.
             * This makes the path-traversal check in on_phpgui_uri_request
             * a simple prefix comparison — a sibling dir like /srv/app-evil/
             * cannot share the prefix /srv/app/ at dir_len chars. */
            size_t gdlen = strlen(g_serve_dir);
            if (gdlen > 0 && g_serve_dir[gdlen - 1] != '/' &&
                gdlen < sizeof(g_serve_dir) - 1) {
                g_serve_dir[gdlen]     = '/';
                g_serve_dir[gdlen + 1] = '\0';
            }

            /* Platform-specific setup + navigate.
             * Each branch is responsible for its own navigation call so that
             * macOS can use loadFileURL:allowingReadAccessToURL: instead of
             * webview_navigate(), and Windows can skip navigation on failure. */
            const char *url = NULL;
            char url_buf[4096 + 64];

#if defined(__linux__)
            setup_phpgui_scheme(wv);
            url = "phpgui://app/index.html";
            webview_navigate(wv, url);

#elif defined(_WIN32)
            if (setup_virtual_host(wv)) {
                url = "https://phpgui.localhost/index.html";
                webview_navigate(wv, url);
            }
            /* On failure, setup_virtual_host() already emitted an error event.
             * url stays NULL so serve_dir_ready is not emitted. */

#elif defined(__APPLE__)
            /* loadFileURL:allowingReadAccessToURL: grants read access to the
             * entire directory, enabling JS/CSS/image assets to load correctly.
             * webview_navigate("file://...") only grants access to the single
             * file, causing asset 404s in any multi-file SPA. */
            macos_load_file_url(wv, g_serve_dir);
            snprintf(url_buf, sizeof(url_buf),
                     "file://%sindex.html", g_serve_dir);
            url = url_buf;
#endif

            if (url) {
                /* Notify PHP of the effective URL (informational) */
                cJSON *evt = cJSON_CreateObject();
                cJSON_AddNumberToObject(evt, "version", 1);
                cJSON_AddStringToObject(evt, "event", "serve_dir_ready");
                cJSON_AddStringToObject(evt, "url", url);
                write_json(evt);
                cJSON_Delete(evt);
            }
        }

    } else if (strcmp(cmd, "ping") == 0) {
        write_event("pong");

    } else if (strcmp(cmd, "destroy") == 0) {
        g_shutdown = 1;
        webview_terminate(wv);

    } else {
        char msg[256];
        snprintf(msg, sizeof(msg), "Unknown command: %s", cmd);
        write_error(msg);
    }

    cJSON_Delete(root);
    free(ctx->json_str);
    free(ctx);
}

/* ── Reader thread: reads JSON commands from stdin ───────────────────────── */

#define LINE_BUF_SIZE 1048576  /* 1 MiB — supports large HTML payloads via set_html */

#ifdef _WIN32
static unsigned __stdcall reader_thread_func(void *arg) {
#else
static void *reader_thread_func(void *arg) {
#endif
    (void)arg;
    char *line = (char *)malloc(LINE_BUF_SIZE);
    if (!line) {
#ifdef _WIN32
        return 0;
#else
        return NULL;
#endif
    }

    while (!g_shutdown && fgets(line, LINE_BUF_SIZE, stdin) != NULL) {
        /* Strip trailing newline */
        size_t len = strlen(line);
        while (len > 0 && (line[len - 1] == '\n' || line[len - 1] == '\r')) {
            line[--len] = '\0';
        }
        if (len == 0) continue;

        /* Validate it's JSON before dispatching */
        cJSON *test = cJSON_Parse(line);
        if (!test) {
            write_error("Invalid JSON input");
            continue;
        }
        cJSON_Delete(test);

        /* Create dispatch context with a copy of the line */
        dispatch_ctx *ctx = (dispatch_ctx *)malloc(sizeof(dispatch_ctx));
        ctx->json_str = strdup(line);

        if (!g_shutdown) {
            webview_dispatch(w, dispatch_command, ctx);
        } else {
            free(ctx->json_str);
            free(ctx);
        }
    }

    free(line);

    /* stdin EOF or error — terminate the webview */
    if (!g_shutdown) {
        g_shutdown = 1;
        if (w) webview_terminate(w);
    }

#ifdef _WIN32
    return 0;
#else
    return NULL;
#endif
}

/* ── Argument parsing ────────────────────────────────────────────────────── */

typedef struct {
    const char *title;
    int width;
    int height;
    int debug;
    const char *url;
} app_config;

static app_config parse_args(int argc, char *argv[]) {
    app_config cfg;
    cfg.title  = "WebView";
    cfg.width  = 800;
    cfg.height = 600;
    cfg.debug  = 0;
    cfg.url    = NULL;

    for (int i = 1; i < argc; i++) {
        if (strcmp(argv[i], "--title") == 0 && i + 1 < argc) {
            cfg.title = argv[++i];
        } else if (strcmp(argv[i], "--width") == 0 && i + 1 < argc) {
            cfg.width = atoi(argv[++i]);
        } else if (strcmp(argv[i], "--height") == 0 && i + 1 < argc) {
            cfg.height = atoi(argv[++i]);
        } else if (strcmp(argv[i], "--debug") == 0) {
            cfg.debug = 1;
        } else if (strcmp(argv[i], "--url") == 0 && i + 1 < argc) {
            cfg.url = argv[++i];
        }
    }

    return cfg;
}

/* ── Main ────────────────────────────────────────────────────────────────── */

int main(int argc, char *argv[]) {
    app_config cfg = parse_args(argc, argv);

#ifdef _WIN32
    InitializeCriticalSection(&stdout_cs);
    /* Set stdout to binary mode to avoid \r\n translation */
    _setmode(_fileno(stdout), _O_BINARY);
    _setmode(_fileno(stdin), _O_BINARY);
#endif

    /* Create webview */
    w = webview_create(cfg.debug, NULL);
    if (!w) {
        fprintf(stderr, "Failed to create webview\n");
        return 1;
    }

#if defined(__APPLE__)
    /* Install UI delegate so alert(), confirm(), and prompt() show native
     * NSAlert panels. WKWebView silences JS dialogs without a delegate. */
    setup_ui_delegate(w);
#endif

    webview_set_title(w, cfg.title);
    webview_set_size(w, cfg.width, cfg.height, WEBVIEW_HINT_NONE);

    /* Inject bridge JS */
    webview_init(w, BRIDGE_JS);

    /* Bind the catch-all invoke function */
    webview_bind(w, "__phpInvoke", on_invoke, NULL);

    /* Navigate to initial URL if provided */
    if (cfg.url) {
        webview_navigate(w, cfg.url);
    }

    /* Signal ready */
    write_event("ready");

    /* Start reader thread */
#ifdef _WIN32
    HANDLE thread = (HANDLE)_beginthreadex(NULL, 0, reader_thread_func, NULL, 0, NULL);
#else
    pthread_t thread;
    pthread_create(&thread, NULL, reader_thread_func, NULL);
#endif

    /* Run the webview event loop (blocks until window closes or terminate) */
    webview_run(w);

    /* Cleanup */
    g_shutdown = 1;

    /* Write closed event before destroying (stdout still valid) */
    write_event("closed");
    fflush(stdout);

    webview_destroy(w);
    w = NULL;
    binding_registry_free_all();

    /*
     * The reader thread may be blocked on fgets(stdin). There is no
     * portable way to unblock it without UB. Since all useful work is
     * done, just exit the process directly.
     */
#ifdef _WIN32
    DeleteCriticalSection(&stdout_cs);
#endif
    _exit(0);
}
