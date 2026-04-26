<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Label;
use PhpGui\Widget\Button;
use PhpGui\Widget\Input;
use PhpGui\Widget\Checkbutton;
use PhpGui\Widget\Combobox;
use PhpGui\Widget\TopLevel;
use PhpGui\Widget\Menu;
use PhpGui\Widget\Image;
use PhpGui\Widget\Frame;
// New in v1.9:
use PhpGui\Widget\Notebook;
use PhpGui\Widget\Text;
use PhpGui\Widget\Scrollbar;
use PhpGui\Widget\Listbox;
use PhpGui\Widget\Treeview;
use PhpGui\Widget\Scale;
use PhpGui\Widget\Spinbox;
use PhpGui\Widget\Progressbar;
use PhpGui\Widget\PanedWindow;
use PhpGui\Widget\LabelFrame;
use PhpGui\Widget\Separator;
use PhpGui\Widget\RadioGroup;
use PhpGui\Widget\Radiobutton;

$app    = new Application();
$window = new Window([
    'title'  => 'PHP GUI v1.9 — Widget Showcase',
    'width'  => 880,
    'height' => 580,
]);
$wid = $window->getId();

// Status bar lives at the bottom of the window; declared first so every
// callback below can capture it.
$status = new Label($wid, [
    'text'   => 'Ready — switch tabs to explore the v1.9 widgets.',
    'font'   => 'Arial 10 italic',
    'fg'     => '#444',
    'bg'     => '#f5f5f5',
    'relief' => 'sunken',
    'padx'   => 10,
    'pady'   => 6,
]);
$status->pack(['side' => 'bottom', 'fill' => 'x']);

// Notebook organises every widget category into its own tab.
$nb = new Notebook($wid);
$nb->pack(['fill' => 'both', 'expand' => 1, 'padx' => 8, 'pady' => 8]);


// =============================================================================
//  Tab 1 — Basics: Button, Input, Checkbutton, Combobox, Image
// =============================================================================

$basics = new Frame($nb->getId());
$nb->addTab($basics, 'Basics');

$btn = new Button($basics->getId(), [
    'text'    => 'Click me',
    'bg'      => '#1976D2',
    'fg'      => 'white',
    'command' => fn() => $status->setText('Button clicked!'),
]);
$btn->pack(['pady' => 6, 'padx' => 12, 'anchor' => 'w']);

$input = new Input($basics->getId(), ['text' => 'Type and press Enter…']);
$input->pack(['pady' => 4, 'padx' => 12, 'fill' => 'x']);
$input->onEnter(fn() => $status->setText('Input: ' . $input->getValue()));

$check = new Checkbutton($basics->getId(), [
    'text'    => 'Send me product updates',
    'command' => fn() => $status->setText('Checkbox toggled'),
]);
$check->pack(['pady' => 4, 'padx' => 12, 'anchor' => 'w']);

$combo = new Combobox($basics->getId(), [
    'values' => ['Apple', 'Banana', 'Cherry', 'Durian'],
]);
$combo->setValue('Apple');
$combo->pack(['pady' => 4, 'padx' => 12, 'fill' => 'x']);

(new Separator($basics->getId()))->pack(['fill' => 'x', 'pady' => 8, 'padx' => 12]);

// Image: prefer the animated GIF, fall back to a PNG if not present.
$gifPath = __DIR__ . '/assets/happy-cat.gif';
$pngPath = __DIR__ . '/assets/example.png';
$logo = new Image($basics->getId(), [
    'path'   => is_file($gifPath) ? $gifPath : $pngPath,
    'relief' => 'sunken',
    'padx'   => 4,
    'pady'   => 4,
]);
$logo->pack(['pady' => 6]);

$logoInfo = new Label($basics->getId(), [
    'text' => $logo->isAnimated()
        ? sprintf('Animated GIF — %d frames', $logo->getFrameCount())
        : 'Static image',
    'font' => 'Arial 10',
    'fg'   => '#666',
]);
$logoInfo->pack();


