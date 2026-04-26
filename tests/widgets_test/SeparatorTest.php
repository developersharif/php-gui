<?php
/**
 * Phase 2.12 — Separator regression tests.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGui\Widget\Separator;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('SeparatorTest');

$tcl    = ProcessTCL::getInstance();
$window = new Window(['title' => 'Separator Test']);
$wid    = $window->getId();

// ---- Horizontal (default) ---------------------------------------------------

$h = new Separator($wid);
$path = ".{$wid}.{$h->getId()}";
TestRunner::assertWidgetExists($path, 'Separator exists in Tcl');
TestRunner::assertEqual(
    'horizontal',
    trim($tcl->evalTcl("{$path} cget -orient")),
    'default orient is horizontal'
);
$h->pack(['fill' => 'x', 'pady' => 6]);

// ---- Vertical ---------------------------------------------------------------

$v = new Separator($wid, ['orient' => 'vertical']);
TestRunner::assertEqual(
    'vertical',
    trim($tcl->evalTcl("{$v->getTclPath()} cget -orient")),
    'vertical orient applied when requested'
);

// ---- Invalid orient throws --------------------------------------------------

TestRunner::assertThrows(
    fn() => new Separator($wid, ['orient' => 'diagonal']),
    \InvalidArgumentException::class,
    'unknown orient rejected'
);

// ---- destroy ---------------------------------------------------------------

$h->destroy();
TestRunner::assertWidgetGone($path, 'Separator gone after destroy()');

TestRunner::summary();
