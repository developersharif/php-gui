<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Input;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('InputTest');

$window = new Window(['title' => 'Input Test']);
$wid = $window->getId();

// Creation + initial value
$input = new Input($wid, ['text' => 'Initial Text']);
$path  = ".{$wid}.{$input->getId()}";
TestRunner::assertWidgetExists($path, 'Input Tcl widget exists after creation');
TestRunner::assertEqual('Initial Text', $input->getValue(), 'Initial text matches after creation');

// setValue / getValue roundtrip
$input->setValue('Changed');
TestRunner::assertEqual('Changed', $input->getValue(), 'getValue() reflects setValue()');

// Empty string roundtrip
$input->setValue('');
TestRunner::assertEqual('', $input->getValue(), 'setValue("") clears the input');

// onEnter callback registration and execution
$entered = false;
$input->onEnter(function () use (&$entered) { $entered = true; });
\PhpGui\ProcessTCL::getInstance()->executeCallback($input->getId());
TestRunner::assert($entered, 'onEnter callback fires when triggered');

// destroy
$input->destroy();
TestRunner::assertWidgetGone($path, 'Input Tcl widget gone after destroy()');

TestRunner::summary();
