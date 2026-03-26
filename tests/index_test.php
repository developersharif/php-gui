<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/TestRunner.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Label;
use PhpGui\Widget\Button;
use PhpGui\Widget\Input;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('IntegrationTest');

// Window
$window = new Window(['title' => 'Integration Test', 'width' => 500, 'height' => 300]);
$wid = $window->getId();
TestRunner::assertWidgetExists(".{$wid}", 'Main window created');

// Label
$label = new Label($wid, ['text' => 'Hello, PHP GUI World!']);
$lpath = ".{$wid}.{$label->getId()}";
TestRunner::assertWidgetExists($lpath, 'Label created');
$label->pack(['pady' => 20]);

// Button — callback integration
$clicked = false;
$button = new Button($wid, [
    'text'    => 'Click Me',
    'command' => function () use ($label, &$clicked) {
        $clicked = true;
        $label->setText('Button clicked!');
    },
]);
$bpath = ".{$wid}.{$button->getId()}";
TestRunner::assertWidgetExists($bpath, 'Button created');
$button->pack(['pady' => 10]);

// Simulate the click
\PhpGui\ProcessTCL::getInstance()->executeCallback($button->getId());
TestRunner::assert($clicked, 'Button callback fires on trigger');
$labelText = trim(\PhpGui\ProcessTCL::getInstance()->evalTcl("{$lpath} cget -text"));
TestRunner::assertEqual('Button clicked!', $labelText, 'Label text updated by button callback');

// Input — getValue/setValue
$input = new Input($wid, ['text' => 'Type here...']);
$ipath = ".{$wid}.{$input->getId()}";
TestRunner::assertWidgetExists($ipath, 'Input created');
TestRunner::assertEqual('Type here...', $input->getValue(), 'Input initial value correct');
$input->setValue('Hello from test');
TestRunner::assertEqual('Hello from test', $input->getValue(), 'Input setValue/getValue roundtrip');
$input->pack(['pady' => 10]);

// onEnter callback
$entered = false;
$input->onEnter(function () use (&$entered) { $entered = true; });
\PhpGui\ProcessTCL::getInstance()->executeCallback($input->getId());
TestRunner::assert($entered, 'Input onEnter callback fires on trigger');

TestRunner::summary();
