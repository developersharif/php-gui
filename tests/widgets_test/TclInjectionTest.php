<?php
/**
 * Regression test for v1.9 — confirms user-supplied strings can no longer
 * break out of their Tcl quoting and execute arbitrary commands.
 *
 * Each scenario sets a widget property to a string designed to escape
 * unsafe interpolation, then asserts:
 *   1. the widget still exists (the injected `destroy` did not run);
 *   2. the property round-trips literally (the value Tcl received matches
 *      the value we passed in).
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\AbstractWidget;
use PhpGui\Widget\Window;
use PhpGui\Widget\Label;
use PhpGui\Widget\Button;
use PhpGui\Widget\Input;
use PhpGui\Widget\Entry;
use PhpGui\Widget\Combobox;
use PhpGui\Widget\Canvas;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('TclInjectionTest');

$tcl    = ProcessTCL::getInstance();
$window = new Window(['title' => 'Injection Test']);
$wid    = $window->getId();

// ---- 1. tclQuote() round-trips every payload literally -----------------------

$payloads = [
    'simple'              => 'Hello, world',
    'embedded-double'     => 'with "quote" inside',
    'destroy-attempt'     => 'evil"; destroy .; "',
    'dollar-substitution' => 'price: $99 for $::secret',
    'command-sub'         => 'before [exit] after',
    'trailing-backslash'  => 'path\\',
    'bracket-pair'        => 'wrap [stuff] here',
    'mixed'               => '"$[cmd]\\',
];

foreach ($payloads as $name => $value) {
    $quoted = AbstractWidget::tclQuote($value);
    // Eval the quoted form through Tcl and check the result we get back is
    // identical to what we put in. If quoting were broken, Tcl would either
    // raise a parse error, substitute a variable, or lose characters.
    $roundTripped = $tcl->evalTcl('return ' . $quoted);
    TestRunner::assertEqual(
        $value,
        $roundTripped,
        "tclQuote round-trip preserves payload [{$name}]"
    );
}

// ---- 2. Label text containing a destroy attempt ------------------------------

$evilText = 'gotcha"; destroy ' . '.' . $wid . '; "';
$label    = new Label($wid, ['text' => $evilText]);
TestRunner::assertWidgetExists(
    ".{$wid}",
    'Window survives Label text injection attempt'
);
$shown = $tcl->evalTcl("{$label->getTclPath()} cget -text");
TestRunner::assertEqual($evilText, $shown, 'Label text round-trips literally');

// ---- 3. Label setText after creation -----------------------------------------

$label->setText('replacement"; destroy .; "');
TestRunner::assertWidgetExists(".{$wid}", 'Window survives setText injection');
TestRunner::assertEqual(
    'replacement"; destroy .; "',
    $tcl->evalTcl("{$label->getTclPath()} cget -text"),
    'setText() round-trips literally'
);

// ---- 4. Button text round-trips literally -----------------------------------

$button = new Button($wid, ['text' => 'click"; destroy .; "']);
TestRunner::assertWidgetExists(".{$wid}", 'Window survives Button injection');
TestRunner::assertEqual(
    'click"; destroy .; "',
    $tcl->evalTcl("{$button->getTclPath()} cget -text"),
    'Button text round-trips literally'
);

// Option values that are syntactically invalid for the option still must
// not execute Tcl. Tk should raise an "invalid color" error rather than
// the embedded `destroy` command running.
try {
    new Button($wid, ['fg' => 'red"; destroy .; "']);
    TestRunner::assert(false, 'Button with malformed -fg payload should reject the value');
} catch (\RuntimeException $e) {
    TestRunner::assertWidgetExists(
        ".{$wid}",
        'Window survives Button option-value injection (Tk rejected the value, did not execute it)'
    );
    TestRunner::assert(
        str_contains($e->getMessage(), 'red"; destroy .; "'),
        'Tk error message contains the literal payload (proves it was parsed as data, not code)'
    );
}

// ---- 5. Input default value, setValue and getValue ---------------------------

$inputPayload = 'evil"; destroy .; "';
$input = new Input($wid, ['text' => $inputPayload]);
TestRunner::assertEqual(
    $inputPayload,
    $input->getValue(),
    'Input default value round-trips through Tcl variable'
);
$input->setValue('again"; destroy .; "');
TestRunner::assertEqual(
    'again"; destroy .; "',
    $input->getValue(),
    'Input::setValue round-trips literally'
);
TestRunner::assertWidgetExists(".{$wid}", 'Window survives Input injection');

// ---- 6. Entry  ---------------------------------------------------------------

$entry = new Entry($wid, ['text' => 'open[exit]close']);
TestRunner::assertEqual(
    'open[exit]close',
    $entry->getValue(),
    'Entry default value preserves command-substitution syntax literally'
);

// ---- 7. Combobox values list with command-substitution payload ---------------

$values = ['one', 'two[exit]three', '"$evil"'];
$combo  = new Combobox($wid, ['values' => $values]);
TestRunner::assertWidgetExists(".{$wid}", 'Window survives Combobox values injection');
$tclValues = $tcl->evalTcl("{$combo->getTclPath()} cget -values");
// Tk returns a Tcl-list string; verify each item appears intact.
foreach ($values as $v) {
    TestRunner::assert(
        str_contains($tclValues, $v),
        "Combobox -values preserves item literally [{$v}]"
    );
}

// ---- 8. Canvas drawText with injected text and option values -----------------

$canvas = new Canvas($wid);
$canvas->pack();
$itemId = $canvas->drawText(10, 10, 'caption"; destroy .; "');
TestRunner::assertWidgetExists(".{$wid}", 'Window survives Canvas drawText text injection');
TestRunner::assert(
    $itemId !== '',
    'Canvas drawText returns an item id even with injection-attempt text'
);
$shown = $tcl->evalTcl("{$canvas->getTclPath()} itemcget {$itemId} -text");
TestRunner::assertEqual(
    'caption"; destroy .; "',
    $shown,
    'Canvas text item content round-trips literally'
);

// Malformed option value goes through tclQuote, fails Tk validation, but
// must not execute the embedded command.
try {
    $canvas->drawText(10, 30, 'ok', ['fill' => '#ff0000"; destroy .; "']);
    TestRunner::assert(false, 'Canvas drawText should reject malformed -fill value');
} catch (\RuntimeException $e) {
    TestRunner::assertWidgetExists(
        ".{$wid}",
        'Window survives Canvas drawText option-value injection'
    );
    TestRunner::assert(
        str_contains($e->getMessage(), '#ff0000"; destroy .; "'),
        'Tk error contains the literal Canvas payload'
    );
}

TestRunner::summary();
