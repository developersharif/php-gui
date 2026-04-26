<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpGui\Application;
use PhpGui\ProcessTCL;
use PhpGui\Widget\Window;
use PhpGui\Widget\Label;
use PhpGui\Widget\Button;
use PhpGui\Widget\Input;
use PhpGui\Widget\TopLevel;
use PhpGui\Widget\Menu;
use PhpGui\Widget\Image;
use PhpGui\Widget\Frame;

$app = new Application();

$window = new Window([
    'title'  => 'PHP GUI — Widget Showcase',
    'width'  => 920,
    'height' => 620,
]);
$wid = $window->getId();
$tcl = ProcessTCL::getInstance();

// Layout uses three section Frames, each owning its own pack-stacked
// children. Frames are placed on the window via grid:
//
//   row 0: title           (columnspan 3)
//   row 1: [inputs frame] [image frame] [dialogs frame]
//   row 2: status bar      (columnspan 3)
//
// Equal column weights with the `same` uniform group make all three
// section columns the same width so the sections sit balanced/centered.
// Row 1 also expands vertically so the sections occupy the body of the
// window rather than hugging the top.
foreach ([0, 1, 2] as $c) {
    $tcl->evalTcl("grid columnconfigure {$window->getTclPath()} {$c} -weight 1 -uniform sections");
}
$tcl->evalTcl("grid rowconfigure {$window->getTclPath()} 1 -weight 1");


// ---------- Title ------------------------------------------------------------

$title = new Label($wid, [
    'text' => 'PHP GUI — Widget Showcase',
    'font' => 'Helvetica 18 bold',
    'fg'   => '#1976D2',
]);
$title->grid(['row' => 0, 'column' => 0, 'columnspan' => 3, 'pady' => 12]);


// ---------- Status bar (declared early so callbacks can capture it) ----------

$status = new Label($wid, [
    'text'   => 'Ready — interact with any widget to see updates here.',
    'font'   => 'Arial 10 italic',
    'fg'     => '#444',
    'bg'     => '#f5f5f5',
    'relief' => 'sunken',
    'padx'   => 10,
    'pady'   => 6,
]);


// ---------- Helper: a section Frame with a bold header label inside ----------

$buildSection = function (string $headerText, int $col) use ($wid): Frame {
    $frame = new Frame($wid);
    // sticky 'n' (top, no horizontal stretch) keeps the frame compact and
    // centered inside its equally-weighted grid column.
    $frame->grid([
        'row'    => 1,
        'column' => $col,
        'sticky' => 'n',
        'padx'   => 10,
        'pady'   => 10,
    ]);

    $header = new Label($frame->getId(), [
        'text' => $headerText,
        'font' => 'Arial 12 bold',
        'fg'   => '#333',
    ]);
    $header->pack(['pady' => 6]);

    return $frame;
};


// ---------- Section: Buttons & Inputs ----------------------------------------

$inputs = $buildSection('Buttons & Inputs', 0);

$styledButton = new Button($inputs->getId(), [
    'text'    => 'Styled Button',
    'bg'      => '#1976D2',
    'fg'      => 'white',
    'font'    => 'Helvetica 12 bold',
    'command' => fn() => $status->setText('Styled Button clicked!'),
]);
$styledButton->pack(['pady' => 4, 'fill' => 'x', 'padx' => 12]);

$input = new Input($inputs->getId(), [
    'text' => 'Type and press Enter…',
    'bg'   => 'lightyellow',
    'font' => 'Arial 12',
]);
$input->pack(['pady' => 4, 'fill' => 'x', 'padx' => 12]);
$input->onEnter(fn() => $status->setText('Input received: ' . $input->getValue()));

$styledLabel = new Label($inputs->getId(), [
    'text'   => 'Styled label',
    'fg'     => 'white',
    'bg'     => '#4CAF50',
    'font'   => 'Arial 11',
    'padx'   => 10,
    'pady'   => 5,
    'relief' => 'raised',
]);
$styledLabel->pack(['pady' => 4, 'fill' => 'x', 'padx' => 12]);

$updateButton = new Button($inputs->getId(), [
    'text'    => 'Update Labels',
    'command' => function () use ($styledLabel, $status) {
        $styledLabel->setBackground('#2196F3');
        $styledLabel->setText('Colors and text updated dynamically');
        $status->setText('Labels updated.');
    },
]);
$updateButton->pack(['pady' => 4, 'fill' => 'x', 'padx' => 12]);


// ---------- Section: Image ---------------------------------------------------

$imageSection = $buildSection('Image (animated GIF)', 1);

$gifPath = __DIR__ . '/assets/happy-cat.gif';
$jpgPath = __DIR__ . '/tests/widgets_test/image/example.jpg';
$pngPath = __DIR__ . '/assets/example.png';
$initialPath = is_file($gifPath) ? $gifPath : $pngPath;

$logo = new Image($imageSection->getId(), [
    'path'   => $initialPath,
    'relief' => 'sunken',
    'padx'   => 4,
    'pady'   => 4,
]);
$logo->pack(['pady' => 6]);

$logoInfo = new Label($imageSection->getId(), [
    'text' => $logo->isAnimated()
        ? sprintf('Animated — %d frames', $logo->getFrameCount())
        : 'Static image',
    'font' => 'Arial 10',
    'fg'   => '#666',
]);
$logoInfo->pack(['pady' => 2]);

