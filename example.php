<?php

require_once __DIR__ . '/vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Label;
use PhpGui\Widget\Button;
use PhpGui\Widget\Input; // New Input widget

$app = new Application();

// Create main window
$window = new Window([
    'title' => 'Hello World Example in php',
    'width' => 500,
    'height' => 300
]);

// Label Example
$label = new Label($window->getId(), [ 
    'text' => 'Hello, PHP GUI World!'
]);
$label->pack(['pady' => 20]);

// Button Example
$button = new Button($window->getId(), [
    'text' => 'Click Me',
    'command' => function() use ($label) {
        echo "Button clicked!\n";
        $label->setText('Button clicked!');
    }
]);
$button->pack(['pady' => 10]);

// Extra styled Button example
$styledButton = new Button($window->getId(), [
    'text'  => 'Styled Button',
    'command' => function() use ($label) {
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
$input->onEnter(function() use ($input) {
    echo "Input Enter Pressed: " . $input->getValue() . "\n";
});

// New button to show the input box value on click
$showButton = new Button($window->getId(), [
    'text' => 'Show Input',
    'command' => function() use ($input) {
         echo "Show Input Button clicked: " . $input->getValue() . "\n";
    }
]);
$showButton->pack(['pady' => 10]);

$app->run();
