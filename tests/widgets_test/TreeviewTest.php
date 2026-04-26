<?php
/**
 * Phase 2.9 — Treeview widget regression tests.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGui\Widget\Treeview;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('TreeviewTest');

$tcl    = ProcessTCL::getInstance();
$window = new Window(['title' => 'Treeview Test', 'width' => 600, 'height' => 400]);
$wid    = $window->getId();

// ---- Flat table mode (columns + headings) -----------------------------------

$tv = new Treeview($wid, [
    'columns'  => ['name', 'size', 'modified'],
    'headings' => ['Name', 'Size', 'Modified'],
    'show'     => 'headings',
]);
$path = ".{$wid}.{$tv->getId()}";
TestRunner::assertWidgetExists($path, 'Treeview exists in Tcl');
TestRunner::assertEqual(0, $tv->getTopLevelCount(), 'fresh Treeview is empty');

// ---- Insert positional values, get them back -------------------------------

$row1 = $tv->insert(null, ['report.pdf', '1.2MB', '2026-01-15']);
TestRunner::assert(is_string($row1) && $row1 !== '', 'insert returns a non-empty row id');
TestRunner::assertEqual(1, $tv->getTopLevelCount(), 'one row at top level');

TestRunner::assertEqual('report.pdf',  $tv->getValue($row1, 'name'),     'getValue reads column 1');
TestRunner::assertEqual('1.2MB',       $tv->getValue($row1, 'size'),     'getValue reads column 2');
TestRunner::assertEqual('2026-01-15',  $tv->getValue($row1, 'modified'), 'getValue reads column 3');

TestRunner::assertEqual(
    ['name' => 'report.pdf', 'size' => '1.2MB', 'modified' => '2026-01-15'],
    $tv->getValues($row1),
    'getValues returns the full row keyed by column'
);

// ---- Insert keyed values ---------------------------------------------------

$row2 = $tv->insert(null, ['name' => 'photo.png', 'size' => '4.7MB', 'modified' => '2026-02-01']);
TestRunner::assertEqual('photo.png', $tv->getValue($row2, 'name'), 'keyed insert preserves column mapping');
TestRunner::assertEqual('4.7MB',     $tv->getValue($row2, 'size'), 'keyed insert preserves column mapping');

// ---- Tcl-special chars round-trip literally --------------------------------

$evil = $tv->insert(null, ['name' => 'evil"; destroy .; "', 'size' => '0', 'modified' => '$now']);
TestRunner::assertWidgetExists(".{$wid}", 'window survives Treeview value injection');
TestRunner::assertEqual(
    'evil"; destroy .; "',
    $tv->getValue($evil, 'name'),
    'value with Tcl-special chars round-trips literally'
);
TestRunner::assertEqual('$now', $tv->getValue($evil, 'modified'), 'dollar-prefixed value preserved literally');

// ---- setValue / setValues ---------------------------------------------------

$tv->setValue($row1, 'size', '2.4MB');
TestRunner::assertEqual('2.4MB', $tv->getValue($row1, 'size'), 'setValue updates a column');

$tv->setValues($row2, ['picture.jpg', '5.0MB', '2026-02-10']);
TestRunner::assertEqual('picture.jpg', $tv->getValue($row2, 'name'), 'setValues positional updates name');
TestRunner::assertEqual('5.0MB',       $tv->getValue($row2, 'size'), 'setValues positional updates size');

$tv->setValues($row1, ['name' => 'final.pdf']);
TestRunner::assertEqual('final.pdf', $tv->getValue($row1, 'name'), 'setValues keyed updates only what is provided');
// columns omitted from the keyed array become empty per orderValues()
TestRunner::assertEqual('', $tv->getValue($row1, 'size'), 'omitted keyed columns clear to empty');

// ---- Unknown column rejected -----------------------------------------------

TestRunner::assertThrows(
    fn() => $tv->getValue($row1, 'no_such_column'),
    \InvalidArgumentException::class,
    'getValue rejects unknown column'
);
TestRunner::assertThrows(
    fn() => $tv->setValue($row1, 'no_such_column', 'x'),
    \InvalidArgumentException::class,
    'setValue rejects unknown column'
);

// ---- Hierarchical mode ------------------------------------------------------

$tree = new Treeview($wid, ['show' => 'tree']);
$root  = $tree->insert(null, [], ['text' => 'project']);
$src   = $tree->insert($root, [], ['text' => 'src']);
$tests = $tree->insert($root, [], ['text' => 'tests']);
$file1 = $tree->insert($src, [], ['text' => 'main.php']);

TestRunner::assertEqual('project',  $tree->getText($root),  'tree-column text round-trips for parent');
TestRunner::assertEqual('main.php', $tree->getText($file1), 'tree-column text round-trips for nested child');

// Tk reports the parent of file1 as src
$reportedParent = trim($tcl->evalTcl("{$tree->getTclPath()} parent " . $file1));
TestRunner::assertEqual($src, $reportedParent, 'Tk reports correct parent for nested row');

// ---- exists / delete --------------------------------------------------------

TestRunner::assert($tree->exists($file1), 'exists() reports true for a known row');
$tree->delete($file1);
TestRunner::assert(!$tree->exists($file1), 'exists() reports false after delete');

// Deleting a parent cascades to children
$tree->delete($root);
TestRunner::assert(!$tree->exists($src),   'descendant src gone after parent delete');
TestRunner::assert(!$tree->exists($tests), 'descendant tests gone after parent delete');

// ---- Selection --------------------------------------------------------------

TestRunner::assertEqual([], $tv->getSelected(), 'empty selection by default');
TestRunner::assertEqual(null, $tv->getSelectedRow(), 'getSelectedRow null when empty');

$tv->setSelected([$row1]);
TestRunner::assertEqual([$row1], $tv->getSelected(), 'setSelected replaces with single row');
TestRunner::assertEqual($row1, $tv->getSelectedRow(), 'getSelectedRow returns first id');

$tv->setSelected([$row1, $row2]);
TestRunner::assertEqual(2, count($tv->getSelected()), 'multi-row selection has 2 entries');

$tv->setSelected([]);
TestRunner::assertEqual([], $tv->getSelected(), 'setSelected([]) clears the selection');

// ---- onSelect virtual-event wiring -----------------------------------------

$received = null;
$tv->onSelect(function (array $rows) use (&$received) {
    $received = $rows;
});

$tv->setSelected([$row2]);
$tcl->evalTcl('update');
$tcl->drainPendingCallbacks();
TestRunner::assertEqual([$row2], $received, 'onSelect handler receives the selected ids');

// ---- clear ------------------------------------------------------------------

$beforeClear = $tv->getTopLevelCount();
TestRunner::assert($beforeClear > 0, 'something was in the tree before clear');
$tv->clear();
TestRunner::assertEqual(0, $tv->getTopLevelCount(), 'clear() removes every top-level row');

// ---- Column / heading config -----------------------------------------------

TestRunner::assertNoThrow(
    fn() => $tv->setColumn('name', ['width' => 200, 'anchor' => 'w']),
    'setColumn does not throw with valid options'
);
TestRunner::assertThrows(
    fn() => $tv->setColumn('no_such', ['width' => 100]),
    \InvalidArgumentException::class,
    'setColumn rejects unknown column'
);
TestRunner::assertNoThrow(
    fn() => $tv->setHeading('size', 'Bytes'),
    'setHeading does not throw'
);

// ---- destroy() unregisters callbacks ---------------------------------------

$selectId = $tv->getId() . '_select';
TestRunner::assertEqual(
    '1',
    trim($tcl->evalTcl("info exists php::callbacks({$selectId})")),
    'select callback registered'
);
$tv->destroy();
TestRunner::assertWidgetGone($path, 'Treeview gone after destroy()');
TestRunner::assertEqual(
    '0',
    trim($tcl->evalTcl("info exists php::callbacks({$selectId})")),
    'select callback freed by destroy()'
);

TestRunner::summary();
