<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../TestRunner.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGui\Widget\Image;
use PhpGuiTest\TestRunner;

$app = new Application();
TestRunner::suite('ImageTest');

$fixturesDir = __DIR__ . '/image';
$pngPath = $fixturesDir . '/test.png';
$gifPath = $fixturesDir . '/test.gif';
$jpgPath = $fixturesDir . '/test.jpg';
$animGifPath = $fixturesDir . '/animated.gif';

if (!is_file($pngPath) || !is_file($gifPath)) {
    echo "  [FAIL] fixture images missing under {$fixturesDir}\n";
    exit(1);
}

$window = new Window(['title' => 'Image Test']);
$wid = $window->getId();
$tcl = ProcessTCL::getInstance();

// Constructor: missing path
TestRunner::assertThrows(
    fn() => new Image($wid, []),
    \InvalidArgumentException::class,
    'Constructor without "path" throws InvalidArgumentException'
);

// Constructor: nonexistent file
TestRunner::assertThrows(
    fn() => new Image($wid, ['path' => $fixturesDir . '/does_not_exist.png']),
    \RuntimeException::class,
    'Constructor with missing file throws RuntimeException'
);

// JPG support via GD transcode (Tk core can't load JPEG directly)
if (is_file($jpgPath) && function_exists('imagecreatefromstring')) {
    $jpgImage = new Image($wid, ['path' => $jpgPath]);
    $jpgPathOut = ".{$wid}.{$jpgImage->getId()}";
    TestRunner::assertWidgetExists($jpgPathOut, 'JPG image label exists in Tcl (via GD transcode)');
    TestRunner::assertEqual($jpgPath, $jpgImage->getPath(), 'getPath() returns the original JPG path, not the temp PNG');
    TestRunner::assert($jpgImage->getWidth() > 0,  'JPG image reports a non-zero width');
    TestRunner::assert($jpgImage->getHeight() > 0, 'JPG image reports a non-zero height');

    // The transcoded temp PNG should exist on disk while the widget lives,
    // and disappear when destroy() is called.
    $tempsBefore = glob(sys_get_temp_dir() . '/phpgui_img_*.png') ?: [];
    TestRunner::assert(count($tempsBefore) >= 1, 'GD transcode produced a temp PNG on disk');

    $jpgImage->destroy();
    TestRunner::assertWidgetGone($jpgPathOut, 'JPG image label gone after destroy()');
    $tempsAfter = glob(sys_get_temp_dir() . '/phpgui_img_*.png') ?: [];
    TestRunner::assert(count($tempsAfter) < count($tempsBefore), 'destroy() unlinks the transcoded temp PNG');
}

// Constructor: unsupported extension
$bogus = $fixturesDir . '/bogus.xyz';
file_put_contents($bogus, 'x');
TestRunner::assertThrows(
    fn() => new Image($wid, ['path' => $bogus]),
    \RuntimeException::class,
    'Constructor with unsupported extension throws RuntimeException'
);
@unlink($bogus);

// PNG creation
$image = new Image($wid, ['path' => $pngPath]);
$path  = ".{$wid}.{$image->getId()}";
$photo = 'phpgui_photo_' . $image->getId();

TestRunner::assertWidgetExists($path, 'PNG image label exists in Tcl');
TestRunner::assertEqual($pngPath, $image->getPath(), 'getPath() returns resolved PNG path');
TestRunner::assertEqual('1', trim($tcl->evalTcl("image inuse {$photo}")), 'photo image is in use by the label');
TestRunner::assertEqual(1, $image->getWidth(),  'getWidth() reports fixture PNG width');
TestRunner::assertEqual(1, $image->getHeight(), 'getHeight() reports fixture PNG height');

// Confirm the label's -image points at our photo (not e.g. a default).
$boundImage = trim($tcl->evalTcl("{$path} cget -image"));
TestRunner::assertEqual($photo, $boundImage, 'label -image is bound to our photo image');

// pack works through the inherited geometry manager
TestRunner::assertNoThrow(fn() => $image->pack(['pady' => 5]), 'pack() does not throw');

