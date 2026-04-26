# Image Widget

The **Image** widget displays an image inside a parent window. Internally it is a Tk `label` whose `-image` is a Tk photo image loaded from disk, so it supports the same layout managers as any other widget (`pack`, `place`, `grid`).

---

### Constructor

```php
new Image(string $parentId, array $options)
```

| Parameter   | Type     | Description                                  |
|-------------|----------|----------------------------------------------|
| `$parentId` | `string` | `getId()` of the parent widget.              |
| `$options`  | `array`  | Configuration options — see table below. `path` is required. |

Throws `InvalidArgumentException` if `path` is missing, and `RuntimeException` if the file does not exist or its extension is not a supported image format.

---

### Options

| Key      | Type     | Description                                                         |
|----------|----------|---------------------------------------------------------------------|
| `path`   | `string` | **Required.** Filesystem path to the image file.                    |
| `bg`     | `string` | Background color shown around the image.                            |
| `relief` | `string` | Border style: `flat`, `raised`, `sunken`, `groove`, `ridge`.        |
| `padx`   | `int`    | Horizontal internal padding.                                        |
| `pady`   | `int`    | Vertical internal padding.                                          |
| `cursor` | `string` | Cursor shown when hovering the image.                               |

Any other key is forwarded as a Tk `-key value` pair on the underlying label.

---

### Supported formats

| Format            | How                                                                                   |
|-------------------|---------------------------------------------------------------------------------------|
| PNG, GIF, PPM/PGM | Loaded directly by Tk's `image create photo`.                                         |
| JPEG, BMP         | Transparently transcoded to a temp PNG via PHP's GD extension before being given to Tk. |

JPEG and BMP support requires `ext-gd` to be enabled (the default in most PHP builds). The transcoded PNG lives in `sys_get_temp_dir()` for as long as the widget exists and is unlinked automatically by `setPath()` and `destroy()`.

---

### Animated GIFs

Multi-frame GIFs play automatically. The widget parses the GIF for per-frame delays from the Graphic Control Extension blocks, then drives a Tcl `after`-based loop that swaps the photo's `-format "gif -index N"` on each tick. The loop runs entirely inside Tk's event loop (which `Application::run()` already pumps), so there is no PHP round-trip per frame.

```php
$loader = new Image($window->getId(), ['path' => 'assets/spinner.gif']);
$loader->pack();

$loader->isAnimated();      // true
$loader->getFrameCount();   // e.g. 12
```

The animation is cancelled automatically on `destroy()`, and on `setPath()` to either a non-GIF or a different GIF (a fresh loop is started for the new file). Frame delays under 20ms are clamped to 100ms to avoid busy-loops on GIFs that encode "0" to mean "as fast as possible".

---

### Examples

**Display a PNG:**
```php
use PhpGui\Widget\Image;

$logo = new Image($window->getId(), ['path' => __DIR__ . '/assets/logo.png']);
$logo->pack(['pady' => 20]);
```

**Swap the image at runtime:**
```php
$photo = new Image($window->getId(), ['path' => 'avatars/default.png']);
$photo->pack();

$btn = new Button($window->getId(), [
    'text'    => 'Load avatar',
    'command' => fn() => $photo->setPath('avatars/user-42.png'),
]);
$btn->pack();
```

**Add a frame and padding:**
```php
$image = new Image($window->getId(), [
    'path'   => 'screenshot.png',
    'relief' => 'sunken',
    'padx'   => 8,
    'pady'   => 8,
    'bg'     => '#222',
]);
$image->pack(['padx' => 12, 'pady' => 12]);
```

---

### Methods

| Method        | Signature                  | Description                                                                 |
|---------------|----------------------------|-----------------------------------------------------------------------------|
| `setPath()`     | `(string $path): void`     | Reloads pixels from a new file. Cancels any running animation and starts a new one if the file is an animated GIF. |
| `getPath()`     | `(): string`               | Returns the currently loaded image path (with normalized separators).       |
| `getWidth()`    | `(): int`                  | Width of the loaded image in pixels (`image width`).                        |
| `getHeight()`   | `(): int`                  | Height of the loaded image in pixels (`image height`).                      |
| `getFrameCount()` | `(): int`                | Total frames in the loaded image (1 for non-animated).                      |
| `isAnimated()`  | `(): bool`                 | True when an animation loop is scheduled for this image.                    |
| `pack()`      | `(array $opts = []): void` | Inherited. Pack layout manager.                                             |
| `place()`     | `(array $opts = []): void` | Inherited. Place layout manager.                                            |
| `grid()`      | `(array $opts = []): void` | Inherited. Grid layout manager.                                             |
| `destroy()`   | `(): void`                 | Removes the label **and** frees the underlying photo image from Tk's image table. |

---

### Notes

- Each `Image` instance owns its own Tk photo image (`phpgui_photo_<id>`). Always call `destroy()` when you replace or discard the widget — photo images are not garbage-collected with their containing label.
- `setPath()` reuses the same photo image, so any other label currently bound to it will also update.
- Paths containing spaces, brackets, or `$` are handled safely; the path is passed via a Tcl variable rather than interpolated into the command.
