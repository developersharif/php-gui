# TopLevel Widget

The **TopLevel** widget is similar to Window but can host additional elements (e.g. dialogs).

### Example
```php
use PhpGui\Widget\TopLevel;
$topLevel = new TopLevel(['text' => 'Initial Top Level']);
```

### Details
- Useful for creating secondary windows.
- Supports dialog methods like chooseColor, dialog, etc.
- Modular design for reusing as a container.