// Swap to a GIF: pixels reload, label keeps the same widget identity.
TestRunner::assertNoThrow(fn() => $image->setPath($gifPath), 'setPath() to GIF does not throw');
TestRunner::assertEqual($gifPath, $image->getPath(), 'getPath() reflects the new path');
TestRunner::assertWidgetExists($path, 'image label still exists after setPath()');

// setPath to a missing file should throw and leave state unchanged.
TestRunner::assertThrows(
    fn() => $image->setPath($fixturesDir . '/missing.png'),
    \RuntimeException::class,
    'setPath() with missing file throws RuntimeException'
);
TestRunner::assertEqual($gifPath, $image->getPath(), 'getPath() unchanged after failed setPath()');

// Path safety: a filename with spaces must work without breaking Tcl parsing.
$spacedPath = $fixturesDir . '/has space.png';
copy($pngPath, $spacedPath);
TestRunner::assertNoThrow(
    fn() => new Image($wid, ['path' => $spacedPath]),
    'Image with a space in its filename loads without Tcl parse error'
);
@unlink($spacedPath);

// Single-frame GIF: parser sees 1 frame, isAnimated() is false, no after token registered.
$singleGif = new Image($wid, ['path' => $gifPath]);
TestRunner::assertEqual(1, $singleGif->getFrameCount(), 'single-frame GIF reports frameCount=1');
TestRunner::assert(!$singleGif->isAnimated(), 'single-frame GIF is not animated');
$singleAfter = trim($tcl->evalTcl("info exists ::phpgui_anim_after(phpgui_photo_{$singleGif->getId()})"));
TestRunner::assertEqual('0', $singleAfter, 'single-frame GIF schedules no after callback');
$singleGif->destroy();

// Animated GIF: 4 frames, animation loop scheduled, cancelled cleanly on destroy.
if (is_file($animGifPath)) {
    $anim = new Image($wid, ['path' => $animGifPath]);
    $animPhoto = 'phpgui_photo_' . $anim->getId();
    TestRunner::assertEqual(4, $anim->getFrameCount(), 'animated GIF reports frameCount=4');
    TestRunner::assert($anim->isAnimated(), 'animated GIF reports isAnimated() = true');

    $hasAfter = trim($tcl->evalTcl("info exists ::phpgui_anim_after({$animPhoto})"));
    TestRunner::assertEqual('1', $hasAfter, 'animated GIF schedules an after callback');

    // Drive the Tk event loop briefly so a frame transition actually happens.
    $tcl->evalTcl('after 250 set ::phpgui_test_done 1; vwait ::phpgui_test_done');
    TestRunner::assertWidgetExists(".{$wid}.{$anim->getId()}", 'animated GIF widget still alive after event-loop tick');

    // setPath() to a non-GIF must stop the animation.
    $anim->setPath($pngPath);
    $stillScheduled = trim($tcl->evalTcl("info exists ::phpgui_anim_after({$animPhoto})"));
    TestRunner::assertEqual('0', $stillScheduled, 'setPath() to non-GIF cancels the animation');
    TestRunner::assert(!$anim->isAnimated(), 'isAnimated() flips to false after swapping to PNG');

    // Swap back to animated, then destroy: after token must be cleared.
    $anim->setPath($animGifPath);
    TestRunner::assertEqual('1', trim($tcl->evalTcl("info exists ::phpgui_anim_after({$animPhoto})")),
        'after token re-registered when swapping back to animated GIF');
    $anim->destroy();
    $afterDestroy = trim($tcl->evalTcl("info exists ::phpgui_anim_after({$animPhoto})"));
    TestRunner::assertEqual('0', $afterDestroy, 'destroy() cancels the animation');
}

// Destroy: both label and photo image must go away.
$image->destroy();
TestRunner::assertWidgetGone($path, 'image label gone after destroy()');
$exists = trim($tcl->evalTcl("lsearch [image names] {$photo}"));
TestRunner::assertEqual('-1', $exists, 'photo image deleted from Tk image table after destroy()');

TestRunner::summary();