$swapImageButton = new Button($imageSection->getId(), [
    'text'    => 'Swap to JPG',
    'command' => function () use ($logo, $logoInfo, $jpgPath, $status) {
        if (!is_file($jpgPath)) {
            $status->setText('Skipped — JPG fixture not found.');
            return;
        }
        $logo->setPath($jpgPath);
        $logoInfo->setText('Static image (transcoded via GD)');
        $status->setText("Swapped to JPG — {$logo->getWidth()}x{$logo->getHeight()}");
    },
]);
$swapImageButton->pack(['pady' => 4, 'fill' => 'x', 'padx' => 12]);

$restoreImageButton = new Button($imageSection->getId(), [
    'text'    => 'Restore animated GIF',
    'command' => function () use ($logo, $logoInfo, $gifPath, $status) {
        if (!is_file($gifPath)) {
            $status->setText('Skipped — GIF asset not found.');
            return;
        }
        $logo->setPath($gifPath);
        $logoInfo->setText(sprintf('Animated — %d frames', $logo->getFrameCount()));
        $status->setText('Animation restored.');
    },
]);
$restoreImageButton->pack(['pady' => 4, 'fill' => 'x', 'padx' => 12]);


// ---------- Section: Dialogs & Windows ---------------------------------------

$dialogs = $buildSection('Dialogs & Windows', 2);

$colorButton = new Button($dialogs->getId(), [
    'text'    => 'Choose Color',
    'command' => function () use ($status) {
        try {
            $color = TopLevel::chooseColor();
            if ($color) {
                $status->setText("Selected color: {$color}");
                $status->setForeground($color);
            }
        } catch (\Exception $e) {
            $status->setText('Error: ' . $e->getMessage());
        }
    },
]);
$colorButton->pack(['pady' => 4, 'fill' => 'x', 'padx' => 12]);

$fileButton = new Button($dialogs->getId(), [
    'text'    => 'Open File',
    'command' => function () use ($status) {
        $file = TopLevel::getOpenFile();
        if ($file) {
            $status->setText('Selected file: ' . basename($file));
        }
    },
]);
$fileButton->pack(['pady' => 4, 'fill' => 'x', 'padx' => 12]);

$dirButton = new Button($dialogs->getId(), [
    'text'    => 'Choose Directory',
    'command' => function () use ($status) {
        try {
            $dir = TopLevel::chooseDirectory();
            if ($dir) {
                $status->setText('Selected directory: ' . basename($dir));
            }
        } catch (\Exception $e) {
            $status->setText('Error: ' . $e->getMessage());
        }
    },
]);
$dirButton->pack(['pady' => 4, 'fill' => 'x', 'padx' => 12]);

$msgButton = new Button($dialogs->getId(), [
    'text'    => 'Show Message',
    'command' => function () use ($status) {
        $result = TopLevel::messageBox('This is a test message', 'okcancel');
        $status->setText("Message result: {$result}");
    },
]);
$msgButton->pack(['pady' => 4, 'fill' => 'x', 'padx' => 12]);

$topLevelButton = new Button($dialogs->getId(), [
    'text'    => 'Open New Window',
    'command' => function () use ($status) {
        $top = new TopLevel([
            'title'  => 'Secondary Window',
            'width'  => 320,
            'height' => 180,
        ]);

        $msg = new Label($top->getId(), [
            'text' => 'This is a separate window.',
            'font' => 'Arial 12',
        ]);
        $msg->pack(['pady' => 20]);

        $closeBtn = new Button($top->getId(), [
            'text'    => 'Close',
            'command' => function () use ($top, $status) {
                $top->destroy();
                $status->setText('Secondary window closed.');
            },
        ]);
        $closeBtn->pack(['pady' => 8]);

        $minBtn = new Button($top->getId(), [
            'text'    => 'Minimize',
            'command' => fn() => $top->iconify(),
        ]);
        $minBtn->pack(['pady' => 4]);

        $status->setText('Secondary window opened.');
    },
]);
$topLevelButton->pack(['pady' => 4, 'fill' => 'x', 'padx' => 12]);


// ---------- Status row at the bottom -----------------------------------------

$status->grid([
    'row'        => 2,
    'column'     => 0,
    'columnspan' => 3,
    'sticky'     => 'ew',
    'padx'       => 10,
    'pady'       => 10,
]);


// ---------- Menu bar ---------------------------------------------------------

$menu = new Menu($wid, ['type' => 'main']);

$fileMenu = $menu->addSubmenu('File');
$fileMenu->addCommand('New',  fn() => $status->setText('Menu: File → New'));
$fileMenu->addCommand('Open', fn() => $status->setText('Menu: File → Open'));
$fileMenu->addSeparator();
$fileMenu->addCommand('Exit', fn() => exit(), ['foreground' => 'red']);

$editMenu = $menu->addSubmenu('Edit');
$editMenu->addCommand('Copy',  fn() => $status->setText('Menu: Edit → Copy'));
$editMenu->addCommand('Paste', fn() => $status->setText('Menu: Edit → Paste'));

$helpMenu  = $menu->addSubmenu('Help');
$aboutMenu = $helpMenu->addSubmenu('About');
$aboutMenu->addCommand('Version', fn() => $status->setText('php-gui — version 1.0'));


$app->run();
