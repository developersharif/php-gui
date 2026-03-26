<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Label;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('LabelTest');

$window = new Window(['title' => 'Label Test']);
$wid = $window->getId();

// Creation
$label = new Label($wid, ['text' => 'Hello World']);
$path  = ".{$wid}.{$label->getId()}";
TestRunner::assertWidgetExists($path, 'Label Tcl widget exists after creation');

// setText
TestRunner::assertNoThrow(fn() => $label->setText('Updated'), 'setText() does not throw');
$text = trim(\PhpGui\ProcessTCL::getInstance()->evalTcl("{$path} cget -text"));
TestRunner::assertEqual('Updated', $text, 'setText() changes Tcl widget text');

// setFont, setForeground, setBackground — verify no exception
TestRunner::assertNoThrow(fn() => $label->setFont('Arial 12'), 'setFont() does not throw');
TestRunner::assertNoThrow(fn() => $label->setForeground('red'),   'setForeground() does not throw');
TestRunner::assertNoThrow(fn() => $label->setBackground('white'), 'setBackground() does not throw');

// pack then destroy
$label->pack(['pady' => 5]);
$label->destroy();
TestRunner::assertWidgetGone($path, 'Label Tcl widget gone after destroy()');

TestRunner::summary();
