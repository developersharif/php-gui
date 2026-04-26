<?php
/**
 * Phase 2.8 — Notebook widget regression tests.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGui\Widget\Frame;
use PhpGui\Widget\Notebook;
use PhpGui\Widget\Label;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('NotebookTest');

$tcl    = ProcessTCL::getInstance();
$window = new Window(['title' => 'Notebook Test', 'width' => 400, 'height' => 300]);
$wid    = $window->getId();

// ---- Creation ---------------------------------------------------------------

$nb = new Notebook($wid);
$path = ".{$wid}.{$nb->getId()}";
TestRunner::assertWidgetExists($path, 'Notebook exists in Tcl');
TestRunner::assertEqual(0, $nb->getTabCount(), 'fresh Notebook has no tabs');
TestRunner::assertEqual(-1, $nb->getSelectedIndex(), 'getSelectedIndex returns -1 when empty');
TestRunner::assertEqual(null, $nb->getSelectedPage(), 'getSelectedPage returns null when empty');

$nb->pack(['fill' => 'both', 'expand' => 1]);

// ---- Add three tabs ---------------------------------------------------------

$page1 = new Frame($nb->getId());
$page2 = new Frame($nb->getId());
$page3 = new Frame($nb->getId());

$nb->addTab($page1, 'General');
$nb->addTab($page2, 'Advanced');
$nb->addTab($page3, 'About');

TestRunner::assertEqual(3, $nb->getTabCount(), 'three tabs added');
TestRunner::assertEqual('General',  $nb->getTabTitle(0), 'tab 0 title');
TestRunner::assertEqual('Advanced', $nb->getTabTitle(1), 'tab 1 title');
TestRunner::assertEqual('About',    $nb->getTabTitle(2), 'tab 2 title');

// First tab is auto-selected
TestRunner::assertEqual(0, $nb->getSelectedIndex(), 'first tab auto-selected');
TestRunner::assert($nb->getSelectedPage() === $page1, 'selected page is page1');

// ---- selectTab / selectPage -------------------------------------------------

$nb->selectTab(2);
TestRunner::assertEqual(2, $nb->getSelectedIndex(), 'selectTab(2) selects tab 2');
TestRunner::assert($nb->getSelectedPage() === $page3, 'selected page is now page3');

$nb->selectPage($page2);
TestRunner::assertEqual(1, $nb->getSelectedIndex(), 'selectPage moves to that page');

// ---- Out-of-range select throws --------------------------------------------

TestRunner::assertThrows(
    fn() => $nb->selectTab(99),
    \OutOfRangeException::class,
    'selectTab out of range throws OutOfRangeException'
);

TestRunner::assertThrows(
    fn() => $nb->selectTab(-1),
    \OutOfRangeException::class,
    'selectTab negative index throws OutOfRangeException'
);

// ---- Tab title with Tcl-special characters round-trips literally -----------

$page4 = new Frame($nb->getId());
$nb->addTab($page4, 'Hello"; destroy .; "');
TestRunner::assertWidgetExists(".{$wid}", 'window survives tab title injection');
TestRunner::assertEqual(
    'Hello"; destroy .; "',
    $nb->getTabTitle(3),
    'tab title with Tcl-special chars round-trips literally'
);

// ---- setTabTitle ------------------------------------------------------------

$nb->setTabTitle(3, 'Renamed');
TestRunner::assertEqual('Renamed', $nb->getTabTitle(3), 'setTabTitle updates the title');

// ---- addTab rejects pages that aren't children of the notebook -------------

$strayWidget = new Label($wid, ['text' => 'stray']);
TestRunner::assertThrows(
    fn() => $nb->addTab($strayWidget, 'Bad'),
    \InvalidArgumentException::class,
    'addTab rejects a widget whose parent is not the Notebook'
);

// ---- selectPage rejects unknown widget --------------------------------------

$other = new Frame($nb->getId());
TestRunner::assertThrows(
    fn() => $nb->selectPage($other),
    \InvalidArgumentException::class,
    'selectPage rejects a widget that is not a tab in this Notebook'
);

// ---- setTabState validation -------------------------------------------------

TestRunner::assertNoThrow(
    fn() => $nb->setTabState(2, 'disabled'),
    'setTabState accepts "disabled"'
);
TestRunner::assertThrows(
    fn() => $nb->setTabState(2, 'weird'),
    \InvalidArgumentException::class,
    'setTabState rejects unknown state'
);

// ---- removeTab --------------------------------------------------------------

$beforeCount = $nb->getTabCount();
$nb->removeTab(0);
TestRunner::assertEqual($beforeCount - 1, $nb->getTabCount(), 'removeTab decreases tab count');
// page1 was removed; the new tab 0 should be page2
TestRunner::assertEqual('Advanced', $nb->getTabTitle(0), 'tab 0 is now what was tab 1');

// ---- onTabChange fires on selection change ----------------------------------

$received = [];
$nb->onTabChange(function (int $idx) use (&$received) {
    $received[] = $idx;
});

// Programmatic selectTab triggers <<NotebookTabChanged>>, but Tk only
// dispatches virtual events while the event loop is pumping — give it a
// kick before draining the PHP callback queue.
$nb->selectTab(2);
$tcl->evalTcl('update');
$tcl->drainPendingCallbacks();
TestRunner::assert(
    in_array(2, $received, true),
    'onTabChange fires when selectTab changes the active tab'
);

// ---- destroy() unregisters callback -----------------------------------------

$cbId = $nb->getId() . '_tab_change';
TestRunner::assertEqual(
    '1',
    trim($tcl->evalTcl("info exists php::callbacks({$cbId})")),
    'tab-change callback registered while alive'
);
$nb->destroy();
TestRunner::assertWidgetGone($path, 'Notebook gone after destroy()');
TestRunner::assertEqual(
    '0',
    trim($tcl->evalTcl("info exists php::callbacks({$cbId})")),
    'tab-change callback freed by destroy()'
);

TestRunner::summary();
