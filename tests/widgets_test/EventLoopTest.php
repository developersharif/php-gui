<?php
/**
 * Phase 1.2 + 1.3 regression tests:
 *   - The new in-process callback queue dispatches every event in order
 *     (the old temp-file bridge dropped all but the latest).
 *   - The new quit signal goes through `::phpgui_quit`, no temp file.
 *   - `destroy()` deregisters the widget's PHP closure so long-running
 *     apps don't leak the captured state forever.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGui\Widget\Button;
use PhpGui\Widget\Menu;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('EventLoopTest');

$tcl    = ProcessTCL::getInstance();
$window = new Window(['title' => 'Event-loop Test']);
$wid    = $window->getId();

// ---- 1. Pending list starts empty -------------------------------------------

$pending = trim($tcl->getVar('::phpgui_pending'));
TestRunner::assertEqual('', $pending, '::phpgui_pending starts empty');

// ---- 2. Multiple callbacks queued by one tick all dispatch ------------------

$fired = [];
$a = new Button($wid, [
    'text'    => 'A',
    'command' => function () use (&$fired) { $fired[] = 'a'; },
]);
$b = new Button($wid, [
    'text'    => 'B',
    'command' => function () use (&$fired) { $fired[] = 'b'; },
]);
$c = new Button($wid, [
    'text'    => 'C',
    'command' => function () use (&$fired) { $fired[] = 'c'; },
]);

// Simulate three near-simultaneous Tcl events. The old bridge would have
// kept only the last id (the file got overwritten); the new queue keeps all.
$tcl->evalTcl("php::executeCallback {$a->getId()}");
$tcl->evalTcl("php::executeCallback {$b->getId()}");
$tcl->evalTcl("php::executeCallback {$c->getId()}");

$queueText = trim($tcl->getVar('::phpgui_pending'));
TestRunner::assertEqual(
    "{$a->getId()} {$b->getId()} {$c->getId()}",
    $queueText,
    'three rapid callbacks all queue, none lost'
);

$dispatched = $tcl->drainPendingCallbacks();
TestRunner::assertEqual(3, $dispatched, 'drainPendingCallbacks returns count of fired callbacks');
TestRunner::assertEqual(['a', 'b', 'c'], $fired, 'callbacks dispatch in FIFO order');
TestRunner::assertEqual(
    '',
    trim($tcl->getVar('::phpgui_pending')),
    '::phpgui_pending is cleared after drain'
);

// ---- 3. Drain on an empty queue is a no-op ----------------------------------

TestRunner::assertEqual(0, $tcl->drainPendingCallbacks(), 'empty drain returns 0');

// ---- 4. Application::tick() drains the queue --------------------------------

$tickFired = false;
$d = new Button($wid, [
    'text'    => 'D',
    'command' => function () use (&$tickFired) { $tickFired = true; },
]);
$tcl->evalTcl("php::executeCallback {$d->getId()}");
TestRunner::assertEqual(
    $d->getId(),
    trim($tcl->getVar('::phpgui_pending')),
    'callback queued before tick()'
);
$app->tick();
TestRunner::assert($tickFired, 'Application::tick() dispatches queued callbacks');

// ---- 5. Quit signal flows through the Tcl variable, no temp file -----------

TestRunner::assertEqual('0', trim($tcl->getVar('::phpgui_quit')), '::phpgui_quit starts at 0');
$tcl->evalTcl('::exit_app');
TestRunner::assertEqual('1', trim($tcl->getVar('::phpgui_quit')), '::exit_app sets ::phpgui_quit = 1');
TestRunner::assert($tcl->shouldQuit(), 'shouldQuit() reports true after exit_app');
// Reset so the rest of the suite can keep running.
$tcl->evalTcl('set ::phpgui_quit 0');

// ---- 6. destroy() unregisters the widget callback ---------------------------

$leakBtn  = new Button($wid, ['text' => 'leak', 'command' => fn() => null]);
$leakId   = $leakBtn->getId();
TestRunner::assertEqual(
    '1',
    trim($tcl->evalTcl("info exists php::callbacks({$leakId})")),
    'Tcl-side callback registry has entry while widget is alive'
);
$leakBtn->destroy();
TestRunner::assertEqual(
    '0',
    trim($tcl->evalTcl("info exists php::callbacks({$leakId})")),
    'callback gone from registry after widget destroy()'
);

// Calling the now-deregistered id should be a safe no-op (closure removed
// from PHP's internal map, so executeCallback skips it).
$tcl->evalTcl("lappend ::phpgui_pending {$leakId}");
$tcl->drainPendingCallbacks(); // must not throw
TestRunner::assertEqual(
    '',
    trim($tcl->getVar('::phpgui_pending')),
    'firing a freed callback id is a safe no-op'
);

// ---- 7. Menu::destroy() also frees its per-command callbacks ----------------

$menu = new Menu($wid, ['type' => 'main']);
$file = $menu->addSubmenu('File');
$file->addCommand('New',  fn() => null);
$file->addCommand('Open', fn() => null);

$cmdId0 = $file->getId() . '_cmd_0';
$cmdId1 = $file->getId() . '_cmd_1';
TestRunner::assertEqual(
    '1',
    trim($tcl->evalTcl("info exists php::callbacks({$cmdId0})")),
    'menu command callback registered while menu alive'
);
$menu->destroy();
TestRunner::assertEqual(
    '0',
    trim($tcl->evalTcl("info exists php::callbacks({$cmdId0})")),
    'menu command callback freed by Menu::destroy()'
);
TestRunner::assertEqual(
    '0',
    trim($tcl->evalTcl("info exists php::callbacks({$cmdId1})")),
    'second menu command callback freed by Menu::destroy()'
);

// ---- 8. No /tmp file leakage from the new bridge ----------------------------

$tmpCallback = sys_get_temp_dir() . '/phpgui_callback.txt';
$tmpQuit     = sys_get_temp_dir() . '/phpgui_quit.txt';
TestRunner::assert(
    !is_file($tmpCallback),
    'no /tmp/phpgui_callback.txt produced by the new in-process queue'
);
TestRunner::assert(
    !is_file($tmpQuit),
    'no /tmp/phpgui_quit.txt produced by the new exit_app'
);

TestRunner::summary();
