# Full Example

A complete application demonstrating multiple widgets, menus, dialogs, and event handling.

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Label;
use PhpGui\Widget\Button;
use PhpGui\Widget\Input;
use PhpGui\Widget\TopLevel;
use PhpGui\Widget\Menu;

$app = new Application();

// Create main window
$window = new Window([
    'title' => 'Hello World Example in PHP',
    'width' => 800,
    'height' => 600
]);

// Label
$label = new Label($window->getId(), [
    'text' => 'Hello, PHP GUI World!'
]);
$label->pack(['pady' => 20]);

// Styled Button
$styledButton = new Button($window->getId(), [
    'text'  => 'Styled Button',
    'command' => function () use ($label) {
        $label->setText('Styled Button clicked!');
    },
    'bg'    => 'blue',
    'fg'    => 'white',
    'font'  => 'Helvetica 16 bold'
]);
$styledButton->pack(['pady' => 10]);

// Input with Enter key binding
$input = new Input($window->getId(), [
    'text' => 'Type here...',
    'bg'   => 'lightyellow',
    'fg'   => 'black',
    'font' => 'Arial 14'
]);
$input->pack(['pady' => 10]);

$input->onEnter(function () use ($input) {
    echo "Input: " . $input->getValue() . "\n";
});

// Styled Label
$styledLabel = new Label($window->getId(), [
    'text'   => 'This is a styled label with custom colors',
    'fg'     => 'white',
    'bg'     => '#4CAF50',
    'font'   => 'Arial 12',
    'padx'   => 10,
    'pady'   => 5,
    'relief' => 'raised'
]);
$styledLabel->pack(['pady' => 5]);

// Dynamic Label
$dynamicLabel = new Label($window->getId(), [
    'text' => 'Dynamic Label',
    'font' => 'Arial 11 italic',
    'fg'   => '#666666'
]);
$dynamicLabel->pack(['pady' => 5]);

// Update Labels Button
$updateButton = new Button($window->getId(), [
    'text' => 'Update Labels',
    'command' => function () use ($dynamicLabel, $styledLabel) {
        $dynamicLabel->setText('Label text updated!');
        $dynamicLabel->setForeground('#009688');
        $styledLabel->setBackground('#2196F3');
        $styledLabel->setText('Colors and text can be changed dynamically');
    }
]);
$updateButton->pack(['pady' => 5]);

// --- Menus ---

$mainMenu = new Menu($window->getId(), ['type' => 'main']);

// File Menu
$fileMenu = $mainMenu->addSubmenu('File');
$fileMenu->addCommand('New', function () use ($dynamicLabel) {
    $dynamicLabel->setText('New File Selected');
});
$fileMenu->addCommand('Open', function () use ($dynamicLabel) {
    $dynamicLabel->setText('Open Selected');
});
$fileMenu->addSeparator();
$fileMenu->addCommand('Exit', function () {
    exit();
}, ['foreground' => 'red']);

// Edit Menu
$editMenu = $mainMenu->addSubmenu('Edit');
$editMenu->addCommand('Copy', function () use ($styledLabel) {
    $styledLabel->setText('Copy Selected');
});
$editMenu->addCommand('Paste', function () use ($styledLabel) {
    $styledLabel->setText('Paste Selected');
});

// Help Menu with Nested Submenu
$helpMenu = $mainMenu->addSubmenu('Help');
$aboutMenu = $helpMenu->addSubmenu('About');
$aboutMenu->addCommand('Version', function () use ($dynamicLabel) {
    $dynamicLabel->setText('Version 1.0');
});

// --- TopLevel & Dialogs ---

// Open New Window
$topLevelButton = new Button($window->getId(), [
    'text' => 'Open New Window',
    'command' => function () use ($dynamicLabel) {
        $topLevel = new TopLevel([
            'title' => 'New Window Example',
            'width' => 300,
            'height' => 200
        ]);

        $label = new Label($topLevel->getId(), [
            'text' => 'This is a new window',
            'font' => 'Arial 14'
        ]);
        $label->pack(['pady' => 20]);

        $closeBtn = new Button($topLevel->getId(), [
            'text' => 'Close Window',
            'command' => function () use ($topLevel, $dynamicLabel) {
                $dynamicLabel->setText('TopLevel window closed');
                $topLevel->destroy();
            }
        ]);
        $closeBtn->pack(['pady' => 10]);
    }
]);
$topLevelButton->pack(['pady' => 10]);

// Color Picker Dialog
$colorButton = new Button($window->getId(), [
    'text' => 'Choose Color',
    'command' => function () use ($dynamicLabel) {
        $color = TopLevel::chooseColor();
        if ($color) {
            $dynamicLabel->setText("Selected color: $color");
            $dynamicLabel->setForeground($color);
        }
    }
]);
$colorButton->pack(['pady' => 5]);

// File Open Dialog
$fileButton = new Button($window->getId(), [
    'text' => 'Open File',
    'command' => function () use ($dynamicLabel) {
        $file = TopLevel::getOpenFile();
        if ($file) {
            $dynamicLabel->setText("Selected: " . basename($file));
        }
    }
]);
$fileButton->pack(['pady' => 5]);

// Directory Chooser Dialog
$dirButton = new Button($window->getId(), [
    'text' => 'Choose Directory',
    'command' => function () use ($dynamicLabel) {
        $dir = TopLevel::chooseDirectory();
        if ($dir) {
            $dynamicLabel->setText("Directory: " . basename($dir));
        }
    }
]);
$dirButton->pack(['pady' => 5]);

// Message Box
$msgButton = new Button($window->getId(), [
    'text' => 'Show Message',
    'command' => function () use ($dynamicLabel) {
        $result = TopLevel::messageBox("This is a test message", "okcancel");
        $dynamicLabel->setText("Message result: $result");
    }
]);
$msgButton->pack(['pady' => 5]);

$app->run();
```

## What This Example Demonstrates

- **Window creation** with custom title and dimensions
- **Labels** with styling (colors, fonts, relief)
- **Buttons** with click callbacks
- **Input fields** with Enter key binding
- **Dynamic updates** — changing widget text, colors at runtime
- **Menus** — main menu bar with submenus and nested menus
- **TopLevel windows** — secondary windows with their own widgets
- **Built-in dialogs** — color picker, file open, directory chooser, message box
