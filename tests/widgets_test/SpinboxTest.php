<?php
/**
 * Phase 2.6 — Spinbox widget regression tests.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGui\Widget\Spinbox;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('SpinboxTest');

$tcl    = ProcessTCL::getInstance();
$window = new Window(['title' => 'Spinbox Test']);
$wid    = $window->getId();

// ---- Numeric range ----------------------------------------------------------

$qty = new Spinbox($wid, ['from' => 1, 'to' => 99, 'increment' => 1]);
$path = ".{$wid}.{$qty->getId()}";
TestRunner::assertWidgetExists($path, 'Spinbox exists in Tcl');
TestRunner::assertEqual('1', $qty->getValue(), 'initial value defaults to from');

$qty->setValue('42');
TestRunner::assertEqual('42', $qty->getValue(), 'setValue/getValue round-trip');
TestRunner::assertEqual(42.0, $qty->getNumericValue(), 'getNumericValue casts to float');

// ---- Explicit `value` option overrides `from` ------------------------------

$preset = new Spinbox($wid, ['from' => 0, 'to' => 100, 'value' => '7']);
TestRunner::assertEqual('7', $preset->getValue(), 'explicit value option used as initial');

// ---- Values list (enumeration) ---------------------------------------------

$enum = new Spinbox($wid, [
    'values' => ['low', 'medium', 'high'],
    'value'  => 'medium',
]);
TestRunner::assertEqual('medium', $enum->getValue(), 'enumeration default value applied');

$valuesAttr = trim($tcl->evalTcl("{$enum->getTclPath()} cget -values"));
foreach (['low', 'medium', 'high'] as $expected) {
    TestRunner::assert(
        str_contains($valuesAttr, $expected),
        "values list contains '{$expected}'"
    );
}

// ---- Tcl-special characters in enumeration round-trip ----------------------

$evil = new Spinbox($wid, [
    'values' => ['plain', 'with [brackets]', '"$dangerous"'],
    'value'  => 'plain',
]);
TestRunner::assertWidgetExists(".{$wid}", 'window survives spinbox enumeration injection attempt');
$evil->setValue('with [brackets]');
TestRunner::assertEqual(
    'with [brackets]',
    $evil->getValue(),
    'value with Tcl-special chars round-trips literally'
);

// ---- onChange fires on setValue() and on Tk -command -----------------------

$received = [];
$qty->onChange(function (string $v) use (&$received) {
    $received[] = $v;
});

$qty->setValue('11');
TestRunner::assertEqual(['11'], $received, 'onChange fires on programmatic setValue');

// Simulate Tk firing -command after a button press
$tcl->setVar('phpgui_spin_' . $qty->getId(), '12');
$tcl->evalTcl("php::executeCallback {$qty->getId()}_change");
$tcl->drainPendingCallbacks();
TestRunner::assertEqual(
    ['11', '12'],
    $received,
    'onChange fires when Tk -command callback runs'
);

// ---- destroy() unregisters callback ----------------------------------------

$cbId = $qty->getId() . '_change';
TestRunner::assertEqual(
    '1',
    trim($tcl->evalTcl("info exists php::callbacks({$cbId})")),
    'change callback registered while alive'
);
$qty->destroy();
TestRunner::assertWidgetGone($path, 'Spinbox gone after destroy()');
TestRunner::assertEqual(
    '0',
    trim($tcl->evalTcl("info exists php::callbacks({$cbId})")),
    'change callback freed by destroy()'
);

TestRunner::summary();