// =============================================================================
//  Tab 2 — Inputs: Scale, Spinbox, Radiobutton (group)
// =============================================================================

$forms = new Frame($nb->getId());
$nb->addTab($forms, 'Inputs');

// Scale (slider) inside a LabelFrame.
$volBox = new LabelFrame($forms->getId(), ['text' => 'Volume']);
$volBox->pack(['fill' => 'x', 'padx' => 12, 'pady' => 6]);

$volReadout = new Label($volBox->getId(), ['text' => '50', 'font' => 'Arial 11 bold']);
$volReadout->pack(['anchor' => 'e', 'padx' => 8]);

$scale = new Scale($volBox->getId(), [
    'from'   => 0,
    'to'     => 100,
    'orient' => 'horizontal',
]);
$scale->setValue(50);
$scale->pack(['fill' => 'x', 'padx' => 8, 'pady' => 4]);
$scale->onChange(function (float $v) use ($volReadout, $status) {
    $volReadout->setText((string) (int) $v);
    $status->setText("Volume = " . (int) $v);
});

// Spinbox inside its own LabelFrame.
$qtyBox = new LabelFrame($forms->getId(), ['text' => 'Quantity']);
$qtyBox->pack(['fill' => 'x', 'padx' => 12, 'pady' => 6]);

$qty = new Spinbox($qtyBox->getId(), ['from' => 1, 'to' => 99, 'value' => 1]);
$qty->pack(['padx' => 8, 'pady' => 4, 'anchor' => 'w']);
$qty->onChange(fn(string $v) => $status->setText("Quantity = {$v}"));

// Radiobutton group inside a LabelFrame.
$tierBox = new LabelFrame($forms->getId(), ['text' => 'Plan']);
$tierBox->pack(['fill' => 'x', 'padx' => 12, 'pady' => 6]);

$tier = new RadioGroup('basic');
foreach ([['basic', 'Basic'], ['pro', 'Pro'], ['ent', 'Enterprise']] as [$value, $label]) {
    (new Radiobutton($tierBox->getId(), $tier, $value, ['text' => $label]))
        ->pack(['anchor' => 'w', 'padx' => 8]);
}
$tier->onChange(fn(string $v) => $status->setText("Plan = {$v}"));


// =============================================================================
//  Tab 3 — Lists & Text (with scrollbars)
// =============================================================================

$lists = new Frame($nb->getId());
$nb->addTab($lists, 'Lists & Text');

// Listbox + scrollbar in a Frame on the left.
$listFrame = new Frame($lists->getId());
$listFrame->pack(['side' => 'left', 'fill' => 'both', 'expand' => 1, 'padx' => 8, 'pady' => 8]);

$cities = new Listbox($listFrame->getId(), [
    'items'  => ['Berlin', 'Tokyo', 'Lagos', 'São Paulo', 'Dhaka', 'Lima', 'Cairo', 'Sydney'],
    'height' => 10,
]);
$cities->pack(['side' => 'left', 'fill' => 'both', 'expand' => 1]);
Scrollbar::attachTo($cities, 'vertical');

$cities->onSelect(function (Listbox $l) use ($status) {
    $idx = $l->getSelectedIndex();
    if ($idx !== null) {
        $status->setText('Picked: ' . $l->getItem($idx));
    }
});

// Text widget + scrollbar on the right.
$textFrame = new Frame($lists->getId());
$textFrame->pack(['side' => 'left', 'fill' => 'both', 'expand' => 1, 'padx' => 8, 'pady' => 8]);

$editor = new Text($textFrame->getId(), [
    'width' => 30,
    'height' => 10,
    'wrap'  => 'word',
]);
$editor->setText("Multi-line Text widget.\n\nType anything here.");
$editor->pack(['side' => 'left', 'fill' => 'both', 'expand' => 1]);
Scrollbar::attachTo($editor, 'vertical');


