<?php
/**
 * Phase 2.1 — Text widget regression tests.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGui\Widget\Text;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('TextTest');

$tcl    = ProcessTCL::getInstance();
$window = new Window(['title' => 'Text Test']);
$wid    = $window->getId();

// ---- Creation + path --------------------------------------------------------

$text = new Text($wid, ['width' => 40, 'height' => 10]);
$path = ".{$wid}.{$text->getId()}";
TestRunner::assertWidgetExists($path, 'Text widget exists in Tcl');
TestRunner::assertEqual($path, $text->getTclPath(), 'getTclPath matches Tcl path');

// ---- Empty state ------------------------------------------------------------

TestRunner::assertEqual('', $text->getText(), 'fresh Text starts empty');
TestRunner::assertEqual(0, $text->getLength(), 'empty Text has length 0');
TestRunner::assertEqual(1, $text->getLineCount(), 'empty Text has 1 line');

// ---- setText / getText round-trip with newlines and special chars ----------

$payload = "Hello, world!\nLine two with [brackets] and \$dollar and \"quotes\".";
$text->setText($payload);
TestRunner::assertEqual(
    $payload,
    $text->getText(),
    'setText/getText round-trips multi-line content with Tcl-special chars'
);
TestRunner::assertEqual(2, $text->getLineCount(), 'two-line content reports 2 lines');

// ---- append -----------------------------------------------------------------

$text->append("\nappended");
TestRunner::assertEqual(
    $payload . "\nappended",
    $text->getText(),
    'append() concatenates without disturbing existing content'
);

// ---- insertAt with valid indices --------------------------------------------

$text->setText('hello world');
$text->insertAt('1.0', '>> ');
TestRunner::assertEqual(
    '>> hello world',
    $text->getText(),
    'insertAt at 1.0 prepends content'
);

$text->setText('a');
$text->insertAt('end', 'b');
TestRunner::assertEqual('ab', $text->getText(), 'insertAt at end appends');

// ---- insertAt rejects malformed indices (Tcl-injection guard) --------------

TestRunner::assertThrows(
    fn() => $text->insertAt('1.0; destroy .', 'evil'),
    \InvalidArgumentException::class,
    'insertAt rejects index containing Tcl injection'
);
TestRunner::assertWidgetExists($path, 'Text widget still alive after rejected insertAt');

// ---- clear ------------------------------------------------------------------

$text->setText('something');
$text->clear();
TestRunner::assertEqual('', $text->getText(), 'clear() empties the widget');

// ---- read-only / disabled ---------------------------------------------------

$logView = new Text($wid);
$logView->setText('first line');
$logView->setState('disabled');
TestRunner::assert($logView->isDisabled(), 'isDisabled() reports true after setState(disabled)');

// append must still work even when the widget is disabled (logs).
$logView->append("\nsecond line");
TestRunner::assertEqual(
    "first line\nsecond line",
    $logView->getText(),
    'append() works on a disabled (read-only) Text by toggling state'
);
TestRunner::assert(
    $logView->isDisabled(),
    'state is restored to disabled after append'
);

TestRunner::assertThrows(
    fn() => $logView->setState('weird'),
    \InvalidArgumentException::class,
    'setState rejects invalid state strings'
);

// ---- destroy cascades -------------------------------------------------------

$text->destroy();
TestRunner::assertWidgetGone($path, 'Text widget gone after destroy()');

TestRunner::summary();
