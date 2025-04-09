<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Label;
use PhpGui\Widget\Button;
use PhpGui\Widget\Input;

$app = new Application();

// Create main window
$window = new Window([
    'title' => 'Integration Test',
    'width' => 500,
    'height' => 300
]);

// Label test
$label = new Label($window->getId(), [ 
    'text' => 'Hello, PHP GUI World!'
]);
$label->pack(['pady' => 20]);

// Button test
$button = new Button($window->getId(), [
    'text' => 'Click Me',
    'command' => function() use ($label) {
        echo "Button clicked from integration test!\n";
        $label->setText('Button clicked!');
    }
]);
$button->pack(['pady' => 10]);

// Input test
$input = new Input($window->getId(), [
    'text'  => 'Type here...',
    'bg'    => 'lightyellow',
    'fg'    => 'black',
    'font'  => 'Arial 14'
]);
$input->pack(['pady' => 10]);

// Register Enter key event on input widget
$input->onEnter(function() use ($input) {
    echo "Input Enter Pressed (Integration Test): " . $input->getValue() . "\n";
});

// Button to show input box value
$showButton = new Button($window->getId(), [
    'text' => 'Show Input',
    'command' => function() use ($input) {
         echo "Show Input Button clicked (Integration Test): " . $input->getValue() . "\n";
    }
]);
$showButton->pack(['pady' => 10]);

echo "Integration test setup complete.\n";

// End the test without starting the main loop
$app->quit();
