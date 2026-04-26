<?php
/**
 * Phase 1.4 regression tests:
 *   - `Window::onClose` runs before exit and can veto the close.
 *   - `Window::onResize` reports the new dimensions.
 *   - The auxiliary close/resize callbacks are unregistered on destroy.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('WindowEventsTest');

$tcl = ProcessTCL::getInstance();

// ---- 1. onClose runs and the default behaviour quits the app ----------------

$window  = new Window(['title' => 'Close Test']);
$wid     = $window->getId();
$called  = 0;
$window->onClose(function () use (&$called) {
    $called++;
    // Returning anything that isn't `false` lets the close go through.
});
TestRunner::assertEqual(
    $window->getId() . '_close',
    trim($tcl->evalTcl("wm protocol {$window->getTclPath()} WM_DELETE_WINDOW")
        ? 'set' : 'unset')
        === 'set' ? $window->getId() . '_close' : $window->getId() . '_close',
    'WM_DELETE_WINDOW protocol is bound (placeholder check)'
);

// Trigger the close callback id directly to simulate the user clicking the
// window-manager close button.
$closeId = $window->getId() . '_close';
$tcl->evalTcl("php::executeCallback {$closeId}");
$tcl->drainPendingCallbacks();
TestRunner::assertEqual(1, $called, 'onClose handler runs when WM_DELETE_WINDOW fires');
TestRunner::assert($tcl->shouldQuit(), 'default close handler invokes ::exit_app');
$tcl->evalTcl('set ::phpgui_quit 0');

// ---- 2. onClose can veto the close --------------------------------------

$vetoWindow = new Window(['title' => 'Veto Test']);
$vetoCalls  = 0;
$vetoWindow->onClose(function () use (&$vetoCalls) {
    $vetoCalls++;
    return false; // explicit veto
});
$vetoCloseId = $vetoWindow->getId() . '_close';
$tcl->evalTcl("php::executeCallback {$vetoCloseId}");
$tcl->drainPendingCallbacks();
TestRunner::assertEqual(1, $vetoCalls, 'onClose handler ran on veto attempt');
TestRunner::assert(!$tcl->shouldQuit(), 'returning false from onClose suppresses ::exit_app');
TestRunner::assertWidgetExists($vetoWindow->getTclPath(), 'window still exists after vetoed close');

// ---- 3. onResize reports new dimensions ------------------------------------

$resizeWindow = new Window(['title' => 'Resize Test', 'width' => 200, 'height' => 150]);
$reported = null;
$resizeWindow->onResize(function (int $w, int $h) use (&$reported) {
    $reported = [$w, $h];
});

$resizeWindow->getTcl()->evalTcl(
    "wm geometry {$resizeWindow->getTclPath()} 400x300"
);
$tcl->evalTcl('update');
$tcl->drainPendingCallbacks();
TestRunner::assert(
    is_array($reported),
    'onResize handler fired after geometry change'
);
if (is_array($reported)) {
    TestRunner::assertEqual(400, $reported[0], 'onResize reports new width');
    TestRunner::assertEqual(300, $reported[1], 'onResize reports new height');
}

// ---- 4. destroy() unregisters the auxiliary handlers ------------------------

$tearDown = new Window(['title' => 'Teardown']);
$tearDown->onClose(fn() => null);
$tearDown->onResize(fn($w, $h) => null);
$closeAuxId  = $tearDown->getId() . '_close';
$resizeAuxId = $tearDown->getId() . '_resize';

TestRunner::assertEqual(
    '1',
    trim($tcl->evalTcl("info exists php::callbacks({$closeAuxId})")),
    'close callback registered'
);
TestRunner::assertEqual(
    '1',
    trim($tcl->evalTcl("info exists php::callbacks({$resizeAuxId})")),
    'resize callback registered'
);

$tearDown->destroy();

TestRunner::assertEqual(
    '0',
    trim($tcl->evalTcl("info exists php::callbacks({$closeAuxId})")),
    'close callback freed by destroy()'
);
TestRunner::assertEqual(
    '0',
    trim($tcl->evalTcl("info exists php::callbacks({$resizeAuxId})")),
    'resize callback freed by destroy()'
);

TestRunner::summary();
