<?php

namespace PhpGui\Widget;

/**
 * Class Image
 * Displays an image inside a parent widget. Backed by a Tk `label` whose
 * `-image` is a Tk photo image loaded from disk.
 *
 * Tk core's `image create photo` only accepts PNG, GIF, and PPM/PGM. JPEG
 * and BMP files are transparently transcoded to a temporary PNG via PHP's
 * GD extension. Animated GIFs cycle frames via a Tcl `after`-driven loop
 * that runs inside the existing Tk event loop.
 *
 * @package PhpGui\Widget
 */
class Image extends AbstractWidget
{
    /** Formats Tk's built-in photo image always supports. */
    private const CORE_FORMATS = ['png', 'gif', 'ppm', 'pgm'];

    /** Formats supported via on-the-fly transcoding through GD. */
    private const GD_FORMATS   = ['jpg', 'jpeg', 'bmp'];

    /** Whether the global Tcl animation proc has been installed yet. */
    private static bool $animProcDefined = false;

    private string $imagePath;       // user-facing original path
    private string $loadedPath;      // path actually fed to Tk (may be a temp PNG)
    private string $photoName;
    private ?string $tempFile = null;

    /** @var int Number of frames in the current image (>=1; 0 = not yet loaded). */
    private int $frameCount = 0;

    public function __construct(string $parentId, array $options = [])
    {
        if (!isset($options['path'])) {
            throw new \InvalidArgumentException('Image path is required');
        }

        parent::__construct($parentId, $options);
        $this->photoName = 'phpgui_photo_' . $this->id;

        [$this->imagePath, $this->loadedPath, $this->tempFile] =
            $this->prepareImage($options['path']);

        $this->create();
    }

    protected function create(): void
    {
        $this->createPhotoFromPath($this->loadedPath, $this->photoName);

        $extra = $this->getOptionString();
        $this->tcl->evalTcl("label {$this->tclPath} -image {$this->photoName}{$extra}");

        $this->maybeStartAnimation();
    }

    /**
     * Replaces the displayed image with a new file on disk. The widget itself
     * is not recreated — only the underlying photo image's pixels change.
     * Any temp file from a previous transcode is removed; any running GIF
     * animation is stopped before the new image is loaded.
     */
    public function setPath(string $path): void
    {
        [$resolved, $loaded, $newTemp] = $this->prepareImage($path);

        $this->stopAnimation();

        $this->tcl->setVar('phpgui_img_path', $loaded);
        // -format {} clears any lingering "gif -index N" set by a previous
        // animation loop, so Tk re-detects the format from the file header.
        $this->tcl->evalTcl("{$this->photoName} configure -file \$phpgui_img_path -format {}");

        $this->cleanupTempFile();

        $this->imagePath  = $resolved;
        $this->loadedPath = $loaded;
        $this->tempFile   = $newTemp;

        $this->maybeStartAnimation();
    }

    public function getPath(): string
    {
        return $this->imagePath;
    }

    public function getWidth(): int
    {
        return (int) trim($this->tcl->evalTcl("image width {$this->photoName}"));
    }

    public function getHeight(): int
    {
        return (int) trim($this->tcl->evalTcl("image height {$this->photoName}"));
    }

    /** Total number of frames in the loaded image. 1 for non-animated. */
    public function getFrameCount(): int
    {
        return $this->frameCount;
    }

    /** True when an animation loop is scheduled for this image. */
    public function isAnimated(): bool
    {
        return $this->frameCount > 1;
    }

    public function destroy(): void
    {
        $this->stopAnimation();
        parent::destroy();
        try {
            $this->tcl->evalTcl("image delete {$this->photoName}");
        } catch (\Throwable) {
            // Already gone; safe to ignore during shutdown paths.
        }
        $this->cleanupTempFile();
    }

