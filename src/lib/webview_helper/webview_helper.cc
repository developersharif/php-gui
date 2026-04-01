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
#else
#include <pthread.h>
#include <unistd.h>
#endif

/* ── Globals ─────────────────────────────────────────────────────────────── */

static webview_t w = NULL;
static volatile int g_shutdown = 0;

#ifdef _WIN32
static CRITICAL_SECTION stdout_cs;
#define STDOUT_LOCK()   EnterCriticalSection(&stdout_cs)
#define STDOUT_UNLOCK() LeaveCriticalSection(&stdout_cs)
#else
static pthread_mutex_t stdout_mutex = PTHREAD_MUTEX_INITIALIZER;
#define STDOUT_LOCK()   pthread_mutex_lock(&stdout_mutex)
#define STDOUT_UNLOCK() pthread_mutex_unlock(&stdout_mutex)
#endif

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
            /* Duplicate the name string — it must outlive this function */
            char *name_copy = strdup(name);
            webview_bind(wv, name, on_bound_call, name_copy);
        }

    } else if (strcmp(cmd, "unbind") == 0) {
        const char *name = cJSON_GetStringValue(cJSON_GetObjectItem(root, "name"));
        if (name) webview_unbind(wv, name);

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
            /* Build JS: window.__phpEmit('eventName', <payload>) */
            char *js = NULL;
            if (payload) {
                size_t len = strlen(event) + strlen(payload) + 64;
                js = (char *)malloc(len);
                snprintf(js, len, "window.__phpEmit('%s', %s);", event, payload);
            } else {
                size_t len = strlen(event) + 64;
                js = (char *)malloc(len);
                snprintf(js, len, "window.__phpEmit('%s', null);", event);
            }
            webview_eval(wv, js);
            free(js);
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

#define LINE_BUF_SIZE 65536

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
    app_config cfg = {
        .title  = "WebView",
        .width  = 800,
        .height = 600,
        .debug  = 0,
        .url    = NULL,
    };

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
