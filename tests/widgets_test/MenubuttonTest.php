<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Menubutton;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('MenubuttonTest');

$window = new Window(['title' => 'Menubutton Test']);
$wid = $window->getId();

$mb   = new Menubutton($wid, ['text' => 'Test Menubutton']);
$path = ".{$wid}.{$mb->getId()}";
TestRunner::assertWidgetExists($path, 'Menubutton Tcl widget exists after creation');

$mbText = trim(\PhpGui\ProcessTCL::getInstance()->evalTcl("{$path} cget -text"));
TestRunner::assertEqual('Test Menubutton', $mbText, 'Menubutton -text option set correctly');

// The attached menu should also exist
$menuPath = ".{$wid}.m_{$mb->getId()}";
TestRunner::assertWidgetExists($menuPath, 'Menubutton attached menu exists');

// destroy
$mb->destroy();
TestRunner::assertWidgetGone($path, 'Menubutton Tcl widget gone after destroy()');

TestRunner::summary();
