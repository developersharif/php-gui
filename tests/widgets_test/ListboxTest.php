<?php
/**
 * Phase 2.3 — Listbox widget regression tests.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGui\Widget\Listbox;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('ListboxTest');

$tcl    = ProcessTCL::getInstance();
$window = new Window(['title' => 'Listbox Test']);
$wid    = $window->getId();

// ---- Creation + initial items via constructor -------------------------------

$lb   = new Listbox($wid, ['items' => ['alpha', 'beta', 'gamma']]);
$path = ".{$wid}.{$lb->getId()}";
TestRunner::assertWidgetExists($path, 'Listbox exists in Tcl');
TestRunner::assertEqual(3, $lb->size(), 'constructor items populate the listbox');
TestRunner::assertEqual('alpha', $lb->getItem(0), 'getItem(0) returns first item');
TestRunner::assertEqual('gamma', $lb->getItem(2), 'getItem(2) returns last item');
TestRunner::assertEqual(null, $lb->getItem(99), 'getItem(out-of-range) returns null');

// ---- addItem with Tcl-special characters round-trips literally --------------

$lb->addItem('with [brackets] $vars and "quotes"');
TestRunner::assertEqual(
    'with [brackets] $vars and "quotes"',
    $lb->getItem(3),
    'addItem preserves Tcl-special characters literally'
);

// ---- getAllItems ------------------------------------------------------------

TestRunner::assertEqual(
    ['alpha', 'beta', 'gamma', 'with [brackets] $vars and "quotes"'],
    $lb->getAllItems(),
    'getAllItems returns every item in order'
);

// ---- removeItem -------------------------------------------------------------

$lb->removeItem(1);
TestRunner::assertEqual(
    ['alpha', 'gamma', 'with [brackets] $vars and "quotes"'],
    $lb->getAllItems(),
    'removeItem(1) deletes "beta"'
);
$lb->removeItem(-1); // silently ignored
TestRunner::assertEqual(3, $lb->size(), 'removeItem with negative index is a no-op');

// ---- setItems replaces wholesale --------------------------------------------

$lb->setItems(['x', 'y', 'z']);
TestRunner::assertEqual(['x', 'y', 'z'], $lb->getAllItems(), 'setItems replaces all content');

// ---- clear ------------------------------------------------------------------

$lb->clear();
TestRunner::assertEqual(0, $lb->size(), 'clear() empties the listbox');
TestRunner::assertEqual([], $lb->getAllItems(), 'getAllItems is empty after clear');
TestRunner::assertEqual([], $lb->getSelectedIndices(), 'no selection on empty listbox');
TestRunner::assertEqual(null, $lb->getSelectedIndex(), 'getSelectedIndex returns null when empty');

// ---- Selection: single-select (browse) --------------------------------------

$lb->setItems(['one', 'two', 'three', 'four']);
$lb->setSelection([2]);
TestRunner::assertEqual([2], $lb->getSelectedIndices(), 'setSelection([2]) selects index 2');
TestRunner::assertEqual(2, $lb->getSelectedIndex(), 'getSelectedIndex returns 2');
TestRunner::assertEqual(['three'], $lb->getSelectedItems(), 'getSelectedItems returns matching strings');

// ---- Selection: multiple-select ---------------------------------------------

$lbm = new Listbox($wid, [
    'selectmode' => 'multiple',
    'items'      => ['a', 'b', 'c', 'd', 'e'],
]);
$lbm->setSelection([0, 2, 4]);
TestRunner::assertEqual([0, 2, 4], $lbm->getSelectedIndices(), 'multi-select holds 3 indices');
TestRunner::assertEqual(['a', 'c', 'e'], $lbm->getSelectedItems(), 'multi-select items match indices');

// out-of-range entries silently dropped
$lbm->setSelection([1, 99, -3, 3]);
TestRunner::assertEqual([1, 3], $lbm->getSelectedIndices(), 'setSelection drops out-of-range indices');

// empty array clears selection
$lbm->setSelection([]);
TestRunner::assertEqual([], $lbm->getSelectedIndices(), 'setSelection([]) clears the selection');

// ---- Invalid selectmode rejected --------------------------------------------

TestRunner::assertThrows(
    fn() => new Listbox($wid, ['selectmode' => 'wat']),
    \InvalidArgumentException::class,
    'unknown selectmode throws InvalidArgumentException'
);

// ---- onSelect fires the callback (via virtual event) ------------------------

$received = null;
$lb->onSelect(function (Listbox $l) use (&$received) {
    $received = $l->getSelectedIndices();
});

$lb->setSelection([1]);
// Programmatic selection doesn't fire <<ListboxSelect>>, so synthesize the
// event. The deferred-fire test exercises the same wiring a real click would.
$tcl->evalTcl("event generate {$path} <<ListboxSelect>>");
$tcl->drainPendingCallbacks();
TestRunner::assertEqual(
    [1],
    $received,
    'onSelect handler fires on <<ListboxSelect>> with current selection'
);

// ---- destroy() unregisters the select callback ------------------------------

$selectId = $lb->getId() . '_select';
TestRunner::assertEqual(
    '1',
    trim($tcl->evalTcl("info exists php::callbacks({$selectId})")),
    'select callback registered while widget alive'
);
$lb->destroy();
TestRunner::assertWidgetGone($path, 'Listbox gone after destroy()');
TestRunner::assertEqual(
    '0',
    trim($tcl->evalTcl("info exists php::callbacks({$selectId})")),
    'select callback freed by destroy()'
);

TestRunner::summary();
