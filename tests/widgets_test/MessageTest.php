<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Message;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('MessageTest');

$window = new Window(['title' => 'Message Test']);
$wid = $window->getId();

// Creation — Message creates a toplevel + label inside
$msg  = new Message($wid, ['text' => 'Test Message']);
$path = ".{$wid}.{$msg->getId()}";
TestRunner::assertWidgetExists($path, 'Message toplevel Tcl widget exists after creation');
TestRunner::assertWidgetExists("{$path}.msg", 'Message inner label widget exists');

// setText
TestRunner::assertNoThrow(fn() => $msg->setText('Updated Message'), 'setText() does not throw');
$labelText = trim(\PhpGui\ProcessTCL::getInstance()->evalTcl("{$path}.msg cget -text"));
TestRunner::assertEqual('Updated Message', $labelText, 'setText() changes inner label text');

TestRunner::summary();
