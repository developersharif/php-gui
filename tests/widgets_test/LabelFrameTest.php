<?php
/**
 * Phase 2.11 — LabelFrame regression tests.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGui\Widget\LabelFrame;
use PhpGui\Widget\Label;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('LabelFrameTest');

$tcl    = ProcessTCL::getInstance();
$window = new Window(['title' => 'LabelFrame Test']);
$wid    = $window->getId();

// ---- Creation with title ----------------------------------------------------

$box = new LabelFrame($wid, ['text' => 'Network']);
$path = ".{$wid}.{$box->getId()}";
TestRunner::assertWidgetExists($path, 'LabelFrame exists in Tcl');
TestRunner::assertEqual('Network', $box->getText(), 'getText returns the title');
$box->pack(['fill' => 'x', 'padx' => 8, 'pady' => 8]);

// ---- Children render inside the frame --------------------------------------

$inner = new Label($box->getId(), ['text' => 'Hostname:']);
$inner->pack();
TestRunner::assertWidgetExists($inner->getTclPath(), 'child Label exists inside LabelFrame');
TestRunner::assertEqual(
    $box->getTclPath(),
    trim($tcl->evalTcl("winfo parent {$inner->getTclPath()}")),
    'Tk reports LabelFrame as the child Label parent'
);

// ---- setText updates the title ---------------------------------------------

$box->setText('Updated Title');
TestRunner::assertEqual('Updated Title', $box->getText(), 'setText changes the displayed title');

// ---- Title with Tcl-special characters round-trips literally ---------------

$evil = new LabelFrame($wid, ['text' => 'Title"; destroy .; "']);
TestRunner::assertWidgetExists(".{$wid}", 'window survives LabelFrame title injection');
TestRunner::assertEqual(
    'Title"; destroy .; "',
    $evil->getText(),
    'LabelFrame title with Tcl-special chars round-trips literally'
);

// ---- Empty title is valid (just a frame border) -----------------------------

$bare = new LabelFrame($wid);
TestRunner::assertWidgetExists($bare->getTclPath(), 'LabelFrame without text option still creates');
TestRunner::assertEqual('', $bare->getText(), 'no-title LabelFrame reports empty getText');

// ---- destroy ----------------------------------------------------------------

$box->destroy();
TestRunner::assertWidgetGone($path, 'LabelFrame gone after destroy()');
TestRunner::assertWidgetGone(
    $inner->getTclPath(),
    'child Label cascades to gone after parent destroy()'
);

TestRunner::summary();
