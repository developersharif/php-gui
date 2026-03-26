<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Button;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('ButtonTest');

$window = new Window(['title' => 'Button Test']);
$wid = $window->getId();

// Button without callback
$btn = new Button($wid, ['text' => 'No Callback']);
$path = ".{$wid}.{$btn->getId()}";
TestRunner::assertWidgetExists($path, 'Button (no callback) Tcl widget exists');
$btnText = trim(\PhpGui\ProcessTCL::getInstance()->evalTcl("{$path} cget -text"));
TestRunner::assertEqual('No Callback', $btnText, 'Button -text option set correctly');

// Button with callback — callback must be registered and executable
$fired = false;
$btn2 = new Button($wid, [
    'text'    => 'With Callback',
    'command' => function () use (&$fired) { $fired = true; },
]);
$path2 = ".{$wid}.{$btn2->getId()}";
TestRunner::assertWidgetExists($path2, 'Button (with callback) Tcl widget exists');

// Simulate the Tcl→PHP callback bridge
\PhpGui\ProcessTCL::getInstance()->executeCallback($btn2->getId());
TestRunner::assert($fired, 'Button callback executes when triggered');

// pack then destroy
$btn->pack();
$btn->destroy();
TestRunner::assertWidgetGone($path, 'Button Tcl widget gone after destroy()');

TestRunner::summary();
