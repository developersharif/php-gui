<?php

namespace PhpGui\Widget;

/**
 * Class Image
 * Represents an image widget in the GUI.
 * 
 * @package PhpGui\Widget
 */
class Image extends AbstractWidget
{
    private $imagePath;
    private static $supportedFormats = ['png', 'jpg', 'jpeg', 'gif', 'bmp'];

    public function __construct(string $parentId, array $options = [])
    {
        if (!isset($options['path'])) {
            throw new \InvalidArgumentException("Image path is required");
        }

        $this->imagePath = str_replace('\\', '/', $options['path']);

        // Validate file exists
        if (!file_exists($this->imagePath)) {
            throw new \RuntimeException("Image file not found: {$options['path']}");
        }

        // Validate file extension
        $extension = strtolower(pathinfo($this->imagePath, PATHINFO_EXTENSION));
        if (!in_array($extension, self::$supportedFormats)) {
            throw new \RuntimeException("Unsupported image format: {$extension}. Supported formats: " . implode(', ', self::$supportedFormats));
        }

        parent::__construct($parentId, $options);
        $this->create();
    }

    protected function create(): void
    {
        // Create unique photo image name
        $photoName = "photo_" . $this->id;

        // Create photo image and load file
        $this->tcl->evalTcl("image create photo $photoName -file {$this->imagePath}");

        // Create label to display the image with options
        $extra = $this->getOptionString();
        $this->tcl->evalTcl("label .{$this->parentId}.{$this->id} -image $photoName {$extra}");
    }

    protected function getOptionString(): string
    {
        $opts = "";
        foreach ($this->options as $key => $value) {
            if ($key === 'path') continue;
            $opts .= " -$key \"$value\"";
        }
        return $opts;
    }
}
