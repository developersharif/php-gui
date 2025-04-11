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
    'title' => 'Hello World Example in php',
    'width' => 800,
    'height' => 600
]);

// Label Example
$label = new Label($window->getId(), [
    'text' => 'Hello, PHP GUI World!'
]);
$label->pack(['pady' => 20]);


// Extra styled Button example
$styledButton = new Button($window->getId(), [
    'text'  => 'Styled Button',
    'command' => function () use ($label) {
        echo "Styled Button clicked!\n";
        $label->setText('Styled Button clicked!');
    },
    'bg'    => 'blue',
    'fg'    => 'white',
    'font'  => 'Helvetica 16 bold'
]);
$styledButton->pack(['pady' => 10]);

// New Input widget example with extra configuration
$input = new Input($window->getId(), [
    'text'  => 'Type here...',
    'bg'    => 'lightyellow',
    'fg'    => 'black',
    'font'  => 'Arial 14'
]);
$input->pack(['pady' => 10]);

// Register event listener for Enter key on the input widget
$input->onEnter(function () use ($input) {
    echo "Input Enter Pressed: " . $input->getValue() . "\n";
});


$styledLabel = new Label($window->getId(), [
    'text' => 'This is a styled label with custom colors',
    'fg' => 'white',
    'bg' => '#4CAF50',
    'font' => 'Arial 12',
    'padx' => 10,
    'pady' => 5,
    'relief' => 'raised'
]);
$styledLabel->pack(['pady' => 5]);

// Dynamic Label update example
$dynamicLabel = new Label($window->getId(), [
    'text' => 'Dynamic Lable',
    'font' => 'Arial 11 italic',
    'fg' => '#666666'
]);
$dynamicLabel->pack(['pady' => 5]);

// Button to demonstrate label updates
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


// Menu Examples
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

// TopLevel Examples
$topLevelButton = new Button($window->getId(), [
    'text' => 'Open New Window',
    'command' => function () use ($dynamicLabel) {
        $topLevel = new TopLevel([
            'title' => 'New Window Example',
            'width' => 300,
            'height' => 200
        ]);

        // Add content to TopLevel
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

        $minimizeBtn = new Button($topLevel->getId(), [
            'text' => 'Minimize',
            'command' => function () use ($topLevel) {
                $topLevel->iconify();
            }
        ]);
        $minimizeBtn->pack(['pady' => 10]);

        $dynamicLabel->setText('New window opened');
    }
]);
$topLevelButton->pack(['pady' => 10]);

// Dialog Examples
$dialogsLabel = new Label($window->getId(), [
    'text' => 'Dialog Examples:',
    'font' => 'Arial 12 bold'
]);
$dialogsLabel->pack(['pady' => 5]);

// Color Picker Dialog
$colorButton = new Button($window->getId(), [
    'text' => 'Choose Color',
    'command' => function () use ($dynamicLabel) {
        try {
            $color = TopLevel::chooseColor();
            if ($color) {
                echo "Selected color: $color\n";
                $dynamicLabel->setText("Selected color: $color");
                $dynamicLabel->setForeground($color);
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
]);
$colorButton->pack(['pady' => 5]);

// File Selection Dialog
$fileButton = new Button($window->getId(), [
    'text' => 'Open File',
    'command' => function () use ($dynamicLabel) {
        $file = TopLevel::getOpenFile();
        if ($file) {
            $dynamicLabel->setText("Selected file: " . basename($file));
        }
    }
]);
$fileButton->pack(['pady' => 5]);

// Directory Selection Dialog 
$dirButton = new Button($window->getId(), [
    'text' => 'Choose Directory',
    'command' => function () use ($dynamicLabel) {
        try {
            $dir = TopLevel::chooseDirectory();
            if ($dir) {
                echo "Selected directory: $dir\n";
                $dynamicLabel->setText("Selected directory: " . basename($dir));
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
]);
$dirButton->pack(['pady' => 5]);

// Message Box Example
$msgButton = new Button($window->getId(), [
    'text' => 'Show Message',
    'command' => function () use ($dynamicLabel) {
        $result = TopLevel::messageBox("This is a test message", "okcancel");
        $dynamicLabel->setText("Message result: $result");
    }
]);
$msgButton->pack(['pady' => 5]);


$app->run();