    /**
     * Resolve a user-supplied path to (originalPath, pathTkShouldLoad, tempFileOrNull).
     * For PNG/GIF/PPM/PGM the three values are: original, original, null.
     * For JPG/JPEG/BMP the third value is a temp PNG that the caller owns.
     *
     * @return array{0:string,1:string,2:?string}
     */
    private function prepareImage(string $path): array
    {
        $normalized = str_replace('\\', '/', $path);

        if (!is_file($normalized)) {
            throw new \RuntimeException("Image file not found: {$path}");
        }

        $extension = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));

        if (\in_array($extension, self::CORE_FORMATS, true)) {
            return [$normalized, $normalized, null];
        }

        if (\in_array($extension, self::GD_FORMATS, true)) {
            $temp = $this->transcodeToPng($normalized, $extension);
            return [$normalized, $temp, $temp];
        }

        throw new \RuntimeException(\sprintf(
            "Unsupported image format '%s'. Supported: %s",
            $extension,
            implode(', ', [...self::CORE_FORMATS, ...self::GD_FORMATS])
        ));
    }

    /**
     * Transcode a JPEG/BMP file to a temp PNG via GD so Tk can display it.
     * The returned path lives in sys_get_temp_dir() and must be unlinked by
     * the caller (we track it on the instance for that purpose).
     */
    private function transcodeToPng(string $path, string $extension): string
    {
        if (!\function_exists('imagecreatefromstring')) {
            throw new \RuntimeException(
                "Loading '{$extension}' requires PHP's GD extension. " .
                "Install php-gd or convert the image to PNG/GIF first."
            );
        }

        $data = @file_get_contents($path);
        if ($data === false) {
            throw new \RuntimeException("Could not read image file: {$path}");
        }

        $gd = @imagecreatefromstring($data);
        if ($gd === false) {
            throw new \RuntimeException(
                "GD failed to decode '{$extension}' image: {$path}"
            );
        }

        imagealphablending($gd, false);
        imagesavealpha($gd, true);

        $tempPath = sys_get_temp_dir() . '/phpgui_img_' . uniqid('', true) . '.png';
        $ok = @imagepng($gd, $tempPath);
        if (!$ok) {
            throw new \RuntimeException("Could not write transcoded PNG to {$tempPath}");
        }

        return $tempPath;
    }

    private function cleanupTempFile(): void
    {
        if ($this->tempFile !== null && is_file($this->tempFile)) {
            @unlink($this->tempFile);
        }
        $this->tempFile = null;
    }

    /**
     * Create the underlying photo image, routing the path through a Tcl
     * variable so paths with spaces, brackets, `$`, or quotes are safe.
     */
    private function createPhotoFromPath(string $path, string $photoName): void
    {
        $this->tcl->setVar('phpgui_img_path', $path);
        $this->tcl->evalTcl("image create photo {$photoName} -file \$phpgui_img_path");
    }

    /**
     * If the loaded file is a multi-frame GIF, composite every frame onto
     * a logical-screen-sized canvas (honoring per-frame offsets, transparency,
     * and disposal methods 1/2) and snapshot each composited result into
     * its own photo image. Animation swaps the label's `-image` between
     * those snapshots — no per-tick decoding, all frames are full-screen
     * sized so the label never resizes, and transparent gaps never flash
     * through the widget background.
     */
    private function maybeStartAnimation(): void
    {
        $extension = strtolower(pathinfo($this->loadedPath, PATHINFO_EXTENSION));
        if ($extension !== 'gif') {
            $this->frameCount = 1;
            return;
        }

        $info = self::parseGif($this->loadedPath);
        $totalFrames = count($info['frames']);
        $this->frameCount = max(1, $totalFrames);

        if ($totalFrames <= 1) {
            return;
        }

        $screenW = max(1, $info['screenW']);
        $screenH = max(1, $info['screenH']);
        $tmp     = "phpgui_tmp_{$this->id}";

        // Resize the main photo to the logical screen, then clear it. The
        // label is already bound to it by name, so it'll reflect updates.
        $this->tcl->evalTcl("{$this->photoName} configure -width {$screenW} -height {$screenH}");
        $this->tcl->evalTcl("{$this->photoName} blank");
        $this->tcl->setVar('phpgui_img_path', $this->loadedPath);

        $framePhotos = [];
        $prev = null;
        for ($i = 0; $i < $totalFrames; $i++) {
            $f = $info['frames'][$i];

            if ($prev !== null) {
                $this->applyDisposal($prev, $screenW, $screenH);
            }

            try {
                $this->tcl->evalTcl(
                    "image create photo {$tmp} -file \$phpgui_img_path -format {gif -index {$i}}"
                );
            } catch (\RuntimeException) {
                // Couldn't decode this frame — keep what we have so far.
                $this->frameCount = max(1, $i);
                break;
            }

            // Overlay rule keeps existing pixels where the source is
            // transparent, so unchanged regions of prior frames remain.
            $this->tcl->evalTcl(
                "{$this->photoName} copy {$tmp} -to {$f['x']} {$f['y']} -compositingrule overlay"
            );
            $this->tcl->evalTcl("image delete {$tmp}");

            $framePhoto = "{$this->photoName}_frame{$i}";
            $this->tcl->evalTcl(
                "image create photo {$framePhoto} -width {$screenW} -height {$screenH}"
            );
            // -compositingrule set copies pixels verbatim, including alpha,
            // so the snapshot freezes the canvas at this instant.
            $this->tcl->evalTcl("{$framePhoto} copy {$this->photoName} -compositingrule set");
            $framePhotos[] = $framePhoto;

            $prev = $f;
        }

        if (count($framePhotos) <= 1) {
            // Salvage frame 0 if compositing collapsed (shouldn't happen).
            $this->frameCount = 1;
            return;
        }

        // Reset main photo to frame 0 so the label currently shows the start.
        $this->tcl->evalTcl("{$this->photoName} blank");
        $this->tcl->evalTcl("{$this->photoName} copy {$framePhotos[0]} -compositingrule set");

        $this->ensureAnimationProc();

        $widgetPath = $this->tclPath;
        $delaysTcl  = '[list ' . implode(' ', array_map(static fn(array $f) => (int) $f['delay'], $info['frames'])) . ']';
        $framesTcl  = '[list ' . implode(' ', $framePhotos) . ']';

        $this->tcl->evalTcl("set ::phpgui_anim_widget({$this->photoName}) {$widgetPath}");
        $this->tcl->evalTcl("set ::phpgui_anim_frames({$this->photoName}) {$framesTcl}");
        $this->tcl->evalTcl("set ::phpgui_anim_delays({$this->photoName}) {$delaysTcl}");

        // Schedule frame 1; the label is already showing frame 0.
        $this->tcl->evalTcl(
            "phpgui_anim_step {$this->photoName} 1 {$this->frameCount}"
        );
    }

    /**
     * Apply a frame's disposal method to the main canvas before drawing
     * the next frame on top. Supports the common cases:
     *   1 (do not dispose) — no-op, leave canvas as-is.
     *   2 (restore to background) — clear the frame's rectangle to transparent.
     *   3 (restore to previous) — fall back to "do not dispose" since
     *      restoring to a snapshot is rarely used in real-world GIFs.
     *
     * @param array{x:int,y:int,w:int,h:int,disposal:int} $frame
     */
    private function applyDisposal(array $frame, int $screenW, int $screenH): void
    {
        if ($frame['disposal'] !== 2) {
            return;
        }
        $clear = "phpgui_clear_{$this->id}";
        // A freshly created photo is fully transparent. Copying it with the
        // `set` rule overwrites the canvas region — including alpha — so
        // the rectangle becomes transparent again.
        $this->tcl->evalTcl(
            "image create photo {$clear} -width {$frame['w']} -height {$frame['h']}"
        );
        $this->tcl->evalTcl(sprintf(
            '%s copy %s -from 0 0 %d %d -to %d %d %d %d -compositingrule set',
            $this->photoName,
            $clear,
            $frame['w'],
            $frame['h'],
            $frame['x'],
            $frame['y'],
            $frame['x'] + $frame['w'],
            $frame['y'] + $frame['h']
        ));
        $this->tcl->evalTcl("image delete {$clear}");
    }

    private function stopAnimation(): void
    {
        $widgetPath = $this->tclPath;
        try {
            // Cancel the after, drop the per-instance Tcl state, and re-bind
            // the label to the main photo *before* freeing the frame photos.
            // Otherwise the label is left pointing at a photo we're about to
            // delete, which Tk would render as a blank widget.
            $this->tcl->evalTcl(<<<TCL
                if {[info exists ::phpgui_anim_after({$this->photoName})]} {
                    after cancel \$::phpgui_anim_after({$this->photoName})
                    unset ::phpgui_anim_after({$this->photoName})
                }
                foreach _v {phpgui_anim_widget phpgui_anim_frames phpgui_anim_delays} {
                    if {[info exists ::\${_v}({$this->photoName})]} {
                        unset ::\${_v}({$this->photoName})
                    }
                }
                if {[winfo exists {$widgetPath}]} {
                    catch {{$widgetPath} configure -image {$this->photoName}}
                }
TCL
            );
            for ($i = 0; $i < $this->frameCount; $i++) {
                try {
                    $this->tcl->evalTcl("image delete {$this->photoName}_frame{$i}");
                } catch (\Throwable) {
                    // Already gone.
                }
            }
        } catch (\Throwable) {
            // Best-effort during teardown.
        }
    }

    /**
     * Define the global Tcl proc that drives the animation loop. Idempotent —
     * runs on the first animated GIF in the process and is a no-op afterwards.
     *
     * The proc swaps the label's -image option to the next pre-loaded frame
     * photo, then reschedules itself via `after`. It bails out cleanly if the
     * label or main photo have been destroyed between callbacks.
     */
    private function ensureAnimationProc(): void
    {
        if (self::$animProcDefined) {
            return;
        }
        $this->tcl->evalTcl(<<<'TCL'
            proc phpgui_anim_step {photo frame total} {
                if {![info exists ::phpgui_anim_widget($photo)]} { return }
                set widget $::phpgui_anim_widget($photo)
                set frames $::phpgui_anim_frames($photo)
                set delays $::phpgui_anim_delays($photo)
                if {![winfo exists $widget]} { return }
                set framePhoto [lindex $frames $frame]
                catch {$widget configure -image $framePhoto}
                set next [expr {($frame + 1) % $total}]
                set delay [lindex $delays $frame]
                if {$delay < 20} { set delay 100 }
                set ::phpgui_anim_after($photo) \
                    [after $delay [list phpgui_anim_step $photo $next $total]]
            }
TCL
        );
        self::$animProcDefined = true;
    }

    /**
     * Minimal GIF89a/87a parser. Extracts:
     *   - the logical-screen size (where frames composite onto)
     *   - per-frame: offset (x, y), size (w, h), delay (ms), disposal method
     * Frames without an explicit Graphic Control Extension inherit delay
     * 100ms and disposal 1. Delays under 20ms are clamped to 100ms — many
     * GIFs encode 0 to mean "as fast as possible", which would peg the CPU.
     *
     * @return array{screenW:int, screenH:int, frames:list<array{x:int,y:int,w:int,h:int,delay:int,disposal:int}>}
     */
    private static function parseGif(string $path): array
    {
        $empty = ['screenW' => 0, 'screenH' => 0, 'frames' => []];
        $data = @file_get_contents($path);
        if ($data === false || strlen($data) < 13) {
            return $empty;
        }

        $sig = substr($data, 0, 6);
        if ($sig !== 'GIF89a' && $sig !== 'GIF87a') {
            return $empty;
        }

        $len = strlen($data);
        $i = 6;
        $screenW = ord($data[$i]) | (ord($data[$i + 1]) << 8);
        $screenH = ord($data[$i + 2]) | (ord($data[$i + 3]) << 8);
        $packed  = ord($data[$i + 4]);
        $i += 7;
        if ($packed & 0x80) {
            $i += 3 * (1 << (($packed & 0x07) + 1));
        }

        $frames = [];
        $pendingDelay    = 100;
        $pendingDisposal = 1;

        while ($i < $len) {
            $b = ord($data[$i]);
            if ($b === 0x3B) {
                break;
            }
            if ($b === 0x21 && $i + 1 < $len) {
                $i++;
                $label = ord($data[$i]);
                $i++;
                if ($label === 0xF9 && $i + 6 <= $len) {
                    $i++; // block size (4)
                    $gcePacked = ord($data[$i]);
                    $i++;
                    $delayLo = ord($data[$i]);
                    $i++;
                    $delayHi = ord($data[$i]);
                    $i++;
                    $i++; // transp idx
                    $i++; // sub-block terminator
                    $pendingDelay    = (($delayLo | ($delayHi << 8)) * 10) ?: 100;
                    $pendingDisposal = ($gcePacked >> 2) & 0x07;
                } else {
                    while ($i < $len && ($size = ord($data[$i])) !== 0) {
                        $i += $size + 1;
                    }
                    $i++;
                }
            } elseif ($b === 0x2C && $i + 10 <= $len) {
                $i++;
                $fx = ord($data[$i]) | (ord($data[$i + 1]) << 8);
                $fy = ord($data[$i + 2]) | (ord($data[$i + 3]) << 8);
                $fw = ord($data[$i + 4]) | (ord($data[$i + 5]) << 8);
                $fh = ord($data[$i + 6]) | (ord($data[$i + 7]) << 8);
                $i += 8;
                $imgPacked = ord($data[$i]);
                $i++;
                if ($imgPacked & 0x80) {
                    $i += 3 * (1 << (($imgPacked & 0x07) + 1));
                }
                $i++; // LZW min code size
                while ($i < $len && ($size = ord($data[$i])) !== 0) {
                    $i += $size + 1;
                }
                $i++;

                $frames[] = [
                    'x'        => $fx,
                    'y'        => $fy,
                    'w'        => $fw,
                    'h'        => $fh,
                    'delay'    => $pendingDelay < 20 ? 100 : $pendingDelay,
                    'disposal' => $pendingDisposal,
                ];
                $pendingDelay    = 100;
                $pendingDisposal = 1;
            } else {
                $i++;
            }
        }

        return ['screenW' => $screenW, 'screenH' => $screenH, 'frames' => $frames];
    }

    protected function getOptionString(): string
    {
        $opts = '';
        foreach ($this->options as $key => $value) {
            if ($key === 'path') {
                continue;
            }
            // Quote the value as a Tcl brace-string. Braces preserve content
            // verbatim and don't allow command/variable substitution. Escape
            // any literal braces in the value to keep nesting balanced.
            $safe  = str_replace(['{', '}'], ['\\{', '\\}'], (string) $value);
            $opts .= " -{$key} {{$safe}}";
        }
        return $opts;
    }
}
