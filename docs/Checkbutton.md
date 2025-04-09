# Checkbutton Widget

The **Checkbutton** widget is used for boolean toggle inputs.

### Example
```php
use PhpGui\Widget\Checkbutton;
$check = new Checkbutton('parentId', ['text' => 'Check me']);
$check->setChecked(true);
```

### Details
- Stores a boolean value (checked or unchecked).
- Provides methods to get and set its state.
- Ideal for forms and settings interfaces.
