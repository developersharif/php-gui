# Button Widget

The **Button** widget triggers actions when clicked.

### Example
```php
use PhpGui\Widget\Button;
$button = new Button('parentId', [
    'text' => 'Submit',
    'command' => function() {
        echo "Button clicked!";
    }
]);
```

### Details
- Can be customized with additional options (e.g., bg, fg, font).
- Registers a PHP callback to handle click events.
- Essential for interactive actions in a GUI.
