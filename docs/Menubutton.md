# Menubutton Widget

The **Menubutton** widget creates a button that is associated with a dropdown menu.

### Example
```php
use PhpGui\Widget\Menubutton;
$menubutton = new Menubutton('parentId', ['text' => 'Options']);
```

### Details
- Configures a drop-down menu for action selection.
- Useful for toolbar menus or context-sensitive controls.
- The associated menu must be populated as needed.
