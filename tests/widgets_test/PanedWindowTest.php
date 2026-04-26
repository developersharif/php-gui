<?php
/**
 * Phase 2.10 — PanedWindow regression tests.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGui\Widget\Frame;
use PhpGui\Widget\PanedWindow;
use PhpGui\Widget\Label;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('PanedWindowTest');

$tcl    = ProcessTCL::getInstance();
$window = new Window(['title' => 'Paned Test', 'width' => 500, 'height' => 300]);
$wid    = $window->getId();

// ---- Creation ---------------------------------------------------------------

$pw = new PanedWindow($wid, ['orient' => 'horizontal']);
$path = ".{$wid}.{$pw->getId()}";
TestRunner::assertWidgetExists($path, 'PanedWindow exists in Tcl');
TestRunner::assertEqual(0, $pw->getPaneCount(), 'fresh PanedWindow has 0 panes');
TestRunner::assertEqual(
    'horizontal',
    trim($tcl->evalTcl("{$path} cget -orient")),
    'horizontal orient applied'
);
$pw->pack(['fill' => 'both', 'expand' => 1]);

// ---- Add panes --------------------------------------------------------------

$left  = new Frame($pw->getId());
$right = new Frame($pw->getId());
$pw->addPane($left,  ['weight' => 1]);
$pw->addPane($right, ['weight' => 3]);
TestRunner::assertEqual(2, $pw->getPaneCount(), 'two panes added');
TestRunner::assert($pw->getPane(0) === $left,  'getPane(0) returns first pane');
TestRunner::assert($pw->getPane(1) === $right, 'getPane(1) returns second pane');
TestRunner::assertEqual(null, $pw->getPane(99), 'getPane(out-of-range) returns null');

// Tk reports the panes in order
$panesPath = trim($tcl->evalTcl("{$path} panes"));
TestRunner::assert(
    str_contains($panesPath, $left->getTclPath())
        && str_contains($panesPath, $right->getTclPath()),
    'Tk reports both pane paths under PanedWindow'
);

// ---- addPane rejects unrelated widget --------------------------------------

$stray = new Label($wid, ['text' => 'stray']);
TestRunner::assertThrows(
    fn() => $pw->addPane($stray),
    \InvalidArgumentException::class,
    'addPane rejects widget whose parent is not the PanedWindow'
);

// ---- configurePane updates weight -------------------------------------------

TestRunner::assertNoThrow(
    fn() => $pw->configurePane(0, ['weight' => 2]),
    'configurePane runs without exception'
);

// ---- removePane reduces count ----------------------------------------------

$pw->removePane(0);
TestRunner::assertEqual(1, $pw->getPaneCount(), 'removePane decreases count');
TestRunner::assert($pw->getPane(0) === $right, 'remaining pane is the second one');

TestRunner::assertThrows(
    fn() => $pw->removePane(99),
    \OutOfRangeException::class,
    'removePane out-of-range throws'
);

// ---- Invalid orient throws --------------------------------------------------

TestRunner::assertThrows(
    fn() => new PanedWindow($wid, ['orient' => 'sideways']),
    \InvalidArgumentException::class,
    'unknown orient rejected'
);

// ---- Vertical creation ------------------------------------------------------

$vpw = new PanedWindow($wid, ['orient' => 'vertical']);
TestRunner::assertEqual(
    'vertical',
    trim($tcl->evalTcl("{$vpw->getTclPath()} cget -orient")),
    'vertical orient applied'
);

// ---- destroy ---------------------------------------------------------------

$pw->destroy();
TestRunner::assertWidgetGone($path, 'PanedWindow gone after destroy()');

TestRunner::summary();
