# Message Widget

The **Message** widget opens a small modal-style top-level window containing a text message and an **OK** button. Clicking OK automatically destroys the window. It is intended for simple notifications and alerts.

---

### Constructor

```php
new Message(?string $parentId, array $options = [])
```

| Parameter   | Type            | Description                                                         |
|-------------|-----------------|---------------------------------------------------------------------|
| `$parentId` | `string\|null`  | `getId()` of the parent widget. Used to construct the Tk path.      |
| `$options`  | `array`         | Configuration options — see table below.                            |

---

### Options

| Key    | Type     | Default     | Description                      |
|--------|----------|-------------|----------------------------------|
| `text` | `string` | `'Message'` | The message text displayed in the popup window. |

---

### Example

**Show a notification:**
```php
use PhpGui\Widget\Message;

$msg = new Message($window->getId(), ['text' => 'File saved successfully!']);
```

The window appears immediately on construction (`update idletasks` is called in `create()`). The user dismisses it with the OK button, which destroys the popup automatically.

**Updating message text before the user dismisses:**
```php
$msg = new Message($window->getId(), ['text' => 'Loading...']);
// Later, if still open:
$msg->setText('Done!');
```

---

### Methods

| Method      | Signature              | Description                                                                           |
|-------------|------------------------|---------------------------------------------------------------------------------------|
| `setText()` | `(string $text): void` | Updates the label text inside the message window if it still exists (`winfo exists`). |
| `destroy()` | `(): void`             | Inherited. Destroys the top-level message window.                                     |

---

### Tk Structure

Internally, `Message` creates the following widgets under `.{parentId}.{id}`:

| Tk path                        | Widget  | Description                 |
|--------------------------------|---------|-----------------------------|
| `.{parentId}.{id}`             | toplevel | The popup window itself.   |
| `.{parentId}.{id}.msg`         | label    | Displays the message text. |
| `.{parentId}.{id}.ok`          | button   | "OK" button to dismiss.    |

---

### Notes

- The popup title is always `"Message"` (set via `wm title`).
- The label is packed with `padx 20 pady 20` and the OK button with `pady 10`.
- `setText()` guards itself with `winfo exists` — calling it after the user has dismissed the window is safe and has no effect.
- For more flexible dialogs (yes/no, type selection), use `TopLevel::messageBox()` or `TopLevel::dialog()` instead.
