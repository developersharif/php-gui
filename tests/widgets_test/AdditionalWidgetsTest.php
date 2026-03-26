<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\Widget\Canvas;
use PhpGui\Widget\Checkbutton;
use PhpGui\Widget\Combobox;
use PhpGui\Widget\Entry;
use PhpGui\Widget\Frame;
use PhpGui\Widget\Window;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('AdditionalWidgetsTest');

$window = new Window(['title' => 'Additional Widgets Test']);
$wid = $window->getId();

// --- Canvas ---
$canvas = new Canvas($wid, ['width' => 200, 'height' => 200]);
$cp = ".{$wid}.{$canvas->getId()}";
TestRunner::assertWidgetExists($cp, 'Canvas Tcl widget exists');
$canvas->destroy();
TestRunner::assertWidgetGone($cp, 'Canvas Tcl widget gone after destroy()');

// --- Checkbutton ---
$check = new Checkbutton($wid, ['text' => 'My Check']);
$chp = ".{$wid}.{$check->getId()}";
TestRunner::assertWidgetExists($chp, 'Checkbutton Tcl widget exists');
TestRunner::assert(!$check->isChecked(), 'Checkbutton starts unchecked');
$check->setChecked(true);
TestRunner::assert($check->isChecked(), 'setChecked(true) makes it checked');
$check->setChecked(false);
TestRunner::assert(!$check->isChecked(), 'setChecked(false) makes it unchecked');
$check->toggle();
TestRunner::assert($check->isChecked(), 'toggle() flips from false to true');
$check->destroy();
TestRunner::assertWidgetGone($chp, 'Checkbutton Tcl widget gone after destroy()');

// --- Combobox ---
$combo = new Combobox($wid, ['values' => 'Alpha Beta Gamma']);
$cbp = ".{$wid}.{$combo->getId()}";
TestRunner::assertWidgetExists($cbp, 'Combobox Tcl widget exists');
$combo->setValue('Beta');
TestRunner::assertEqual('Beta', $combo->getValue(), 'Combobox setValue/getValue roundtrip');
$combo->destroy();
TestRunner::assertWidgetGone($cbp, 'Combobox Tcl widget gone after destroy()');

// --- Entry ---
$entry = new Entry($wid, ['text' => 'Default']);
$ep = ".{$wid}.{$entry->getId()}";
TestRunner::assertWidgetExists($ep, 'Entry Tcl widget exists');
TestRunner::assertEqual('Default', $entry->getValue(), 'Entry initial text correct');
$entry->setValue('Modified');
TestRunner::assertEqual('Modified', $entry->getValue(), 'Entry setValue/getValue roundtrip');
$entry->destroy();
TestRunner::assertWidgetGone($ep, 'Entry Tcl widget gone after destroy()');

// --- Frame ---
$frame = new Frame($wid, []);
$fp = ".{$wid}.{$frame->getId()}";
TestRunner::assertWidgetExists($fp, 'Frame Tcl widget exists');
$frame->destroy();
TestRunner::assertWidgetGone($fp, 'Frame Tcl widget gone after destroy()');

TestRunner::summary();