// =============================================================================
//  Tab 4 — Treeview (file-table demo)
// =============================================================================

$treeTab = new Frame($nb->getId());
$nb->addTab($treeTab, 'Treeview');

$tv = new Treeview($treeTab->getId(), [
    'columns'  => ['name', 'size', 'kind'],
    'headings' => ['Name', 'Size', 'Kind'],
    'show'     => 'headings',
    'height'   => 12,
]);
$tv->pack(['side' => 'left', 'fill' => 'both', 'expand' => 1, 'padx' => 8, 'pady' => 8]);
Scrollbar::attachTo($tv, 'vertical');

$tv->setColumn('name', ['width' => 240]);
$tv->setColumn('size', ['width' =>  90, 'anchor' => 'e']);
$tv->setColumn('kind', ['width' => 120]);

$rows = [
    ['report.pdf',     '1.2 MB', 'PDF document'],
    ['photo.jpg',      '4.7 MB', 'JPEG image'],
    ['presentation.key','9.1 MB','Keynote'],
    ['notes.md',       '12 KB',  'Markdown'],
    ['archive.zip',    '88 MB',  'Zip archive'],
];
foreach ($rows as $row) {
    $tv->insert(null, $row);
}

$tv->onSelect(function (array $rowIds) use ($tv, $status) {
    if ($rowIds === []) return;
    $r = $tv->getValues($rowIds[0]);
    $status->setText("Selected: {$r['name']} ({$r['size']})");
});


// =============================================================================
//  Tab 5 — Layout: PanedWindow + LabelFrame + Separator
// =============================================================================

$layoutTab = new Frame($nb->getId());
$nb->addTab($layoutTab, 'Layout');

$paned = new PanedWindow($layoutTab->getId(), ['orient' => 'horizontal']);
$paned->pack(['fill' => 'both', 'expand' => 1, 'padx' => 8, 'pady' => 8]);

$leftPane  = new Frame($paned->getId());
$rightPane = new Frame($paned->getId());
$paned->addPane($leftPane,  ['weight' => 1]);
$paned->addPane($rightPane, ['weight' => 2]);

(new Label($leftPane->getId(), [
    'text' => "Drag the divider →\n\nLeft pane",
    'font' => 'Arial 11',
    'fg'   => '#1976D2',
]))->pack(['padx' => 12, 'pady' => 12]);

(new Label($rightPane->getId(), [
    'text' => 'Right pane (weight 2:1)',
    'font' => 'Arial 11 bold',
]))->pack(['padx' => 12, 'pady' => 8]);

(new Separator($rightPane->getId()))->pack(['fill' => 'x', 'padx' => 12, 'pady' => 4]);

(new Label($rightPane->getId(), [
    'text' => "PanedWindow lets users resize\nthe split with the mouse.\n\nLabelFrame and Separator below\ngroup related controls.",
    'fg'   => '#444',
    'justify' => 'left',
]))->pack(['padx' => 12, 'pady' => 4, 'anchor' => 'w']);


// =============================================================================
//  Tab 6 — Progress
// =============================================================================

$progressTab = new Frame($nb->getId());
$nb->addTab($progressTab, 'Progress');

$detBox = new LabelFrame($progressTab->getId(), ['text' => 'Determinate']);
$detBox->pack(['fill' => 'x', 'padx' => 12, 'pady' => 8]);

$bar = new Progressbar($detBox->getId(), ['maximum' => 100, 'length' => 360]);
$bar->setValue(40);
$bar->pack(['padx' => 8, 'pady' => 8])
;
(new Button($detBox->getId(), [
    'text'    => '+10%',
    'command' => function () use ($bar, $status) {
        $bar->step(10);
        $status->setText('Progress: ' . (int) $bar->getValue() . '%');
    },
]))->pack(['side' => 'left', 'padx' => 8, 'pady' => 4]);

