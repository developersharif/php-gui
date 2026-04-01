#!/usr/bin/env php
<?php

/**
 * Downloads the pre-built WebView helper binary for the current platform.
 *
 * Called automatically via composer post-install/post-update hooks,
 * or manually: php scripts/install-webview-helper.php
 *
 * Falls back to build-from-source instructions if download fails.
 */

const REPO = 'developersharif/php-gui';

function getOs(): string
{
    return match (PHP_OS_FAMILY) {
        'Darwin'  => 'darwin',
        'Windows' => 'windows',
        default   => 'linux',
    };
}

function getArch(): string
{
    $arch = php_uname('m');
    if ($arch === 'AMD64') return 'x86_64';
    $os = getOs();
    if ($os === 'darwin' && $arch === 'aarch64') return 'arm64';
    return $arch;
}

function getBinaryName(): string
{
    $os = getOs();
    $arch = getArch();
    $ext = $os === 'windows' ? '.exe' : '';
    return "webview_helper_{$os}_{$arch}{$ext}";
}

function getLibDir(): string
{
    // When run from project root (development or post-install)
    $dir = dirname(__DIR__) . '/src/lib/';
    if (is_dir($dir)) {
        return $dir;
    }

    // When installed as a dependency (vendor/developersharif/php-gui/scripts/)
    $dir = dirname(__DIR__) . '/src/lib/';
    if (!is_dir(dirname($dir))) {
        @mkdir(dirname($dir), 0755, true);
    }
    @mkdir($dir, 0755, true);
    return $dir;
}

function getLatestReleaseTag(): ?string
{
    $url = 'https://api.github.com/repos/' . REPO . '/releases/latest';

    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: php-gui-installer\r\n",
            'timeout' => 15,
        ],
    ]);

    $json = @file_get_contents($url, false, $context);
    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);
    return $data['tag_name'] ?? null;
}

function downloadBinary(string $tag, string $binaryName, string $destPath): bool
{
    $url = sprintf(
        'https://github.com/%s/releases/download/%s/%s',
        REPO,
        $tag,
        $binaryName
    );

    echo "  Downloading from: {$url}\n";

    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: php-gui-installer\r\n",
            'timeout' => 60,
            'follow_location' => true,
        ],
    ]);

    $data = @file_get_contents($url, false, $context);
    if ($data === false) {
        return false;
    }

    if (file_put_contents($destPath, $data) === false) {
        return false;
    }

    // Make executable on Unix
    if (PHP_OS_FAMILY !== 'Windows') {
        chmod($destPath, 0755);
    }

    return true;
}

function buildFromSourceInstructions(): string
{
    $os = getOs();
    $msg = "  Build from source:\n";
    $msg .= "    cd src/lib/webview_helper && bash build.sh\n";
    if ($os === 'linux') {
        $msg .= "  Requires: sudo apt-get install -y cmake libgtk-3-dev libwebkit2gtk-4.1-dev\n";
    } elseif ($os === 'darwin') {
        $msg .= "  Requires: brew install cmake\n";
    } else {
        $msg .= "  Requires: cmake and Visual Studio Build Tools\n";
    }
    return $msg;
}

// ── Main ──────────────────────────────────────────────────────────────────

// Skip in CI environments — binary is built from source in a later step
if (getenv('CI') || getenv('GITHUB_ACTIONS')) {
    echo "php-gui: Skipping WebView helper download (CI environment detected).\n";
    exit(0);
}

echo "php-gui: Installing WebView helper binary...\n";

$binaryName = getBinaryName();
$libDir = getLibDir();
$destPath = $libDir . $binaryName;

// Skip if binary already exists
if (file_exists($destPath)) {
    echo "  Binary already exists: {$destPath}\n";
    echo "  To force re-download, delete it and run again.\n";
    exit(0);
}

echo "  Platform: " . getOs() . "/" . getArch() . "\n";
echo "  Binary: {$binaryName}\n";

// Fetch latest release tag
echo "  Fetching latest release...\n";
$tag = getLatestReleaseTag();

if ($tag === null) {
    echo "  NOTE: Could not fetch release info from GitHub.\n";
    echo "  This may be due to network issues or no releases published yet.\n";
    echo "\n";
    echo buildFromSourceInstructions();
    // Exit 0 — download is best-effort, not a hard requirement
    exit(0);
}

echo "  Release: {$tag}\n";

// Download
if (downloadBinary($tag, $binaryName, $destPath)) {
    $size = round(filesize($destPath) / 1024 / 1024, 1);
    echo "  Installed: {$destPath} ({$size} MB)\n";
    exit(0);
}

// Download failed — warn but don't break composer install
echo "  NOTE: Download failed. The WebView feature requires the helper binary.\n";
echo "\n";
echo buildFromSourceInstructions();
exit(0);
