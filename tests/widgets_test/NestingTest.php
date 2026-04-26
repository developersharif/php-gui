<?php
/**
 * Verifies that widgets can be nested arbitrarily deep — i.e. a Button can
 * live inside a Frame which lives inside another Frame which lives inside
 * the Window. Before AbstractWidget tracked full Tcl paths, this layout
 * raised "bad window path name" because every child was assumed to live
 * one level under root.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGui\Widget\Frame;
use PhpGui\Widget\Button;
use PhpGui\Widget\Label;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('NestingTest');

$tcl    = ProcessTCL::getInstance();
$window = new Window(['title' => 'Nesting Test']);
$wid    = $window->getId();

// Window's full Tcl path is just `.<id>`.
TestRunner::assertEqual(".{$wid}", $window->getTclPath(), 'Window getTclPath returns root path');

// One-level nesting: Frame inside Window.
$outer    = new Frame($window->getId());
$expected = ".{$wid}.{$outer->getId()}";
TestRunner::assertEqual($expected, $outer->getTclPath(), 'outer Frame path is .window.frame');
TestRunner::assertWidgetExists($expected, 'outer Frame exists in Tcl');
$outer->pack(['fill' => 'both', 'expand' => 1, 'padx' => 8, 'pady' => 8]);

// Two-level nesting: Frame inside Frame.
$inner    = new Frame($outer->getId());
$expected = ".{$wid}.{$outer->getId()}.{$inner->getId()}";
TestRunner::assertEqual($expected, $inner->getTclPath(), 'inner Frame path is .window.outer.inner');
TestRunner::assertWidgetExists($expected, 'inner Frame exists in Tcl');
$inner->pack(['fill' => 'both', 'expand' => 1]);

// Three-level: Button inside the inner Frame.
$button   = new Button($inner->getId(), ['text' => 'Deep']);
$expected = ".{$wid}.{$outer->getId()}.{$inner->getId()}.{$button->getId()}";
TestRunner::assertEqual($expected, $button->getTclPath(), 'Button path is four levels deep');
TestRunner::assertWidgetExists($expected, 'deeply-nested Button exists in Tcl');
TestRunner::assertNoThrow(fn() => $button->pack(['pady' => 4]), 'pack() works on a deeply-nested Button');

// winfo parent should report the inner frame, proving Tk really sees the
// nesting (not just that the path string is well-formed).
$reportedParent = trim($tcl->evalTcl("winfo parent {$button->getTclPath()}"));
TestRunner::assertEqual($inner->getTclPath(), $reportedParent, 'Tk reports inner Frame as the Button parent');

// Label nested in inner frame, alongside the button — verifies sibling
// children of a non-root parent both render.
$label = new Label($inner->getId(), ['text' => 'Hello from depth']);
$label->pack(['pady' => 2]);
TestRunner::assertWidgetExists($label->getTclPath(), 'sibling Label inside inner Frame exists');

// Destroying the outer frame must take its descendants with it.
$buttonPath = $button->getTclPath();
$outer->destroy();
TestRunner::assertWidgetGone($outer->getTclPath(), 'outer Frame gone after destroy()');
TestRunner::assertWidgetGone($buttonPath, 'descendant Button gone after outer destroy() (Tk cascade)');

TestRunner::summary();
