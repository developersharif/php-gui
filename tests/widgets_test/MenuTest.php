<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Menu;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('MenuTest');

$window = new Window(['title' => 'Menu Test']);
$wid = $window->getId();

// Basic menu creation (normal type, attached to parent window frame area)
$menu = new Menu($wid, []);
TestRunner::assertWidgetExists(".{$menu->getId()}", 'Menu Tcl widget exists after creation');

// addCommand — callback must be registered and callable
$cmdFired = false;
$menu->addCommand('Item 1', function () use (&$cmdFired) { $cmdFired = true; });
// The callback ID is {menuId}_cmd_0; fire it directly
$callbackId = $menu->getId() . '_cmd_0';
\PhpGui\ProcessTCL::getInstance()->executeCallback($callbackId);
TestRunner::assert($cmdFired, 'Menu command callback fires when triggered');

// addSeparator
TestRunner::assertNoThrow(fn() => $menu->addSeparator(), 'addSeparator() does not throw');

// addSubmenu returns a Menu instance
$sub = null;
TestRunner::assertNoThrow(function () use ($menu, &$sub) {
    $sub = $menu->addSubmenu('Submenu');
}, 'addSubmenu() does not throw');
TestRunner::assert($sub instanceof Menu, 'addSubmenu() returns a Menu instance');
TestRunner::assertWidgetExists(".{$sub->getId()}", 'Submenu Tcl widget exists');

// Main-type menu attaches to window
$mainMenu = new Menu($wid, ['type' => 'main']);
TestRunner::assertWidgetExists(".{$mainMenu->getId()}", 'Main menu Tcl widget exists');

// destroy
$menu->destroy();
TestRunner::assertWidgetGone(".{$menu->getId()}", 'Menu Tcl widget gone after destroy()');

TestRunner::summary();