(new Button($detBox->getId(), [
    'text'    => 'Reset',
    'command' => function () use ($bar, $status) {
        $bar->setValue(0);
        $status->setText('Progress reset.');
    },
]))->pack(['side' => 'left', 'padx' => 4, 'pady' => 4]);

$indetBox = new LabelFrame($progressTab->getId(), ['text' => 'Indeterminate']);
$indetBox->pack(['fill' => 'x', 'padx' => 12, 'pady' => 8]);

$busy = new Progressbar($indetBox->getId(), ['mode' => 'indeterminate', 'length' => 360]);
$busy->pack(['padx' => 8, 'pady' => 8]);

(new Button($indetBox->getId(), [
    'text'    => 'Start',
    'command' => function () use ($busy, $status) {
        $busy->start();
        $status->setText('Indeterminate animation started.');
    },
]))->pack(['side' => 'left', 'padx' => 8, 'pady' => 4]);

(new Button($indetBox->getId(), [
    'text'    => 'Stop',
    'command' => function () use ($busy, $status) {
        $busy->stop();
        $status->setText('Indeterminate animation stopped.');
    },
]))->pack(['side' => 'left', 'padx' => 4, 'pady' => 4]);


// =============================================================================
//  Tab 7 — Dialogs
// =============================================================================

$dialogs = new Frame($nb->getId());
$nb->addTab($dialogs, 'Dialogs');

$buttons = [
    ['Choose Color', function () use ($status) {
        $color = TopLevel::chooseColor();
        $status->setText($color ? "Color: {$color}" : 'Color picker cancelled.');
    }],
    ['Open File', function () use ($status) {
        $file = TopLevel::getOpenFile();
        $status->setText($file ? 'File: ' . basename($file) : 'File picker cancelled.');
    }],
    ['Choose Directory', function () use ($status) {
        $dir = TopLevel::chooseDirectory();
        $status->setText($dir ? 'Dir: ' . basename($dir) : 'Directory picker cancelled.');
    }],
    ['Show Message', function () use ($status) {
        $r = TopLevel::messageBox('Hello from php-gui v1.9!', 'okcancel');
        $status->setText("Message result: {$r}");
    }],
    ['New Window', function () use ($status) {
        $top = new TopLevel(['title' => 'Secondary Window', 'width' => 320, 'height' => 160]);
        (new Label($top->getId(), ['text' => 'A separate top-level window.', 'font' => 'Arial 11']))
            ->pack(['pady' => 20]);
        (new Button($top->getId(), [
            'text'    => 'Close',
            'command' => function () use ($top, $status) {
                $top->destroy();
                $status->setText('Secondary window closed.');
            },
        ]))->pack(['pady' => 8]);
        $status->setText('Secondary window opened.');
    }],
];
foreach ($buttons as [$text, $cmd]) {
    (new Button($dialogs->getId(), ['text' => $text, 'command' => $cmd]))
        ->pack(['fill' => 'x', 'padx' => 24, 'pady' => 4]);
}


// =============================================================================
//  Menu bar
// =============================================================================

$menu = new Menu($wid, ['type' => 'main']);

$fileMenu = $menu->addSubmenu('File');
$fileMenu->addCommand('New',  fn() => $status->setText('Menu: File → New'));
$fileMenu->addCommand('Open', fn() => $status->setText('Menu: File → Open'));
$fileMenu->addSeparator();
$fileMenu->addCommand('Exit', fn() => exit(), ['foreground' => 'red']);

$helpMenu = $menu->addSubmenu('Help');
$helpMenu->addCommand('About', fn() => $status->setText('php-gui v1.9 — multi-tab showcase.'));


// Notebook tab change updates the status bar so users know where they are.
$nb->onTabChange(function (int $idx) use ($nb, $status) {
    $status->setText('Tab: ' . $nb->getTabTitle($idx));
});


$app->run();
