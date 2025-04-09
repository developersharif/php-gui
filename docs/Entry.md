# Entry Widget

The **Entry** widget represents a single-line input field for user text entry.

### Example
```php
use PhpGui\Widget\Entry;
$entry = new Entry('parentId', ['text' => 'Default text']);
echo $entry->getValue();
```

### Details
- Automatically binds a text variable.
- Provides methods to get and set text.
- Ideal for form inputs and search boxes.
