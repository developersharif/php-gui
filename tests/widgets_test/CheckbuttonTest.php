<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\Widget\Checkbutton;
use PhpGui\Widget\Window;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('CheckbuttonTest');

$window = new Window(['title' => 'Checkbutton Test']);
$wid = $window->getId();

$check = new Checkbutton($wid, ['text' => 'Accept Terms']);
$path  = ".{$wid}.{$check->getId()}";

TestRunner::assertWidgetExists($path, 'Checkbutton Tcl widget exists');
$cbText = trim(\PhpGui\ProcessTCL::getInstance()->evalTcl("{$path} cget -text"));
TestRunner::assertEqual('Accept Terms', $cbText, 'Checkbutton -text option set correctly');

// Initial state
TestRunner::assert(!$check->isChecked(), 'Checkbutton starts unchecked');

// setChecked true
$check->setChecked(true);
TestRunner::assert($check->isChecked(), 'isChecked() true after setChecked(true)');

// setChecked false
$check->setChecked(false);
TestRunner::assert(!$check->isChecked(), 'isChecked() false after setChecked(false)');

// toggle false → true
$check->toggle();
TestRunner::assert($check->isChecked(), 'toggle() flips false→true');

// toggle true → false
$check->toggle();
TestRunner::assert(!$check->isChecked(), 'toggle() flips true→false');

// Callback fires when triggered
$fired = false;
$check2 = new Checkbutton($wid, [
    'text'    => 'With Callback',
    'command' => function () use (&$fired) { $fired = true; },
]);
\PhpGui\ProcessTCL::getInstance()->executeCallback($check2->getId());
TestRunner::assert($fired, 'Checkbutton callback fires when triggered');

// destroy
$check->destroy();
TestRunner::assertWidgetGone($path, 'Checkbutton Tcl widget gone after destroy()');

TestRunner::summary();
