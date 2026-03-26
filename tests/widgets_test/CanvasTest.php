<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\Widget\Canvas;
use PhpGui\Widget\Window;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('CanvasTest');

$window = new Window(['title' => 'Canvas Test']);
$wid = $window->getId();
$canvas = new Canvas($wid, ['width' => 300, 'height' => 300]);
$cp = ".{$wid}.{$canvas->getId()}";

TestRunner::assertWidgetExists($cp, 'Canvas Tcl widget exists');

// drawLine — returns an item ID (non-empty string)
$lineId = $canvas->drawLine(0, 0, 100, 100, ['fill' => 'black']);
TestRunner::assertNotEmpty($lineId, 'drawLine() returns a non-empty item ID');

// drawRectangle
$rectId = $canvas->drawRectangle(10, 10, 90, 90, ['fill' => 'blue']);
TestRunner::assertNotEmpty($rectId, 'drawRectangle() returns a non-empty item ID');

// drawOval
$ovalId = $canvas->drawOval(20, 20, 80, 80, ['fill' => 'red']);
TestRunner::assertNotEmpty($ovalId, 'drawOval() returns a non-empty item ID');

// drawText
$textId = $canvas->drawText(50, 50, 'Hello', ['anchor' => 'center']);
TestRunner::assertNotEmpty($textId, 'drawText() returns a non-empty item ID');

// Item IDs must be distinct
$ids = [$lineId, $rectId, $ovalId, $textId];
TestRunner::assertEqual(count($ids), count(array_unique($ids)), 'All canvas item IDs are unique');

// delete a single item — must not throw
TestRunner::assertNoThrow(fn() => $canvas->delete($lineId), 'delete() single item does not throw');

// clear all items — must not throw
TestRunner::assertNoThrow(fn() => $canvas->clear(), 'clear() does not throw');

// After clear(), the canvas widget itself still exists
TestRunner::assertWidgetExists($cp, 'Canvas Tcl widget still exists after clear()');

// destroy
$canvas->destroy();
TestRunner::assertWidgetGone($cp, 'Canvas Tcl widget gone after destroy()');

TestRunner::summary();
