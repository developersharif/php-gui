# Window Widget

The **Window** widget creates a top-level window. It accepts options like title, width, and height.
  
### Example
```php
use PhpGui\Widget\Window;
$window = new Window([
    'title'  => 'Main Window',
    'width'  => 400,
    'height' => 300
]);
```

### Details
- Automatically creates a Tcl toplevel window.
- Acts as the container for other widgets.
- Overrides default properties if not specified.
