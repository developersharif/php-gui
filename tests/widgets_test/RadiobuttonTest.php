<?php
/**
 * Phase 2.4 — Radiobutton + RadioGroup regression tests.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGui\Widget\RadioGroup;
use PhpGui\Widget\Radiobutton;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('RadiobuttonTest');

$tcl    = ProcessTCL::getInstance();
$window = new Window(['title' => 'Radio Test']);
$wid    = $window->getId();

// ---- RadioGroup defaults ----------------------------------------------------

$tier = new RadioGroup('basic');
TestRunner::assertEqual('basic', $tier->getDefault(), 'RadioGroup remembers default');
TestRunner::assertEqual('basic', $tier->getValue(), 'getValue() returns default before any pick');
TestRunner::assert(
    str_starts_with($tier->getVariableName(), 'phpgui_radio_'),
    'RadioGroup variable name is namespaced'
);

// ---- Two radios share the variable: selecting one deselects the other ------

$basic = new Radiobutton($wid, $tier, 'basic', ['text' => 'Basic']);
$pro   = new Radiobutton($wid, $tier, 'pro',   ['text' => 'Pro']);
$ent   = new Radiobutton($wid, $tier, 'ent',   ['text' => 'Enterprise']);
$basic->pack(); $pro->pack(); $ent->pack();

TestRunner::assertWidgetExists($basic->getTclPath(), 'first Radiobutton exists');
TestRunner::assertWidgetExists($pro->getTclPath(),   'second Radiobutton exists');

TestRunner::assert($basic->isSelected(), 'default value selects "basic" radio');
TestRunner::assert(!$pro->isSelected(),  '"pro" not selected by default');

// All three radios should report the same -variable
TestRunner::assertEqual(
    $tier->getVariableName(),
    trim($tcl->evalTcl("{$basic->getTclPath()} cget -variable")),
    'first radio is bound to the group variable'
);
TestRunner::assertEqual(
    $tier->getVariableName(),
    trim($tcl->evalTcl("{$pro->getTclPath()} cget -variable")),
    'second radio is bound to the same group variable'
);

// ---- select() and setValue() flip the active radio --------------------------

$pro->select();
TestRunner::assertEqual('pro', $tier->getValue(), 'select() updates group value');
TestRunner::assert($pro->isSelected(), 'pro is now selected');
TestRunner::assert(!$basic->isSelected(), 'basic deselected after pro select');

$tier->setValue('ent');
TestRunner::assertEqual('ent', $tier->getValue(), 'setValue() updates group value');
TestRunner::assert($ent->isSelected(), 'ent now selected via setValue');
TestRunner::assert(!$pro->isSelected(), 'pro deselected via setValue');

// ---- onChange fires for both setValue() and Tk-side -command callbacks ----

$received = [];
$tier->onChange(function (string $v) use (&$received) {
    $received[] = $v;
});

$tier->setValue('pro');
TestRunner::assertEqual(['pro'], $received, 'onChange fires on setValue()');

// Simulate a real user click by invoking the radio's -command callback id.
$tcl->evalTcl("php::executeCallback {$basic->getId()}");
// The Tk -command callback runs after Tk has already updated the variable
// to the radio's -value, so simulate that too.
$tcl->setVar($tier->getVariableName(), 'basic');
$tcl->drainPendingCallbacks();
TestRunner::assertEqual(
    ['pro', 'basic'],
    $received,
    'onChange fires when a Radiobutton -command runs (user click path)'
);

// ---- Per-radio command receives the value -----------------------------------

$clicked = null;
$cmdRadio = new Radiobutton($wid, $tier, 'cmd', [
    'text'    => 'Cmd',
    'command' => function (string $v) use (&$clicked) {
        $clicked = $v;
    },
]);
$cmdRadio->pack();

$tcl->setVar($tier->getVariableName(), 'cmd');
$tcl->evalTcl("php::executeCallback {$cmdRadio->getId()}");
$tcl->drainPendingCallbacks();
TestRunner::assertEqual('cmd', $clicked, 'per-radio command callback receives the value');

// ---- Radio text round-trips literally even with Tcl-special chars ----------

$evil = new Radiobutton($wid, $tier, 'evil', [
    'text' => 'Hello"; destroy .; "',
]);
$evil->pack();
TestRunner::assertWidgetExists(".{$wid}", 'window survives Radiobutton text injection');
TestRunner::assertEqual(
    'Hello"; destroy .; "',
    trim($tcl->evalTcl("{$evil->getTclPath()} cget -text")),
    'Radiobutton text round-trips literally'
);

// ---- Two independent groups don't interfere --------------------------------

$theme = new RadioGroup('light');
$light = new Radiobutton($wid, $theme, 'light', ['text' => 'Light']);
$dark  = new Radiobutton($wid, $theme, 'dark',  ['text' => 'Dark']);
$light->pack(); $dark->pack();

$dark->select();
TestRunner::assertEqual('dark', $theme->getValue(), 'theme group selects dark');
TestRunner::assertEqual('cmd',  $tier->getValue(),  'tier group is unaffected by theme change');

// ---- destroy() frees the radio's per-instance callback ----------------------

$id = $basic->getId();
TestRunner::assertEqual(
    '1',
    trim($tcl->evalTcl("info exists php::callbacks({$id})")),
    'radio callback registered while alive'
);
$basic->destroy();
TestRunner::assertEqual(
    '0',
    trim($tcl->evalTcl("info exists php::callbacks({$id})")),
    'radio callback freed by destroy()'
);

TestRunner::summary();
