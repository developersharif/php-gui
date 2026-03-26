<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('WindowTest');

// Creation
$window = new Window(['title' => 'Window Test', 'width' => 400, 'height' => 300]);
TestRunner::assertNotEmpty($window->getId(), 'Window has a non-empty ID');
TestRunner::assertWidgetExists(".{$window->getId()}", 'Window Tcl widget exists after creation');

// Second window — IDs must be unique
$window2 = new Window(['title' => 'Second Window', 'width' => 200, 'height' => 150]);
TestRunner::assert(
    $window->getId() !== $window2->getId(),
    'Two windows have distinct IDs'
);
TestRunner::assertWidgetExists(".{$window2->getId()}", 'Second window Tcl widget exists');

// Layout methods must throw on top-level widgets
TestRunner::assertThrows(
    fn() => $window->pack(),
    \RuntimeException::class,
    'pack() throws on top-level window'
);
TestRunner::assertThrows(
    fn() => $window->place(),
    \RuntimeException::class,
    'place() throws on top-level window'
);
TestRunner::assertThrows(
    fn() => $window->grid(),
    \RuntimeException::class,
    'grid() throws on top-level window'
);

TestRunner::summary();
