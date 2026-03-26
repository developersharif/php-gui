<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\Widget\TopLevel;
use PhpGuiTest\TestRunner;

// Note: interactive dialog methods (chooseColor, chooseDirectory, getOpenFile,
// getSaveFile, messageBox, dialog, popupMenu) are excluded here because they
// block waiting for user input and cannot run in a headless CI environment.

$app = new Application();
TestRunner::suite('TopLevelWidgetTest');

// Creation
$tl = new TopLevel(['title' => 'Test TopLevel', 'width' => 300, 'height' => 200]);
TestRunner::assertWidgetExists(".{$tl->getId()}", 'TopLevel Tcl widget exists after creation');

// setTitle
TestRunner::assertNoThrow(fn() => $tl->setTitle('New Title'), 'setTitle() does not throw');
$title = trim(\PhpGui\ProcessTCL::getInstance()->evalTcl("wm title .{$tl->getId()}"));
TestRunner::assertEqual('New Title', $title, 'setTitle() changes the window manager title');

// setGeometry
TestRunner::assertNoThrow(fn() => $tl->setGeometry(400, 250), 'setGeometry() does not throw');
\PhpGui\ProcessTCL::getInstance()->evalTcl('update idletasks');
$geo = trim(\PhpGui\ProcessTCL::getInstance()->evalTcl("wm geometry .{$tl->getId()}"));
TestRunner::assert(str_starts_with($geo, '400x250'), 'setGeometry() sets correct dimensions');

// setGeometry with position
TestRunner::assertNoThrow(fn() => $tl->setGeometry(400, 250, 10, 20), 'setGeometry() with position does not throw');

// setResizable
TestRunner::assertNoThrow(fn() => $tl->setResizable(false, false), 'setResizable(false,false) does not throw');
TestRunner::assertNoThrow(fn() => $tl->setResizable(true,  true),  'setResizable(true,true) does not throw');

// setMinsize / setMaxsize
TestRunner::assertNoThrow(fn() => $tl->setMinsize(100, 100), 'setMinsize() does not throw');
TestRunner::assertNoThrow(fn() => $tl->setMaxsize(800, 600), 'setMaxsize() does not throw');

// iconify / deiconify / withdraw
TestRunner::assertNoThrow(fn() => $tl->iconify(),   'iconify() does not throw');
TestRunner::assertNoThrow(fn() => $tl->deiconify(), 'deiconify() does not throw');
TestRunner::assertNoThrow(fn() => $tl->withdraw(),  'withdraw() does not throw');

// focus
TestRunner::assertNoThrow(fn() => $tl->focus(), 'focus() does not throw');

// Second TopLevel — IDs must be unique
$tl2 = new TopLevel(['title' => 'Second']);
TestRunner::assert($tl->getId() !== $tl2->getId(), 'Two TopLevel widgets have distinct IDs');

// destroy
$id = $tl->getId();
$tl->destroy();
TestRunner::assertWidgetGone(".{$id}", 'TopLevel Tcl widget gone after destroy()');

TestRunner::summary();
