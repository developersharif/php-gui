<?php
/**
 * Phase 2.5 — Scale widget regression tests.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGui\Widget\Scale;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('ScaleTest');

$tcl    = ProcessTCL::getInstance();
$window = new Window(['title' => 'Scale Test']);
$wid    = $window->getId();

// ---- Creation + defaults ----------------------------------------------------

$s = new Scale($wid, ['from' => 0, 'to' => 100, 'orient' => 'horizontal']);
$path = ".{$wid}.{$s->getId()}";
TestRunner::assertWidgetExists($path, 'Scale exists in Tcl');
TestRunner::assertEqual(0.0, $s->getFrom(), 'getFrom returns the from option');
TestRunner::assertEqual(100.0, $s->getTo(), 'getTo returns the to option');
TestRunner::assertEqual(0.0, $s->getValue(), 'initial value matches from');

// ---- setValue / getValue round-trip ---------------------------------------

$s->setValue(42.5);
TestRunner::assertEqual(42.5, $s->getValue(), 'setValue/getValue round-trip with float');

$s->setValue(75);
TestRunner::assertEqual(75.0, $s->getValue(), 'setValue accepts int and returns float');

// ---- onChange fires on setValue() and on Tk -command ----------------------

$received = [];
$s->onChange(function (float $v) use (&$received) {
    $received[] = $v;
});

$s->setValue(33);
TestRunner::assertEqual([33.0], $received, 'onChange fires on programmatic setValue');

// Simulate the Tk-side -command callback (user dragging the thumb)
$tcl->setVar('phpgui_scale_' . $s->getId(), '90');
$tcl->evalTcl("php::executeCallback {$s->getId()}_change");
$tcl->drainPendingCallbacks();
TestRunner::assertEqual(
    [33.0, 90.0],
    $received,
    'onChange fires when Tk -command callback runs'
);

// ---- Vertical orient is accepted -------------------------------------------

$vs = new Scale($wid, ['from' => 0, 'to' => 10, 'orient' => 'vertical']);
TestRunner::assertEqual(
    'vertical',
    trim($tcl->evalTcl("{$vs->getTclPath()} cget -orient")),
    'vertical orient accepted'
);

// ---- Invalid orient throws -------------------------------------------------

TestRunner::assertThrows(
    fn() => new Scale($wid, ['orient' => 'sideways']),
    \InvalidArgumentException::class,
    'unknown orient is rejected'
);

// ---- Negative ranges work ---------------------------------------------------

$signed = new Scale($wid, ['from' => -50, 'to' => 50]);
$signed->setValue(-25);
TestRunner::assertEqual(-25.0, $signed->getValue(), 'negative values round-trip');

// ---- destroy() unregisters callback ---------------------------------------

$cbId = $s->getId() . '_change';
TestRunner::assertEqual(
    '1',
    trim($tcl->evalTcl("info exists php::callbacks({$cbId})")),
    'change callback registered while alive'
);
$s->destroy();
TestRunner::assertWidgetGone($path, 'Scale gone after destroy()');
TestRunner::assertEqual(
    '0',
    trim($tcl->evalTcl("info exists php::callbacks({$cbId})")),
    'change callback freed by destroy()'
);

TestRunner::summary();
