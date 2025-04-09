<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\Window;
use PhpGui\Widget\Message;

$app = new Application();
$window = new Window(['title' => 'Message Test']);

// Create a Message widget.
$message = new Message($window->getId(), ['text' => 'Test Message']);
echo "MessageTest: Message widget created with text: 'Test Message'\n";

// Update the message text.
$message->setText("Updated Message");
echo "MessageTest: Message text updated\n";

$app->quit();
