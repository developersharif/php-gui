# Combobox Widget

The **Combobox** widget creates a dropdown selection box with a list of options.

### Example
```php
use PhpGui\Widget\Combobox;
$combo = new Combobox('parentId', ['values' => 'Option1 Option2 Option3']);
```

### Details
- Uses space-separated values to generate the option list.
- Provides methods to get or set the selected value.
- Great for selection lists and forms.
