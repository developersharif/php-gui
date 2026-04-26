<?php
/**
 * Phase 2.7 — Progressbar widget regression tests.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGui\Widget\Progressbar;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('ProgressbarTest');

$tcl    = ProcessTCL::getInstance();
$window = new Window(['title' => 'Progress Test']);
$wid    = $window->getId();

// ---- Determinate mode (default) --------------------------------------------

$bar = new Progressbar($wid, ['maximum' => 100]);
$path = ".{$wid}.{$bar->getId()}";
TestRunner::assertWidgetExists($path, 'Progressbar exists in Tcl');
TestRunner::assertEqual('determinate', $bar->getMode(), 'default mode is determinate');
TestRunner::assertEqual(0.0, $bar->getValue(), 'default value is 0');
TestRunner::assertEqual(100.0, $bar->getMaximum(), 'getMaximum reports the option');

$bar->setValue(40);
TestRunner::assertEqual(40.0, $bar->getValue(), 'setValue updates the bar');

$bar->setValue(40.5);
TestRunner::assertEqual(40.5, $bar->getValue(), 'setValue accepts floats');

// ---- step() increments ------------------------------------------------------

$bar->setValue(10);
$bar->step(5);
TestRunner::assertEqual(15.0, $bar->getValue(), 'step(5) adds 5 to current value');
$bar->step();
TestRunner::assertEqual(16.0, $bar->getValue(), 'step() defaults to amount=1');

// ---- setMaximum -------------------------------------------------------------

$bar->setMaximum(200);
TestRunner::assertEqual(200.0, $bar->getMaximum(), 'setMaximum updates the maximum');

// ---- Switch to indeterminate mode ------------------------------------------

$bar->setMode('indeterminate');
TestRunner::assertEqual('indeterminate', $bar->getMode(), 'setMode switches to indeterminate');

// ---- start / stop don't throw ----------------------------------------------

TestRunner::assertNoThrow(fn() => $bar->start(20), 'start(20) does not throw');
TestRunner::assertNoThrow(fn() => $bar->stop(), 'stop() does not throw');

// ---- Invalid mode throws ----------------------------------------------------

TestRunner::assertThrows(
    fn() => new Progressbar($wid, ['mode' => 'fast']),
    \InvalidArgumentException::class,
    'unknown mode rejected at construction'
);
TestRunner::assertThrows(
    fn() => $bar->setMode('weird'),
    \InvalidArgumentException::class,
    'unknown mode rejected by setMode'
);

// ---- Invalid orient throws --------------------------------------------------

TestRunner::assertThrows(
    fn() => new Progressbar($wid, ['orient' => 'sideways']),
    \InvalidArgumentException::class,
    'unknown orient rejected'
);

// ---- Invalid start interval throws -----------------------------------------

TestRunner::assertThrows(
    fn() => $bar->start(0),
    \InvalidArgumentException::class,
    'start interval < 1ms rejected'
);

// ---- destroy() --------------------------------------------------------------

$bar->destroy();
TestRunner::assertWidgetGone($path, 'Progressbar gone after destroy()');

TestRunner::summary();
