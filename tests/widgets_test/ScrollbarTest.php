<?php
/**
 * Phase 2.2 — Scrollbar widget regression tests.
 *
 * Covers manual `bindTo`, the `attachTo` factory, both orientations, and
 * the wiring contract that a scrolled-target's xscrollcommand/yscrollcommand
 * actually points back at the scrollbar (so the slider thumb tracks the
 * content).
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGui\Widget\Frame;
use PhpGui\Widget\Text;
use PhpGui\Widget\Scrollbar;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('ScrollbarTest');

$tcl    = ProcessTCL::getInstance();
$window = new Window(['title' => 'Scrollbar Test', 'width' => 400, 'height' => 300]);
$wid    = $window->getId();

// ---- Vertical creation ------------------------------------------------------

$sb = new Scrollbar($wid, ['orient' => 'vertical']);
$path = ".{$wid}.{$sb->getId()}";
TestRunner::assertWidgetExists($path, 'vertical Scrollbar exists in Tcl');
TestRunner::assertEqual('vertical', $sb->getOrient(), 'vertical orient reported');
TestRunner::assertEqual(
    'vertical',
    trim($tcl->evalTcl("{$path} cget -orient")),
    'Tk reports orient = vertical'
);

// ---- Horizontal creation ----------------------------------------------------

$sbH = new Scrollbar($wid, ['orient' => 'horizontal']);
TestRunner::assertEqual('horizontal', $sbH->getOrient(), 'horizontal orient reported');

// ---- Invalid orient throws --------------------------------------------------

TestRunner::assertThrows(
    fn() => new Scrollbar($wid, ['orient' => 'sideways']),
    \InvalidArgumentException::class,
    'Scrollbar rejects unknown orient values'
);

// ---- Manual bindTo wires both directions ------------------------------------

$frame = new Frame($wid);
$frame->pack(['fill' => 'both', 'expand' => 1]);

$text = new Text($frame->getId(), ['width' => 30, 'height' => 8]);
$text->pack(['side' => 'left', 'fill' => 'both', 'expand' => 1]);

$sbBind = new Scrollbar($frame->getId(), ['orient' => 'vertical']);
$sbBind->pack(['side' => 'right', 'fill' => 'y']);
$sbBind->bindTo($text);

$cmdAfter = trim($tcl->evalTcl("{$sbBind->getTclPath()} cget -command"));
TestRunner::assertEqual(
    "{$text->getTclPath()} yview",
    $cmdAfter,
    "Scrollbar's -command points at target's yview"
);

$yScroll = trim($tcl->evalTcl("{$text->getTclPath()} cget -yscrollcommand"));
TestRunner::assertEqual(
    "{$sbBind->getTclPath()} set",
    $yScroll,
    "target's -yscrollcommand points at scrollbar's set"
);

// Drive scrolling: insert enough content to make the slider non-full, then
// have Tk report a get-range. After yview-moveto 0.5 the first fraction must
// be greater than 0.
$text->setText(str_repeat("line\n", 100));
$tcl->evalTcl("{$text->getTclPath()} yview moveto 0.5");
$tcl->evalTcl('update idletasks');
$range = explode(' ', trim($tcl->evalTcl("{$text->getTclPath()} yview")));
TestRunner::assert(
    is_array($range) && count($range) === 2 && (float) $range[0] > 0.0,
    'after yview moveto 0.5 the first fraction is > 0 (target is actually scrolled)'
);

// ---- attachTo factory wires + packs in one call -----------------------------

$frame2 = new Frame($wid);
$frame2->pack(['fill' => 'both', 'expand' => 1, 'pady' => 4]);

$text2 = new Text($frame2->getId(), ['width' => 30, 'height' => 6]);
$text2->pack(['side' => 'left', 'fill' => 'both', 'expand' => 1]);

$attached = Scrollbar::attachTo($text2, 'vertical');
TestRunner::assertWidgetExists($attached->getTclPath(), 'attachTo creates a Scrollbar widget');
TestRunner::assertEqual(
    "{$text2->getTclPath()} yview",
    trim($tcl->evalTcl("{$attached->getTclPath()} cget -command")),
    'attachTo wires the scrollbar -command to target yview'
);
TestRunner::assertEqual(
    "{$attached->getTclPath()} set",
    trim($tcl->evalTcl("{$text2->getTclPath()} cget -yscrollcommand")),
    'attachTo wires the target -yscrollcommand back to scrollbar'
);
TestRunner::assertEqual(
    'vertical',
    $attached->getOrient(),
    'attachTo creates the requested orientation'
);

// ---- Horizontal attachTo wires xview ----------------------------------------

$frame3 = new Frame($wid);
$frame3->pack(['fill' => 'both', 'expand' => 1]);
$text3 = new Text($frame3->getId(), ['width' => 30, 'height' => 4, 'wrap' => 'none']);
$text3->pack(['side' => 'top', 'fill' => 'both', 'expand' => 1]);
$hsb = Scrollbar::attachTo($text3, 'horizontal');
TestRunner::assertEqual(
    "{$text3->getTclPath()} xview",
    trim($tcl->evalTcl("{$hsb->getTclPath()} cget -command")),
    'horizontal attachTo wires xview, not yview'
);
TestRunner::assertEqual(
    "{$hsb->getTclPath()} set",
    trim($tcl->evalTcl("{$text3->getTclPath()} cget -xscrollcommand")),
    'horizontal attachTo wires -xscrollcommand'
);

// ---- attachTo refuses to wrap a top-level widget ----------------------------

TestRunner::assertThrows(
    fn() => Scrollbar::attachTo($window, 'vertical'),
    \InvalidArgumentException::class,
    'attachTo rejects a top-level widget (no parent to scroll inside)'
);

// ---- destroy cleans up ------------------------------------------------------

$sb->destroy();
TestRunner::assertWidgetGone($path, 'Scrollbar gone after destroy()');

TestRunner::summary();
