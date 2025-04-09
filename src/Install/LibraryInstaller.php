<?php

namespace PhpGui\Install;


/**
 * Garbage class
 */
class LibraryInstaller
{
    private static function findLibraryFile(string $libFile): ?string 
    {

        $systemPaths = [
            '/usr/lib',
            '/usr/local/lib',
            '/usr/lib/x86_64-linux-gnu',  // Debian/Ubuntu
            '/usr/lib64',                 // RHEL/CentOS
            'C:\\Windows\\System32',      // Windows
            'C:\\Windows\\SysWOW64'       // Windows 64-bit
        ];

        foreach ($systemPaths as $path) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $libFile;
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        return null;
    }

    public static function install(): void
    {
        $baseDir = dirname(dirname(__DIR__));
        $libDir = $baseDir . '/lib';
        $srcLibDir = $baseDir . '/src/lib';
        
        echo "Installing TCL library...\n";
        echo "Base directory: $baseDir\n";
        
        if (!is_dir($libDir)) {
            echo "Creating lib directory: $libDir\n";
            mkdir($libDir, 0755, true);
        }

        $libFile = PHP_OS_FAMILY === 'Windows' ? 'tcl86t.dll' : 'libtcl.so';
        $targetPath = $libDir . '/' . $libFile;

        if (file_exists($targetPath)) {
            echo "Library already installed at: $targetPath\n";
            return;
        }


        echo "Checking src/lib directory...\n";
        $srcLibPath = $srcLibDir . '/' . $libFile;
        if (file_exists($srcLibPath)) {
            echo "Found in src/lib, copying to project root lib...\n";
            if (!copy($srcLibPath, $targetPath)) {
                throw new \RuntimeException("Failed to copy from src/lib to lib directory");
            }
            chmod($targetPath, 0755);
            echo "Successfully installed $libFile from src/lib\n";
            echo "Source: $srcLibPath\n";
            echo "Target: $targetPath\n";
            return;
        }


        echo "Searching for $libFile...\n";
        $vendorLibDir = $baseDir . '/vendor/php-gui/php-gui/lib';
        $sourcePath = $vendorLibDir . '/' . $libFile;

        if (!file_exists($sourcePath)) {
            echo "Not found in vendor dir, checking system paths...\n";
            $systemPath = self::findLibraryFile($libFile);
            if ($systemPath === null) {
                throw new \RuntimeException(
                    "TCL library ($libFile) not found in vendor directory or system paths.\n" .
                    "Please install TCL development package for your system:\n" .
                    "  Debian/Ubuntu: sudo apt-get install tcl-dev\n" .
                    "  RHEL/CentOS: sudo yum install tcl-devel\n" .
                    "  Windows: Download TCL from https://www.tcl.tk/software/tcltk/"
                );
            }
            
            echo "Found system library at: $systemPath\n";
            

            if (PHP_OS_FAMILY !== 'Windows' && function_exists('symlink')) {
                echo "Creating symlink to system library...\n";
                if (@symlink($systemPath, $targetPath)) {
                    echo "Successfully created symlink to system library\n";
                    echo "Source: $systemPath\n";
                    echo "Target: $targetPath\n";
                    return;
                }
                echo "Failed to create symlink, falling back to copy...\n";
            }
            
            echo "Copying system library to project...\n";
            if (!copy($systemPath, $targetPath)) {
                throw new \RuntimeException(
                    "Failed to copy system library.\n" .
                    "Source: $systemPath\n" .
                    "Target: $targetPath\n" .
                    "Please check file permissions."
                );
            }
            
            chmod($targetPath, 0755);
            echo "Successfully copied system library\n";
            echo "Source: $systemPath\n";
            echo "Target: $targetPath\n";
            return;
        }


        echo "Copying vendor library to: $targetPath\n";
        if (!copy($sourcePath, $targetPath)) {
            throw new \RuntimeException("Failed to copy vendor library to lib directory");
        }
        chmod($targetPath, 0755);
        echo "Successfully installed vendor library\n";
        echo "Source: $sourcePath\n";
        echo "Target: $targetPath\n";
    }
}
