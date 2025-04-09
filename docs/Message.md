# Message Widget

The **Message** widget displays a message in a dedicated window along with an OK button to dismiss.

### Example
```php
use PhpGui\Widget\Message;
$message = new Message('parentId', ['text' => 'This is a message']);
$message->setText("Updated Message");
```

### Details
- Opens as a new top-level window layered over its parent.
- Contains a label for text and a button for dismissal.
- Ideal for notifications and alerts.
