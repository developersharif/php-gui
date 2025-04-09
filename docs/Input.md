# Input Widget

The **Input** widget is an editable text entry field.

### Example
```php
use PhpGui\Widget\Input;
$input = new Input('parentId', ['text' => 'Enter value...']);
$input->onEnter(function() use ($input) {
    echo "Entered: " . $input->getValue();
});
```

### Details
- Binds a Tcl textvariable automatically.
- Supports both getting and setting its text.
- Provides event binding (e.g., pressing Enter) for immediate feedback.
