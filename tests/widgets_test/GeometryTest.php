<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Label;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('GeometryTest');

// pack
$wp = new Window(['title' => 'Pack Test']);
$lp = new Label($wp->getId(), ['text' => 'Pack']);
TestRunner::assertNoThrow(fn() => $lp->pack(['pady' => 5]), 'pack() does not throw');
$info = trim(\PhpGui\ProcessTCL::getInstance()->evalTcl("pack info .{$wp->getId()}.{$lp->getId()}"));
TestRunner::assertNotEmpty($info, 'pack info non-empty after pack()');

// place
$wpl = new Window(['title' => 'Place Test']);
$ll  = new Label($wpl->getId(), ['text' => 'Place']);
TestRunner::assertNoThrow(fn() => $ll->place(['x' => 50, 'y' => 100]), 'place() does not throw');
$placeInfo = trim(\PhpGui\ProcessTCL::getInstance()->evalTcl("place info .{$wpl->getId()}.{$ll->getId()}"));
TestRunner::assertNotEmpty($placeInfo, 'place info non-empty after place()');

// grid
$wg = new Window(['title' => 'Grid Test']);
$lg = new Label($wg->getId(), ['text' => 'Grid']);
TestRunner::assertNoThrow(fn() => $lg->grid(['row' => 0, 'column' => 0]), 'grid() does not throw');
$gridInfo = trim(\PhpGui\ProcessTCL::getInstance()->evalTcl("grid info .{$wg->getId()}.{$lg->getId()}"));
TestRunner::assertNotEmpty($gridInfo, 'grid info non-empty after grid()');

// Top-level widgets must refuse all geometry managers
$topWin = new Window(['title' => 'TopLevel Geometry']);
TestRunner::assertThrows(fn() => $topWin->pack(),  \RuntimeException::class, 'pack() throws on top-level');
TestRunner::assertThrows(fn() => $topWin->place(), \RuntimeException::class, 'place() throws on top-level');
TestRunner::assertThrows(fn() => $topWin->grid(),  \RuntimeException::class, 'grid() throws on top-level');

TestRunner::summary();
