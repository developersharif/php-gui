# Label Widget

The **Label** widget displays read-only text within a window.

### Example
```php
use PhpGui\Widget\Label;
$label = new Label('parentId', ['text' => 'Static Text']);
$label->setText('Updated Text');
```

### Details
- Simple widget to display text.
- Supports text update via setText method.
- Often used to describe actions and statuses.
